# Hub address and contact validation — October 14, 2026

Updated Coverage Hubs' Add municipal hub and Hub details/operational settings forms. Hub name, province, municipality/city, barangay, street/building address, and contact number are required. Operational status is also required when editing.

Both forms use the project's existing PSGC API source through a company-admin-only location proxy. Cascading selects use API codes; the backend independently verifies province → municipality → barangay membership and obtains canonical names. Street/building details remain a required text field because PSGC does not provide individual building addresses. The composed address is stored in the existing address column; no schema change was needed.

For existing hubs, province/municipality are fixed and verified against their stored coverage location to avoid changing saved parcel routes. Barangay and street/building details remain editable. Old free-text addresses are displayed as the street field until completed with an API-selected barangay; already composed addresses are split for editing. No existing addresses or contact numbers were rewritten automatically.

Contact numbers require a string matching `^09[0-9]{9}$`: exactly 11 ASCII digits starting with 09. Browser input removes nondigits and uses numeric input mode, length limits, and a pattern. The backend rejects invalid values on direct submissions.

Address responses are cached for six hours. Requests have bounded connection/response timeouts and normal TLS verification. Missing/invalid API data prevents saving and shows retry guidance; no unverified free-text geographic fallback is accepted. No OTP or Google OAuth/TLS code was changed.

## Files

Created:

- `app/Services/HubAddress.php`
- `public/js/hub-address.js`
- `resources/views/logistics/partials/hub-address-fields.blade.php`
- `tests/Feature/HubAddressTest.php`
- This document.

Changed:

- `app/Http/Controllers/LogisticsManagementController.php`
- `routes/web.php`
- `resources/views/logistics/hubs.blade.php`
- `resources/views/logistics/partials/hub-management.blade.php`

## Actual verification

- `php artisan test --filter=HubAddressTest`: 6 passed, 79 assertions, isolated SQLite. Covers required fields, malformed phones, tampered geography, successful create/update, fixed hub location, duplicate hubs, API failure, caching, company isolation, and rejection of the other five account roles.
- `php artisan test`: 162 passed, 1,996 assertions; exit 0, 37,031 ms.
- Targeted Pint and `php vendor/bin/pint --dirty --test`: passed.
- `php artisan view:cache`, `git diff --check`, `node --check public/js/hub-address.js`, and location route listing: passed. Existing CRLF/LF notices remain.
- Outside-sandbox live API check: provinces, Laguna municipalities, Santa Cruz barangays, and canonical-address generation succeeded. Example returned Laguna `043400000`, Santa Cruz `043426000`, and Alipit `043426001`. No hub record was written. Initial sandbox API probes were blocked by the sandbox proxy; normal TLS verification was retained on the permitted rerun.
- Rolled-back local MySQL page check: Kap's Coverage Hubs returned HTTP 200; both create/update forms rendered API address selectors and exact phone constraints.

The first API-outage test still matched the earlier successful fake response. Replacing the fake HTTP factory made the intended outage test exercise failure correctly; the targeted rerun passed. No failed test is counted as a pass.

## Limits

Browser dropdown interactions/responsive behavior still require manual checking; JavaScript syntax and server behavior were verified, not browser interaction. The external API and network must be reachable for uncached locations. This uses the existing province-based PSGC hierarchy; locations without a province in that hierarchy need a separately designed region-based path. Legacy hub names that do not match API names need authorized reconciliation rather than a silent geography change. Existing nullable/invalid legacy contact details remain untouched until submitted, at which point all required fields must pass validation. No migration, database reset, deployment, email, or payment processing was performed.
