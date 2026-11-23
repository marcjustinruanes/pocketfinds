-- PocketFinds full backend reconstruction (2026-08-31).
--
-- Run this in the Supabase SQL editor (Project -> SQL Editor), paste the
-- whole file, and run it in one go. Not executed by the app or by any
-- agent — Claude's own attempts to run this directly against the database
-- were blocked by the harness's safety classifier, which is why this exists
-- as a file for you to run instead.
--
-- What this fixes: the live database was missing almost the entire backend
-- layer the Flutter app assumes exists — zero custom functions at all,
-- `products`/`users`/`orders`/`shipments` had RLS enabled with ZERO
-- policies (which denies everyone, including the app's own legitimate
-- queries), `orders` was missing the app_buyer_id/app_seller_id columns
-- checkout writes to, and `rider_profiles`/`profiles`/`courier_profiles`
-- didn't exist as tables at all. Separately, `cart_items`/`messages`/
-- `buyer_payment_accounts` had RLS OFF with `anon` (fully unauthenticated)
-- holding full CRUD — a live, active data-exposure bug, fixed here too.
--
-- Wrapped in one transaction: if anything fails, nothing is applied.

BEGIN;

-- ============================================================================
-- 0) Helper functions (used inside RLS policies and by several RPCs below)
-- ============================================================================
CREATE OR REPLACE FUNCTION public.my_legacy_user_id()
RETURNS bigint
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT id FROM public.users WHERE lower(email) = lower(auth.email());
$$;
GRANT EXECUTE ON FUNCTION public.my_legacy_user_id() TO authenticated;

CREATE OR REPLACE FUNCTION public.current_app_user_id()
RETURNS bigint
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT public.my_legacy_user_id();
$$;
GRANT EXECUTE ON FUNCTION public.current_app_user_id() TO authenticated;

-- ============================================================================
-- 1) public.users: new columns for the registration wizard's ID/selfie
--    fields, plus a narrow public read policy (name/business/location only)
--    so product cards can show seller info to guests too.
-- ============================================================================
ALTER TABLE public.users
  ADD COLUMN IF NOT EXISTS id_type text,
  ADD COLUMN IF NOT EXISTS id_photo_path text,
  ADD COLUMN IF NOT EXISTS selfie_photo_path text;

DROP POLICY IF EXISTS "Anyone can view basic seller directory info" ON public.users;
CREATE POLICY "Anyone can view basic seller directory info"
  ON public.users
  FOR SELECT
  TO anon, authenticated
  USING (true);

GRANT SELECT (id, given_names, last_name, business_name, username, municipality, province)
  ON public.users TO anon, authenticated;

-- ============================================================================
-- 2) get_my_profile() / update_my_profile()
-- ============================================================================
CREATE OR REPLACE FUNCTION public.get_my_profile()
RETURNS public.users
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT * FROM public.users WHERE lower(email) = lower(auth.email()) LIMIT 1;
$$;
GRANT EXECUTE ON FUNCTION public.get_my_profile() TO authenticated;

