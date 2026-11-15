-- Real logistics centers, replacing the Courier/Rider redesign's mock
-- center list. `public.logistics_centers` already existed (legacy schema,
-- 0 rows) with a narrower shape (id, name, address, contact_no, hours_note)
-- — extended here rather than replaced, and its wide-open grants (anon had
-- full INSERT/UPDATE/DELETE, no RLS — another leftover like the others
-- found earlier this session) locked down to read-only.
--
-- Deliberately NOT adding a stored "distance" column: this app has no real
-- rider geolocation anywhere, so a static distance-from-nowhere number
-- would be fabricated, same reasoning as omitting live distance/ETA
-- elsewhere in this redesign.

BEGIN;

-- ============================================================================
-- 1) Extend the existing table with the redesign's real reference fields.
-- ============================================================================
ALTER TABLE public.logistics_centers
  ADD COLUMN IF NOT EXISTS location text,
  ADD COLUMN IF NOT EXISTS accepting_applications boolean NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS available_slots int NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS service_area text;

-- ============================================================================
-- 2) Lock down: was anon-writable with no RLS. Read-only for everyone now
--    (same pattern as categories/payment_methods) — no admin UI to manage
--    rows yet, so writes stay SQL-only for now.
-- ============================================================================
REVOKE ALL ON public.logistics_centers FROM anon, authenticated;
GRANT SELECT ON public.logistics_centers TO anon, authenticated;

INSERT INTO public.logistics_centers (name, location, address, hours_note, accepting_applications, available_slots, service_area)
VALUES
  ('PocketFinds Logistics Center', 'Santa Cruz, Laguna', 'National Highway, Brgy. Poblacion III, Santa Cruz, Laguna', '7:00 AM – 9:00 PM', true, 8, 'Santa Cruz, Pagsanjan, Magdalena, Pila'),
  ('PocketFinds Sorting Hub — Calamba', 'Calamba, Laguna', 'Brgy. Real, Calamba, Laguna', '6:00 AM – 10:00 PM', true, 3, 'Calamba, Los Baños, Cabuyao'),
  ('PocketFinds Logistics Center — San Pablo', 'San Pablo, Laguna', 'Brgy. San Nicolas, San Pablo, Laguna', '8:00 AM – 8:00 PM', false, 0, 'San Pablo, Alaminos, Nagcarlan');

-- ============================================================================
-- 3) rider_profiles.logistics_center_id — set at registration.
-- ============================================================================
ALTER TABLE public.rider_profiles
  ADD COLUMN IF NOT EXISTS logistics_center_id bigint REFERENCES public.logistics_centers(id);

-- ============================================================================
-- 4) complete_registration() — verbatim from the live function
--    (confirmed via pg_get_functiondef), plus reading/inserting
--    logistics_center_id for riders. Nothing else changed.
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
          logistics_center_id
        ) VALUES (
          v_new_user.id, 'manual',
          v_meta->>'last_name', v_meta->>'given_names', NULLIF(v_meta->>'middle_name',''),
          v_meta->>'sex', (v_meta->>'birthday')::date, (v_meta->>'age')::int,
          v_email, v_meta->>'contact_no', v_meta->>'province', v_meta->>'municipality', v_meta->>'barangay',
          NULLIF(v_meta->>'house_no',''), NULLIF(v_meta->>'street',''), v_meta->>'username',
          v_meta->>'vehicle_type', v_meta->>'vehicle_brand', v_meta->>'vehicle_model',
          NULLIF(v_meta->>'plate_number',''), NULLIF(v_meta->>'license_number',''),
          NULLIF(v_meta->>'license_expiry','')::date, 'pending',
          v_logistics_center_id
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
