-- Consolidated Messages fix — run this ONE file in the Supabase SQL editor.
--
-- Why: `fetch_my_conversations()` has been throwing "structure of query does
-- not match function result type" in the app (Messages tab). The most likely
-- explanation is that `2026_09_04_chat_features.sql` and/or
-- `2026_09_04_messages_phase3.sql` were never fully run against this
-- database, even though the Flutter app already codes against their final
-- shape (seen-ticks, reactions, presence, block/unblock, "chat ended after
-- delivery" all already exist in the Dart code and are simply inert without
-- this SQL). This file is SAFE TO RUN even if you already ran either or both
-- of those files before — every statement uses DROP-IF-EXISTS/CREATE-OR-
-- REPLACE/ADD-COLUMN-IF-NOT-EXISTS/CREATE-TABLE-IF-NOT-EXISTS, so re-running
-- is a no-op for anything already applied.
--
-- PART 1 and PART 2 below are the unchanged bodies of those two files.
-- PART 3 (at the end) then re-defines `fetch_my_conversations()` and
-- `fetch_conversation_messages()` one more time, with every returned column
-- explicitly cast to match its declared type — this is what actually
-- eliminates the "structure of query does not match function result type"
-- error, regardless of the exact underlying column types — and adds new
-- product-preview columns so a message carrying a real product_id renders as
-- a tappable product card in the app instead of dead text.

BEGIN;

-- ============================================================================
-- PART 1 — from sql/2026_09_04_chat_features.sql (reply-to, reactions,
-- presence, image attachments already on `messages`).
-- ============================================================================

ALTER TABLE public.messages
  ADD COLUMN IF NOT EXISTS reply_to_id bigint REFERENCES public.messages(id) ON DELETE SET NULL;

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

ALTER TABLE public.users ADD COLUMN IF NOT EXISTS last_active_at timestamptz;

CREATE OR REPLACE FUNCTION public.touch_my_presence()
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  UPDATE public.users SET last_active_at = now() WHERE id = public.current_app_user_id();
$$;
GRANT EXECUTE ON FUNCTION public.touch_my_presence() TO authenticated;

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
-- PART 2 — from sql/2026_09_04_messages_phase3.sql (block/unblock,
-- conversation_is_active).
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.blocked_users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  blocker_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  blocked_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (blocker_id, blocked_id)
);
ALTER TABLE public.blocked_users ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.blocked_users FROM anon, authenticated;

CREATE OR REPLACE FUNCTION public.block_user(p_user_id bigint)
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  INSERT INTO public.blocked_users (blocker_id, blocked_id)
  VALUES (public.current_app_user_id(), p_user_id)
  ON CONFLICT (blocker_id, blocked_id) DO NOTHING;
$$;
GRANT EXECUTE ON FUNCTION public.block_user(bigint) TO authenticated;

CREATE OR REPLACE FUNCTION public.unblock_user(p_user_id bigint)
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  DELETE FROM public.blocked_users
  WHERE blocker_id = public.current_app_user_id() AND blocked_id = p_user_id;
$$;
GRANT EXECUTE ON FUNCTION public.unblock_user(bigint) TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_blocked_users()
RETURNS TABLE (user_id bigint, name text)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT u.id, trim(u.given_names || ' ' || u.last_name)
  FROM public.blocked_users b
  JOIN public.users u ON u.id = b.blocked_id
  WHERE b.blocker_id = public.current_app_user_id()
  ORDER BY b.created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_blocked_users() TO authenticated;

CREATE OR REPLACE FUNCTION public.is_blocked_either_way(p_a bigint, p_b bigint)
RETURNS boolean
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.blocked_users
    WHERE (blocker_id = p_a AND blocked_id = p_b) OR (blocker_id = p_b AND blocked_id = p_a)
  );
$$;

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
  IF public.is_blocked_either_way(v_me, p_receiver_id) THEN
    RAISE EXCEPTION 'You cannot message this user.';
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

CREATE OR REPLACE FUNCTION public.conversation_is_active(p_counterpart_id bigint)
RETURNS boolean
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.current_app_user_id();
  v_has_any boolean;
  v_has_open boolean;