CREATE OR REPLACE FUNCTION public.update_my_profile(
  p_contact_no text, p_house_no text, p_street text,
  p_barangay text, p_municipality text, p_province text
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
    updated_at = now()
  WHERE lower(email) = lower(auth.email())
  RETURNING * INTO v_row;

  IF v_row.id IS NULL THEN
    RAISE EXCEPTION 'Profile not found.';
  END IF;
  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.update_my_profile(text,text,text,text,text,text) TO authenticated;

-- ============================================================================
-- 3) Rider identity chain tables — none of these existed at all.
--    profiles.id -> auth.users.id -> courier_profiles.user_id -> profiles.id
--    rider_profiles.user_id -> public.users.id (legacy bigint side)
-- ============================================================================
CREATE TABLE IF NOT EXISTS public.profiles (
  id uuid PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  first_name text,
  last_name text,
  middle_initial text,
  sex text,
  email text,
  contact_no text,
  birthday date,
  role text NOT NULL DEFAULT 'courier' CHECK (role = 'courier'),
  account_status text NOT NULL DEFAULT 'pending'
    CHECK (account_status IN ('pending','approved','rejected','suspended')),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.courier_profiles (
  user_id uuid PRIMARY KEY REFERENCES public.profiles(id) ON DELETE CASCADE,
  vehicle_type text,
  plate_number text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.rider_profiles (
  user_id bigint PRIMARY KEY REFERENCES public.users(id) ON DELETE CASCADE,
  auth_method text,
  last_name text,
  given_names text,
  middle_name text,
  sex text,
  birthday date,
  age smallint,
  email text,
  contact_no text,
  province text,
  municipality text,
  barangay text,
  house_no text,
  street text,
  username text,
  vehicle_type text,
  vehicle_brand text,
  vehicle_model text,
  plate_number text,
  license_number text,
  license_expiry date,
  status text NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','approved','rejected','suspended')),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.courier_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.rider_profiles ENABLE ROW LEVEL SECURITY;
-- No public policies on purpose — only SECURITY DEFINER RPCs below touch these.
REVOKE ALL ON public.profiles FROM anon, authenticated;
REVOKE ALL ON public.courier_profiles FROM anon, authenticated;
REVOKE ALL ON public.rider_profiles FROM anon, authenticated;

-- ============================================================================
-- 4) complete_registration() — verbatim from sql/2026_08_27_registration_documents.sql
--    Part 3 (the last confirmed-real body), which itself was already the
--    verified original plus the id_type/id_photo_path/selfie_photo_path
--    additions. Needs #1 and #3 above to exist first.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.complete_registration()
 RETURNS users
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
    DECLARE
      v_email text := auth.email();
      v_uid uuid := auth.uid();
      v_meta jsonb := (auth.jwt() -> 'user_metadata');
      v_account_type text := v_meta->>'account_type';
      v_id_type text := v_meta->>'id_type';
      v_id_photo_path text := v_meta->>'id_photo_path';
      v_selfie_photo_path text := v_meta->>'selfie_photo_path';
      v_new_user public.users;
      v_existing public.users;
    BEGIN
      IF v_email IS NULL THEN
        RAISE EXCEPTION 'Not authenticated.';
      END IF;

      SELECT * INTO v_existing FROM public.users WHERE lower(email) = lower(v_email);
      IF v_existing.id IS NOT NULL THEN
        RETURN v_existing;
      END IF;

      IF v_account_type NOT IN ('buyer','rider') THEN
        RAISE EXCEPTION 'Missing or invalid registration data.';
      END IF;

      IF v_meta->>'username' IS NULL OR EXISTS (SELECT 1 FROM public.users WHERE username = (v_meta->>'username')) THEN
        RAISE EXCEPTION 'That username is already taken.';
      END IF;

      INSERT INTO public.users (
        account_type, auth_method, last_name, given_names, middle_name, sex, birthday, age,
        email, contact_no, province, municipality, barangay, house_no, street, username, status,
        id_type, id_photo_path, selfie_photo_path
      ) VALUES (
        v_account_type, 'manual',
        v_meta->>'last_name', v_meta->>'given_names', NULLIF(v_meta->>'middle_name',''),
        v_meta->>'sex', (v_meta->>'birthday')::date, (v_meta->>'age')::int,
        v_email, v_meta->>'contact_no', v_meta->>'province', v_meta->>'municipality', v_meta->>'barangay',
        NULLIF(v_meta->>'house_no',''), NULLIF(v_meta->>'street',''), v_meta->>'username', 'pending',
        v_id_type, NULLIF(v_id_photo_path,''), NULLIF(v_selfie_photo_path,'')
      ) RETURNING * INTO v_new_user;

      IF v_account_type = 'rider' THEN
        INSERT INTO public.rider_profiles (
          user_id, auth_method, last_name, given_names, middle_name, sex, birthday, age,
          email, contact_no, province, municipality, barangay, house_no, street, username,
          vehicle_type, vehicle_brand, vehicle_model, plate_number, license_number, license_expiry, status
        ) VALUES (
          v_new_user.id, 'manual',
          v_meta->>'last_name', v_meta->>'given_names', NULLIF(v_meta->>'middle_name',''),
          v_meta->>'sex', (v_meta->>'birthday')::date, (v_meta->>'age')::int,
          v_email, v_meta->>'contact_no', v_meta->>'province', v_meta->>'municipality', v_meta->>'barangay',
          NULLIF(v_meta->>'house_no',''), NULLIF(v_meta->>'street',''), v_meta->>'username',
          v_meta->>'vehicle_type', v_meta->>'vehicle_brand', v_meta->>'vehicle_model',
          NULLIF(v_meta->>'plate_number',''), NULLIF(v_meta->>'license_number',''),
          NULLIF(v_meta->>'license_expiry','')::date, 'pending'
        );

        IF v_uid IS NOT NULL THEN
          INSERT INTO public.profiles (
            id, first_name, last_name, middle_initial, sex, email, contact_no, birthday,
            role, account_status
          ) VALUES (
            v_uid, v_meta->>'given_names', v_meta->>'last_name',
            NULLIF(left(coalesce(v_meta->>'middle_name',''),1), ''),
            initcap(v_meta->>'sex'), v_email, v_meta->>'contact_no', (v_meta->>'birthday')::date,
            'courier', 'pending'
          ) ON CONFLICT (id) DO NOTHING;

          INSERT INTO public.courier_profiles (user_id, vehicle_type, plate_number)
          VALUES (
            v_uid, v_meta->>'vehicle_type',
            COALESCE(NULLIF(v_meta->>'plate_number', ''), 'N/A')
          ) ON CONFLICT (user_id) DO NOTHING;
        END IF;
      END IF;

      RETURN v_new_user;
    END;
    $function$;
GRANT EXECUTE ON FUNCTION public.complete_registration() TO authenticated;

-- ============================================================================
-- 5) shipments.courier_id: stays bigint (legacy `users.id`), matching
--    riders' legacy id the same way buyer_id/seller_id already do —
--    NOT auth.uid(). An earlier version of this migration converted it to
--    uuid to match auth.uid() directly, but something outside this
--    migration (most likely Laravel's own logistics/courier-assignment
--    code, which only knows the legacy bigint id) kept recreating the
--    column as bigint and writing real bigint values into it — reverting
--    that uuid conversion three separate times over one session. Rather
--    than keep fighting whatever's doing that, every RPC below resolves
--    the caller's legacy id via `public.my_legacy_user_id()` (same
--    email-based bridge used everywhere else) instead of using
--    `auth.uid()` for courier_id comparisons.
-- ============================================================================
ALTER TABLE public.shipments DROP CONSTRAINT IF EXISTS shipments_courier_id_fkey;
ALTER TABLE public.shipments
  ADD CONSTRAINT shipments_courier_id_fkey
  FOREIGN KEY (courier_id) REFERENCES public.users(id);

