-- Registration wizard: ID photo + selfie storage, and the
-- complete_registration() extension to record them.
--
-- Run this in the Supabase SQL editor (Project -> SQL Editor). It is NOT
-- run automatically by the app or by any agent — there is no Supabase
-- CLI/MCP access in the Flutter repo's environment.
--
-- ============================================================================
-- PART 1 — Storage bucket + RLS policies (safe to run now, no dependencies)
-- ============================================================================

-- Private bucket for ID photos + selfies captured during registration.
-- (You can instead create this via Studio -> Storage -> New bucket if you'd
-- rather set size/MIME-type limits through the UI; leave "Public" OFF either
-- way — these are identity documents.)
insert into storage.buckets (id, name, public)
values ('identity-documents', 'identity-documents', false)
on conflict (id) do nothing;

-- Every uploaded object is stored at `{auth.uid()}/id_photo.<ext>` or
-- `{auth.uid()}/selfie.<ext>` (see RegistrationDocumentsService in the
-- Flutter app) — these policies make sure a user can only touch objects
-- under their own uid folder, and nobody else's.
create policy "identity-documents: owner can insert"
  on storage.objects for insert
  to authenticated
  with check (
    bucket_id = 'identity-documents'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "identity-documents: owner can select"
  on storage.objects for select
  to authenticated
  using (
    bucket_id = 'identity-documents'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "identity-documents: owner can update"
  on storage.objects for update
  to authenticated
  using (
    bucket_id = 'identity-documents'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

-- ============================================================================
-- PART 2 — New columns on public.users (safe to run now)
-- ============================================================================

alter table public.users
  add column if not exists id_type text,
  add column if not exists id_photo_path text,
  add column if not exists selfie_photo_path text;

-- ============================================================================
-- PART 3 — complete_registration() extension
-- ============================================================================
--
-- This is the ACTUAL existing function body (retrieved via
-- `select pg_get_functiondef('public.complete_registration()'::regprocedure);`
-- on 2026-08-27), unchanged except for three additions, each marked
-- "-- NEW:" below:
--   1. Three new local variables reading id_type/id_photo_path/
--      selfie_photo_path out of user_metadata, same pattern as every other
--      field already read that way.
--   2. Those three columns added to the public.users INSERT.
-- Nothing else — the claim-existing-legacy-row-by-email early return, the
-- username-uniqueness check, and every rider_profiles/profiles/
-- courier_profiles insert (role='courier', capitalized sex, plate_number
-- fallback) are byte-for-byte what was already live.
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
      -- NEW: registration-wizard ID/selfie fields.
      v_id_type text := v_meta->>'id_type';
      v_id_photo_path text := v_meta->>'id_photo_path';
      v_selfie_photo_path text := v_meta->>'selfie_photo_path';
      v_new_user public.users;
      v_existing public.users;
    BEGIN
      IF v_email IS NULL THEN
        RAISE EXCEPTION 'Not authenticated.';
      END IF;

      -- Claim an existing pre-Supabase (Laravel/website) account by
      -- verified email instead of rejecting it as a duplicate.
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
        id_type, id_photo_path, selfie_photo_path -- NEW
      ) VALUES (
        v_account_type, 'manual',
        v_meta->>'last_name', v_meta->>'given_names', NULLIF(v_meta->>'middle_name',''),
        v_meta->>'sex', (v_meta->>'birthday')::date, (v_meta->>'age')::int,
        v_email, v_meta->>'contact_no', v_meta->>'province', v_meta->>'municipality', v_meta->>'barangay',
        NULLIF(v_meta->>'house_no',''), NULLIF(v_meta->>'street',''), v_meta->>'username', 'pending',
        v_id_type, NULLIF(v_id_photo_path,''), NULLIF(v_selfie_photo_path,'') -- NEW
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
