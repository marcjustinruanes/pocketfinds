# PocketFinds (Flutter)

A Flutter mobile translation of the PocketFinds marketplace, covering the
**Guest**, **Authentication**, **Buyer**, and **Rider** experiences. It talks
directly to the existing PocketFinds Supabase project — the same database,
tables, and Row Level Security policies the original website uses — so data
created on the app and the website stay in sync. Seller/Admin/Logistics
remain website-only and are out of scope for this app.

## Stack

- **Flutter** (Dart) — UI and app logic
- **Supabase** — Postgres database, Auth, and Row Level Security (via
  `supabase_flutter`); no custom backend or API layer
- Configuration lives in [lib/utils/supabase_config.dart](lib/utils/supabase_config.dart)
  (public anon/publishable key only — never the service-role key)

## Getting started

```bash
flutter pub get
flutter run
```

Run `flutter analyze` and `flutter test` before committing changes.

## Project structure

- `lib/screens/` — Guest, Auth, Buyer, and Rider screens
- `lib/services/` — Supabase queries/RPC calls, grouped by feature
- `lib/models/` — typed wrappers around Supabase rows
- `lib/theme/` — the shared design system (colors, spacing, radius, shadows)
- `lib/widgets/` — shared UI components reused across screens

## Notes

- Authentication uses real Supabase Auth (`signUp`/`signInWithPassword`), not
  a custom scheme. A `complete_registration()` Postgres function links a new
  sign-up to an existing legacy `users` row by verified email, so accounts
  created on the original website can be claimed by signing up again with the
  same email.
- ID/selfie/vehicle-document photo upload isn't wired up yet — no Supabase
  Storage bucket exists for it.
