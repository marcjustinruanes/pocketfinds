# Logistics implementation

This update extends the existing Laravel/MySQL application and Blade logistics layout. It retains the six operational roles represented by the existing account types and logistics subroles. Platform Admin and Logistics Admin remain separate.

Transfer behavior has since been updated: Hub Staff request transfers, Logistics Admin separately approves and dispatches, and destination staff confirm receipt. See [hub-transfer-workflow.md](hub-transfer-workflow.md) for current authorization, migration, and verification results.

## Changes

- Logistics Admin can manage their company's municipal hubs, riders, hub staff, vehicle availability, and maintenance notes. Existing platform approval of company vehicles is preserved.
- Hub Staff can read parcels on their assigned hub's route, identified by municipality **and province**, and perform only operations belonging to that hub. Suspended staff and accounts with invalid or closed hub assignments are rejected by the backend.
- Rider self-claim queues and assignment operations require an approved rider, the correct company and hub, a matching vehicle type, and an available approved company vehicle when applicable.
- User & Account Management provides creation, profile editing, hub assignment, activation/suspension, password changes, and assignment history for riders and hub staff only. Client-supplied platform privileges and company names cannot grant access.
- Dashboard statistics and recent activities use database queries. Monitoring remains status-based; this update adds no GPS coordinates or simulated tracking.
- Issues can be recorded, assigned within the responsible hub, updated, and resolved with an event history. These operational issues complement the existing failed/returned/cancelled shipment list and do not replace platform complaints.
- Delivery History includes delivered, completed, failed, returned, and cancelled parcels, with tracking/order search, date/status/hub filters, and pagination.
- Reports include parcel/pickup statuses, rider performance, municipal hub parcel counts, current vehicle availability, date/hub filters, and CSV export. Report date filters use parcel creation dates. CSV values are escaped against spreadsheet formula injection.
- Audited logistics/rider operations write actor, route action, affected record ID, and timestamp to company-scoped Activity Logs. Messaging, lookup, logout, and read-only requests are excluded. Transfer batches record each affected leg. Passwords and request bodies are never written to these logs. Historical actions before installation are not reconstructed.
- Database operational changes and activity records share a transaction. Exceptions, rendered error responses, and validation-error redirects roll back database changes. Rider/staff decision emails are sent only after commit. The follow-up adds transaction-aware upload cleanup and deferred replacement deletion; filesystem operations are not atomically committed with the database.

## Database

`2026_10_13_000001_add_logistics_operations` adds hub details and operational status, province fields for existing transfer legs, logistics activity logs, logistics issues, and issue events. It reuses existing users, hubs, shipments, transfers, assignments, and vehicles. Existing records are preserved. This migration has been applied to the local database.

## Validation

- `php artisan test`: existing suite plus logistics regression tests; tests use in-memory SQLite.
- `php vendor/bin/pint --dirty --test`: formatting check for changed PHP sources.
- `php artisan view:cache`: Blade compilation.
- `npm run build`: existing Vite build.
- `php database/verify_logistics.php`: logistics admin and hub staff page checks against local MySQL; session database writes roll back.

Camera scanning retains the existing external html5-qrcode script, camera permission handling, and manual tracking-number fallback. Physical camera/USB scanner operation has not been tested. No GPS service is configured. The follow-up upgraded the three affected development packages, and both npm audits now report zero vulnerabilities. The build still reports that optimized font fallbacks require the optional fontaine package; the build succeeds without it.

The follow-up audit, confirmed defects, command results, dependency recommendations, and remaining coverage gaps are recorded in [logistics-review.md](logistics-review.md).

Current remediation details and verification results are recorded in [logistics-follow-up.md](logistics-follow-up.md).
