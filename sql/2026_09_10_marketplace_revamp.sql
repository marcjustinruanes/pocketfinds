-- Marketplace revamp: coins/rewards, platform vouchers, online-payment
-- verification, address completeness, following list, visual search.
--
-- Run this in the Supabase SQL editor (Project -> SQL Editor). Not run
-- automatically by the app or any agent — no Supabase CLI/MCP access from
-- this environment. Idempotent throughout (DROP-IF-EXISTS / CREATE-OR-
-- REPLACE / ADD-COLUMN-IF-NOT-EXISTS / CREATE-TABLE-IF-NOT-EXISTS), safe to
-- re-run.

BEGIN;

-- ============================================================================
-- 1) COINS — a real ledger (ground truth is always SUM(delta), never a
--    cached/fabricated balance column).
-- ============================================================================
CREATE TABLE IF NOT EXISTS public.coin_transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  delta bigint NOT NULL,
  reason text NOT NULL, -- 'review' | 'redeem' | future reasons
  order_id uuid REFERENCES public.orders(id) ON DELETE SET NULL,
  review_id uuid REFERENCES public.reviews(id) ON DELETE SET NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE public.coin_transactions ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.coin_transactions FROM anon, authenticated;
-- No policies on purpose — RPC-only, same lockdown pattern as every other
-- table this session.

CREATE OR REPLACE FUNCTION public.fetch_my_coin_balance()
RETURNS bigint
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT COALESCE(SUM(delta), 0)::bigint
  FROM public.coin_transactions
  WHERE user_id = public.my_legacy_user_id();
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_coin_balance() TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_my_coin_transactions()
RETURNS TABLE (id uuid, delta bigint, reason text, order_id uuid, created_at timestamptz)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT id, delta, reason, order_id, created_at
  FROM public.coin_transactions
  WHERE user_id = public.my_legacy_user_id()
  ORDER BY created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_coin_transactions() TO authenticated;

-- submit_review(): unchanged eligibility logic, now also awards a flat
-- coin reward and reports it back so the UI can show a real "+N coins"
-- moment. Return type changes (adds coins_earned), so the old function
-- (RETURNS public.reviews) is dropped first.
DROP FUNCTION IF EXISTS public.submit_review(uuid, uuid, int, text);

