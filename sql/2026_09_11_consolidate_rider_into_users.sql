-- Consolidates the rider identity chain into `public.users` and drops the
-- now-redundant tables, per explicit request.
--
-- Before this migration, a rider had data split across THREE places:
--   1. public.users            — the real, legacy account row (bigint id).
--      Already had vehicle_type/vehicle_brand/vehicle_model/plate_number/
--      license_number/license_expiry/shipping_fee/etc. columns sitting
--      unused for riders.
--   2. public.rider_profiles   — a near-exact duplicate of #1's rider
--      columns, keyed by user_id -> users.id, kept in sync only because
--      complete_registration() wrote both at once.
--   3. public.profiles + public.courier_profiles — a parallel uuid-keyed
--      chain (auth.users.id -> profiles.id -> courier_profiles.user_id)
--      created during an earlier, abandoned "real Supabase Auth" rider
--      design. Nothing in this codebase ever reads either table — grep
--      across every .sql file and the whole Flutter app confirms
--      complete_registration() is the ONLY thing that ever touched them,
--      and only to INSERT. Pure dead weight.
--
-- After this migration, a rider is one row in `public.users` — the four
-- rider-only fields `rider_profiles` had that `users` didn't
-- (is_online, logistics_center_id, or_cr_path, license_photo_path) are
-- added directly to `users`; everything else already lived there.
--
-- Run this in the Supabase SQL editor (Project -> SQL Editor), paste the
-- whole file, run it in one go. Not executed by the app or by any agent —
-- same as every other file in sql/, this is for you to run by hand.
--
-- Wrapped in one transaction: if anything fails, nothing is applied.

BEGIN;

-- ============================================================================
-- 1) public.users: the four columns `rider_profiles` had that `users`
--    doesn't. Everything else a rider needs (vehicle_type, vehicle_brand,
--    vehicle_model, plate_number, license_number, license_expiry) already
--    exists on `users`.
-- ============================================================================
ALTER TABLE public.users
  ADD COLUMN IF NOT EXISTS is_online boolean NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS logistics_center_id bigint REFERENCES public.logistics_centers(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS or_cr_path text,
  ADD COLUMN IF NOT EXISTS license_photo_path text;

-- ============================================================================
-- 2) Backfill: copy anything a real rider_profiles row has that the matching
--    users row doesn't (defensive — complete_registration() always wrote
--    both from the same values, so this should be a no-op in practice).
--    Guarded with to_regclass so this is a no-op — not an error — if
--    rider_profiles has already been dropped (e.g. deleted by hand before
--    running this file).
-- ============================================================================
DO $$
BEGIN
  IF to_regclass('public.rider_profiles') IS NOT NULL THEN
    UPDATE public.users u SET
      is_online = COALESCE(rp.is_online, u.is_online),
      logistics_center_id = COALESCE(u.logistics_center_id, rp.logistics_center_id),
      or_cr_path = COALESCE(u.or_cr_path, rp.or_cr_path),
      license_photo_path = COALESCE(u.license_photo_path, rp.license_photo_path),
      vehicle_type = COALESCE(u.vehicle_type, rp.vehicle_type),
      vehicle_brand = COALESCE(u.vehicle_brand, rp.vehicle_brand),
      vehicle_model = COALESCE(u.vehicle_model, rp.vehicle_model),
      plate_number = COALESCE(u.plate_number, rp.plate_number),
      license_number = COALESCE(u.license_number, rp.license_number),
      license_expiry = COALESCE(u.license_expiry, rp.license_expiry)
    FROM public.rider_profiles rp
    WHERE rp.user_id = u.id;
  END IF;
END $$;