-- ============================================================================
-- 6) orders: add the app_buyer_id/app_seller_id columns OrderService writes
--    to on every checkout (dual-write alongside the legacy buyer_id/
--    seller_id columns) — these never existed, so checkout was failing
--    outright with a "column not found" error, independent of RLS.
-- ============================================================================
ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS app_buyer_id bigint REFERENCES public.users(id),
  ADD COLUMN IF NOT EXISTS app_seller_id bigint REFERENCES public.users(id);

UPDATE public.orders
  SET app_buyer_id = buyer_id, app_seller_id = seller_id
  WHERE app_buyer_id IS NULL;

-- ============================================================================
-- 7) Cross-table visibility helpers (SECURITY DEFINER, not raw subqueries,
--    to avoid the RLS-recursion bug already documented for this project:
--    orders' policy checks shipments and shipments' policy checks orders).
-- ============================================================================
CREATE OR REPLACE FUNCTION public.is_my_order(p_order_id uuid)
RETURNS boolean
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.orders
    WHERE id = p_order_id AND app_buyer_id = public.my_legacy_user_id()
  );
$$;
GRANT EXECUTE ON FUNCTION public.is_my_order(uuid) TO authenticated;

-- Covers BOTH "assigned to me" and "available/unclaimed" — a rider needs
-- to see order items/total for shipments they can browse in the Available
-- tab too, not only ones already claimed (matches the shipments SELECT
-- policy's own visibility rule below, so a rider never sees a shipment
-- without also being able to see its order).
CREATE OR REPLACE FUNCTION public.shipment_visible_to_rider(p_order_id uuid)
RETURNS boolean
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.shipments
    WHERE order_id = p_order_id
      AND (
        (shipping_status = 'available' AND courier_id IS NULL)
        OR courier_id = public.my_legacy_user_id()
      )
  );
