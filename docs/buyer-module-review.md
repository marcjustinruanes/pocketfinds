# Buyer website embedded in Flutter

## Implementation selected by the user

The buyer module now renders the actual Laravel website inside Flutter using the
official `webview_flutter` plugin. The previous native approximation is no longer
the buyer shell. Website HTML, responsive CSS, SVG icons, fonts, JavaScript,
modals, navigation and Laravel form actions are reused directly.

Buyer source: the pinned GitHub revision
`f4158032a1f568bd238e1390ddd93dd440f1c086`, installed into
`api/storage/buyer-website`. The adjacent `../pocketfinds` checkout still supplies
Composer dependencies, the existing environment and uploaded files. Its source
and local changes were preserved.
An app-local gateway in `api/BuyerWeb.php` runs its existing HTTP kernel and routes,
serves its public assets, and uses an isolated Laravel browser session. The source
is loaded from the backend at runtime, rather than copied into Dart screens.

## Complete buyer route coverage

| Website area | Source used in the app |
| --- | --- |
| Dashboard, categories, shops, deals, about/footer | Actual `buyer.dashboard` / `guest.home` templates and shared storefront scripts/styles |
| Browse/search/filter/sort/pagination | Actual `buyer.browse` and its controller |
| Product/gallery/video/options/quantity/related products/reviews | Actual `buyer.product`, including its image viewer, stock and option checks |
| Shop and shop categories | Actual `buyer.shop` and category navigation |
| Wishlist and following stores | Actual saved/follow controllers and templates |
| Cart, selection, quantity/options edits, removal | Actual `buyer.cart`, cart modals, AJAX and Laravel cart records |
| Address add/edit/default/delete and checkout selection | Existing website forms/controllers with CSRF and buyer ownership |
| Voucher preview and order notes | Actual cart controls and backend validation |
| COD checkout | Original `BuyerController::checkout`, including per-seller orders, prices and inventory handling |
| Orders, search, tab counts, detail dialogs | Actual `buyer.orders`, shared native order records |
| Cancellation, receipt confirmation, Buy Again, review | Original Laravel actions and eligibility checks |
| Messages, polling, attachments, reactions/replies/reports | Actual messages page/modal/scripts and authenticated private-media route |
| Notifications and announcement links/details | Original website dropdown, open action and announcement views |
| FAQs | Original published FAQ controller/template |
| Account/profile/photo/contact verification/password | Original account template and its Laravel actions, including real file inputs |
| Profile address review and saved delivery addresses | Original website account/address workflow |
| Connected payment profiles | Original account connect/verify/remove controls; separate from order payment verification |
| Logout | Website logout followed by native sign-out and browser-cookie clearing |

## Explicit exceptions

1. **Shopping assistant removed from the app.** Its native screen and API adapter
   were deleted. An app-local empty Blade partial excludes the web widget, and
   embedded assistant endpoints return 404. The separate website source remains
   unchanged; no assistant conversation records are deleted.
2. **Current GCash flow retained.** The real web cart hands selected cart IDs,
   payment method, address, voucher and note to the existing native checkout.
   No order, payment or stock change happens merely by crossing this bridge.
   The existing native API performs authoritative quotes, reservations, receipt
   submission and admin verification. Old seller-message payment instructions
   are replaced only in the app response. Legacy seller-GCash routes cannot
   initiate that older flow inside the embedded buyer session.
3. **Settings retained.** App Settings appears in the account page/menu and opens
   the original native screen: personal information/social links, username,
   email/mobile verification, password, addresses, push preferences, payment
   information, vouchers, coins, dark mode, Help Center, policies, About and
   deactivation. Light mode uses the website CSS unchanged; the app's optional
   dark preference supplies dark color tokens.
4. **Existing platform-payment order details retained.** GCash orders display
   backend payment status, submission/deadline/rejection information and native
   payment/resubmission/tracking links in the web order list. Receipt submission
   is never treated as verified payment.

The Android wrapper supports ordinary file selection/capture, JavaScript dialogs,
back navigation, safe external links, video/media range responses and web location
permission prompts. Document attachments use the existing authorized media-link
mechanism when opening the device viewer. No credentials are embedded in Dart.

## Session and security

- Native sign-in requests a random, hashed, single-use handoff ticket valid for
  60 seconds; the app bearer never enters a WebView URL or JavaScript.
- The browser cookie is HTTP-only, SameSite=Lax and isolated from desktop cookies.
  App-local session files are ignored by git.
- Every authenticated web request checks its original native token, approved buyer
  role and inactivity/expiry. Native token revocation blocks browser access.
- Original Laravel CSRF, ownership, validation, storage and financial actions remain.
- Native bridge messages require a trusted origin, buyer path and session nonce.
  They open native UI; they cannot approve payment or release money.
- Admin, seller and rider dashboards are not exposed by the embedded gateway.
- Private messages/evidence go through existing membership/ownership checks.

## Verification

Results: 81 Flutter regression tests passed, plus the focused bridge/Settings
checks; Dart analysis is clean. Ten handoff security assertions and 26 actual
HTTP page/action checks passed. The existing website-account suite passed
31 assertions. The Android debug APK built successfully. Device checks confirmed
the actual dashboard/account pages, native Settings and native file chooser.
Temporary test accounts, cart rows and uploaded profile files were removed.

- `api/tests/buyer-web.php`: handoff/role/expiry/replay/revocation checks, rolled-back fixtures.
- `scripts/check-buyer-web.php`: actual HTTP rendering of all 12 buyer page types,
  CSS/JS assets, assistant exclusion, native GCash form handoff, no premature
  order/stock changes, cart ownership, CSRF and browser-session revocation.
  Temporary accounts/cart rows are removed; no orders or real messages are sent.
- Flutter tests include origin/path/nonce checks, retained Settings, native payment
  workflows and the existing buyer/rider regression suite.
- Emulator checks confirm the real dashboard/account UI and the native Settings
  entry. The build includes the Android WebView plugin.

Real GCash transfer/admin approval, live verification email, push delivery and
all possible seller/admin interactions are not established by read-only page
checks. They continue to depend on the existing backend configuration and staff
workflow. This work does not publish or modify the separate website dashboards.

The Laravel backend and its installed website source must be running for these
pages, just as the app already requires its API for real account/order data.
Use `scripts/start-local-api.ps1`; it sets the multipart upload limit to 20 MB
while Laravel retains the individual 5 MB photo/other file validation limits.