CREATE OR REPLACE FUNCTION public.submit_review(p_order_id uuid, p_product_id uuid, p_rating int, p_comment text)
RETURNS TABLE (
  id uuid, order_item_id uuid, buyer_id bigint, seller_id bigint, product_id uuid,
  rating int, comment text, seller_reply text, order_id uuid, created_at timestamptz,
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
  v_coins_earned CONSTANT bigint := 20; -- business rule: flat coins per real review
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

  INSERT INTO public.reviews (order_item_id, buyer_id, seller_id, product_id, rating, comment, order_id)
  VALUES (v_item_id, v_buyer_id, v_seller_id, p_product_id, p_rating, NULLIF(p_comment, ''), p_order_id)
  RETURNING * INTO v_row;

  INSERT INTO public.coin_transactions (user_id, delta, reason, order_id, review_id)
  VALUES (v_buyer_id, v_coins_earned, 'review', p_order_id, v_row.id);

  RETURN QUERY SELECT
    v_row.id, v_row.order_item_id, v_row.buyer_id, v_row.seller_id, v_row.product_id,
    v_row.rating, v_row.comment, v_row.seller_reply, v_row.order_id, v_row.created_at,
    v_coins_earned;
END;
$$;
GRANT EXECUTE ON FUNCTION public.submit_review(uuid, uuid, int, text) TO authenticated;

-- redeem_coins(): server-validated, same trust model apply_voucher() already
-- uses — real balance check, real 20%-of-subtotal cap, atomically spends the
-- coins (negative ledger row) before returning the real discount amount.
-- 1 coin = ₱1 of discount.
CREATE OR REPLACE FUNCTION public.redeem_coins(p_coins bigint, p_subtotal numeric)
RETURNS TABLE (discount_amount numeric, coins_charged bigint)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_buyer_id bigint := public.my_legacy_user_id();
  v_balance bigint;
  v_cap bigint;
BEGIN
  IF v_buyer_id IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF p_coins IS NULL OR p_coins <= 0 THEN
    RAISE EXCEPTION 'Choose how many coins to use.';
  END IF;

  SELECT COALESCE(SUM(delta), 0) INTO v_balance
  FROM public.coin_transactions WHERE user_id = v_buyer_id;

  v_cap := floor(p_subtotal * 0.20)::bigint; -- business rule: max 20% of subtotal

  IF p_coins > v_balance THEN
    RAISE EXCEPTION 'You only have % coins available.', v_balance;
  END IF;
  IF p_coins > v_cap THEN
    RAISE EXCEPTION 'You can use at most % coins on this order.', v_cap;
  END IF;

  INSERT INTO public.coin_transactions (user_id, delta, reason)
  VALUES (v_buyer_id, -p_coins, 'redeem');

  RETURN QUERY SELECT p_coins::numeric, p_coins;
END;
$$;
GRANT EXECUTE ON FUNCTION public.redeem_coins(bigint, numeric) TO authenticated;

-- ============================================================================
-- 2) PLATFORM VOUCHERS — separate, marketplace-wide concept alongside the
--    existing per-seller `vouchers`/`apply_voucher()`.
-- ============================================================================
CREATE TABLE IF NOT EXISTS public.platform_vouchers (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  title text NOT NULL,
  description text,
  type text NOT NULL CHECK (type IN ('free_shipping', 'discount', 'coins_cashback')),
  discount_amount numeric,
  discount_percent numeric,
  minimum_spend numeric,
  expires_at date,
  usage_limit int,
  used_count int NOT NULL DEFAULT 0,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE public.platform_vouchers ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.platform_vouchers FROM anon, authenticated;

INSERT INTO public.platform_vouchers (title, description, type, minimum_spend, is_active)
SELECT 'Free Shipping', 'Free shipping on orders over ₱500.', 'free_shipping', 500, true
WHERE NOT EXISTS (SELECT 1 FROM public.platform_vouchers WHERE title = 'Free Shipping');

INSERT INTO public.platform_vouchers (title, description, type, discount_percent, minimum_spend, is_active)
SELECT '10% Off', 'Get 10% off your order (up to ₱100 off).', 'discount', 10, 300, true
WHERE NOT EXISTS (SELECT 1 FROM public.platform_vouchers WHERE title = '10% Off');

INSERT INTO public.platform_vouchers (title, description, type, discount_amount, minimum_spend, is_active)
SELECT 'Coins Cashback', 'Earn 20 bonus coins after this order.', 'coins_cashback', 20, 200, true
WHERE NOT EXISTS (SELECT 1 FROM public.platform_vouchers WHERE title = 'Coins Cashback');

CREATE OR REPLACE FUNCTION public.fetch_available_platform_vouchers(p_subtotal numeric)
RETURNS TABLE (
  id uuid, title text, description text, type text, discount_amount numeric,
  discount_percent numeric, minimum_spend numeric, expires_at date, eligible boolean
)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT
    v.id, v.title, v.description, v.type, v.discount_amount, v.discount_percent,
    v.minimum_spend, v.expires_at,
    (v.is_active
      AND (v.expires_at IS NULL OR v.expires_at >= CURRENT_DATE)
      AND (v.usage_limit IS NULL OR v.used_count < v.usage_limit)
      AND (v.minimum_spend IS NULL OR p_subtotal >= v.minimum_spend)
    ) AS eligible
  FROM public.platform_vouchers v
  WHERE v.is_active AND (v.expires_at IS NULL OR v.expires_at >= CURRENT_DATE)
  ORDER BY eligible DESC, v.minimum_spend NULLS FIRST;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_available_platform_vouchers(numeric) TO authenticated;

CREATE OR REPLACE FUNCTION public.apply_platform_voucher(p_voucher_id uuid, p_subtotal numeric)
RETURNS TABLE (discount_amount numeric, free_shipping boolean, bonus_coins bigint)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v public.platform_vouchers;
  v_discount numeric := 0;
BEGIN
  SELECT * INTO v FROM public.platform_vouchers WHERE id = p_voucher_id FOR UPDATE;
  IF v.id IS NULL THEN
    RAISE EXCEPTION 'Voucher not found.';
  END IF;
  IF NOT v.is_active OR (v.expires_at IS NOT NULL AND v.expires_at < CURRENT_DATE) THEN
    RAISE EXCEPTION 'This voucher is no longer available.';
  END IF;
  IF v.usage_limit IS NOT NULL AND v.used_count >= v.usage_limit THEN
    RAISE EXCEPTION 'This voucher has been fully claimed.';
  END IF;
  IF v.minimum_spend IS NOT NULL AND p_subtotal < v.minimum_spend THEN
    RAISE EXCEPTION 'This voucher needs a minimum spend of %.', v.minimum_spend;
  END IF;

  IF v.type = 'discount' THEN
    v_discount := COALESCE(v.discount_amount, 0);
    IF v.discount_percent IS NOT NULL THEN
      v_discount := GREATEST(v_discount, round(p_subtotal * v.discount_percent / 100, 2));
    END IF;
  END IF;

  UPDATE public.platform_vouchers SET used_count = used_count + 1 WHERE id = p_voucher_id;

  RETURN QUERY SELECT
    v_discount,
    (v.type = 'free_shipping'),
    (CASE WHEN v.type = 'coins_cashback' THEN COALESCE(v.discount_amount, 0)::bigint ELSE 0 END);
END;
$$;
GRANT EXECUTE ON FUNCTION public.apply_platform_voucher(uuid, numeric) TO authenticated;

-- ============================================================================
-- 3) ONLINE PAYMENT VERIFICATION
-- ============================================================================
ALTER TABLE public.payment_methods ADD COLUMN IF NOT EXISTS requires_verification boolean NOT NULL DEFAULT false;
UPDATE public.payment_methods SET requires_verification = true
WHERE requires_verification = false
  AND (lower(name) LIKE '%gcash%' OR lower(name) LIKE '%paymaya%' OR lower(name) LIKE '%maya%'
    OR lower(name) LIKE '%card%' OR lower(name) LIKE '%credit%' OR lower(name) LIKE '%debit%'
    OR lower(name) LIKE '%bank%' OR lower(name) LIKE '%paypal%' OR lower(name) LIKE '%wallet%'
    OR lower(name) LIKE '%online%');

