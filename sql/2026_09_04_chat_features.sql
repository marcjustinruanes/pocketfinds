-- Messages feature expansion: images, reply-to, reactions, seen/online.
--
-- `public.messages` already had attachment_path/name/type/mime/size columns
-- and a public `messages` Storage bucket, both leftover from the old
-- Laravel site and never wired into this Flutter app — no new columns
-- needed for images. Everything else here (reply_to_id, reactions,
-- presence) is new.
--
-- Run this in the Supabase SQL editor (no Supabase CLI/MCP access from
-- this environment — same handoff pattern as every prior migration).

BEGIN;

-- ============================================================================
-- 1) messages: reply-to
-- ============================================================================
ALTER TABLE public.messages
  ADD COLUMN IF NOT EXISTS reply_to_id bigint REFERENCES public.messages(id) ON DELETE SET NULL;

-- ============================================================================
-- 2) message_reactions — new. One reaction per user per message. RPC-only,
--    same lockdown pattern as public.messages itself.
-- ============================================================================
CREATE TABLE IF NOT EXISTS public.message_reactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  message_id bigint NOT NULL REFERENCES public.messages(id) ON DELETE CASCADE,
  user_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  emoji text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (message_id, user_id)
);

ALTER TABLE public.message_reactions ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.message_reactions FROM anon, authenticated;
-- No policies added on purpose — RPC-only (react_to_message / the
-- reactions aggregate embedded in fetch_conversation_messages).

-- ============================================================================
-- 3) users: real presence (no fabricated "online dot" — a real timestamp,
--    "online" is a client-side "within the last ~90s" read of this).
-- ============================================================================
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS last_active_at timestamptz;

CREATE OR REPLACE FUNCTION public.touch_my_presence()
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  UPDATE public.users SET last_active_at = now() WHERE id = public.current_app_user_id();
$$;
GRANT EXECUTE ON FUNCTION public.touch_my_presence() TO authenticated;

-- Privacy-scoped: only returns a row if the caller and that counterpart
-- actually share a message thread (not exposed to everyone).
CREATE OR REPLACE FUNCTION public.get_conversation_presence(p_counterpart_id bigint)
RETURNS TABLE (last_active_at timestamptz)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT u.last_active_at
  FROM public.users u
  WHERE u.id = p_counterpart_id
    AND EXISTS (
      SELECT 1 FROM public.messages m
      WHERE (m.sender_id = public.current_app_user_id() AND m.receiver_id = p_counterpart_id)
         OR (m.sender_id = p_counterpart_id AND m.receiver_id = public.current_app_user_id())
    );
$$;
GRANT EXECUTE ON FUNCTION public.get_conversation_presence(bigint) TO authenticated;

-- ============================================================================
-- 4) message_attachment_visible + storage policies on the `messages`
--    bucket — sender can upload into their own folder, either participant
--    of the conversation that message belongs to can read it back.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.message_attachment_visible(p_path text)
RETURNS boolean
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.messages
    WHERE attachment_path = p_path
      AND (sender_id = public.current_app_user_id() OR receiver_id = public.current_app_user_id())
  );
$$;
GRANT EXECUTE ON FUNCTION public.message_attachment_visible(text) TO authenticated;

DROP POLICY IF EXISTS "messages bucket: sender can upload" ON storage.objects;
CREATE POLICY "messages bucket: sender can upload"
  ON storage.objects FOR INSERT TO authenticated
  WITH CHECK (
    bucket_id = 'messages'
    AND (storage.foldername(name))[1] = public.current_app_user_id()::text
  );

DROP POLICY IF EXISTS "messages bucket: participant can view" ON storage.objects;
CREATE POLICY "messages bucket: participant can view"
  ON storage.objects FOR SELECT TO authenticated
  USING (
    bucket_id = 'messages'
    AND public.message_attachment_visible(name)
  );

-- ============================================================================
-- 5) send_message — rewritten to accept a reply target and/or an
--    attachment. Return type is unchanged (public.messages), but the
--    parameter list grew, so the old 3-arg overload is dropped first.
-- ============================================================================
DROP FUNCTION IF EXISTS public.send_message(bigint, text, uuid);

CREATE OR REPLACE FUNCTION public.send_message(
  p_receiver_id bigint,
  p_body text,
  p_product_id uuid DEFAULT NULL,
  p_reply_to_id bigint DEFAULT NULL,
  p_attachment_path text DEFAULT NULL,
  p_attachment_name text DEFAULT NULL,
  p_attachment_mime text DEFAULT NULL,
  p_attachment_size bigint DEFAULT NULL
)
RETURNS public.messages
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.current_app_user_id();
  v_row public.messages;
BEGIN
  IF v_me IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF (p_body IS NULL OR btrim(p_body) = '') AND p_attachment_path IS NULL THEN
    RAISE EXCEPTION 'Message must have text or an attachment.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM public.users WHERE id = p_receiver_id) THEN
    RAISE EXCEPTION 'Recipient does not exist.';
  END IF;
  IF p_reply_to_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM public.messages
    WHERE id = p_reply_to_id
      AND ((sender_id = v_me AND receiver_id = p_receiver_id)
        OR (sender_id = p_receiver_id AND receiver_id = v_me))
  ) THEN
    RAISE EXCEPTION 'Cannot reply to that message.';
  END IF;

  INSERT INTO public.messages (
    sender_id, receiver_id, body, product_id, read, created_at, updated_at,
    reply_to_id, attachment_path, attachment_name, attachment_type, attachment_mime, attachment_size
  )
  VALUES (
    v_me, p_receiver_id, NULLIF(p_body, ''), p_product_id, false, now(), now(),
    p_reply_to_id, p_attachment_path, p_attachment_name,
    CASE WHEN p_attachment_mime LIKE 'image/%' THEN 'image' WHEN p_attachment_mime IS NOT NULL THEN 'file' ELSE NULL END,
    p_attachment_mime, p_attachment_size
  )
  RETURNING * INTO v_row;

  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.send_message(bigint, text, uuid, bigint, text, text, text, bigint) TO authenticated;