BEGIN
  SELECT
    count(*) > 0,
    count(*) FILTER (WHERE coalesce(s.shipping_status, o.status) NOT IN ('completed', 'delivered', 'returned', 'cancelled')) > 0
  INTO v_has_any, v_has_open
  FROM public.orders o
  LEFT JOIN public.shipments s ON s.order_id = o.id
  WHERE (o.app_buyer_id = v_me AND o.app_seller_id = p_counterpart_id)
     OR (o.app_seller_id = v_me AND o.app_buyer_id = p_counterpart_id)
     OR (o.app_buyer_id = v_me AND s.courier_id = p_counterpart_id)
     OR (s.courier_id = v_me AND o.app_buyer_id = p_counterpart_id);

  RETURN (NOT v_has_any) OR v_has_open;
END;
$$;
GRANT EXECUTE ON FUNCTION public.conversation_is_active(bigint) TO authenticated;

-- ============================================================================
-- PART 3 — NEW: hardened + product-preview final versions. Defined last so
-- they win over whatever PART 1/2 (or any earlier live version) declared.
-- Every returned column is explicitly cast to its declared type, which is
-- what actually fixes "structure of query does not match function result
-- type" regardless of the underlying table column types.
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
  product_name text,
  product_image text,
  product_price numeric,
  attachment_path text,
  attachment_name text,
  attachment_type text,
  attachment_mime text,
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
    m.id::bigint,
    m.sender_id::bigint,
    m.receiver_id::bigint,
    m.body::text,
    m.read::boolean,
    m.created_at::timestamp,
    m.updated_at::timestamp,
    m.product_id,
    p.name::text,
    p.image::text,
    p.price::numeric,
    m.attachment_path::text,
    m.attachment_name::text,
    m.attachment_type::text,
    m.attachment_mime::text,
    m.attachment_size::bigint,
    m.reply_to_id::bigint,
    r.sender_id::bigint,
    r.body::text,
    (r.attachment_path IS NOT NULL),
    COALESCE(
      (SELECT jsonb_agg(jsonb_build_object('user_id', mr.user_id, 'emoji', mr.emoji))
       FROM public.message_reactions mr WHERE mr.message_id = m.id),
      '[]'::jsonb
    )
  FROM public.messages m
  LEFT JOIN public.messages r ON r.id = m.reply_to_id
  LEFT JOIN public.products p ON p.id = m.product_id
  WHERE (m.sender_id = public.current_app_user_id() AND m.receiver_id = p_counterpart_id)
     OR (m.sender_id = p_counterpart_id AND m.receiver_id = public.current_app_user_id())
  ORDER BY m.created_at ASC NULLS LAST, m.id ASC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_conversation_messages(bigint) TO authenticated;

DROP FUNCTION IF EXISTS public.fetch_my_conversations();

CREATE OR REPLACE FUNCTION public.fetch_my_conversations()
RETURNS TABLE (
  counterpart_id bigint,
  counterpart_name text,
  counterpart_role text,
  last_message_body text,
  last_message_attachment_type text,
  last_message_product_name text,
  last_message_at timestamp,
  unread_count bigint,
  is_active boolean
)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.current_app_user_id();
BEGIN
  RETURN QUERY
  WITH mine AS (
    SELECT
      m.*,
      CASE WHEN m.sender_id = v_me THEN m.receiver_id ELSE m.sender_id END AS cp_id
    FROM public.messages m
    WHERE m.sender_id = v_me OR m.receiver_id = v_me
  ),
  latest AS (
    SELECT DISTINCT ON (cp_id)
      cp_id,
      body AS last_message_body,
      attachment_type AS last_message_attachment_type,
      product_id AS last_message_product_id,
      created_at AS last_message_at
    FROM mine
    ORDER BY cp_id, created_at DESC NULLS LAST, id DESC
  ),
  unread AS (
    SELECT cp_id, count(*) AS unread_count
    FROM mine
    WHERE receiver_id = v_me AND read = false
    GROUP BY cp_id
  )
  SELECT
    l.cp_id::bigint,
    trim(u.given_names || ' ' || u.last_name)::text,
    u.account_type::text,
    l.last_message_body::text,
    l.last_message_attachment_type::text,
    p.name::text,
    l.last_message_at::timestamp,
    coalesce(un.unread_count, 0)::bigint,
    public.conversation_is_active(l.cp_id)::boolean
  FROM latest l
  JOIN public.users u ON u.id = l.cp_id
  LEFT JOIN public.products p ON p.id = l.last_message_product_id
  LEFT JOIN unread un ON un.cp_id = l.cp_id
  WHERE NOT public.is_blocked_either_way(v_me, l.cp_id)
  ORDER BY l.last_message_at DESC NULLS LAST;
END;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_conversations() TO authenticated;

COMMIT;
