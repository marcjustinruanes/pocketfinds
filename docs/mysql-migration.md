# MySQL migration

The workspace application now connects through Eloquent to standalone MySQL
Server 8.0.46 at `127.0.0.1:3307`, database `pocketfinds`. MySQL Workbench can
inspect the existing database; do not execute the schema creation script against
this populated database.

All 43 source application tables and 514 source rows were imported. Every
original column value was checked against the private source snapshot, allowing
only the deliberate rewrite of nine old storage URL values to local routes.
All 71 foreign-key relationships were verified. Four source indexes that were
not table constraints were also preserved, including the conditional unique
index for company policy terms.

Laravel's three database queue tables were added because the source lacked them.
The resulting database has 46 tables and initially 516 rows, including two new
migration-history entries. Old PostgreSQL migrations are retained in
`database/archive/postgresql/migrations` and no longer execute automatically.
Fresh MySQL installations use the active baseline migration.

All 155 source storage objects were copied and verified with SHA-256 checksums.
Products and documents use `storage/app/public`; profile pictures use its
`profiles` directory. Message attachments use `storage/app/private/messages`
and retain sender/receiver authorization checks. Uploads no longer require a
remote storage service. Relative storage URLs work on both the local host and
the configured application domain.

Active Supabase database settings, storage settings, credentials, and runtime
references were removed. Existing database passwords and authentication hashes
were preserved in the import. The MySQL password now uses the standard
`DB_PASSWORD` setting; the temporary migration password setting was removed.

Verification covered guest browsing/product/shop pages, registration forms, all
five roles' dashboards and main pages, Eloquent inserts, UUIDs, auto-increment,
JSON, fractional dimensions, database queue writes, local uploads, and message
attachment authorization. Test database writes were rolled back. The existing
two automated tests also passed.

Private recovery files are in the ignored directory
`storage/app/private/mysql-migration`. They include the source environment and
configuration, source records in JSONL, per-table checksums, file checksums,
verification reports, and `mysql-backup.sql` with a SHA-256 file. This SQL backup
was restored into an isolated test database and every table value matched the
working MySQL database. Treat this
directory as confidential and keep a separate secure copy before removing the
old cloud project. The SQL backup restores into an empty selected MySQL database.

The remote Supabase project was not deleted. Project deletion requires separate
Supabase account-management access, which is unavailable in this session.
This workspace migration does not automatically redeploy a separately hosted
copy of the app or migrate other teammates' environments. Local uploaded files
must be copied along with the app if it moves to another host.

Restart any existing Laravel server and queue workers to load the new settings.
In Workbench, connect as your configured MySQL user on port 3307, refresh Schemas,
and expand `pocketfinds`.
