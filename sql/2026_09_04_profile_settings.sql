-- Profile & Settings: real socials, notification preference, username/
-- email change, self-service deactivation, and Help Center (message an
-- admin + a real complaint record). Avatar upload needs no new backend —
-- `profile-images` (public, real owner-scoped RLS) and `users.profile_picture`
-- both already exist and are simply unwired in the Flutter app.
--
-- Run this in the Supabase SQL editor (no Supabase CLI/MCP access from
-- this environment).

BEGIN;

-- ============================================================================
-- 1) Socials + notification preference — real columns.
-- ============================================================================
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS facebook_url text;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS instagram_url text;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS twitter_url text;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS tiktok_url text;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS notify_enabled boolean NOT NULL DEFAULT true;

-- ============================================================================
-- 2) update_my_profile_details — name/socials/notification pref/avatar path.
--    Kept separate from the existing address-only update_my_profile() to
--    avoid widening that function's blast radius.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.update_my_profile_details(
  p_given_names text,
  p_last_name text,
  p_middle_name text DEFAULT NULL,
  p_facebook_url text DEFAULT NULL,
  p_instagram_url text DEFAULT NULL,
  p_twitter_url text DEFAULT NULL,
  p_tiktok_url text DEFAULT NULL,
  p_notify_enabled boolean DEFAULT NULL,
  p_profile_picture text DEFAULT NULL
)
RETURNS public.users
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_row public.users;
BEGIN
  UPDATE public.users SET
    given_names = coalesce(NULLIF(p_given_names, ''), given_names),
    last_name = coalesce(NULLIF(p_last_name, ''), last_name),
    middle_name = NULLIF(p_middle_name, ''),
    facebook_url = NULLIF(p_facebook_url, ''),
    instagram_url = NULLIF(p_instagram_url, ''),
    twitter_url = NULLIF(p_twitter_url, ''),
    tiktok_url = NULLIF(p_tiktok_url, ''),
    notify_enabled = coalesce(p_notify_enabled, notify_enabled),
    profile_picture = coalesce(p_profile_picture, profile_picture),
    updated_at = now()
  WHERE lower(email) = lower(auth.email())
  RETURNING * INTO v_row;

  IF v_row.id IS NULL THEN
    RAISE EXCEPTION 'Profile not found.';
  END IF;
  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.update_my_profile_details(text, text, text, text, text, text, text, boolean, text) TO authenticated;

-- ============================================================================
-- 3) Username change — real uniqueness check + update, atomic.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.check_username_available(p_username text)
RETURNS boolean
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT NOT EXISTS (
    SELECT 1 FROM public.users
    WHERE username = p_username AND lower(email) <> lower(auth.email())
  );
$$;
GRANT EXECUTE ON FUNCTION public.check_username_available(text) TO authenticated;

CREATE OR REPLACE FUNCTION public.update_my_username(p_username text)
RETURNS public.users
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_row public.users;
BEGIN
  IF p_username IS NULL OR btrim(p_username) = '' THEN
    RAISE EXCEPTION 'Username cannot be empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM public.users WHERE username = p_username AND lower(email) <> lower(auth.email())) THEN
    RAISE EXCEPTION 'That username is already taken.';
  END IF;

  UPDATE public.users SET username = p_username, updated_at = now()
  WHERE lower(email) = lower(auth.email())
  RETURNING * INTO v_row;

  IF v_row.id IS NULL THEN
    RAISE EXCEPTION 'Profile not found.';
  END IF;
  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.update_my_username(text) TO authenticated;

-- ============================================================================
-- 4) Email change — real uniqueness pre-check only. The actual change +
--    confirmation-link flow is Supabase Auth's own built-in
--    `updateUser(email:)` (called client-side); this just stops a
--    duplicate `public.users` row before that real flow starts.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.check_email_available(p_email text)
RETURNS boolean
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT NOT EXISTS (
    SELECT 1 FROM public.users
    WHERE lower(email) = lower(p_email) AND lower(email) <> lower(auth.email())
  );
$$;
GRANT EXECUTE ON FUNCTION public.check_email_available(text) TO authenticated;

-- ============================================================================
-- 5) Self-service deactivation — reuses the real, already-checked-constraint
--    `suspended` status (same word the ERP spec itself uses for admin's
--    "Activate, Suspend, or Deactivate Accounts" action) rather than
--    inventing a new status value outside `users_status_check`.
-- ============================================================================
CREATE OR REPLACE FUNCTION public.deactivate_my_account()
RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  UPDATE public.users SET status = 'suspended', updated_at = now()
  WHERE lower(email) = lower(auth.email());
$$;
GRANT EXECUTE ON FUNCTION public.deactivate_my_account() TO authenticated;

-- ============================================================================
-- 6) Help Center — message a real admin account, plus a real formal
--    complaint record (the existing `complaints` table, currently
--    RLS-enabled/zero-policy — safe but unusable until now).
-- ============================================================================
CREATE OR REPLACE FUNCTION public.fetch_admin_contact()
RETURNS TABLE (admin_id bigint, admin_name text)
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT id, trim(given_names || ' ' || last_name)
  FROM public.users
  WHERE account_type = 'admin'
  ORDER BY id
  LIMIT 1;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_admin_contact() TO authenticated;

CREATE OR REPLACE FUNCTION public.submit_complaint(p_type text, p_subject text, p_description text)
RETURNS public.complaints
LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
AS $$
DECLARE
  v_me bigint := public.my_legacy_user_id();
  v_row public.complaints;
BEGIN
  IF v_me IS NULL THEN
    RAISE EXCEPTION 'Not a registered PocketFinds user.';
  END IF;
  IF p_subject IS NULL OR btrim(p_subject) = '' THEN
    RAISE EXCEPTION 'Please enter a subject.';
  END IF;

  INSERT INTO public.complaints (complainant_id, complaint_type, subject, description, status, created_at)
  VALUES (v_me, p_type, p_subject, p_description, 'open', now())
  RETURNING * INTO v_row;

  RETURN v_row;
END;
$$;
GRANT EXECUTE ON FUNCTION public.submit_complaint(text, text, text) TO authenticated;

CREATE OR REPLACE FUNCTION public.fetch_my_complaints()
RETURNS SETOF public.complaints
LANGUAGE sql SECURITY DEFINER SET search_path = public
AS $$
  SELECT * FROM public.complaints
  WHERE complainant_id = public.my_legacy_user_id()
  ORDER BY created_at DESC;
$$;
GRANT EXECUTE ON FUNCTION public.fetch_my_complaints() TO authenticated;

COMMIT;