-- ============================================================================
-- 6) fetch_conversation_messages — rewritten to also return the replied-to
--    message's preview and an aggregated reactions array. Return type
--    changed (was `setof public.messages`), so the old function is dropped
--    first. `read`/`updated_at` (already on the row) double as the seen
--    flag/seen-at timestamp for the caller's own messages — no new column.
-- ============================================================================
DROP FUNCTION IF EXISTS public.fetch_conversation_messages(bigint);

CREATE OR REPLACE FUNCTION public.fetch_conversation_messages(p_counterpart_id bigint)
RETURNS TABLE (
  id bigint,
  sender_id bigint,
  receiver_id bigint,
  body text,
  read boolean,
  created_at timestamp,
  updated_at timestamp,
  product_id uuid,
  attachment_path character varying,
  attachment_name character varying,
  attachment_type character varying,
  attachment_mime character varying,
  attachment_size bigint,
  reply_to_id bigint,
  reply_to_sender_id bigint,
  reply_to_body text,
  reply_to_has_attachment boolean,
  reactions jsonb
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT
    m.id, m.sender_id, m.receiver_id, m.body, m.read, m.created_at, m.updated_at, m.product_id,
    m.attachment_path, m.attachment_name, m.attachment_type, m.attachment_mime, m.attachment_size,
    m.reply_to_id, r.sender_id, r.body, (r.attachment_path IS NOT NULL),
    COALESCE(
      (SELECT jsonb_agg(jsonb_build_object('user_id', mr.user_id, 'emoji', mr.emoji))
       FROM public.message_reactions mr WHERE mr.message_id = m.id),
      '[]'::jsonb
    )
  FROM public.messages m
  LEFT JOIN public.messages r ON r.id = m.reply_to_id
  WHERE (m.sender_id = public.current_app_user_id() AND m.receiver_id = p_counterpart_id)
     OR (m.sender_id = p_counterpart_id AND m.receiver_id = public.current_app_user_id())
  ORDER BY m.created_at ASC NULLS LAST, m.id ASC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_conversation_messages(bigint) TO authenticated;

-- ============================================================================
-- 7) react_to_message — toggles the caller's own reaction on a message they
--    can see (sender or receiver).
-- ============================================================================
CREATE OR REPLACE FUNCTION public.react_to_message(p_message_id bigint, p_emoji text)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.current_app_user_id();
  v_existing text;
BEGIN
  IF v_me IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM public.messages
    WHERE id = p_message_id AND (sender_id = v_me OR receiver_id = v_me)
  ) THEN
    RAISE EXCEPTION 'Message not found.';
  END IF;

  SELECT emoji INTO v_existing FROM public.message_reactions
  WHERE message_id = p_message_id AND user_id = v_me;

  IF v_existing IS NULL THEN
    INSERT INTO public.message_reactions (message_id, user_id, emoji) VALUES (p_message_id, v_me, p_emoji);
  ELSIF v_existing = p_emoji THEN
    DELETE FROM public.message_reactions WHERE message_id = p_message_id AND user_id = v_me;
  ELSE
    UPDATE public.message_reactions SET emoji = p_emoji, created_at = now()
    WHERE message_id = p_message_id AND user_id = v_me;
  END IF;
END;
$$;
GRANT EXECUTE ON FUNCTION public.react_to_message(bigint, text) TO authenticated;

-- ============================================================================
-- 8) fetch_my_conversations — small addition: last message's attachment
--    type, so an image-only last message can preview as "Photo" instead of
--    a blank body. Return type changed, old function dropped first.
-- ============================================================================
DROP FUNCTION IF EXISTS public.fetch_my_conversations();

CREATE OR REPLACE FUNCTION public.fetch_my_conversations()
RETURNS TABLE (
  counterpart_id bigint,
  counterpart_name text,
  counterpart_role text,
  last_message_body text,
  last_message_attachment_type text,
  last_message_at timestamp,
  unread_count bigint
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  WITH me AS (SELECT public.current_app_user_id() AS id),
  mine AS (
    SELECT
      m.*,
      CASE WHEN m.sender_id = (SELECT id FROM me) THEN m.receiver_id ELSE m.sender_id END AS counterpart_id
    FROM public.messages m
    WHERE m.sender_id = (SELECT id FROM me) OR m.receiver_id = (SELECT id FROM me)
  ),
  latest AS (
    SELECT DISTINCT ON (counterpart_id)
      counterpart_id, body AS last_message_body, attachment_type AS last_message_attachment_type,
      created_at AS last_message_at
    FROM mine
    ORDER BY counterpart_id, created_at DESC NULLS LAST, id DESC
  ),
  unread AS (
    SELECT counterpart_id, count(*) AS unread_count
    FROM mine
    WHERE receiver_id = (SELECT id FROM me) AND read = false
    GROUP BY counterpart_id
  )
  SELECT
    l.counterpart_id,
    u.given_names || ' ' || u.last_name AS counterpart_name,
    u.account_type AS counterpart_role,
    l.last_message_body,
    l.last_message_attachment_type,
    l.last_message_at,
    COALESCE(un.unread_count, 0) AS unread_count
  FROM latest l
  JOIN public.users u ON u.id = l.counterpart_id
  LEFT JOIN unread un ON un.counterpart_id = l.counterpart_id
  ORDER BY l.last_message_at DESC NULLS LAST;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_conversations() TO authenticated;

COMMIT;
