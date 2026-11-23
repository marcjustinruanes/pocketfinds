-- Review media + anonymous reviews.
--
--   1. A review can attach one real photo or video as proof — stored in a
--      new, public `review-media` Storage bucket (owner-folder-scoped
--      write, same pattern `payment-proofs`/`profile-images` already use).
--   2. Coins are only awarded when that media is actually attached (no
--      more automatic flat award for a text-only review), and the amount
--      drops from the old flat 20 to a flat 4 per review.
--   3. A buyer can mark a review anonymous — `fetch_product_reviews()`
--      then masks the real name down to its first letter + asterisks
--      instead of returning it; `fetch_my_reviews()` still returns the
--      buyer's own real data plus the `is_anonymous` flag so their own
--      "My Reviews" list can say so.
--
-- Run this in the Supabase SQL editor (no Supabase CLI/MCP access from
-- this environment).

BEGIN;

-- ============================================================================
-- 1) New columns on the existing (pre-existing, legacy) reviews table.
-- ============================================================================
ALTER TABLE public.reviews ADD COLUMN IF NOT EXISTS media_url text;
ALTER TABLE public.reviews ADD COLUMN IF NOT EXISTS is_anonymous boolean NOT NULL DEFAULT false;

-- ============================================================================
-- 2) review-media Storage bucket — public (reviews are public-facing), but
--    only the owner (their own auth uid folder) can write into it.
-- ============================================================================
insert into storage.buckets (id, name, public)
values ('review-media', 'review-media', true)
on conflict (id) do nothing;

drop policy if exists "review-media: owner can insert" on storage.objects;
create policy "review-media: owner can insert"
  on storage.objects for insert
  to authenticated
  with check (bucket_id = 'review-media' and (storage.foldername(name))[1] = auth.uid()::text);

drop policy if exists "review-media: owner can update" on storage.objects;
create policy "review-media: owner can update"
  on storage.objects for update
  to authenticated
  using (bucket_id = 'review-media' and (storage.foldername(name))[1] = auth.uid()::text);

drop policy if exists "review-media: anyone can view" on storage.objects;
create policy "review-media: anyone can view"
  on storage.objects for select
  to anon, authenticated
  using (bucket_id = 'review-media');

-- ============================================================================
-- 3) submit_review() — now takes the uploaded media's storage path and the
--    anonymous flag; coins (flat 4, was flat 20) only awarded when media
--    is actually attached. Old 4-arg signature dropped since the return
--    shape and business rule both change.
-- ============================================================================
-- Same eligibility logic as the version this replaces (real delivered-order
-- + order-item check) — only the media/anonymous params and the coin rule
-- change. New signature (6 params, 2 with defaults), so the old 4-param
-- one is dropped first.
DROP FUNCTION IF EXISTS public.submit_review(uuid, uuid, int, text);

CREATE OR REPLACE FUNCTION public.submit_review(
  p_order_id uuid,
  p_product_id uuid,
  p_rating int,
  p_comment text,
  p_media_url text DEFAULT NULL,
  p_is_anonymous boolean DEFAULT false
)
RETURNS TABLE (
  id uuid, order_item_id uuid, buyer_id bigint, seller_id bigint, product_id uuid,
  rating int, comment text, seller_reply text, order_id uuid, created_at timestamptz,
  media_url text, is_anonymous boolean,
  coins_earned bigint
)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_buyer_id bigint := public.my_legacy_user_id();
  v_order public.orders;
  v_seller_id bigint;
  v_item_id uuid;
  v_row public.reviews;
  v_coins_earned CONSTANT bigint := 4; -- business rule: flat coins per review, was 20, and now only when media is attached
BEGIN
  IF v_buyer_id IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF p_rating < 1 OR p_rating > 5 THEN
    RAISE EXCEPTION 'Rating must be between 1 and 5.';
  END IF;

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

  INSERT INTO public.reviews (order_item_id, buyer_id, seller_id, product_id, rating, comment, order_id, media_url, is_anonymous)
  VALUES (v_item_id, v_buyer_id, v_seller_id, p_product_id, p_rating, NULLIF(p_comment, ''), p_order_id, NULLIF(p_media_url, ''), coalesce(p_is_anonymous, false))
  RETURNING * INTO v_row;

  IF v_row.media_url IS NOT NULL THEN
    INSERT INTO public.coin_transactions (user_id, delta, reason, order_id, review_id)
    VALUES (v_buyer_id, v_coins_earned, 'review', p_order_id, v_row.id);
  END IF;

  RETURN QUERY SELECT
    v_row.id, v_row.order_item_id, v_row.buyer_id, v_row.seller_id, v_row.product_id,
    v_row.rating, v_row.comment, v_row.seller_reply, v_row.order_id, v_row.created_at,
    v_row.media_url, v_row.is_anonymous,
    CASE WHEN v_row.media_url IS NOT NULL THEN v_coins_earned ELSE 0 END;
END;
$$;
GRANT EXECUTE ON FUNCTION public.submit_review(uuid, uuid, int, text, text, boolean) TO authenticated;

-- ============================================================================
-- 4) fetch_product_reviews() — masks the reviewer's name when the review
--    was posted anonymously (first letter + asterisks, real length hidden
--    behind a fixed run so the mask itself doesn't leak name length), and
--    now also returns media_url so the product page can show the photo/
--    video attached.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.fetch_product_reviews(p_product_id uuid)
RETURNS TABLE (
  id uuid, rating int, comment text, seller_reply text, created_at timestamptz,
  reviewer_name text, media_url text, is_anonymous boolean
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT r.id, r.rating, r.comment, r.seller_reply, r.created_at,
         CASE
           WHEN r.is_anonymous THEN left(u.given_names, 1) || '*****'
           ELSE u.given_names || ' ' || left(u.last_name, 1) || '.'
         END AS reviewer_name,
         r.media_url, r.is_anonymous
  FROM public.reviews r
  JOIN public.users u ON u.id = r.buyer_id
  WHERE r.product_id = p_product_id
  ORDER BY r.created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_product_reviews(uuid) TO anon, authenticated;

-- ============================================================================
-- 5) fetch_my_reviews() — the buyer's own list; real data plus the new
--    columns so "My Reviews" can show the attached media and an
--    "Anonymous" indicator.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.fetch_my_reviews()
RETURNS TABLE (
  id uuid, product_id uuid, rating int, comment text, seller_reply text, created_at timestamptz,
  media_url text, is_anonymous boolean
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT id, product_id, rating, comment, seller_reply, created_at, media_url, is_anonymous
  FROM public.reviews
  WHERE buyer_id = public.my_legacy_user_id()
  ORDER BY created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_reviews() TO authenticated;

COMMIT;
