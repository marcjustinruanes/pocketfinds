# Role-aware navigation restructuring — October 14, 2026

Implemented the approved navigation-only restructuring. All six account roles, existing URLs/route names, backend permissions, recruitment workflows, company/hub/assignment scoping, and existing records remain unchanged. No migration, dependency change, database installation/reset/reseed, or deployment was performed. Earlier workspace changes were preserved.

## Implemented groups

Dashboard/Home and My Account remain standalone entries; Sign Out retains its existing POST form/confirmation behavior.

| Role | Expandable groups and children |
|---|---|
| Platform Admin | Accounts & Recruitment: Registrations, User Accounts. Marketplace & Resources: Products, Company Vehicles. Compliance: Policies, Update Requests, Complaints & Disputes, Seller Compliance. Finance & Reports: Commission, Reports. Communications: Messages, Announcements. System: FAQs, AI Workspace, Settings. |
| Buyer | Shopping: Browse Products, Cart. Orders: My Orders. Communications: Messages, Announcements. Support: FAQs. These groups are inside the existing account dropdown; the storefront header, mobile bottom navigation, notifications, messages/cart shortcuts, and Buyer floating assistant are retained. |
| Seller | Orders: Notifications, Order Management. Store: Inventory, Vouchers, Customer Feedback. Tracking & Reports: Reports. Communications: Messages, Announcements. |
| Logistics Admin | Operations: Hub Transfers, Pickup Requests, Hub Transfer Requests, Delivery Assignments, Scan Parcel. Personnel & Resources: Rider Management, Hub Staff, Coverage Hubs, Vehicle Fleet. Tracking & Reports: Live Monitor, Issues, Delivery History, Reports. Administration: User & Account Management, Activity Logs, Messages. |
| Hub Staff | My Hub is the dashboard entry. Operations: Hub Transfers, Delivery Assignments, Scan Parcel. Hub Resources: Hub Vehicles. Tracking: Live Monitor, Issues, Delivery History. Communications: Messages. No company-admin recruitment, pickup approval queue, account management, logs, reports, or company fleet links are shown. |
| Rider | Pickup Operations: Pickup Requests, My Pickups. Transfer Operations: My Hub Transfers. Delivery Operations: Delivery Requests, My Deliveries. Earnings & History: Profit, Delivery History. Communications: Messages. |

Role-specific menu definitions remain in their own Blade partials. Only the group markup and JavaScript behavior are shared. Seller Compliance links to the existing `admin.compliance` page; no page was created, renamed, removed, or merged. Both existing logistics transfer entry points remain.

Group buttons expose `aria-expanded` and `aria-controls`. The current route opens its parent group and marks the parent destination with `aria-current="page"`, including transfer details, account edits, waybills, AI order review, Buyer shop/product/announcement details, and Rider delivery details. The Logistics My Account matcher deliberately excludes the plural `logistics.accounts` route family.

Existing child icons and count expressions are retained. Seller notification counts still query only the signed-in user's unread notifications. The Buyer FAQ group and authenticated Buyer assistant are omitted for Platform Admin sessions visiting Buyer pages; guest assistant behavior is retained. This changes visibility only, not server access.

Shared sidebar handling replaces the old Admin/Seller handlers and Logistics/Rider inline handlers. It preserves Admin's `sidebar_collapsed`, Logistics' `logistics_sidebar_collapsed`, and Rider's `rider_sidebar_collapsed` keys, and adds `seller_sidebar_collapsed`. The active portal writes only its own key. Mobile toggling does not overwrite desktop collapse preference. Hidden sidebars are inert; mobile opening focuses the first entry, Escape closes the sidebar and returns focus to the toggle, and outside clicks close it. Storage failures do not disable navigation. Buyer's existing dropdown keyboard/outside-click script remains unchanged.

Buyer Home actually includes `guest.home`; navigation assets are loaded there only in Buyer mode. Buyer layout pages also load them. CSS additions are scoped to navigation, including the account dropdown rather than the notification dropdown.

## Exact files changed and purpose