$$;
GRANT EXECUTE ON FUNCTION public.shipment_visible_to_rider(uuid) TO authenticated;

-- ============================================================================
-- 8) products: RLS is on with zero policies (deny-all) — add back public
--    read of active listings only.
-- ============================================================================
DROP POLICY IF EXISTS "Anyone can view active products" ON public.products;
CREATE POLICY "Anyone can view active products"
  ON public.products
  FOR SELECT
  TO anon, authenticated
  USING (status = 'active');

-- ============================================================================
-- 9) cart_items: currently RLS OFF with `anon` holding full CRUD — anyone
--    unauthenticated can read/edit/delete every buyer's cart right now.
--    Lock down to RLS, scoped to the owning buyer only.
-- ============================================================================
REVOKE ALL ON public.cart_items FROM anon, authenticated;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.cart_items TO authenticated;
ALTER TABLE public.cart_items ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Buyers manage their own cart" ON public.cart_items;
CREATE POLICY "Buyers manage their own cart"
  ON public.cart_items
  FOR ALL
  TO authenticated
  USING (buyer_id = public.my_legacy_user_id())
  WITH CHECK (buyer_id = public.my_legacy_user_id());

-- ============================================================================
-- 10) orders: RLS policies (was RLS-on-zero-policies = deny-all, breaking
--     checkout/order history entirely).
-- ============================================================================
DROP POLICY IF EXISTS "Buyers and assigned riders can view orders" ON public.orders;
CREATE POLICY "Buyers and assigned riders can view orders"
  ON public.orders
  FOR SELECT
  TO authenticated
  USING (
    app_buyer_id = public.my_legacy_user_id()
    OR public.shipment_visible_to_rider(id)
  );

DROP POLICY IF EXISTS "Buyers insert their own orders" ON public.orders;
CREATE POLICY "Buyers insert their own orders"
  ON public.orders
  FOR INSERT
  TO authenticated
  WITH CHECK (app_buyer_id = public.my_legacy_user_id());

-- ============================================================================
-- 11) shipments: RLS policies (available-to-claim visible to any rider, own
--     assigned shipments, buyer's own order's shipment; buyer can insert one
--     for their own order at checkout).
-- ============================================================================
DROP POLICY IF EXISTS "Riders can view available or their own shipments" ON public.shipments;
CREATE POLICY "Riders can view available or their own shipments"
  ON public.shipments
  FOR SELECT
  TO authenticated
  USING (
    (shipping_status = 'available' AND courier_id IS NULL)
    OR courier_id = public.my_legacy_user_id()
    OR public.is_my_order(order_id)
  );

DROP POLICY IF EXISTS "Buyers create a shipment for their own order" ON public.shipments;
CREATE POLICY "Buyers create a shipment for their own order"
  ON public.shipments
  FOR INSERT
  TO authenticated
  WITH CHECK (public.is_my_order(order_id));

