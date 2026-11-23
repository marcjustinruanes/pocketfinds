-- Closes five real gaps found in the ERP-spec audit (Buyer + Rider only —
-- Seller/Admin/Logistics are confirmed web-only, out of scope for this
-- Flutter app). Every table this touches already existed for this exact
-- purpose (vouchers, order_status_history, orders.discount_amount/
-- voucher_code) — this is wiring, not new invention.
--
-- Run this in the Supabase SQL editor (no Supabase CLI/MCP access from
-- this environment).

BEGIN;

-- ============================================================================
-- 1) apply_voucher — real, seller-scoped voucher validation + atomic
--    used_count increment. `vouchers` stays RPC-only (already RLS-enabled/
--    zero-grants — safe, just unusable until now).
-- ============================================================================
CREATE OR REPLACE FUNCTION public.apply_voucher(p_code text, p_seller_id bigint, p_subtotal numeric)
RETURNS TABLE (discount_amount numeric, free_shipping boolean)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_voucher public.vouchers;
BEGIN
  SELECT * INTO v_voucher FROM public.vouchers
  WHERE upper(code) = upper(p_code) AND seller_id = p_seller_id
  FOR UPDATE;

  IF v_voucher.id IS NULL THEN
    RAISE EXCEPTION 'Invalid voucher code for this seller.';
  END IF;
  IF NOT v_voucher.is_active THEN
    RAISE EXCEPTION 'This voucher is no longer active.';
  END IF;
  IF v_voucher.expires_at IS NOT NULL AND v_voucher.expires_at < current_date THEN
    RAISE EXCEPTION 'This voucher has expired.';
  END IF;
  IF v_voucher.minimum_spend IS NOT NULL AND p_subtotal < v_voucher.minimum_spend THEN
    RAISE EXCEPTION 'This voucher requires a minimum spend of %.', v_voucher.minimum_spend;
  END IF;
  IF v_voucher.usage_limit IS NOT NULL AND v_voucher.used_count >= v_voucher.usage_limit THEN
    RAISE EXCEPTION 'This voucher has already been used the maximum number of times.';
  END IF;

  UPDATE public.vouchers SET used_count = used_count + 1, updated_at = now() WHERE id = v_voucher.id;

  RETURN QUERY SELECT coalesce(v_voucher.discount_amount, 0)::numeric, (v_voucher.type = 'free_shipping');
END;
$$;
GRANT EXECUTE ON FUNCTION public.apply_voucher(text, bigint, numeric) TO authenticated;

-- ============================================================================
-- 2) record_order_status_history / fetch_order_status_history — real
--    read/write for the existing `order_status_history` table (RLS-enabled,
--    zero policies today — safe but unusable; stays RPC-only).
-- ============================================================================
CREATE OR REPLACE FUNCTION public.record_order_status_history(p_order_id uuid, p_status text, p_notes text DEFAULT NULL)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.my_legacy_user_id();
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM public.orders o
    LEFT JOIN public.shipments s ON s.order_id = o.id
    WHERE o.id = p_order_id AND (o.app_buyer_id = v_me OR s.courier_id = v_me)
  ) THEN
    RAISE EXCEPTION 'Not authorized to record status for this order.';
  END IF;

  INSERT INTO public.order_status_history (order_id, status, changed_by, notes)
  VALUES (p_order_id, p_status, v_me, p_notes);
END;
$$;
GRANT EXECUTE ON FUNCTION public.record_order_status_history(uuid, text, text) TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_order_status_history(p_order_id uuid)
RETURNS SETOF public.order_status_history
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT h.*
  FROM public.order_status_history h
  JOIN public.orders o ON o.id = h.order_id
  LEFT JOIN public.shipments s ON s.order_id = o.id
  WHERE h.order_id = p_order_id
    AND (o.app_buyer_id = public.my_legacy_user_id() OR s.courier_id = public.my_legacy_user_id())
  ORDER BY h.created_at ASC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_order_status_history(uuid) TO authenticated;

-- ============================================================================
-- 3) confirm_order_received — the spec's explicit buyer action
--    (delivered -> completed), missing entirely until now.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.confirm_order_received(p_shipment_id uuid)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.my_legacy_user_id();
  v_order_id uuid;
BEGIN
  IF v_me IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;

  SELECT o.id INTO v_order_id
  FROM public.shipments s
  JOIN public.orders o ON o.id = s.order_id
  WHERE s.id = p_shipment_id AND o.app_buyer_id = v_me AND s.shipping_status = 'delivered';

  IF v_order_id IS NULL THEN
    RAISE EXCEPTION 'This order cannot be confirmed yet.';
  END IF;

  UPDATE public.shipments SET shipping_status = 'completed', updated_at = now() WHERE id = p_shipment_id;
  UPDATE public.orders SET status = 'completed', updated_at = now() WHERE id = v_order_id;

  INSERT INTO public.order_status_history (order_id, status, changed_by, notes)
  VALUES (v_order_id, 'completed', v_me, 'Buyer confirmed receipt.');
