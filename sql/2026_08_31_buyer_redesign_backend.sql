-- Buyer UI/UX redesign backend: wishlist + shop follows (new tables),
-- notifications + reviews (real legacy tables, currently fully locked out
-- or unwired — same lockdown-then-RPC treatment as messages/cart_items),
-- and two new RPCs (shop stats, buyer-visible rider info).

BEGIN;

-- ============================================================================
-- 1) wishlist_items — new, same pattern as cart_items.
-- ============================================================================
CREATE TABLE IF NOT EXISTS public.wishlist_items (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  buyer_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  product_id uuid NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (buyer_id, product_id)
);

ALTER TABLE public.wishlist_items ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.wishlist_items FROM anon, authenticated;
GRANT SELECT, INSERT, DELETE ON public.wishlist_items TO authenticated;

DROP POLICY IF EXISTS "Buyers manage their own wishlist" ON public.wishlist_items;
CREATE POLICY "Buyers manage their own wishlist"
  ON public.wishlist_items FOR ALL TO authenticated
  USING (buyer_id = public.my_legacy_user_id())
  WITH CHECK (buyer_id = public.my_legacy_user_id());

-- ============================================================================
-- 2) shop_follows — new. Row-level access stays owner-scoped; the public
--    follower *count* goes through get_shop_stats() below, not raw access.
-- ============================================================================
CREATE TABLE IF NOT EXISTS public.shop_follows (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  buyer_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  seller_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (buyer_id, seller_id)
);

ALTER TABLE public.shop_follows ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.shop_follows FROM anon, authenticated;
GRANT SELECT, INSERT, DELETE ON public.shop_follows TO authenticated;

DROP POLICY IF EXISTS "Buyers manage their own follows" ON public.shop_follows;
CREATE POLICY "Buyers manage their own follows"
  ON public.shop_follows FOR ALL TO authenticated
  USING (buyer_id = public.my_legacy_user_id())
  WITH CHECK (buyer_id = public.my_legacy_user_id());

-- ============================================================================
-- 3) notifications — real table, 28 existing rows, currently fully locked
--    (RLS on, zero policies, zero grants from an earlier defensive pass).
-- ============================================================================
REVOKE ALL ON public.notifications FROM anon, authenticated;
-- RLS already enabled; no public policies — RPC-only, same as messages.

CREATE OR REPLACE FUNCTION public.fetch_my_notifications()
RETURNS SETOF public.notifications
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT * FROM public.notifications
  WHERE user_id = public.my_legacy_user_id()
  ORDER BY created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_notifications() TO authenticated;

CREATE OR REPLACE FUNCTION public.mark_notification_read(p_id uuid)
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  UPDATE public.notifications SET is_read = true
  WHERE id = p_id AND user_id = public.my_legacy_user_id();
$$;
GRANT EXECUTE ON FUNCTION public.mark_notification_read(uuid) TO authenticated;

CREATE OR REPLACE FUNCTION public.unread_notification_count()
RETURNS bigint
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT count(*) FROM public.notifications
  WHERE user_id = public.my_legacy_user_id() AND is_read = false;
$$;
GRANT EXECUTE ON FUNCTION public.unread_notification_count() TO authenticated;

-- ============================================================================
-- 4) reviews — real table/schema, currently empty and fully locked.
-- ============================================================================
REVOKE ALL ON public.reviews FROM anon, authenticated;
-- RLS already enabled; no public policies — RPC-only.