-- ============================================================================
-- 12) Rider RPCs
-- ============================================================================
CREATE OR REPLACE FUNCTION public.get_my_rider_profile()
RETURNS public.rider_profiles
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT * FROM public.rider_profiles WHERE lower(email) = lower(auth.email()) LIMIT 1;
$$;
GRANT EXECUTE ON FUNCTION public.get_my_rider_profile() TO authenticated;

CREATE OR REPLACE FUNCTION public.claim_shipment(p_shipment_id uuid)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  UPDATE public.shipments
  SET courier_id = public.my_legacy_user_id(), shipping_status = 'accepted', updated_at = now()
  WHERE id = p_shipment_id AND shipping_status = 'available' AND courier_id IS NULL;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'This delivery is no longer available.';
  END IF;
END;
$$;
GRANT EXECUTE ON FUNCTION public.claim_shipment(uuid) TO authenticated;

CREATE OR REPLACE FUNCTION public.update_delivery_status(p_shipment_id uuid, p_status text)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_current text;
  v_expected_next text;
BEGIN
  SELECT shipping_status INTO v_current
  FROM public.shipments WHERE id = p_shipment_id AND courier_id = public.my_legacy_user_id();

  IF v_current IS NULL THEN
    RAISE EXCEPTION 'Delivery not found or not assigned to you.';
  END IF;

  v_expected_next := CASE v_current
    WHEN 'accepted' THEN 'picked_up'
    WHEN 'picked_up' THEN 'out_for_delivery'
    WHEN 'out_for_delivery' THEN 'delivered'
    ELSE NULL
  END;

  IF v_expected_next IS NULL OR p_status <> v_expected_next THEN
    RAISE EXCEPTION 'Invalid status transition from % to %.', v_current, p_status;
  END IF;

  UPDATE public.shipments SET
    shipping_status = p_status,
    picked_up_at = CASE WHEN p_status = 'picked_up' THEN now() ELSE picked_up_at END,
    out_for_delivery_at = CASE WHEN p_status = 'out_for_delivery' THEN now() ELSE out_for_delivery_at END,
    delivered_at = CASE WHEN p_status = 'delivered' THEN now() ELSE delivered_at END,
    updated_at = now()
  WHERE id = p_shipment_id;
END;
$$;
GRANT EXECUTE ON FUNCTION public.update_delivery_status(uuid,text) TO authenticated;

CREATE OR REPLACE FUNCTION public.get_delivery_contact_info(p_shipment_id uuid)
RETURNS TABLE (buyer_name text, buyer_contact text)
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT u.given_names || ' ' || u.last_name, u.contact_no::text
  FROM public.shipments s
  JOIN public.orders o ON o.id = s.order_id
  JOIN public.users u ON u.id = o.app_buyer_id
  WHERE s.id = p_shipment_id AND s.courier_id = public.my_legacy_user_id();
$$;
GRANT EXECUTE ON FUNCTION public.get_delivery_contact_info(uuid) TO authenticated;

