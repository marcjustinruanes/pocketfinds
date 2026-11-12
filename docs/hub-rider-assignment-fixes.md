# Hub Staff → Rider assignment fixes

Implemented 11 November 2026. Scope is initial pickup/delivery assignment eligibility, capacity, offer acceptance and related interface behavior. No migrations, live data repairs, route changes or hub-transfer workflow changes were made.

## Behavior

All three entry points use `RiderDeliveryOffers` for validation and assignment:

- Staff selection and automatic allocation both create a personal **60-second offer** with no `accepted_at` and no shipment rider ownership.
- The rider must accept the current, unexpired offer. Only then is pickup ownership set or delivery advanced to `assigned_to_rider`; accepted financial snapshots continue through the existing model hook.
- The rider must be approved, online, a rider account without staff/admin flags, in the same company and assigned to the matching operational hub. Pickup uses the origin hub; delivery uses the destination hub, including province. Two-wheel vehicle ownership must be valid; company-vehicle riders need an approved available matching vehicle.
- Active pickups and deliveries share the existing maximum of three jobs. Offers do not reserve capacity. Acceptance checks current combined capacity again while holding the rider lock.
- Operational locks follow the existing `GcashLocks` ordering. Rider, hub, vehicle, shipment and offer state are checked within transactions. A current locking capacity read prevents an older MySQL repeatable-read snapshot from bypassing the limit. Company riders are serialized during offer rotation so nested retries retain the lock order; this favors correctness and may cause contention in larger companies.
- Duplicate acceptance is rejected. Repeating a manual offer for the same rider while it is live returns the existing offer without duplicate notification. Replacing an offer invalidates the previous rider's ability to accept it.
- Decline/expiry releases the offer and clears the shipment offer-expiry marker. Automatic rotation still respects the existing three-failure limit; staff can send a manual offer afterward.
- The manual delivery form remains available for unclaimed sorted parcels regardless of offer history. Only destination hub staff can use its delivery controls. Screens distinguish offers awaiting confirmation, expired offers and accepted jobs. Rider dashboard accept buttons show only that rider's live offers. Scanner messages and staff buttons now say **Send offer**, rather than reporting immediate assignment.

Existing seller/rider physical pickup confirmations, final-delivery departure, proof/OTP, COD, shipping-payment gates, financial snapshot hooks, recovery/reassignment and hub-transfer workflows remain in place. Previously accepted historical assignments were not reset.

## Files changed

Application:

- `app/Services/RiderDeliveryOffers.php`: centralized eligibility, offer creation, acceptance, locking and capacity enforcement.
- `app/Http/Controllers/LogisticsController.php`: delegate manual offers and rider selection; eager-load pickup offer details. Preserve the original four-wheel transfer rider selection.
- `app/Http/Controllers/RiderController.php`: delegate pickup/delivery acceptance; scope dashboard and counts to personal offers; correct decline messages.
- `public/js/parcel-scan.js`: delivery offer controls and feedback only.
- `resources/views/logistics/assign.blade.php`: restore manual controls and distinguish offered/accepted riders.
- `resources/views/logistics/hub-dashboard.blade.php`: pickup offer feedback and wording.
- `resources/views/logistics/requests.blade.php`: pickup offer feedback and wording.
- `resources/views/rider/dashboard.blade.php`: personal-offer headings.
- `resources/views/rider/requests.blade.php` and `pickup-requests.blade.php`: confirmation and combined-capacity instructions.

Verification:

- `tests/Feature/RiderAssignmentWorkflowTest.php`: nine focused tests, including changed eligibility, capacity, stale/replaced offers, duplicate requests, vehicle availability, manual retry, dashboard controls and audit rollback.
- `tests/Feature/LogisticsAuthorizationTest.php`: assert offered state and explicit acceptance before ownership.
- `tests/Feature/LogisticsReconciliationTest.php`: add explicit rider acceptance to the GCash end-to-end fixture.
- `tests/Feature/RemainingLogisticsAuditTest.php`: update the pickup offer label and operational fixture to go online, accept offers and supply the already-required pickup tracking number.
- `tests/Support/rider-assignment-mysql.php`: disposable MySQL schema fixture with two-process lock contention checks. Clones eight table definitions, copies no live rows and removes its database in `finally`.
- This report.

## Verification results

| Check | Result |
| --- | --- |
| Focused assignment suite | **9 passed, 193 assertions**. |
| Main regression suite | **194 tests: 192 passed, 2 failed; 3,316 assertions**. Covers assignment, logistics authorization, hub transfers, parcel custody, shipping finance, commission production fixes, fulfillment and reconciliation. The two failures are existing registration fixtures missing middle name, PDF resume and NBI clearance; they were already observed before this task. |
| Extended recovery/audit suite | **45 tests: 36 passed, 6 failures, 3 errors; 479 assertions**. All nine failing cases also fail with the original assignment service/controllers loaded from Git. They concern old compensation expectations, missing tracking/evidence fixtures, recovery fixture collection and unrelated handover behavior. They were left outside this patch. |
| GCash delivery / RTS / approved pickup UI | **4 passed, 124 assertions**, after updating fixtures to confirm offers explicitly. These cases are also included in the larger suites where applicable. |
| MySQL competing pickup/delivery acceptance | Observed a real row-lock wait. With two active jobs, one acceptance committed, the competing acceptance received 422, and combined active jobs remained exactly three, even with a transaction snapshot established before the lock wait. |
| MySQL duplicate parcel acceptance | Observed a real row-lock wait. One acceptance committed, the duplicate received 422, and only one order-history event was written. |
| Blade compilation, PHP syntax, scanner JavaScript syntax | Passed. |
| Pint on service and new verification files | Passed. |
| Diff whitespace check | Passed; existing Windows line-ending normalization notices only. |

Main regression command:

```powershell
php -d memory_limit=512M vendor/pestphp/pest/bin/pest --filter='RiderAssignmentWorkflowTest|LogisticsAuthorizationTest|HubTransferWorkflowTest|ParcelCustodyTest|LogisticsShippingTest|CommissionProductionFixTest|FulfillmentWorkflowTest|LogisticsReconciliationTest'
```

MySQL verification, with process-only environment override because this workspace's `.env` currently identifies itself as production:

```powershell
$env:APP_ENV = 'local'
php tests/Support/rider-assignment-mysql.php
Remove-Item Env:APP_ENV
```

JUnit evidence is in ignored `storage/app/private/rider-assignment-*.xml`. The initial broad run exhausted PHP's 128 MB limit; the reported completed main regression used 512 MB. Pest also emits an existing permission warning when writing `vendor/pestphp/pest/.temp/test-run-history`; completed test results above are taken from the runner and JUnit reports.

Browser interaction, scanner hardware and broader production load/deadlock testing were not performed. The earlier live configuration blockers (carrier rates/accounts and hub staffing) are unchanged and remain outside these six fixes.