CREATE OR REPLACE FUNCTION public.fetch_product_reviews(p_product_id uuid)
RETURNS TABLE (
  id uuid, rating int, comment text, seller_reply text, created_at timestamptz,
  reviewer_name text
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT r.id, r.rating, r.comment, r.seller_reply, r.created_at,
         u.given_names || ' ' || left(u.last_name, 1) || '.' AS reviewer_name
  FROM public.reviews r
  JOIN public.users u ON u.id = r.buyer_id
  WHERE r.product_id = p_product_id
  ORDER BY r.created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_product_reviews(uuid) TO anon, authenticated;

CREATE OR REPLACE FUNCTION public.get_product_rating_summary(p_product_id uuid)
RETURNS TABLE (average numeric, review_count bigint)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT coalesce(avg(rating), 0)::numeric(3,2), count(*)
  FROM public.reviews WHERE product_id = p_product_id;
$$;
GRANT EXECUTE ON FUNCTION public.get_product_rating_summary(uuid) TO anon, authenticated;

CREATE OR REPLACE FUNCTION public.submit_review(p_order_id uuid, p_product_id uuid, p_rating int, p_comment text)
RETURNS public.reviews
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_buyer_id bigint := public.my_legacy_user_id();
  v_order public.orders;
  v_seller_id bigint;
  v_item_id uuid;
  v_row public.reviews;
BEGIN
  IF v_buyer_id IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF p_rating < 1 OR p_rating > 5 THEN
    RAISE EXCEPTION 'Rating must be between 1 and 5.';
  END IF;

  -- `orders.status` never actually reaches 'completed'/'delivered' in this
  -- app yet (nothing advances it post-checkout) — the real, reliable
  -- delivered signal is the linked shipment's status instead.
  SELECT o.* INTO v_order
  FROM public.orders o
  JOIN public.shipments s ON s.order_id = o.id
  WHERE o.id = p_order_id AND o.app_buyer_id = v_buyer_id
    AND s.shipping_status IN ('delivered', 'completed');
  IF v_order.id IS NULL THEN
    RAISE EXCEPTION 'You can only review products from your own delivered orders.';
  END IF;

  SELECT (item->>'id')::uuid INTO v_item_id
  FROM jsonb_array_elements(v_order.items::jsonb) AS item
  WHERE item->>'product_id' = p_product_id::text
  LIMIT 1;
  IF v_item_id IS NULL THEN
    RAISE EXCEPTION 'That product is not part of this order.';
  END IF;

  v_seller_id := v_order.app_seller_id;

  IF EXISTS (SELECT 1 FROM public.reviews WHERE order_id = p_order_id AND product_id = p_product_id AND buyer_id = v_buyer_id) THEN
    RAISE EXCEPTION 'You already reviewed this product for this order.';
  END IF;

  INSERT INTO public.reviews (order_item_id, buyer_id, seller_id, product_id, rating, comment, order_id)
  VALUES (v_item_id, v_buyer_id, v_seller_id, p_product_id, p_rating, NULLIF(p_comment, ''), p_order_id)
  RETURNING * INTO v_row;

  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.submit_review(uuid, uuid, int, text) TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_my_reviews()
RETURNS TABLE (id uuid, product_id uuid, rating int, comment text, seller_reply text, created_at timestamptz)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT id, product_id, rating, comment, seller_reply, created_at
  FROM public.reviews
  WHERE buyer_id = public.my_legacy_user_id()
  ORDER BY created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_reviews() TO authenticated;

-- ============================================================================
-- 5) get_shop_stats — public. Follower count, active product count, avg
--    rating + review count for a seller.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.get_shop_stats(p_seller_id bigint)
RETURNS TABLE (follower_count bigint, product_count bigint, average_rating numeric, review_count bigint)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT
    (SELECT count(*) FROM public.shop_follows WHERE seller_id = p_seller_id),
    (SELECT count(*) FROM public.products WHERE seller_id = p_seller_id AND status = 'active'),
    (SELECT coalesce(avg(rating), 0)::numeric(3,2) FROM public.reviews WHERE seller_id = p_seller_id),
    (SELECT count(*) FROM public.reviews WHERE seller_id = p_seller_id);
$$;
GRANT EXECUTE ON FUNCTION public.get_shop_stats(bigint) TO anon, authenticated;

-- ============================================================================
-- 6) get_my_delivery_rider — mirrors get_delivery_contact_info() in
--    reverse: the buyer sees their assigned rider's name/contact only once
--    one exists on their own order's shipment.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.get_my_delivery_rider(p_shipment_id uuid)
RETURNS TABLE (rider_id bigint, rider_name text, rider_contact text)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT u.id, u.given_names || ' ' || u.last_name, u.contact_no::text
  FROM public.shipments s
  JOIN public.orders o ON o.id = s.order_id
  JOIN public.users u ON u.id = s.courier_id
  WHERE s.id = p_shipment_id
    AND o.app_buyer_id = public.my_legacy_user_id()
    AND s.courier_id IS NOT NULL;
$$;
GRANT EXECUTE ON FUNCTION public.get_my_delivery_rider(uuid) TO authenticated;

COMMIT;