| File | Purpose |
|---|---|
| `resources/views/admin/partials/sidebar.blade.php` | Approved Admin groups, Seller Compliance link, current-route markers. |
| `resources/views/logistics/partials/sidebar.blade.php` | Separate Logistics Admin/Hub Staff groups and preserved role gates/counts. |
| `resources/views/rider/partials/sidebar.blade.php` | Rider operational groups and contextual active links. |
| `resources/views/seller/partials/sidebar.blade.php` | Seller groups, retained notification count, active order/waybill family. |
| `resources/views/buyer/partials/storefront-header.blade.php` | Nested account-dropdown navigation using existing Buyer routes. |
| `resources/views/buyer/partials/assistant.blade.php` | Hide authenticated Buyer-only widget for other roles while preserving Buyer/guest widgets. |
| `resources/views/admin/layout.blade.php` | Shared assets and Admin collapse key. |
| `resources/views/logistics/layout.blade.php` | Shared assets/key; remove duplicate inline sidebar handler. |
| `resources/views/rider/layout.blade.php` | Shared assets/key; remove duplicate inline sidebar handler. |
| `resources/views/seller/layout.blade.php` | Shared assets and separate Seller collapse key. |
| `resources/views/buyer/layout.blade.php` | Shared group assets for Buyer pages. |
| `resources/views/guest/home.blade.php` | Load group assets only for the existing Buyer Home mode. |
| `resources/views/components/navigation-group.blade.php` — new | Accessible reusable parent button and child panel. |
| `public/css/navigation.css` — new | Group, focus, account-dropdown, and mobile collapsed-shell styling. |
| `public/js/navigation.js` — new | Group expansion and consolidated portal-specific sidebar behavior. |
| `public/js/admin.js` | Remove old sidebar handling; other behavior retained. |
| `public/js/seller.js` | Remove old sidebar handling; other behavior retained. |
| `tests/Feature/NavigationTest.php` — new | All six rendered menus, active parents, badges, empty-group checks, and restricted Buyer support visibility. |
| `tests/JavaScript/navigation.test.cjs` — new | Simulated-DOM checks for toggles, storage isolation, mobile focus/close/resize, and blocked storage. |
| `docs/sidebar-restructuring.md` — new | This implementation/verification report. |

## Actual verification

| Command/check | Actual result |
|---|---|
| `php artisan test --filter=NavigationTest` | Final run: **4 passed, 228 assertions**, exit 0. Covers six rendered role menus and nine contextual active-route cases. |
| `php artisan test --filter='NavigationTest\|HubHiringTest\|LogisticsAuthorizationTest'` | Completed combined run: **59 passed, 1,371 assertions**, exit 0, before the final badge assertions and active-link edge-case check. Final versions were included in the full run below. |
| `php artisan test` | **171 passed, 2,329 assertions**, exit 0; 48,609 ms. Includes existing recruitment and authorization regressions. Uses isolated SQLite fixtures. |
| `node tests/JavaScript/navigation.test.cjs` | **4 passed**, exit 0. Uses simulated DOM objects, not a browser. |
| `node --test tests/JavaScript/navigation.test.cjs` | Initial runner attempt failed with Windows `spawn EPERM`. Direct execution above subsequently ran all four tests successfully without requiring child-process spawning. |
| `node --check public/js/navigation.js`; corresponding checks for `admin.js` and `seller.js` | Exit 0 for all three. |
| `php vendor/bin/pint tests/Feature/NavigationTest.php` | Exit 0; formatted the new PHP test. |
| `php vendor/bin/pint --dirty --test` | Exit 0; passed. |
| `php artisan view:cache` | Exit 0; Blade compilation passed. |
| `php artisan route:list --except-vendor --json` | Exit 0; inspected existing routes: Admin 59, Buyer 45, Logistics 75, Rider 25, Seller 40. Counts include actions as well as pages. |
| Pre-task route snapshot comparison | `routes/web.php` byte-for-byte unchanged. |
| Pre-task sidebar route comparison | No existing sidebar route references removed. Only `admin.compliance` added to the Admin sidebar. |
| `git diff --check` | Exit 0; line-ending normalization notices remain. |
| Comparison of affected files against pre-task snapshots | Reviewed navigation-only changes; earlier backend/workspace changes preserved. |

Initial rendered tests failed on a dropped Seller notification-count setup, whitespace in the test's label extraction, and double-escaped ampersands. The count setup and label escaping were corrected; extraction now selects the actual label span. Successful reruns are recorded separately above. An initial helper read of the shared storefront failed on Windows' default encoding and was retried explicitly as UTF-8.

## Limits and unchanged concerns

- No browser tool was available. Actual desktop/mobile appearance, mouse/touch operation, screen-reader output, and real-browser keyboard focus behavior were not verified. The DOM simulation and rendered attributes do not establish those results.
- No SMTP, hardware scanning, or MySQL concurrency tests were performed for this UI task. No real emails/payments were processed.
- Buyer/Seller middleware still permits Platform Admin access to their portals. The business intent of those exceptions remains unverified; this task does not change them or add cross-portal Admin sidebar links.
- Buyer/Seller/Admin middleware still differs from Logistics/Rider in status enforcement. No session/authorization policy change was made.
- Hub Vehicles still filters by assigned hub ID without an explicit company filter. No inconsistent live records or actual data exposure were verified; the previously reported concern remains unchanged.
- Existing document completion/storage concerns, actorless/raw personnel writes, OTP/Google OAuth/TLS issues, and recruitment limitations remain outside this task. Initial personnel approval restrictions and staff interview requirements remain protected by the existing backend and regression tests.
