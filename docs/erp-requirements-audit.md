# ERP requirements audit

Sources: `ERP-Flow-1.pdf` (5 pages) and `ERP-Components-updated-1.pdf`
(5 pages), supplied from Downloads. These documents are requirements to compare
against the product, not instructions to edit unrelated repositories.

Scope: Flutter buyer/rider app and its local PHP API. Website source is unchanged.
The website team's seller, administrator and logistics dashboards remain their
interfaces; mobile actions update the same native MySQL records and notifications.
Existing COD, GCash, Google, rewards, reviews, cancellation, issues, delivery OTP,
returns, messaging and account management remain available.

## Buyer

| Requirement | Result / implementation |
| --- | --- |
| Last/first name, optional middle initial, required sex | Application Personal page; server validates required fields |
| Email, contact, birthday, calculated age | Contact page; server calculates age from the real birthday and ignores supplied age; buyer minimum remains the requested 16 |
| Address API: province, municipality, barangay | Address page uses cascading PSGC Region → Province → Municipality → Barangay; NCR works without a province list; failed lookup has retry |
| Street/house number | Manual address fields; persisted on the native user profile and available to the existing saved-address/checkout flow |
| Upload ID | Required Documents page and server-validated JPEG/PNG/PDF, maximum 5 MB; app currently selects images |
| Admin approval before login | Submit → real email code → verified `pending` → native admin review → decision email → approved login |
| Categories, search, details, quantities and variants | Existing buyer catalog, search and product screens; native option IDs and stock/price validation |
| Cart selection, vouchers, discounts, payment, order placement | Existing cart and checkout; COD and saved/resumable GCash workflow retained |
| Delivery address confirmation | Existing owned saved-address selection with immutable order address snapshot |
| Order tracking and feedback | Existing real order history, delivered vs completed, buyer receipt confirmation and completed-order reviews |
| Messaging, account management, logout | Existing mobile screens and authorized API endpoints |

Buyer pages are Personal → Contact → Address → Documents → Security/review.
Back moves between pages without clearing values. Email verification remains a
separate screen. Pending approval is explicitly displayed after successful email
verification, and the login endpoint rejects pending accounts.

## Courier / rider

| Requirement | Result / implementation |
| --- | --- |
| Personal/contact/address/ID application fields | Same required application fields as buyer, with the existing rider minimum 18 |
| Choose vehicle and plate | Rider Vehicle page and unique plate validation |
| Upload OR/CR and driver license | Required image documents, license number/expiry, native user document columns |
| Logistics approval and decision email | Select an accepting hub; email verification leaves `pending`; existing company review/decision mail applies |
| Native approval prerequisites | ID type, selfie, vehicle brand/model are also collected so the existing website can actually approve the applicant |
| Invitation activation | Existing invitation/password activation remains available alongside “Apply as rider” |
| Items for seller pickup | Rider Home shortcut and Tasks button open Seller pickups; available and owned/history tabs |
| Accept pickup | Company/origin-area eligibility, logistics approval prerequisite, atomic locked assignment, independent concurrent acceptance test |
| Actual parcel pickup | Scan QR/barcode or enter exact tracking ID; assigned rider only; one audit event on retry |
| Seller → sorting center | Rider receipt waits for seller's separate handover confirmation; `picked_up` then instructs delivery to origin center; native staff scan records arrival |
| Items for buyer delivery | Existing available/assigned final-leg tasks, destination-area authorization and refresh |
| Actual buyer delivery | Existing verified center pickup, out-for-delivery, email OTP handover, separate buyer confirmation |
| Failed delivery, reason, retry/return | Existing failure reasons, cap, schedule and return workflow retained |
| History and profit | Existing delivery history/earnings; seller pickups have their own persistent pickup history; no invented pickup fee or duplicate delivery credit |
| Messaging, account management, logout | Existing screens retained; assigned pickup riders can also message the relevant seller |

Rider application adds a Vehicle page before Security/review. A company-created
invitation is still a valid alternative; the company already controls that intake.

## Delivery status meaning

Both documents describe the complete journey, not just the last delivery leg:

`Placed → Confirmed → Preparing → Ready for Pickup → Seller Pickup → At Sorting
Center → Sorted → Assigned to Delivery Rider → Out for Delivery → Delivered →
Buyer Confirms Receipt → Order Completed`.

Native seller confirmation and origin-center receipt are independent of the
mobile rider's scan. The app does not impersonate seller/staff confirmation.
Final-leg pickup verification remains in its separate verification record so it
cannot overwrite the earlier seller-pickup timestamp.

Flow PDF page 5 defines Returned as **returned to seller**. Consequently,
destination-center receipt now records `return_received_at` and keeps the return
in progress. `delivery/confirm-seller-return`, authorized to the order's seller,
requires the matching physical parcel and prior center receipt before setting
`returned`. Its actor/time are audited; retries are idempotent. Historical returned
rows are retained. Restocking/refunds are not automatically fabricated by hub
receipt.

## Website responsibilities and integration handoff

Components pages 2, 4 and 5 list seller, admin and sorting-center functions. They
are outside this buyer/rider application's role scope. Existing website source
was inspected for the integration points, without modifying it:

| Website owner | Required work / integration |
| --- | --- |
| Seller | Inventory, order notice/acceptance/preparation, label, ready-for-pickup, seller handover confirmation, feedback and financial/performance reports |
| Administrator | Buyer/seller/logistics application review, identity documents, approve/reject and decision email; user activation/suspension; category/product compliance; disputes; commission (PDF specifies 10%); sales/commission reports; announcements/policies |
| Logistics company | Rider review and email decision, pickup approval, incoming parcel scans, destination sorting, origin/destination area assignment, delivery monitoring and reports |
| Seller/logistics return interfaces | Wire staff physical return receipt and seller physical receipt to the audited mobile API contract, or adopt equivalent guards in the website backend |
| All website dashboards | Refresh/poll or use their own realtime channel to reflect committed shared data; mobile polling cannot add realtime behavior to independent website pages |

Native `AdminController::approveUser` and logistics `approveRider` already support
verified pending applications and decision mail. Native seller confirm-pickup and
staff scan already complete the pickup leg. These interfaces still need a joint
device/website acceptance test with the website team. This audit does not certify
every website report, commission calculation or policy screen as tested.

## API and data

- `auth/register-buyer` retains its route and accepts `account_type=buyer|rider`.
  Both require the full application; documents are base64 fields in `documents`.
- Native `users`, verification tokens, hub hiring history, assignment records,
  shipment/order history and notifications are reused. No new schema is needed
  beyond the previously installed mobile/rider migrations.
- `pickup/list` returns eligible pickups and the signed-in rider's own history.
- `pickup/accept` / `pickup/confirm` require shipment UUID and retry request UUID;
  confirm also requires the parcel's tracking code.
- Pickup and delivery use different assignment columns and assignment legs.
- Documents follow the existing website's MediaLifecycle storage convention;
  the mobile API does not expose them as public product media.
- Native approval remains manual and authorized; email verification grants no
  authenticated shopping/delivery session.

Automated checks use disposable/rolled-back fixtures and intercept mail; no real
registration or OTP messages were sent during this audit. Camera capture, physical
parcel handover and real review-decision email delivery require device/website
acceptance testing.

Validation on this device: 40 authentication/application checks, 64 rider checks
(including competing seller-pickup acceptance), 41 commerce integration checks,
36 buyer checks and 59 Flutter tests passed. Flutter analysis and PHP syntax
checks passed. An updated Android debug APK was built successfully.
