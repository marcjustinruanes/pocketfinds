# Commission production-readiness remediation

Deployment steps: [commission-deployment.md](commission-deployment.md). No further migration is required on the currently configured database; activation remains a separate approved operation.

7 November 2026. **Selected application checks now pass; production commission activation is still not approved.** Enrollment remains disabled, no receiving account was created, and no configured database migration, historical timestamp conversion, payment, refund or financial backfill was executed.

## Fixes

- `MediaLifecycle` now safely cleans `commission-proofs` on rollback and recognizes evidence referenced by commission payments, credits, recoveries and recovery repayments. Tests cover rejected duplicate references, outer transaction rollback and keeping every kind of referenced proof. Existing files were not swept or deleted.
- Restored checkout rejection for inactive/unserved seller regions, missing product weights and carrier-rate mismatches, plus the GCash delivery street/house requirement. Preview warnings remain available. Actual checkout tests verify that invalid submissions create neither orders nor prepaid purchases.
- Fixed rider availability persistence: the authenticated endpoint explicitly saves only its two validated availability fields; the general User mass-assignment policy was not expanded.
- Fixed compensation snapshot creation when an existing offered/requested assignment becomes accepted by the same rider. Offering alone does not create compensation, repeated saves do not duplicate it, and recognition/payment still require the original completion and financial evidence. Completed historical assignments were not backfilled.
- Restored the buyer messaging link to the owned GCash purchase with both buyer and shop scope, valid UUID handling and private-proof guidance. Foreign, wrong-shop and array-valued purchase parameters cannot expose a linked purchase.
- Repaired test fixtures to use current rider availability, tracking confirmation, private NBI evidence, vehicle labels and review-media schema. Finance expectations now match the tests' explicit settings (PHP 20 pickup/PHP 60 transfer); production compensation rates were not changed. Investigation tests submit real synthetic image evidence and valid reasons before asserting ownership restrictions. Existing storefront/flat Seller navigation, review update and address-editor contracts are tested without reverting their current product behavior. In particular, review updates remain the application's existing editable-review behavior; this task did not introduce a new one-edit restriction.

## Final validation

| Selection | Result |
| --- | --- |
| Previous readiness regression selection, including commission, COD/GCash checkout, reporting, rider earnings, role authorization and navigation | 290 executions / 4,451 assertions passed |
| Separate existing COD, cash desk, payroll workspace and salary/attendance suites | 154 executions / 891 assertions passed |
| New production fixes, existing media lifecycle and sensitive-document suites | 23 executions / 176 assertions passed |
| Four original workflow fixes and actual role HTTP pages | 11 tests / 217 assertions passed |
| Final scoped buyer messaging check after malformed-input protection | Two tests / 34 assertions passed |
| Isolated MySQL | Eight groups passed, including actual-schema migration, receipt/refund races, recovery ownership and remittance reservation |
| Synthetic browser | Six Seller/Platform Admin/Logistics previews at 1440px/390px passed; no page overflow, unlabelled visible commission inputs or writes; cancelled confirmations stayed safe |
| Syntax | Changed PHP, commission/checkout JavaScript and whitespace checks passed |

Total of the listed PHP selections: **480 executions / 5,769 assertions, zero failures/errors**. Counts include inherited and repeated cases; they are not 480 distinct new scenarios and do not establish a whole-repository-green release. The earlier 28 failures/two errors in the assessed selection no longer reproduce in the completed selection. The historical private characterization that expected an orphaned proof is retained as old evidence, not run as a release test.

## Pending migration and timezone review

Added `tests/Support/commission-schema-audit.php`, a read-only inventory of migration history, current required columns, effective enrollment and MySQL session offset. Its statement flags inspect the whole file, including rollback code, and are advisory rather than a migration plan.

The configured application uses `Asia/Manila`; its MySQL session uses `+08:00` (28,800 seconds). All **14 checked required columns** already exist, including rider availability/offer fields, `reviews.media`, issue investigation, source order links and commission timestamps/reference protection, despite **28 pending migration records**. Nothing was marked as migrated merely because a column existed.

Reviewed pending migration sources and separated their operational risks:

- Guarded additive owner/custody/audit, shipping, documents, activation and rider fields still need destination constraint/index verification. Owner uniqueness and enum changes can reject existing conflicting data.
- Some migration files create tables/columns without complete guards (buyer/seller completion tools, review media and collage settings); replaying imported DDL can fail even when migration history says pending.
- `2026_10_31_100000_add_delivery_guarantees` can insert a voucher. `2026_11_06_000003_align_default_rider_pay_rates` changes configured rates. `2026_11_07_100000_disable_existing_free_shipping_vouchers` disables existing offers. None belongs in a blind commission deployment.
- Retired-rider-field removal and buyer-payment-account removal are destructive data changes and were not applied.
- `2026_11_07_130000` and `130001` shift historical datetime/timestamp values; the first also rewrites delivery-policy JSON. The current database contains existing application activity under Philippine time, so blanket conversion risks shifting already-local records. No conversion or automatic migration-history repair was performed.

Activation-boundary regression cases pass with both UTC and Philippine application/storage conventions, using an explicit `+08:00` policy timestamp. Pre-cutoff creation remains unenrolled and disabling enrollment does not erase obligations. These synthetic cases do **not** establish the provenance of every old naive datetime. Legacy timestamp interpretation still requires a staging/data-owner review before production cutover. Preserve mixed-era records until there is authoritative evidence for a targeted correction; do not guess an eight-hour shift.

## Data preservation and remaining activation gates

Read-only comparison against the verified 127-table SQL backup found no financial-history changes. Differences were runtime cache/session activity and `users.remember_token`; these were left intact. All 15 commission/refund tables remain empty, receiving-account count is zero, enrollment is false and the activation timestamp is null. No production scheduler reconciliation was run as a test.

The remaining gates are the verified Platform GCash receiving destination, authenticated hosted/staging acceptance, actual scheduler and queue execution with monitoring, private-file/application-key restore acceptance, production environment and database-user configuration, and the destination migration/legacy-timezone decision. Source, routes, views and assets must be deployed together as a reviewed candidate. These operations were not inferred from green synthetic tests. **Do not enable commission collection yet.**

Private evidence: `commission-remediation-final.xml/txt`, `commission-remediation-existing.xml/txt`, `commission-remediation-media.xml/txt`, `commission-remediation-workflows.xml/txt`, `commission-remediation-messaging.xml/txt`, `commission-remediation-mysql.txt`, `commission-remediation-browser.txt`, `commission-remediation-schema.json`, `commission-remediation-state.txt`, `commission-remediation-preservation.json`, `commission-remediation-concurrent-differences.json` and synthetic screenshots under `commission-ui`.
