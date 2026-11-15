# Website reference and mobile buyer changes

The November 9 page-by-page integration review is recorded in
[buyer-module-review.md](buyer-module-review.md), including retained Settings,
the current GCash exception, additional integrations and validation limits.

Guest authentication uses a centered login modal over the current dashboard,
matching the supplied mobile website reference. Guest search, product/category/
shop actions, cart, favorites and navigation open the shared login form. Close
returns to the same guest page. Create one closes the modal and opens buyer
registration on a separate page. The modal reuses real login, Google sign-in
and password-reset logic.

## November 8 mobile storefront follow-up

Compared the buyer dashboard (`guest.home`), browse page and shop entry points
with the adjacent website checkout at `eded8bae2de1f90e1f5eebde8d1cd368b99913ce`.
The public reference remains https://github.com/marcjustinruanes/pocketfinds.

- Buyer Home now includes a horizontally scrollable Discover Shops section,
  backed by the existing `list_shops` API, with real ratings, locations and
  product counts. Cards open the seller's mobile storefront. Pull-to-refresh
  reloads shops; failures expose a retry action; an empty list hides the section.
- All Products / See All and Browse all products open the existing paginated
  catalog without requiring a search term. Category, price, rating, location
  and sorting controls remain available there.
- Home quick-add opens product details for products with variations so the
  buyer can choose an option before adding to the cart.

These are additions to the existing buyer implementation described below.
Full visual parity across every website page and live device/payment/email
integration remain unverified. The website source was not changed.

Validation: `flutter test --no-pub` passed all 67 tests. Targeted Dart analysis
of `lib/screens/buyer/home_tab.dart` reported no issues.

Reference: https://github.com/marcjustinruanes/pocketfinds at
`ca70ed18054a722b50933c6188fc09b51dc53977` (October 30, 2026).
The adjacent website source was read but not edited.

## Implemented in the mobile workspace

| Website behavior | Mobile implementation |
| --- | --- |
| Branded authentication, login by username/email, Google | Existing real logo, website palette and gradient, mobile authentication panel, Remember me persistence |
| Five-page buyer application | ID type and ID/selfie; personal details and suffix; email verification and phone; PSGC address and postal code; chosen username and confirmed password; published terms acknowledgement |
| Email verified before application submission | Valid email domains accepted; hashed, five-minute email code; encrypted 30-minute email proof; no authenticated shopping session |
| Pending administrator approval | Native users and HubHiring registration; verified applications enter the existing pending queue |
| My Account cards | Profile, address, delivery addresses, GCash accounts and account information |
| Profile/address review | Native AccountUpdateRequest records; one pending request; current details stay unchanged until native admin approval |
| Current password required for password changes | API verifies current password and native strong-password rules |
| Connect/remove GCash profile | Native BuyerPaymentAccount records and email verification, send cooldown, attempt limit, retry without losing the form |
| Product/message reports with evidence | Native reports, ownership/participant checks, optional image/video evidence |
| Announcements and FAQs | Published native records scoped to buyer/all audiences |

The previous cart, COD/GCash checkout, payment resumption, saved addresses,
order tracking, receipt confirmation, reviews, Buy Again, issues, rewards,
wishlist, following and rider workflows remain available. Profile photo and
reward features are reachable from My Account. Native tables are reused;
these account changes need no new business-table migration. The existing
mobile API setup and email-challenge migration are still required.

## Deliberate differences and deployment limits

- Buyer minimum remains **16**, as requested, rather than the reference's 18.
- Layouts adapt to phone screens rather than reproducing desktop widths.
- Existing ERP rider application/activation flows are retained.
- The local website version lacks the reference's newer `BuyerCommerce`
  extension and commerce tables. App-local adapters now provide advanced
  shipping, reminders, voucher allocation, editable item reviews and returns
  using additive mobile tables. Website dashboards do not automatically use
  these tables. See `buyer-implementation-progress.md` for integration limits.
- Identity files currently use the installed website's MediaLifecycle storage
  convention. The reference's newer sensitive-document storage migration is
  not installed by this work.
- Google sign-in still requires an Android OAuth client registered for the
  app package and signing certificate. Website Web credentials alone cannot
  complete Android OAuth configuration.
- Real verification mail uses the installed server's SMTP configuration.
  Automated tests mock mail and do not establish live inbox delivery.

## Validation

Run `php api/tests/website-buyer.php`, `registration.php`, `buyer.php`,
`integration.php` and `rider.php` from the workspace root, with each filename
under `api/tests/`. Together these perform 214 checks. Fixtures are rolled
back or isolated, and no real mail is sent. Flutter tests cover the five-step
application, navigation, session persistence, GCash retry, storefront layout
and the existing buyer/rider client workflows.