CREATE TABLE IF NOT EXISTS public.payment_verifications (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id uuid NOT NULL UNIQUE REFERENCES public.orders(id) ON DELETE CASCADE,
  buyer_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  reference_number text NOT NULL,
  proof_path text NOT NULL,
  status text NOT NULL DEFAULT 'submitted'
    CHECK (status IN ('pending', 'submitted', 'under_review', 'confirmed', 'rejected')),
  rejection_reason text,
  submitted_at timestamptz NOT NULL DEFAULT now(),
  reviewed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE public.payment_verifications ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.payment_verifications FROM anon, authenticated;

insert into storage.buckets (id, name, public)
values ('payment-proofs', 'payment-proofs', false)
on conflict (id) do nothing;

drop policy if exists "payment-proofs: owner can insert" on storage.objects;
create policy "payment-proofs: owner can insert"
  on storage.objects for insert
  to authenticated
  with check (bucket_id = 'payment-proofs' and (storage.foldername(name))[1] = auth.uid()::text);

drop policy if exists "payment-proofs: owner can select" on storage.objects;
create policy "payment-proofs: owner can select"
  on storage.objects for select
  to authenticated
  using (bucket_id = 'payment-proofs' and (storage.foldername(name))[1] = auth.uid()::text);

drop policy if exists "payment-proofs: owner can update" on storage.objects;
create policy "payment-proofs: owner can update"
  on storage.objects for update
  to authenticated
  using (bucket_id = 'payment-proofs' and (storage.foldername(name))[1] = auth.uid()::text);

CREATE OR REPLACE FUNCTION public.submit_payment_verification(p_order_id uuid, p_reference_number text, p_proof_path text)
RETURNS public.payment_verifications
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_buyer_id bigint := public.my_legacy_user_id();
  v_row public.payment_verifications;
BEGIN
  IF v_buyer_id IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM public.orders WHERE id = p_order_id AND app_buyer_id = v_buyer_id) THEN
    RAISE EXCEPTION 'Order not found.';
  END IF;
  IF p_reference_number IS NULL OR btrim(p_reference_number) = '' THEN
    RAISE EXCEPTION 'Reference number is required.';
  END IF;
  IF p_proof_path IS NULL OR btrim(p_proof_path) = '' THEN
    RAISE EXCEPTION 'Payment proof is required.';
  END IF;

  INSERT INTO public.payment_verifications (order_id, buyer_id, reference_number, proof_path, status, submitted_at, updated_at)
  VALUES (p_order_id, v_buyer_id, btrim(p_reference_number), p_proof_path, 'submitted', now(), now())
  ON CONFLICT (order_id) DO UPDATE SET
    reference_number = EXCLUDED.reference_number,
    proof_path = EXCLUDED.proof_path,
    status = 'submitted',
    rejection_reason = NULL,
    submitted_at = now(),
    updated_at = now()
  RETURNING * INTO v_row;

  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.submit_payment_verification(uuid, text, text) TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_payment_verification(p_order_id uuid)
RETURNS public.payment_verifications
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT pv.* FROM public.payment_verifications pv
  JOIN public.orders o ON o.id = pv.order_id
  WHERE pv.order_id = p_order_id
    AND (o.app_buyer_id = public.my_legacy_user_id() OR o.app_seller_id = public.my_legacy_user_id());
$$;
GRANT EXECUTE ON FUNCTION public.fetch_payment_verification(uuid) TO authenticated;

