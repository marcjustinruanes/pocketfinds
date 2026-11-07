# Manual commission payments and messaging

7 November 2026. Payments remain manual. This update adds conversations and in-app notifications to the existing commission system; it does not integrate GCash transfers or change the 10% calculation. Commission enrollment and production settings were not changed.

## Payment flows

- Buyer GCash continues directly to the Seller. After eligibility reconciliation, the Seller manually sends commission to the active Platform destination and submits the reference, private proof and optional message. Only Platform Admin actual-receipt verification collects commission.
- COD collection, verified cash turnover and net Seller payout retain the existing controls. Logistics prepares a reserved remittance, manually sends it, then submits the actual amount, reference, private proof and optional message. Withheld and in-transit funds remain uncollected until Platform Admin verifies receipt. Partial remittances leave the remaining balance outstanding.
- Admin can reply, request corrections, reject proof or verify actual receipt. Rejecting a Seller claim releases its unverified allocations as before. Rejecting COD proof records the rejection but keeps the financial status under review, its allocations reserved and sent funds in transit. A rejected screenshot cannot undo an actual external transfer.

## Messages and notifications

Each payment/remittance and recovery record has a Messages section linking to its private conversation. Seller conversations are scoped to the owning Seller and Platform Admin; COD conversations to current approved Admins of the owning Logistics company and Platform Admin. Buyers, Riders, Hub Staff, foreign Sellers/companies and suspended accounts cannot access conversations or correction evidence.

Conversations show sender identity/role snapshots, server timestamps displayed in PHT and chronological entries, with stable 50-entry pagination. Both sides can send text and optional private image/PDF correction evidence. Original payment proof remains intact. Messages cannot change amounts, references, allocations, payment revisions or verification state. Existing recovery workflows handle corrected actual Seller receipt claims while preserving rejected originals.

Submissions, messages and review decisions notify the other side through the existing notification center. Notifications link to the correct conversation and omit proof contents and message text. Existing commission-due notifications now open Commission Statements. UUID retry keys and unique event keys prevent duplicate messages/notifications, and database rollback includes notifications and attachment cleanup. There is no edit/delete conversation endpoint.

## Installation

Added `2026_11_08_000004_add_commission_payment_messages.php`: an append-only conversation table and nullable review-decision metadata on payments and recoveries. Existing rows are not rewritten. Foreign keys preserve linked history and destructive rollback is refused.

**Installed on the configured `pocketfinds` database, batch 113**, after explicit installation approval. Migrations 000001–000003 were verified in batches 110–112. Applied only `2026_11_08_000004_add_commission_payment_messages.php`; unrelated migrations were not run.

The fresh complete SQL backup restored and verified 129 tables / 1,095 rows before installation. Post-install comparison matched every original table and all 1,095 rows, excluding only the new migration-history entry and the additive nullable review fields. All three message foreign keys use restrictive deletion, and submission/event retry keys are unique. The live message table remains empty; no synthetic transaction was inserted into the configured database. Compiled views were cleared.

Post-install verification passed **10 isolated tests / 312 assertions**, covering both role conversations, notification links, replies, correction evidence, permissions and Admin verification. Existing `.env` contents matched their pre-install hash, enrollment remains false and the activation timestamp remains null. This is not a claim that real authenticated hosted sessions or external payments were exercised.

Private installation evidence: `storage/app/private/commission-messaging-install-{before,after}.json`, `commission-messaging-installed-final.json`, `commission-messaging-install-backup.txt`, `commission-messaging-installed-http.xml/txt`, and backup `database-backups/commission-complete-20261107-152316` with its verified manifest/checksum. Temporary MySQL client credentials and the scratch restore database were removed.

For another destination, inspect actual schema and migration history first. After approval and a verified backup, apply only this exact migration path when prerequisites are installed and messaging schema is absent. Do not blindly replay imported DDL or run unrelated pending migrations. Missing messaging installation fails closed for new messages/notes while preserving existing payment workflows.

## Validation

- New messaging and combined transaction HTTP selection: **10 executions / 312 assertions passed**, covering manual GCash/COD flows, corrections, COD rejection/transit retention, actual-receipt attestation, notifications and links, private correction evidence, chronological pagination, fresh role/company authorization, retries, malformed uploads, rollback cleanup and missing-installation behavior.
- Existing commission and refund selection: **101 executions / 923 assertions passed**. Combined: **111 executions / 1,235 assertions, zero failures/errors**, including inherited/repeated cases.
- Isolated MySQL: **10 groups passed**, including actual-schema migration, receipt/refund races, remittance reservations, account activation races and simultaneous message retries producing exactly one message/notification with unchanged financial state.
- Changed PHP syntax and whitespace checks passed. No hosted-session or real-money certification is claimed. Tests used synthetic accounts, fake/private evidence and isolated SQLite/MySQL databases.

New files: `CommissionConversations`, the migration, conversation/message-link/optional-message/payment-review Blade partials and `CommissionMessagingTest`. Integration changes are limited to commission payment/recovery services, scoped conversation routes/controller, commission views/styles, commission-notification routing, media reference protection and test/schema-audit support.

Private evidence: `storage/app/private/commission-messaging-http.xml/txt`, `commission-messaging-regression.xml/txt`, `commission-messaging-mysql.txt` and the synthetic fixture artifacts. No real order, payment, receiving account or commission obligation was created or changed by these tests.
