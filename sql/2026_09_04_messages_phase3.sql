-- Messages Phase 3: block/unblock (real, new table), and a real
-- "conversation ended after delivery" signal computed from actual
-- order/shipment status (no new "ended" column — derived from data that
-- already exists).
--
-- Run this in the Supabase SQL editor (no Supabase CLI/MCP access from
-- this environment).

BEGIN;

-- ============================================================================
-- 1) blocked_users — real, RPC-only (same lockdown pattern as messages).
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

-- send_message now refuses to send if either side has blocked the other.
-- Same signature as the version from 2026_09_04_chat_features.sql — just
-- the body changes, so a plain CREATE OR REPLACE is enough here.
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

-- ============================================================================
-- 2) conversation_is_active — real: true unless every order/shipment
--    relationship between the caller and that counterpart (as buyer<->
--    seller or buyer<->rider) has reached a terminal state. True (open) by
--    default when there's no order relationship at all — nothing to end.
-- ============================================================================
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

-- fetch_my_conversations now also returns is_active, and excludes any
-- counterpart in a block relationship with the caller (either direction).
-- Return type changed, so the old function is dropped first.
DROP FUNCTION IF EXISTS public.fetch_my_conversations();

CREATE OR REPLACE FUNCTION public.fetch_my_conversations()
RETURNS TABLE (
  counterpart_id bigint,
  counterpart_name text,
  counterpart_role text,
  last_message_body text,
  last_message_attachment_type text,
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
      cp_id, body AS last_message_body, attachment_type AS last_message_attachment_type,
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
    l.cp_id,
    trim(u.given_names || ' ' || u.last_name),
    u.account_type,
    l.last_message_body,
    l.last_message_attachment_type,
    l.last_message_at,
    coalesce(un.unread_count, 0),
    public.conversation_is_active(l.cp_id)
  FROM latest l
  JOIN public.users u ON u.id = l.cp_id
  LEFT JOIN unread un ON un.cp_id = l.cp_id
  WHERE NOT public.is_blocked_either_way(v_me, l.cp_id)
  ORDER BY l.last_message_at DESC NULLS LAST;
END;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_conversations() TO authenticated;

COMMIT;
