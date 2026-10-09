# PocketFinds

PocketFinds is a Laravel application using Eloquent with standalone MySQL and local file storage. XAMPP is not required.

## Local setup

1. Install PHP 8.3 or newer with `pdo_mysql` enabled, Composer, and MySQL Server.
2. Run `composer install`. Run `npm install` and `npm run build` when building frontend assets.
3. Copy `.env.example` to `.env` only for a new installation. The existing migrated `.env` already has the working MySQL connection; do not overwrite it.
4. Configure the MySQL host, port, database, username, and password. The current workspace uses `127.0.0.1:3307` and database `pocketfinds`. Set `APP_URL` for your own host.
5. Create an empty MySQL database before running `php artisan migrate`. The active baseline creates the application tables. On a new installation, generate an application key with `php artisan key:generate`.
6. Run `php artisan storage:link` to expose public uploads. Message attachments remain private and are served through authenticated routes.
7. Start the app with `php artisan serve`. Restart an existing server after changing `.env` or PHP configuration. If using background jobs, start `php artisan queue:work`.

## MySQL Workbench

Connect using Standard TCP/IP, host `127.0.0.1`, port `3307`, and your configured MySQL account. Refresh Schemas and expand `pocketfinds` to view the migrated tables. Do not rerun the CREATE TABLE script against the populated database.

The SQL schema is in `database/pocketfinds-mysql-schema.sql`; queue infrastructure is in `database/mysql-queue-tables.sql`. Laravel's active migrations install both for new databases. The earlier PostgreSQL migrations are archived and do not run against MySQL.

## Migration and backups

See [the migration report](docs/mysql-migration.md) for validation, storage paths, and recovery details. Private backups under `storage/app/private/mysql-migration` contain personal records and credentials and must never be committed or shared publicly.

The application no longer connects to Supabase. The old cloud project remains available as a recovery source; it was not deleted. A separately hosted application and teammates' installations need their own environment changes and uploaded files copied to their host.

## Checks

Run `php artisan test` for the automated tests. `database/verify_mysql_app.php` exercises the migrated app using transaction rollback and temporary upload files; `database/verify_local_assets.php` verifies copied files against their checksums.
