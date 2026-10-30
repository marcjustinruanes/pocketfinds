# Hub Staff cash workflow improvements

30 October 2026. Existing Cash Desk, treasury, financial events and account notification infrastructure extended. No financial calculation, ledger, payment eligibility, cash transfer rule, role, migration or source business record changed. No GCash transfer automated or deployment performed.

Staff Cash Desk now offers scoped queues for pending Rider counts, authorized treasury releases and unresolved differences, with whole-queue counts and retained pagination filters. A selected notification can open its exact remittance/handover. History explains the responsible role and next action; authorized treasury release shows the authorized amount as an entry starting point. Actual release still requires confirmation and the existing locked balance/qualification checks. Empty filtered queues are distinguished from an empty cash history.

Staff guidance explains the page handoff without linking Staff into unauthorized Admin pages: Staff counts/releases in Cash Desk; Admin authorizes and verifies actual company-account receipt in Seller Settlements. Existing Admin cross-links remain. Recorded hub custody, outstanding Rider amounts, reservations and treasury availability remain distinct.

## In-app notification recipients

| Successful action | Recipients |
| --- | --- |
| Rider or supervised recovery submission | Approved same-company Staff at the recorded operational receiving hub, excluding the actor |
| Staff physical remittance count | Approved original Rider and approved company Admin, excluding the actor |
| Admin treasury authorization | Approved Staff at the origin operational hub |
| Staff treasury release | Approved company Admin |
| Admin treasury receipt | Approved Staff at the origin operational hub |

Alerts contain generic workflow guidance and a handover reference, not account numbers, proof paths or investigation allegations. No Buyer, Seller, foreign-company Staff, unrelated-hub Staff or suspended user is selected. Current-user scope is rechecked when opening an alert; an old alert does not retain access after a hub/role/company change. Staff/Rider alerts open Cash Desk/Cash Remittances; Admin treasury alerts open Seller Settlements. Existing notification dropdown and mark-read infrastructure is reused.

Financial events and notifications are inserted within the existing transaction. They become visible only after commit. Existing retry paths return before another event/notification, preventing replay spam. Notification failure rolls back the recording transaction rather than leaving a half-recorded cash action. No email/provider notification system or duplicate financial record was added.

## Verification

- Selected Cash Desk, Seller payouts, reconciliation, authorization and recovery regression: **122 tests / 1,982 assertions passed**, zero failures/errors.
- New focused StaffCashNotificationsTest: **3 tests / 27 assertions passed**. Verifies all five milestones, unrelated/suspended recipient exclusion, duplicate retries, role-correct links, changed-hub denial, filtered queues and notification failure rolling back count/custody/event. Its inherited fixture tests are not added again.
- Existing isolated MySQL runner: **26 check groups passed**, covering financial concurrency, custody, reservations, releases/receipts, payouts, recovery and audit. Notification hooks ran within these flows; this is not a new standalone notification-delivery stress benchmark.
- Synthetic Staff view at **1440px and 390px**: no document overflow/unlabelled inspected visible fields; controls at least 46px. Preview includes a pending remittance and authorized treasury release. Mobile history visually inspected. No physical cash, real GCash, physical device or comprehensive accessibility certification.
- Changed service/controller PHP syntax and git whitespace checks passed. Private evidence uses staff-cash-notifications.*, staff-cash-improvements.*, staff-cash-improvements-mysql.txt and staff-cash-final-* under the private review directory.

Files: LogisticsCashDesk/LogisticsTreasury services (event notifications); CommerceController::notificationOpen (authorized destinations); LogisticsCashDeskController::index (read-only queues); cash-desk Blade view; new StaffCashNotificationsTest; this report.

Remaining boundaries: notifications appear through the existing dropdown/page requests, not guaranteed real-time push. Historical milestones are not backfilled as alerts. No available recipient means no alert is fabricated. Admin must still investigate differences; resolving one does not edit counts or forgive liability. Actual cash-in and provider receipt remain manual. Existing pending migrations 000006–000009 and deployment acceptance remain a separate rollout requirement; none was applied here.
