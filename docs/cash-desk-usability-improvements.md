# Cash Desk usability improvements

30 October 2026. Existing Cash Desk controllers, routes, role scopes, services, schema and synthetic fixtures were inspected before editing. The configured database was queried read-only: zero COD collections, handovers, allocations and custody movements; one finance settings record. No business record or financial service was changed.

The page now separates outstanding Rider liability, amounts reserved for counting, overdue subsets, on-hand hub custody and released cash in transit. Outstanding rows show reserved and available-to-submit amounts and named hubs. History uses descriptive lifecycle labels, clearer cards, resolution wording and explanatory empty states. Existing Staff-only count/release/receipt forms remain role-scoped; Admin only authorizes and reviews.

Cutoff entry retains server-side HH:mm/revision validation and explains Philippine time and flag-only effects. Transfer selection starts with placeholders, excludes inactive/foreign hubs, rejects the same origin/destination and displays current recorded origin custody with an excess-amount warning. Authorization still does not reserve or move cash; the existing locked balance check at physical release remains authoritative. This warning is a page-time snapshot, not a funds guarantee.

Added section navigation, scoped restrained pink styling, spacing, readable amount columns, labelled horizontally scrollable tables, confirmation prompts and submission loading/duplicate-click protection. Transfer and cutoff validation failures retain entered values. Release and receipt success messages now explain the actual stage. Without JavaScript, native form constraints and existing server validation/authorization remain in force.

Changed files:

- `resources/views/logistics/cash-desk.blade.php`
- `public/css/logistics-cash-desk.css` (Cash Desk-scoped additions)
- `public/js/logistics-cash-desk.js` (new progressive enhancement)
- `app/Http/Controllers/LogisticsCashDeskController.php` (transfer success wording only)
- `tests/Feature/LogisticsCashDeskTest.php` (two additional isolated checks)

Verification:

- Selected Cash Desk, COD, Seller payout, Rider compensation, reconciliation, recovery and authorization regression: **160 tests / 2,225 assertions passed**, zero failures/errors. Final focused Cash Desk run: **25 tests / 143 assertions passed**, overlapping the regression.
- Existing isolated MySQL runner: **26 check groups passed**, including reservation/count/custody, insufficient cash, transfer concurrency, financial rollback, payments and reporting snapshots. No source business rows are imported.
- Synthetic real-view HTTP renders for Admin, Staff and Rider, then Chromium at **1440px and 390px**: no page overflow, no unlabelled visible controls, visible controls at least 44px. Admin count controls absent. Same-hub validity, excess-balance warning, cancelled confirmation, loading and back-navigation reset checked successfully. Mobile transfer and desktop Staff history screenshots visually inspected.
- PHP/JS syntax checks passed. `git diff --check` passed with existing line-ending notices. Initial Blade integration errors were corrected before the final passing runs.

Evidence: `cash-desk-regression.xml/json`, `cash-desk-focused.xml/json`, `cash-desk-mysql.txt`, `cash-desk-browser.txt` and synthetic screenshots/helpers under private `storage/app/private/logistics-ui-review/`.

No new migration, role, policy, balance, payout rule or deployment. Existing migrations 000006–000009 remain Pending, confirmed read-only; the configured environment still needs its reviewed rollout before write acceptance. Browser previews use synthetic data/local assets, not real cash or physical-device acceptance. Balance hints may become stale; only the existing locked Staff release check determines available cash. Historical discrepancies still require authorized financial review rather than amount edits.

Follow-up UI refinement: increased body/label/help-text sizes, moved subset explanations below shorter metric labels, removed equal-height stretching between cutoff and transfer cards, and added clearer collection empty states. With fewer than two operational company hubs, the unusable transfer form is replaced by an explanatory state. The existing server permissions are unchanged. Focused verification passed again: 25 tests / 143 assertions. Eight synthetic desktop/mobile views, including single-hub state, passed overflow/label checks.