-- Seller-side — no Flutter caller yet (seller is web-only), but real and
-- authorized, ready for a future web seller portal.
CREATE OR REPLACE FUNCTION public.confirm_payment_verification(p_order_id uuid, p_notes text DEFAULT NULL)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_seller_id bigint := public.my_legacy_user_id();
BEGIN
  IF NOT EXISTS (SELECT 1 FROM public.orders WHERE id = p_order_id AND app_seller_id = v_seller_id) THEN
    RAISE EXCEPTION 'Not authorized to confirm this payment.';
  END IF;
  UPDATE public.payment_verifications
  SET status = 'confirmed', reviewed_at = now(), updated_at = now(), rejection_reason = NULL
  WHERE order_id = p_order_id;
  -- Not routed through record_order_status_history() — that RPC only
  -- authorizes the buyer/rider on the order, not the seller; this function
  -- already did its own seller-ownership check above. Best-effort: if the
  -- table's status vocabulary rejects this value, the real status update
  -- above still stands — only the optional timeline entry is skipped.
  BEGIN
    INSERT INTO public.order_status_history (order_id, status, changed_by, notes)
    VALUES (p_order_id, 'payment_confirmed', v_seller_id, p_notes);
  EXCEPTION WHEN OTHERS THEN NULL;
  END;
END;
$$;
GRANT EXECUTE ON FUNCTION public.confirm_payment_verification(uuid, text) TO authenticated;

CREATE OR REPLACE FUNCTION public.reject_payment_verification(p_order_id uuid, p_reason text)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_seller_id bigint := public.my_legacy_user_id();
BEGIN
  IF NOT EXISTS (SELECT 1 FROM public.orders WHERE id = p_order_id AND app_seller_id = v_seller_id) THEN
    RAISE EXCEPTION 'Not authorized to reject this payment.';
  END IF;
  UPDATE public.payment_verifications
  SET status = 'rejected', reviewed_at = now(), updated_at = now(), rejection_reason = p_reason
  WHERE order_id = p_order_id;
  BEGIN
    INSERT INTO public.order_status_history (order_id, status, changed_by, notes)
    VALUES (p_order_id, 'payment_rejected', v_seller_id, p_reason);
  EXCEPTION WHEN OTHERS THEN NULL;
  END;
END;
$$;
GRANT EXECUTE ON FUNCTION public.reject_payment_verification(uuid, text) TO authenticated;

-- ============================================================================
-- 4) ADDRESS completeness + orders gains coin/voucher columns
-- ============================================================================
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS region text;

-- The old 6-arg overload is dropped first — leaving both the old 6-arg and
-- new 7-arg (with a DEFAULT-ed 7th param) versions around would make any
-- 6-named-argument call ambiguous (it could match either), since Postgres
-- treats different arg lists as distinct overloads rather than replacing
-- across arg-count changes.
DROP FUNCTION IF EXISTS public.update_my_profile(text,text,text,text,text,text);

CREATE OR REPLACE FUNCTION public.update_my_profile(
  p_contact_no text, p_house_no text, p_street text,
  p_barangay text, p_municipality text, p_province text,
  p_region text DEFAULT NULL
)
RETURNS public.users
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_row public.users;
BEGIN
  UPDATE public.users SET
    contact_no = p_contact_no,
    house_no = NULLIF(p_house_no, ''),
    street = NULLIF(p_street, ''),
    barangay = p_barangay,
    municipality = p_municipality,
    province = p_province,
    region = COALESCE(NULLIF(p_region, ''), region),
    updated_at = now()
  WHERE lower(email) = lower(auth.email())
  RETURNING * INTO v_row;

  IF v_row.id IS NULL THEN
    RAISE EXCEPTION 'Profile not found.';
  END IF;
  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.update_my_profile(text,text,text,text,text,text,text) TO authenticated;

ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS coins_used bigint NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS coin_discount_amount numeric NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS platform_voucher_id uuid REFERENCES public.platform_vouchers(id),
  ADD COLUMN IF NOT EXISTS platform_discount_amount numeric NOT NULL DEFAULT 0;

-- ============================================================================
-- 5) VISUAL SEARCH — real color/shape similarity via pgvector (not a deep
--    embedding), disclosed as a coarser technique than semantic AI search.
-- ============================================================================
CREATE EXTENSION IF NOT EXISTS vector;

ALTER TABLE public.products ADD COLUMN IF NOT EXISTS image_signature vector(48);

CREATE OR REPLACE FUNCTION public.find_visually_similar_products(p_signature vector(48), p_limit int DEFAULT 20)
RETURNS TABLE (id uuid, distance real)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT id, (image_signature <-> p_signature)::real AS distance
  FROM public.products
  WHERE status = 'active' AND image_signature IS NOT NULL
  ORDER BY image_signature <-> p_signature
  LIMIT p_limit;
$$;
GRANT EXECUTE ON FUNCTION public.find_visually_similar_products(vector, int) TO authenticated, anon;

COMMIT;
