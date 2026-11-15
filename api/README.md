# Local MySQL API

New app-only platform GCash verification, assigned-delivery and admin payout
integration: [setup and verification](../docs/platform-payments-and-rider-integration.md).
Run the additive `php api/install-platform-payments.php` after the existing
buyer/rider installers and configure the official backend QR/account before use.
The older seller-verified GCash descriptions below apply to legacy purchases.

The Flutter app calls this API. It reuses the existing Laravel website's MySQL
connection to **127.0.0.1:3307 / pocketfinds**, its users/password hashes,
Product/Order models, logistics access rules, mail configuration and storage.
No website source files or database credentials are copied into Flutter.

## Required ERP functions

Current rider protocol: [rider requirements](../docs/rider-requirements-implementation.md).
Run `php api/install-rider-requirements.php` after the existing installers and
`powershell -File scripts/start-rider-worker.ps1` for presence/offer maintenance.
Public rider applications are disabled; logistics invitations activate riders.
The older application description below is retained as historical ERP context.

See [`../docs/erp-requirements-audit.md`](../docs/erp-requirements-audit.md) for
the page-by-page buyer/rider requirements and website-team responsibilities.
Applications now require personal/address/ID information; rider applications
also require an accepting hub and complete vehicle/license documents. Verified
applications stay pending until the existing admin/logistics review approves
them. Invitation activation remains supported. `pickup/*` handles the separate
seller-to-center leg. `delivery/receive-return` records center receipt, while
`delivery/confirm-seller-return` requires the order's seller to confirm physical
receipt before `returned`. These changes reuse existing tables and previously
installed mobile migrations.

## Start on this device

Start Windows service `MYSQL80`, then from this project:

```powershell
php api/install.php
php api/rider-migrate.php
powershell -ExecutionPolicy Bypass -File scripts/start-local-api.ps1 -BindAddress 0.0.0.0
```

The additive SQL installer is safe to repeat. It creates mobile email challenges,
registration tokens and checkout retry records, adds review media/anonymous
fields, and imports legacy wishlist entries into the website's current wishlist
table. It does not recreate the database or replace existing account data.

The API automatically finds the website when this app is inside the Laravel
project, or in the original sibling layout at `../pocketfinds`. For other layouts, set
`POCKETFINDS_WEBSITE_PATH` to its full path before starting PHP. PHP must satisfy
the website's installed Laravel dependencies (this device uses `C:\php\php.exe`).
The website's `.env` remains the source of MySQL credentials.

Verify `http://127.0.0.1:8081/health`; it reports the database name and driver.
The API runs separately from the website and must stay running while using the app.

## Run Flutter

```powershell
flutter pub get
flutter run
```

Android emulator defaults to `http://10.0.2.2:8081`; desktop/iOS simulator/web use
`http://127.0.0.1:8081`. On a physical phone, use the computer's LAN address:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8081
```

Replace the example address with this PC's Wi-Fi IPv4 address. Phone and PC must
be on the same network, and Windows Firewall must permit the API port on the
private network. Use `API_PUBLIC_URL` when starting PHP so emailed account links
also point to an address reachable from the phone. Android permits local HTTP;
iOS local network transport is configured in `ios/Runner/Info.plist`.

For deployment, serve the API through HTTPS and use the HTTPS address in
`API_BASE_URL`. The PHP built-in server and local HTTP settings are for development.

## Account and purchase behavior

- Existing approved website accounts log in with their existing email/password.
  Old Supabase sessions are not reused; sign in once after updating the app.
- Registration saves an unverified account using the website's registration
  controller, then emails a six-digit code. Buyers become approved only after
  verification. Rider accounts are created by their logistics company.
  SMTP delivery uses the website's mail settings. Password reset and email change
  send a code and local confirmation link; a log mailer does not deliver mail.
- Google login verifies signed Google tokens against the website's Web client ID.
  The app fetches this public ID from the API. Google registration prefills the
  buyer form and follows the same email verification flow. Android also requires
  its own OAuth client registration; see [mobile authentication](../docs/mobile-auth.md).
- COD checkout validates server prices, stock, seller delivery fees and discounts
  inside a transaction. Shipments follow seller/logistics processing; checkout
  does not expose unprocessed orders as available rider jobs.
- GCash follows the website reservation workflow. Open **My Orders → wallet**
  to see pending purchases, wait for seller instructions, upload the receipt,
  and place the order after seller verification. Reservations can expire.
- Messages, notifications, follows, wishlist snapshots, reviews use
  the shared tables. Private message/payment media requires an expiring media
  ticket plus an ownership check. Upload content/type/size are checked on server.
- Postgres functions/RLS are replaced by API authorization; the original SQL
  files remain historical references. Do not run them against MySQL.
- Visual search uses existing JSON signatures. Preview missing-image backfill
  with `php scripts/backfill_image_signatures.php`; add `--write` to save it.
  The backfill reads local images and requires PHP GD, with no cloud credentials.

## SQL and checks

The verified mobile rider workflow, its additive SQL, API contract, and website
team handoff are documented in [rider-workflow.md](../docs/rider-workflow.md).
Website source is not modified by the mobile implementation.

The mobile buyer purchase and order-management flow is documented in
[buyer-workflow.md](../docs/buyer-workflow.md). Its adapter reuses the website's
existing buyer controllers and tables; no additional buyer migration is needed.

- Setup SQL: `sql/mysql/2026_10_29_mobile_api.sql`
- Query reference by feature: `sql/mysql/mobile_queries.sql`
- Executed queries and transaction rules: `api/Queries.php`, `api/Actions.php`,
  `api/Messages.php`, `api/Commerce.php`, `api/Auth.php`, `api/Gcash.php`

```powershell
php api/tests/integration.php
php api/tests/buyer.php
php api/tests/registration.php
flutter analyze
flutter test
```

The MySQL integration checks use temporary fixture rows inside an outer
transaction and roll them back. They require this local database and an active
COD method and simple stocked product; they never send email or make payments.
