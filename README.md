# PocketFinds (Flutter)

Phone builds default to `https://pocketfindsph.shop/app-api`, served by the
website's public entry point using the existing mobile API and bearer auth.
This works across Wi-Fi and mobile data while the host computer, database,
web server and Cloudflare connector remain online. Local development can
override it with `--dart-define=API_BASE_URL=http://YOUR_IP:8081`.
After changing an API address in source, rebuild and reinstall the APK.

A Flutter mobile translation of the PocketFinds marketplace, covering the
**Guest**, **Authentication**, **Buyer**, and **Rider** experiences. It talks
to a local PHP API backed by the existing MySQL `pocketfinds` database — the
same accounts, products, orders and storage the website uses — so data
created on the app and the website stay in sync. Seller/Admin/Logistics
remain website-only and are out of scope for this app.

## Stack

- **Flutter** (Dart) — UI and app logic
- **MySQL** — existing `pocketfinds` database on `127.0.0.1:3307`
- **Local PHP API** — reuses the neighboring Laravel website's configuration and models
- Configuration: [lib/utils/backend_config.dart](lib/utils/backend_config.dart)
- Setup and device connection instructions: [api/README.md](api/README.md)

## Getting started

Start the `MYSQL80` Windows service, then run the local API in a separate terminal:

```powershell
php api/install.php
powershell -ExecutionPolicy Bypass -File scripts/start-local-api.ps1 -BindAddress 0.0.0.0
```

```bash
flutter pub get
flutter run
```

Run `flutter analyze` and `flutter test` before committing changes.

## Project structure

- `lib/screens/` — Guest, Auth, Buyer, and Rider screens
- `lib/services/` — local API calls, grouped by feature
- `lib/models/` — typed wrappers around database rows
- `api/` — MySQL API, authorization, transactions and integration checks
- `sql/mysql/` — additive setup SQL and the query reference
- `lib/theme/` — the shared design system (colors, spacing, radius, shadows)
- `lib/widgets/` — shared UI components reused across screens

## Website UI

The guest and buyer storefronts use the PocketFinds website's original logo,
hero photograph, “Find It. Love It. Pocket It.” headline, pink palette, and
circular category carousel. Shared search, product cards, typography, and
navigation adapt the website's styling for mobile and tablet screens. Category
tiles use icons because the current mobile category model has no image field.

Brand images are bundled in `assets/brand/`. The shared storefront components
are `brand_wordmark.dart`, `storefront_hero.dart`, and
`storefront_categories.dart`. Buyer search uses server-side catalog filters.

The app and website now share the local MySQL database through the API.

## Notes

Rider implementation and website-team integration endpoints are documented in
[rider requirements](docs/rider-requirements-implementation.md).

- Existing approved website accounts use their existing email and password.
- SMTP and Google sign-in use the website/server configuration; see the API guide.
- Original Postgres scripts are historical. Use only `sql/mysql/` with MySQL.
- Buyer implementation, automated results, worker setup and outstanding device/
  website integration checks: [buyer progress](docs/buyer-implementation-progress.md).