-- ============================================================================
-- 3) get_my_rider_profile() — was `RETURNS public.rider_profiles` (every
--    column, including internal bookkeeping). Now returns only the fields
--    RiderProfile.fromMap() (lib/models/rider_profile.dart) actually reads,
--    sourced from `users` instead — narrower AND avoids ever handing a
--    rider's own client the rest of their `users` row (id photos, address,
--    etc.) through this RPC. Same call shape from Dart's side: still one
--    RPC with no params, still a single JSON object (or null if the caller
--    isn't a registered user), still has a `user_id` key.
-- ============================================================================
DROP FUNCTION IF EXISTS public.get_my_rider_profile();
DROP TYPE IF EXISTS public.rider_profile_view;

CREATE TYPE public.rider_profile_view AS (
  user_id bigint,
  vehicle_type text,
  vehicle_brand text,
  vehicle_model text,
  plate_number text,
  license_number text,
  status text,
  is_online boolean,
  logistics_center_id bigint
);

CREATE FUNCTION public.get_my_rider_profile()
RETURNS public.rider_profile_view
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT id, vehicle_type, vehicle_brand, vehicle_model, plate_number,
         license_number, status, is_online, logistics_center_id
  FROM public.users
  WHERE lower(email) = lower(auth.email())
  LIMIT 1;
$$;
GRANT EXECUTE ON FUNCTION public.get_my_rider_profile() TO authenticated;

-- ============================================================================
-- 4) set_online_status() — now flips users.is_online directly.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.set_online_status(p_online boolean)
RETURNS void
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  UPDATE public.users
  SET is_online = p_online, updated_at = now()
  WHERE lower(email) = lower(auth.email());
$$;
GRANT EXECUTE ON FUNCTION public.set_online_status(boolean) TO authenticated;

-- ============================================================================
-- 5) complete_registration() — collapses to a single INSERT INTO users.
--    Every line preserved verbatim from the live function (the rider
--    branch's fields just moved into the one INSERT's column/value lists,
--    guarded by a CASE so buyers get NULL in all of them); the
--    rider_profiles/profiles/courier_profiles inserts are gone, and so is
--    the `v_uid`/auth.uid() variable that only existed to feed them.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.complete_registration()
RETURNS users
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path TO 'public'
AS $function$
    DECLARE
      v_email text := auth.email();
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
        id_type, id_photo_path, selfie_photo_path,
        vehicle_type, vehicle_brand, vehicle_model, plate_number, license_number, license_expiry,
        logistics_center_id, or_cr_path, license_photo_path
      ) VALUES (
        v_account_type, 'manual',
        v_meta->>'last_name', v_meta->>'given_names', NULLIF(v_meta->>'middle_name',''),
        v_meta->>'sex', (v_meta->>'birthday')::date, (v_meta->>'age')::int,
        v_email, v_meta->>'contact_no', v_meta->>'province', v_meta->>'municipality', v_meta->>'barangay',
        NULLIF(v_meta->>'house_no',''), NULLIF(v_meta->>'street',''), v_meta->>'username', 'pending',
        v_id_type, NULLIF(v_id_photo_path,''), NULLIF(v_selfie_photo_path,''),
        CASE WHEN v_account_type = 'rider' THEN v_meta->>'vehicle_type' END,
        CASE WHEN v_account_type = 'rider' THEN v_meta->>'vehicle_brand' END,
        CASE WHEN v_account_type = 'rider' THEN v_meta->>'vehicle_model' END,
        CASE WHEN v_account_type = 'rider' THEN NULLIF(v_meta->>'plate_number','') END,
        CASE WHEN v_account_type = 'rider' THEN NULLIF(v_meta->>'license_number','') END,
        CASE WHEN v_account_type = 'rider' THEN NULLIF(v_meta->>'license_expiry','')::date END,
        CASE WHEN v_account_type = 'rider' THEN v_logistics_center_id END,
        CASE WHEN v_account_type = 'rider' THEN NULLIF(v_or_cr_path,'') END,
        CASE WHEN v_account_type = 'rider' THEN NULLIF(v_license_photo_path,'') END
      ) RETURNING * INTO v_new_user;

      RETURN v_new_user;
    END;
    $function$;
GRANT EXECUTE ON FUNCTION public.complete_registration() TO authenticated;

-- ============================================================================
-- 6) Drop the now-empty-of-purpose tables. courier_profiles first (it FKs
--    to profiles.id); rider_profiles has no dependents.
-- ============================================================================
DROP TABLE IF EXISTS public.courier_profiles;
DROP TABLE IF EXISTS public.profiles;
DROP TABLE IF EXISTS public.rider_profiles;

COMMIT;
