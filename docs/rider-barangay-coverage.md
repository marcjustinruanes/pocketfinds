# Logistics rider barangay coverage

Implemented 11 November 2026 for Logistics Admin and Hub Staff dispatch. No Rider controller, Rider dashboard/template, offer status/timeout/acceptance transition, or hub-transfer implementation was changed in this task.

## Use

1. As Logistics Admin, open **Rider Management → Manage barangay routes**, or use the link on the rider's account management page.
2. Select one or more barangays from the rider's assigned operational hub municipality and save. Saving with all boxes cleared removes coverage.
3. The rider list displays the assigned barangay names. After a hub move, old coverage does not apply; configure coverage for the new hub.
4. Hub Staff's pickup dashboard selects riders covering the seller's pickup barangay. Delivery Assignments and the parcel scanner select riders covering the order's saved destination barangay. Empty choices show an explicit no-eligible-rider message and disable sending an offer.

Existing riders start with no assigned coverage. **Admins must configure coverage before those riders can receive new pickup/delivery offers.** Accepted jobs are not reset or migrated. No routes were inferred from riders' residential addresses.

## Implementation

- Inspected the current MySQL geography: `users` and `buyer_addresses` store barangay names alongside province/municipality; `orders.shipping_address` is JSON; `logistics_hubs` stores region/province/municipality. The application's own database had no barangay coverage table. Both user and hub IDs are signed `BIGINT`.
- The additive migration `2026_11_11_000001_add_rider_barangay_routes.php` creates rider/hub/PSGC-barangay-code records with canonical names, normalized matching keys, foreign keys and a duplicate-code constraint. It was applied successfully to the configured `pocketfinds` database. The new table starts empty; existing user/order/shipment data was not rewritten.
- `RiderBarangayCoverage` uses the existing cached `HubAddress` geographic source. It validates choices against the hub municipality, supports region-only NCR hubs, normalizes names for matching, and protects edits with company authorization, hub/user locks and a stale-form version check. Lookup failure preserves saved routes and exposes a retry message.
- `RiderCoverageController` exposes two Logistics Admin-only routes: GET/PUT `/logistics/riders/{id}/coverage`. Company restrictions are enforced on reads and writes. Hub Staff, riders, sellers and buyers cannot manage coverage.
- The shared `RiderDeliveryOffers::eligible()` selection query additionally joins matching coverage by rider, **current hub ID**, and normalized parcel barangay. This prevents automatic offer entry points from bypassing coverage. Existing eligibility, capacity and acceptance logic remain in place; no Rider module code or interface was edited. Missing coverage or a missing parcel barangay produces no candidates, with no all-riders fallback. During deployment before the migration exists, the existing eligibility behavior remains available; after installation, coverage is required.
- `LogisticsController` supplies per-parcel choices to the Hub Staff/admin assignment screens and scanner instead of a hub-wide rider list. Four-wheel hub-transfer rider selection is unchanged.

## Files for this change

- New: `app/Services/RiderBarangayCoverage.php`, `app/Models/RiderBarangayRoute.php`, `app/Http/Controllers/RiderCoverageController.php`.
- New: `database/migrations/2026_11_11_000001_add_rider_barangay_routes.php` and `resources/views/logistics/rider-coverage.blade.php`.
- Updated: `app/Models/User.php` (coverage relationship), `app/Services/RiderDeliveryOffers.php` (shared coverage filter), `app/Http/Controllers/LogisticsController.php`, and `routes/web.php`.
- Updated Logistics views: `riders`, `account-edit`, `assign`, `hub-dashboard`, and `requests`.
- Updated: `public/js/parcel-scan.js` (Hub Staff no-coverage messages only).
- New: `tests/Feature/RiderBarangayCoverageTest.php`.
- Updated: `tests/Support/rider-assignment-mysql.php` to install coverage in its disposable schema and verify existing concurrency guarantees with matching pickup/delivery coverage.
- This report. Earlier workspace changes were preserved.

## Verification

| Check | Result |
| --- | --- |
| Barangay coverage tests | **12 passed, 122 assertions**: admin create/edit/clear/list; permissions/company boundaries; unknown/duplicate/foreign codes; stale forms and hub changes; pickup/delivery distinction; dropdown filtering; manual and automatic offers; missing addresses/coverage; eligibility and capacity preservation; lookup failures; audit rollback; NCR geography. |
| Assignment/coverage final focused run | **21 passed, 315 assertions**. |
| Regression suite | **115 passed, 2,506 assertions**: coverage, initial assignment, logistics authorization, hub transfers, parcel custody and logistics shipping. This run preceded the two extra dropdown/NCR tests; those passed in the final focused run. |
| Disposable MySQL | Migration created successfully against cloned signed-ID table definitions. Both real two-process lock contention checks passed with coverage enabled: competing pickup/delivery acceptance stays at three active jobs; duplicate acceptance writes one history transition. Audit database removed; no live rows copied. |
| New migration in configured database | Applied successfully; confirmed new table exists with zero initial route records. |
| Formatting, PHP syntax, JavaScript syntax, Blade compilation | Passed. |

Commands:

```powershell
php -d memory_limit=512M vendor/pestphp/pest/bin/pest --filter='RiderBarangayCoverageTest|RiderAssignmentWorkflowTest|LogisticsAuthorizationTest|HubTransferWorkflowTest|ParcelCustodyTest|LogisticsShippingTest'
php artisan test --filter=RiderBarangayCoverageTest
$env:APP_ENV = 'local'
php tests/Support/rider-assignment-mysql.php
Remove-Item Env:APP_ENV
```

Tests fake the geographic source; physical browser/scanner interactions and production load were not tested. The existing Pest warning about writing its vendor test-run-history file remains; it did not prevent the reported tests from passing. Earlier carrier-rate/account and hub-staffing configuration findings remain outside this feature.
