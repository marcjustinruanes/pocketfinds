# Critical logistics security and custody package — A01–A03

Implemented 29 October 2026 against `docs/logistics-module-audit.md`. No A04–A15 work, new role, second company operator, financial correction policy or emergency bypass was implemented. No financial history, rate snapshot, payout, remittance balance or delivery record was backfilled.

## A01 — Physical hub receipt

- Origin receipt now rechecks the actor's current database status and Hub Staff role, company, operational assigned hub and receiving location under the existing transaction/locking footprint. Company Admin is denied.
- Transfer arrival also requires the exact destination hub ID and current approved destination Hub Staff. The service enforces this boundary, including callers using legacy scanner completion routes. Destination identity uses the actual hub record; absent historical batch display-name snapshots do not grant or remove authority.
- Admin still monitors company parcels, authorizes and dispatches transfers, records/reviews discrepancies and performs existing financial oversight. The transfer screen shows an awaiting-hub-receipt explanation instead of an Admin arrival form; scanner responses no longer offer Admin physical receipt.
- Local receipt/sorting, route order, partial batch receipt, rider pickup confirmation, duplicate protection and the original earning triggers remain. Successful physical receipt still records the receiving actor and timestamps; a whole batch earns once, not once per parcel.

## A02 — Physical final delivery

- Generic logistics status requests for Delivered return 403 for both Admin and Hub Staff. Delivered is removed from their offered transition list. Existing operational actions and closing an already delivered order remain distinct from creating delivery evidence.
- The assigned rider path rechecks current approved rider identity and company under locks, requires a current non-cancelled/non-rejected delivery assignment, and only updates that rider's matching assignment.
- COD exact collection and handover confirmation remain atomic with delivery, timestamps, order history and earnings. GCash has the same assigned-rider delivery authority without inventing a COD collection.
- Buyer confirmation, automatic completion rules, remittance verification, settlement eligibility and recipient acknowledgement were not changed. No generic Admin delivery action can create earnings through the previously allowed override.

## A03 — Private documents

- New ID, selfie, licence, OR/CR, vehicle and resume uploads use the `sensitive_documents` private local disk. The shared upload lifecycle redirects the existing sensitive directories, so registration and document-update callers retain their current field/action contracts. Buyer/seller ID and selfie uploads sharing these directories receive the same protection; company logos and unrelated public media remain public.
- Document links contain an encrypted opaque token, not a public storage path. Every request authenticates and rechecks current status/ownership/role/company. Approved company Admin can review their company's rider/Hub Staff documents; Hub Staff and riders cannot browse colleagues' documents. Approved owners can read their own documents, and existing approved Platform Admin review remains available. A copied URL is not an authorization grant.
- Successful document access is logged using actor ID and a document-path hash, without raw document paths or contents. Responses use a generic filename, private/no-store caching, nosniff, a sandboxed content policy and a restricted image/PDF MIME list. Sensitive User file attributes are hidden from normal model JSON serialization; Blade document review still reads the attributes normally.
- Registration, review thumbnails, existing lightboxes and relevant seller/Admin document-update links now use private delivery. Missing or unauthorized files fail closed. Rollback cleanup and reference protection work with the private disk; files still referenced by account history are retained.

### Legacy migration and local result

`php artisan documents:privatize` is a dry run. `php artisan documents:privatize --apply`:

1. Takes an exclusive migration file lock.
2. Finds referenced document paths and files in the known sensitive public directories, preserving unreferenced legacy files privately too.
3. Rejects unsafe/out-of-root/symlink sources.
4. Copies without overwriting a conflicting private file, verifies identical SHA-256 contents, then removes only the verified public duplicate.
5. Leaves database references unchanged. Retries are safe. A conflict/read/copy/delete error retains the original and reports failure; missing files are reported rather than fabricated.

Local application: **107 files privatized, zero migration failures**. Initial sandboxed deletion was denied by Windows; the same verified migration completed with escalated filesystem execution. A subsequent dry run reports **zero public candidates**. Read-only verification also found **zero referenced sensitive paths remaining public**.

**10 unique referenced files were already missing in both stores.** Their ten user/field references remain unchanged and need authorized investigation/re-upload. The command deliberately returns a nonzero exit code while missing references remain; this is not a copy-verification failure. Private aggregate evidence, user IDs/field names and path hashes are in `storage/app/private/logistics-ui-review/critical-document-migration.json`. No document contents or public/private filenames are included in that review artifact.

For another deployment: drain old upload workers and use a maintenance window; ensure private storage is outside every web-served/symlinked root; deploy code and run the dry run/apply commands; resolve conflicts and confirm zero public candidates; restart workers and purge any CDN/proxy copies of old public document URLs. The authenticated public-disk fallback exists only for staged migration compatibility; it does **not** protect a public file from a web server serving its old static URL. Complete migration before treating a deployment as protected. Previously downloaded/cached copies cannot be revoked by moving origin files.

## Verification

Final regression: **319 tests / 4,045 assertions passed; zero failures or errors in this selected run.** Results are recorded in `storage/app/private/logistics-ui-review/critical-security-final.xml`. The tested suites cover private documents, registration/fulfillment, media lifecycle, COD, Cash Desk, shipping, seller settlements, rider compensation, reconciliation, financial foundation, company/hub authorization, transfers, navigation and buyer commerce. The earlier focused and regression runs overlap this total and must not be added to it. PHP syntax checks and `git diff --check` also passed.