END;
$$;
GRANT EXECUTE ON FUNCTION public.confirm_order_received(uuid) TO authenticated;

-- ============================================================================
-- 4) update_delivery_status — rewritten to also support the spec's
--    delivery-failure branch (out_for_delivery -> delivery_failed, then
--    either retry -> out_for_delivery, or give up -> returned). Parameter
--    list grew (new optional p_reason), so the old 2-arg overload is
--    dropped first.
-- ============================================================================
DROP FUNCTION IF EXISTS public.update_delivery_status(uuid, text);

CREATE OR REPLACE FUNCTION public.update_delivery_status(p_shipment_id uuid, p_status text, p_reason text DEFAULT NULL)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_current text;
  v_order_id uuid;
  v_me bigint := public.my_legacy_user_id();
BEGIN
  SELECT shipping_status, order_id INTO v_current, v_order_id
  FROM public.shipments WHERE id = p_shipment_id AND courier_id = v_me;

  IF v_current IS NULL THEN
    RAISE EXCEPTION 'Delivery not found or not assigned to you.';
  END IF;

  IF NOT (
    (v_current = 'accepted' AND p_status = 'picked_up') OR
    (v_current = 'picked_up' AND p_status = 'out_for_delivery') OR
    (v_current = 'out_for_delivery' AND p_status IN ('delivered', 'delivery_failed')) OR
    (v_current = 'delivery_failed' AND p_status IN ('out_for_delivery', 'returned'))
  ) THEN
    RAISE EXCEPTION 'Invalid status transition from % to %.', v_current, p_status;
  END IF;

  IF p_status = 'delivery_failed' AND (p_reason IS NULL OR btrim(p_reason) = '') THEN
    RAISE EXCEPTION 'A reason is required to report a failed delivery.';
  END IF;

  UPDATE public.shipments SET
    shipping_status = p_status,
    picked_up_at = CASE WHEN p_status = 'picked_up' THEN now() ELSE picked_up_at END,
    out_for_delivery_at = CASE WHEN p_status = 'out_for_delivery' THEN now() ELSE out_for_delivery_at END,
    delivered_at = CASE WHEN p_status = 'delivered' THEN now() ELSE delivered_at END,
    updated_at = now()
  WHERE id = p_shipment_id;

  INSERT INTO public.order_status_history (order_id, status, changed_by, notes)
  VALUES (v_order_id, p_status, v_me, p_reason);
END;
$$;
GRANT EXECUTE ON FUNCTION public.update_delivery_status(uuid, text, text) TO authenticated;

-- ============================================================================
-- 5) Rider registration: real OR/CR + driver's-license PHOTO uploads
--    (previously only a license *number* text field existed).
-- ============================================================================
ALTER TABLE public.rider_profiles ADD COLUMN IF NOT EXISTS or_cr_path text;
ALTER TABLE public.rider_profiles ADD COLUMN IF NOT EXISTS license_photo_path text;

-- Same signature/return type as the current live function — every existing
-- line preserved verbatim (fetched and diffed before writing this), only
-- the two new fields added to the DECLARE list and the rider_profiles
-- INSERT's column/value lists.
CREATE OR REPLACE FUNCTION public.complete_registration()
RETURNS users
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $function$
    DECLARE
      v_email text := auth.email();
      v_uid uuid := auth.uid();
      v_meta jsonb := (auth.jwt() -> 'user_metadata');
      v_account_type text := v_meta->>'account_type';
      v_id_type text := v_meta->>'id_type';
      v_id_photo_path text := v_meta->>'id_photo_path';
      v_selfie_photo_path text := v_meta->>'selfie_photo_path';
      v_or_cr_path text := v_meta->>'or_cr_path';
      v_license_photo_path text := v_meta->>'license_photo_path';
      v_logistics_center_id bigint := NULLIF(v_meta->>'logistics_center_id','')::bigint;
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
          vehicle_type, vehicle_brand, vehicle_model, plate_number, license_number, license_expiry, status,
          logistics_center_id, or_cr_path, license_photo_path
        ) VALUES (
          v_new_user.id, 'manual',
          v_meta->>'last_name', v_meta->>'given_names', NULLIF(v_meta->>'middle_name',''),
          v_meta->>'sex', (v_meta->>'birthday')::date, (v_meta->>'age')::int,
          v_email, v_meta->>'contact_no', v_meta->>'province', v_meta->>'municipality', v_meta->>'barangay',
          NULLIF(v_meta->>'house_no',''), NULLIF(v_meta->>'street',''), v_meta->>'username',
          v_meta->>'vehicle_type', v_meta->>'vehicle_brand', v_meta->>'vehicle_model',
          NULLIF(v_meta->>'plate_number',''), NULLIF(v_meta->>'license_number',''),
          NULLIF(v_meta->>'license_expiry','')::date, 'pending',
          v_logistics_center_id, NULLIF(v_or_cr_path,''), NULLIF(v_license_photo_path,'')
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

COMMIT;
