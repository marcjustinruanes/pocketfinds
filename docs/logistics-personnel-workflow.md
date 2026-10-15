# Logistics personnel workflow — October 14, 2026

Implemented the approved separation of recruitment decisions from account maintenance. This report describes this task only; earlier transfer, hiring, address, media, and other workspace changes were preserved.

## Behavior

- Rider applications can move from pending to approved or rejected. Approved Riders can be suspended, and suspended Riders can be reactivated.
- Hub Staff must move from pending to interview before approval. Interview scheduling requires the existing date, time, and location inputs; final approval requires the interview metadata. The existing confirmation dialog remains the company's attestation that the interview happened. No new interview-completion status or deadline rule was added.
- The existing exception allowing Hub Staff rejection before an interview is retained for unsuitable or incomplete applications. Only pending/interview applications can be rejected, and a reason is required. Approved personnel must use suspension instead.
- Platform Admin cannot approve or reject Rider/Staff recruitment through generic registration endpoints. Personnel suspension requires approved status; reactivation requires suspended status. Pending, interview, and rejected accounts cannot be activated or suspended to bypass recruitment. Unrelated Buyer, Seller, and Logistics founder approval behavior remains intact.
- Generic company account creation always writes pending. Explicit non-pending status inputs fail validation. Creation locks the company hub and rechecks role-specific intake availability, including operational status, switches, deadlines, and capacity.
- Generic account editing rejects status inputs and preserves the stored status. Profile/password changes continue through the existing validation and model boundary. Account forms display status without a status selector. Platform Admin's user page hides inappropriate personnel actions and explains where recruitment decisions belong.

Transition authorization and validation run inside the existing hub/account locking transaction for authenticated Eloquent personnel status updates. Company Logistics Admin decisions require the same company; Hub Staff and unrelated roles cannot make decisions. Fresh locked account metadata is used for approval, rather than document/interview data from an earlier read. Existing stale status/hub/company checks, capacity checks, append-only history, activity audit rollback, and commit-dependent notifications remain in place.

Initial approval requires the existing ID reference/type and selfie reference. Riders also require license reference/number/expiry, vehicle type/ownership, and own-vehicle brand/model/plate/OR/CR references, or the existing approved available company-vehicle eligibility check. License expiry retains the registration convention of a required date; this task introduces no new expiry policy. Suspended legacy personnel are not retrospectively subjected to initial application document requirements; operational hub and capacity checks still apply on reactivation.

The existing identity-matched rejected-applicant reapplication service remains available, preserves account IDs and rejection history, and starts a new pending attempt. Its validated write uses a request-scoped internal registration context, cleared in `finally`, so reapplication is not treated as an administrative recruitment decision.

## Exact files changed

- `app/Services/HubHiring.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/LogisticsController.php`
- `app/Http/Controllers/LogisticsManagementController.php`
- `app/Http/Controllers/AdminController.php`
- `resources/views/logistics/partials/account-form.blade.php`
- `resources/views/admin/users.blade.php`
- `tests/Feature/HubHiringTest.php`
- `tests/Feature/LogisticsAuthorizationTest.php`
- `docs/logistics-personnel-workflow.md` — new report.

No migrations are required. No existing account, hub, company assignment, application, or document was changed in the live database. No authentication/OAuth files, dependencies, or `.env` changes were made. No deployment, reset, reseed, or destructive database operation was performed.

## Actual verification

| Command | Actual result |
|---|---|
| `php artisan test --filter='HubHiringTest\|LogisticsAuthorizationTest'` | Final run: exit 0; **55 passed, 1,154 assertions**. |
| `php artisan test` | Final run: exit 0; **167 passed, 2,101 assertions**, 23,699 ms. |
| Targeted `php vendor/bin/pint` on the affected PHP files | Exit 0; formatting applied. |
| `php vendor/bin/pint --dirty --test` | Final run: exit 0; passed. |
| `php artisan view:cache` | Exit 0; Blade compilation passed. |
| `php artisan route:list --path=logistics/staff` | Exit 0; seven existing routes listed. |
| `php artisan route:list --path=admin/users` | Exit 0; three existing routes listed. |
| `git diff --check` | Passed; Git emitted line-ending normalization notices. |
| Comparison against pre-task snapshots of affected files | Reviewed substantive changes; no unrelated behavioral change found. |

Tests use isolated SQLite fixtures. New coverage checks mandatory interviews, duplicate/invalid decisions, early rejection with reason, staff reapplication/history, Platform Admin restrictions and legitimate suspension/reactivation, generic creation/edit bypasses, maintenance of pending status during password/profile edits, missing/stale document metadata, and staff capacity from the valid interview stage. Existing tests retain six-role authorization/company isolation, hub movement/capacity, rollback, and committed Rider notifications. Staff notification expectations additionally require committed status/history and zero transaction level before mail invocation. Tests do not send real emails.

Initial regression failures exposed a null vehicle-type call, old expectations allowing generic approval, and a duplicated fixture column/table after extending document fixtures. These were corrected before successful reruns. A formatting check failed after an intermediate attempt to preserve pre-existing formatting; the affected files were formatted and the final check passed. These earlier failures are not reported as successful checks.

## Remaining limits

- Manually created pending accounts without the required document references cannot be approved. The account-maintenance form does not upload recruitment documents; authorized document completion/reconciliation is needed. This task does not create a new document-upload workflow or relax approval requirements.
- Approval verifies required stored document references and metadata, not file authenticity or physical file availability. The existing registration upload validation remains responsible for format/size validation. Document review and interview completion still require an authorized human decision.
- The actor-dependent transition guard covers authenticated Eloquent writes. Raw SQL/query-builder writes and unattended writes without an authenticated actor remain trusted maintenance paths; future writers must explicitly follow the recruitment rules. Existing live records were not retrospectively reconciled.
- No browser interaction, SMTP delivery, or MySQL concurrency tests were performed for this task. SQLite stale-write regressions are not concurrent MySQL execution. The existing MySQL hub/account locking mechanism was retained; earlier hiring concurrency results are not claimed as verification of these new rules.
- OTP, Google identity binding, stateless OAuth, callback TLS verification, and existing document storage/retention concerns remain outside this task.