- Negative checks cover Admin origin/batch/legacy scanner receipt, Admin/Hub Staff GCash delivery override, revoked rider status behind a stale authenticated instance, wrong companies, colleagues, Hub Staff document browsing, buyer/seller document access, revoked Admin, tampered tokens and traversal payloads.
- Positive checks cover assigned Staff receipt/earnings, assigned rider delivery/COD/earnings, owner/company Admin/existing Platform Admin document access, private registration/update uploads, rollback cleanup, retained references and opaque document-review URLs.
- Migration checks cover dry run, preserved references/bytes, repeat execution and refusal to overwrite mismatched private content.
- Real multi-process MySQL checks passed for the new physical batch-receipt race: Admin receives 403, actual destination Staff records one arrival, replayed arrival is rejected and two parcels earn one PHP 150 trip entitlement. The isolated runner now clones `hub_transfer_events` as well as the other workflow schemas to exercise the real receipt event write.
- Existing MySQL cash reservation/count/release/receipt, insufficient custody, shipping/reference ownership, treasury, seller payout/dispute, rider recognition/funding/reservation/payment/hold/acknowledgement and consistent report-snapshot races also passed. Only the runner's random schema-only database and private fixture files are created/removed; source business data is unchanged.
- Browser fixture checks at 1440px and 390px covered Admin transfer oversight, Staff arrival confirmation and rider document review. No document overflow or unlabelled form controls; expected sidebar parent/active links and Dashboard/My Hub ordering remain. Admin HTML contains no receipt form; Staff HTML contains it. The document review control uses an opaque private endpoint with no plaintext document path. Desktop Admin and mobile Staff screenshots were visually inspected. The receipt button is at least 44px high and its checkbox has a padded labelled tap row; unrelated pre-existing roster filters are still 37px. No claim is made that every control throughout logistics was redesigned.

Browser previews use isolated synthetic fixtures; authorization/file response behavior is tested through Laravel HTTP tests, not inferred from a static preview. Private helpers/screenshots are under `storage/app/private/logistics-ui-review/` and are not product routes.

The known hiring/rider-workbook fixture failures and device skips remain outside this package. Adding a private fake disk to their setup does not repair or weaken their registration expectations. These regression results do not claim that the full repository or the original manual workbook is green.

## Changed files in this package

| Area | Files |
| --- | --- |
| Receipt/delivery authorization | `app/Services/LogisticsAccess.php`, `app/Services/HubTransferWorkflow.php`, `app/Http/Controllers/LogisticsController.php`, `app/Http/Controllers/RiderController.php` |
| Private document delivery/storage | `app/Services/SensitiveDocuments.php` (new), `app/Http/Controllers/SensitiveDocumentController.php` (new), `app/Services/MediaLifecycle.php`, `app/Models/User.php`, `config/filesystems.php`, `routes/web.php`, `routes/console.php` |
| Document review links | `resources/views/components/admin-doc-thumb.blade.php`, `resources/views/admin/compliance.blade.php`, `resources/views/admin/doc-requests.blade.php`, `resources/views/admin/update-requests.blade.php`, `resources/views/seller/account.blade.php` |
| Operational UI | `resources/views/logistics/transfer-show.blade.php`, `resources/views/logistics/monitor.blade.php`, `public/css/logistics-ui.css` |
| Security/regression tests | `tests/Feature/SensitiveDocumentsTest.php` (new), `tests/Feature/RiderCompensationTest.php`, `tests/Feature/HubTransferWorkflowTest.php`, `tests/Feature/CodCollectionTest.php`, `tests/Feature/LogisticsAuthorizationTest.php`, `tests/Feature/FulfillmentWorkflowTest.php`, `tests/Feature/MediaLifecycleTest.php`, `tests/Feature/BuyerCommerceTest.php`, `tests/Feature/HubHiringTest.php`, `tests/Support/cash-desk-mysql.php` |
| Documentation | `docs/critical-logistics-security-package.md` (this report) |

No database schema change was needed. Existing field values and additive finance schema were preserved. Other pre-existing workspace changes belong to earlier work and are not claimed as changes made by this package.

## Exception proposals — approval required before implementation

**Unavailable receiving Hub Staff:** keep the parcel Picked Up/In Transit until actual assigned Staff can attest receipt. Proposed exception intake would record the affected hub/batch, actor, actual event time versus recording time, reason staff is unavailable, rider confirmation, manifest and private evidence. Admin reviews and arranges a legitimately assigned Staff receiver using approved staffing procedures. The request alone must not mark receipt, verify cash, earn compensation or impersonate Staff. Any ability to use a substitute receiver, retrospective receipt or different hub requires an explicitly approved custody policy and design. No such shortcut is enabled now.

**Emergency delivery correction:** propose a separate immutable correction request containing shipment/assignment, claimed handover time, recipient evidence/confirmation, rider statement, reason the normal rider path failed and duplicate/COD checks. Admin investigates, with the normal assigned rider confirming when possible. Approval is needed on admissible evidence, who may attest if the rider is permanently unavailable, correction timestamps and financial treatment before a state-changing correction is built. Investigation must not fabricate buyer confirmation, cash collection, Hub Staff count or earnings. Existing records must remain visible; no silent overwrite/backdated financial event.

Still requiring action: investigate the ten missing documents; apply migration and cache purge on any other deployed environment; approve any emergency correction policy before implementation. No A04–A15 implementation was started.
