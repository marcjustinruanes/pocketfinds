-- CRITICAL FIX, found during an ERP-spec feature audit: three real legacy
-- tables have RLS fully DISABLED and `anon` (fully unauthenticated) holds
-- full INSERT/SELECT/UPDATE/DELETE/TRUNCATE on them. Same class of bug
-- already fixed this session for messages/notifications/reviews. Run this
-- immediately regardless of whether/when the features that use these
-- tables (rider document resubmission, unserviceable-area management, the
-- ID-type dropdown) get built in the app.
--
-- Run this in the Supabase SQL editor (no Supabase CLI/MCP access from
-- this environment).

BEGIN;

-- document_update_requests: contains user_id, uploaded document paths
-- (id_file, business_permit_file), review status/notes — sensitive
-- compliance data. Currently anyone with no login can read, forge, or
-- wipe every user's resubmission requests.
REVOKE ALL ON public.document_update_requests FROM anon, authenticated;
ALTER TABLE public.document_update_requests ENABLE ROW LEVEL SECURITY;
-- No policies added on purpose — nothing in the Flutter app uses this
-- table yet; it goes RPC-only (owner-scoped) once that feature is built,
-- same pattern as messages/notifications/reviews.

-- unserviceable_areas: lower sensitivity (municipality/note), but still a
-- fully open write surface (anyone could TRUNCATE or spam it).
REVOKE ALL ON public.unserviceable_areas FROM anon, authenticated;
ALTER TABLE public.unserviceable_areas ENABLE ROW LEVEL SECURITY;
-- Read-only public policy: this is reference data the buyer-facing app
-- may eventually check against ("we don't deliver to this area yet").
DROP POLICY IF EXISTS "Anyone can read unserviceable areas" ON public.unserviceable_areas;
CREATE POLICY "Anyone can read unserviceable areas"
  ON public.unserviceable_areas FOR SELECT TO anon, authenticated
  USING (true);

-- id_types: a plain lookup list (9 rows: "Passport", "Driver's License",
-- etc.) — already used by the registration wizard's ID-type dropdown.
-- Low sensitivity, but anon currently has write/delete/truncate on it too.
REVOKE ALL ON public.id_types FROM anon, authenticated;
ALTER TABLE public.id_types ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Anyone can read id types" ON public.id_types;
CREATE POLICY "Anyone can read id types"
  ON public.id_types FOR SELECT TO anon, authenticated
  USING (true);

COMMIT;