-- ============================================================================
-- 13) messages: CRITICAL — RLS was OFF and `anon` had full CRUD (read/forge/
--     wipe every user's private messages, no login required). Verbatim from
--     sql/2026_08_27_messages_lockdown_and_rpcs.sql.
-- ============================================================================
REVOKE ALL ON public.messages FROM anon, authenticated;
ALTER TABLE public.messages ENABLE ROW LEVEL SECURITY;

CREATE OR REPLACE FUNCTION public.fetch_my_conversations()
RETURNS TABLE (
  counterpart_id bigint,
  counterpart_name text,
  last_message_body text,
  last_message_at timestamp,
  unread_count bigint
)
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
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
      counterpart_id, body AS last_message_body, created_at AS last_message_at
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
    l.last_message_body,
    l.last_message_at,
    coalesce(un.unread_count, 0) AS unread_count
  FROM latest l
  JOIN public.users u ON u.id = l.counterpart_id
  LEFT JOIN unread un ON un.counterpart_id = l.counterpart_id
  ORDER BY l.last_message_at DESC NULLS LAST;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_conversations() TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_conversation_messages(p_counterpart_id bigint)
RETURNS SETOF public.messages
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT *
  FROM public.messages
  WHERE (sender_id = public.current_app_user_id() AND receiver_id = p_counterpart_id)
     OR (sender_id = p_counterpart_id AND receiver_id = public.current_app_user_id())
  ORDER BY created_at ASC NULLS LAST, id ASC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_conversation_messages(bigint) TO authenticated;

CREATE OR REPLACE FUNCTION public.send_message(p_receiver_id bigint, p_body text, p_product_id uuid DEFAULT NULL)
RETURNS public.messages
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_me bigint := public.current_app_user_id();
  v_row public.messages;
BEGIN
  IF v_me IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF p_body IS NULL OR btrim(p_body) = '' THEN
    RAISE EXCEPTION 'Message body cannot be empty.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM public.users WHERE id = p_receiver_id) THEN
    RAISE EXCEPTION 'Recipient does not exist.';
  END IF;

  INSERT INTO public.messages (sender_id, receiver_id, body, product_id, read, created_at, updated_at)
  VALUES (v_me, p_receiver_id, p_body, p_product_id, false, now(), now())
  RETURNING * INTO v_row;

  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.send_message(bigint,text,uuid) TO authenticated;

CREATE OR REPLACE FUNCTION public.mark_messages_read(p_counterpart_id bigint)
RETURNS void
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  UPDATE public.messages
  SET read = true, updated_at = now()
  WHERE receiver_id = public.current_app_user_id()
    AND sender_id = p_counterpart_id
    AND read = false;
$$;
GRANT EXECUTE ON FUNCTION public.mark_messages_read(bigint) TO authenticated;

-- ============================================================================
-- 14) Registration wizard storage bucket (ID photo + selfie), verbatim from
--     sql/2026_08_27_registration_documents.sql Part 1.
-- ============================================================================
INSERT INTO storage.buckets (id, name, public)
VALUES ('identity-documents', 'identity-documents', false)
ON CONFLICT (id) DO NOTHING;

DROP POLICY IF EXISTS "identity-documents: owner can insert" ON storage.objects;
CREATE POLICY "identity-documents: owner can insert"
  ON storage.objects FOR INSERT TO authenticated
  WITH CHECK (
    bucket_id = 'identity-documents'
    AND (storage.foldername(name))[1] = auth.uid()::text
  );

DROP POLICY IF EXISTS "identity-documents: owner can select" ON storage.objects;
CREATE POLICY "identity-documents: owner can select"
  ON storage.objects FOR SELECT TO authenticated
  USING (
    bucket_id = 'identity-documents'
    AND (storage.foldername(name))[1] = auth.uid()::text
  );

DROP POLICY IF EXISTS "identity-documents: owner can update" ON storage.objects;
CREATE POLICY "identity-documents: owner can update"
  ON storage.objects FOR UPDATE TO authenticated
  USING (
    bucket_id = 'identity-documents'
    AND (storage.foldername(name))[1] = auth.uid()::text
  );

-- ============================================================================
-- 15) Defensive lockdown of tables the Flutter app never touches but that
--     currently have `anon` holding full CRUD (buyer_payment_accounts,
--     vouchers) or leftover wide grants despite RLS already blocking access
--     (commissions/complaints/notifications/reviews/announcements). Nothing
--     here changes app behavior — these tables are unused by any current
--     screen.
-- ============================================================================
REVOKE ALL ON public.buyer_payment_accounts FROM anon, authenticated;
ALTER TABLE public.buyer_payment_accounts ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.vouchers FROM anon, authenticated;
ALTER TABLE public.vouchers ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.commissions FROM anon, authenticated;
REVOKE ALL ON public.complaints FROM anon, authenticated;
REVOKE ALL ON public.notifications FROM anon, authenticated;
REVOKE ALL ON public.reviews FROM anon, authenticated;
REVOKE ALL ON public.announcements FROM anon, authenticated;

COMMIT;
