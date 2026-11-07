# PocketFinds deployment and commission activation

**Deployment instructions only. Collection has not been activated.** Keep enrollment disabled through steps 1–5. Step 6 requires the completed checks and your explicit activation approval.

## 1. Configure these manually

- **Platform GCash:** Platform Admin opens `/commissions` and publishes the real, independently verified receiving account. No receiving account is currently configured.
- **People and accounts:** assign receipt reviewers, confirm Logistics treasury accounts and Seller payout destinations, and agree who handles disputed references, refunds and daily reconciliation.
- **Production environment:** use HTTPS, correct proxy handling, a dedicated application database user, and these settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
COMMISSION_ENABLED=false
COMMISSION_ACTIVATION_AT=null
```

Keep the existing `APP_KEY`. Preserve the production `.env`, database and private uploads when deploying code.

- **Backups:** back up the complete database, private payment-proof files and application key securely. Verify restoration and keep an off-host copy. SQL restoration alone does not restore receipt files.
- **Legacy timezone decision:** application time is `Asia/Manila` and the MySQL connection uses `+08:00`. Have the data owner review old timestamp provenance on staging. Do not guess an eight-hour conversion.

## 2. Migrations: none needed on the currently configured database

Confirmed in the configured `pocketfinds` database:

| Commission migration | Installed batch |
| --- | --- |
| `2026_11_08_000001_add_platform_commission_ledger` | 110 |
| `2026_11_08_000002_complete_commission_workflows` | 111 |

All 15 feature tables exist. No additional migration is required for these code fixes on this database.

If deploying to a different database, first inspect its migration status and actual schema. These are read-only checks:

```sh
php artisan migrate:status
```

The source repository also includes the read-only `php tests/Support/commission-schema-audit.php` inventory. Run it from a checkout containing that support file, using the intended database configuration. It does not repair migration history.

**Do not run the other 28 pending migrations automatically.** Some change rates or vouchers, drop data, or rewrite historical timestamps. Imported tables can already exist despite pending migration records.

Only if a destination genuinely lacks the commission schema, obtain separate approval and a verified backup before using these exact paths, in order:

```sh
php artisan migrate --path=database/migrations/2026_11_08_000001_add_platform_commission_ledger.php --force
php artisan migrate --path=database/migrations/2026_11_08_000002_complete_commission_workflows.php --force
```

Those commands are conditional instructions, not approval to execute them. If a migration is pending but its tables already exist, stop and reconcile the schema/history on staging instead of replaying it.

## 3. Deploy the reviewed code with commission OFF

1. Freeze the reviewed release and test it on staging with a representative restored database and private files. Confirm Seller, Platform Admin, Logistics Admin and Hub Staff workflows through authenticated browsers.
2. Take the verified production backup, then use a controlled maintenance window. Pause/drain scheduled and queue work before switching the release.
3. Deploy the complete reviewed source, migrations, views and assets together. Preserve `.env` and persistent storage. Include directly served `public/css` and `public/js` assets as well as any Vite build output. Do not include SQL dumps or private test artifacts in the public web root.
4. Install dependencies from the lock files and refresh Laravel caches in the deployed release:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan route:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

Build Vite assets in the release/build environment with `npm ci` and `npm run build` if that deployment uses them. Confirm the PHP application and worker processes use this same release and configuration. The web document root must be `public/`.

**Do not use `composer setup`, `artisan key:generate`, or unrestricted `artisan migrate --force` for this deployment.** The setup script regenerates the application key and runs all pending migrations.

## 4. Verify background execution

- Configure one scheduler owner to run `php artisan schedule:run` **every minute**, with the deployed application as its working directory. On Windows, use a service-account Scheduled Task; on Linux, use cron. Use the correct PHP binary, private logs, persistent cache locks and failure alerts.
- Supervise the configured database queue worker using `php artisan queue:work`; verify restarts and failed-job monitoring.
- `php artisan schedule:list` should show `commissions:reconcile` every five minutes. Listing alone is not execution evidence. Observe successful runs across at least two five-minute boundaries and verify behavior with synthetic obligations on staging.

Resume approved background work after the release switch. Keep collection disabled while checking it. Existing scheduled jobs can affect active orders, so complete the legacy-timezone review before enabling unverified production schedules.

## 5. Check the deployed release before activation

- Confirm effective enrollment is false and the activation timestamp is null in both HTTP and CLI/worker configuration.
- Confirm the verified receiving account and reviewer permissions. Hub Staff must not gain commission management permissions.
- On staging, verify COD deduction/remittance/receipt verification; separate Seller GCash payment; rejected-reference recovery; zero-balance proof; full/partial refund; residual payout; private evidence and exports.
- Complete hosted authenticated acceptance, backup/file restoration acceptance, scheduler/queue checks and the destination schema/timezone decision.

The selected automated checks passed **480 executions / 5,769 assertions**, eight MySQL groups and six synthetic browser previews. They do not certify hosted authentication, actual GCash transfers or production operations.

## 6. Activate only after everything above is verified

Obtain explicit business approval for a **prospective date/time with timezone**. Example only—replace this date with your approved future activation time:

```dotenv
COMMISSION_ENABLED=true
COMMISSION_ACTIVATION_AT="2026-12-01T00:00:00+08:00"
```

Before that time, refresh the production configuration and restart long-lived workers so all processes agree:

```sh
php artisan config:cache
php artisan queue:restart
```

Restart any persistent scheduler/application processes through their supervisor as appropriate. Recheck the effective values through authenticated HTTP and CLI, not just the `.env` file. Do not backdate the cutoff or enroll old transactions.

After the cutoff, inspect the first eligible COD and GCash snapshots: 10% of merchandise after Seller-funded discounts, excluding shipping. A pre-cutoff GCash purchase must remain grandfathered even if its order is placed later. Count commission as collected only after actual receipt verification. Monitor pending receipts, unapplied credits, refunds and treasury/transit reconciliation.

## If a problem occurs

Set `COMMISSION_ENABLED=false`, rebuild the configuration cache and restart long-lived workers. Continue servicing existing obligations and credits. Do not drop commission tables, run destructive rollback, delete audit history or restore a backup over intervening real transactions. Use a reviewed forward fix.

No activation, receiving-account change, migration or historical-data update was performed while preparing this guide.
