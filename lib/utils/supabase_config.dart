/// Connection details for our EXISTING Supabase project — the same one the
/// PocketFinds Laravel website's database already lives in. We are not
/// creating a new backend or a new database; this just tells the Flutter
/// app where to find it.
///
/// The project URL is derived from the Postgres connection string already
/// used by the Laravel app (see `.env` -> `DB_USERNAME=postgres.<project-ref>`)
/// and confirmed live against the project.
///
/// The publishable key (`sb_publishable_...`, Supabase's newer replacement
/// for the legacy anon JWT) is the public "client" API key for a Supabase
/// project. It is meant to be shipped inside an app — it is not a secret,
/// and it is NOT the service-role key. Row Level Security policies (already
/// set up on every table this app touches) are what actually protect the
/// data; this key alone grants no access beyond what those policies allow.
/// Find it in your Supabase dashboard under:
/// Project Settings -> API -> "Project API keys" -> anon / public
/// (or "publishable" on newer projects).
class SupabaseConfig {
  static const String url = 'https://nmrbmbdnnozoktstmkfb.supabase.co';

  static const String publishableKey =
      'sb_publishable_Dl89edUOI-lYzUoZbkuwIQ_wi6X86sG';

  static bool get isConfigured => publishableKey.isNotEmpty;
}
