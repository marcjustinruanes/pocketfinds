# Logistics Admin feature and responsibility review

30 October 2026. Read-only application review. No code, route, permission, business record or migration changed. This document is a recommendation, not authorization to remove features.

Inspected the actual logistics sidebar; web route groups; Logistics/LogisticsAdmin middleware; LogisticsController, LogisticsManagementController, LogisticsOperationsController, HubTransferController and financial controllers; LogisticsAccess, HubTransferWorkflow, PhoneScanner, RTS/recovery and financial service boundaries; scanner JS and relevant Blade workspaces. Judgments below distinguish page overlap from duplicated business transactions. Not every possible security interleaving or physical device was tested.

## Page and feature decisions

| Existing feature | Recommendation | Implementation-based explanation |
| --- | --- | --- |
| Dashboard | Keep | Company current-state overview with stage destinations. Staff receives its own My Hub dashboard. Neither replaces assignment or financial workspaces. |
| Scan Parcel | Improve | Keep Admin company-scoped lookup and authorized assignment/exception shortcuts. Make physical receiving visibly Staff-owned. Scanner is an input method calling existing guarded actions, not a separate logistics workflow. |
| USB/camera/paired-phone scanning | Keep | Alternate inputs to the same lookup. Phone sessions bind account/company/hub/role and expire; pairing does not grant another user's receiving authority. Device compatibility is an acceptance boundary, not proof the feature is redundant. |
| Pickup Requests | Keep | Admin approves/rejects carrier acceptance before pickup and sees a separate approved/unassigned assignment queue. Rider self-claim happens afterward; it does not replace company approval. |
| Pickup rider assignment and Rider self-claim | Improve | Both use approved uncollected work with owner/hub/eligibility guards. These are allocation alternatives, not two pickups. Clearly distinguish assigned versus open work; do not remove either without changing the operating policy. |
| Delivery Assignments | Improve | Keep final-mile allocation and ownership overview. It also lists intermediate hub stages, overlapping Monitor/Transfers; emphasize sorted/unassigned parcels and link transfer rows to their batch. Rider choices can include another hub even though the server rejects them; filter choices by parcel destination in a future UX change. |
| Assignment evidence & reassignment | Keep | Evidence classification and controlled pre-collection replacement preserve owner revision/history/rate snapshots. Admin cannot replace possession attestation: Seller confirms pickup possession; destination Staff confirms no release. |
| Hub Transfers | Keep | Requests, approval, dispatch, rider pickup and destination member receipt are distinct necessary stages. Admin dispatch authorizes the trip; it is not proof of physical pickup. Partial receipt must retain whole-trip rules. |
| Older Hub Requests entry/routes | Merge | GET already redirects to Hub Transfers awaiting queue. Existing route-group forms still call compatibility decision endpoints backed by the same batch service. Consolidate presentation/documentation around Hub Transfers; do not delete active contracts blindly. |
| Scan transfer assignment/receipt shortcuts | Merge | Prefer contextual links into existing batch details to reduce repeated dispatch UI. Retain one authoritative service and per-parcel receipt support. Do not create a second transfer module or lose member-level evidence. |
| Live Monitor | Improve | Current database status list, not independently verified GPS/live custody. Rename/describe accordingly. Remove the offered Out for delivery choice from logistics UI: server already rejects it and assigns it to Rider. Approved pickup rows must link to assignment queue, not always the review queue. |
| Failure reporting and retry | Improve | Rider failure and scoped Admin/Staff exception handling coexist. Keep documented reasons and distinguish operational decisions from actual handover. Retrying the same eligible rider is not reassignment, receipt or proof the parcel returned to hub. |
| Delivery History | Keep | Searchable terminal/current-result records with dates and actors; useful separately from active Monitor. Failed rows can also appear in Monitor because actionable failures are not successful completion. It is not an immutable attempt-event archive. |
| Operational Reports / CSV | Improve | Keep shipment/rider/hub/fleet analytics distinct from financial reconciliation. Completed includes Delivered and Completed; label this population precisely. Parcel date filters use creation dates, not delivery/payment dates. Existing private/no-store, scoped audited CSV should remain. |
| Issues / lost and damaged investigations | Keep | Scoped investigation, evidence, revisions, resolution outcomes and deliberate public milestones. A resolved issue is neither financial liability adjudication nor parcel receipt. |
| Supervised parcel/cash recovery | Keep | Necessary for suspended-rider custody exceptions without account reactivation. Admin monitors; assigned Staff receives/counts. Resolving an issue must not release recovery holds. |
| RTS | Keep | Admin may request/monitor. Assigned Rider transports; actual Staff receives/releases; only owning Seller confirms full sellable receipt/restock. Cross-hub reverse dispatch, partial/damaged restocking and return pay remain deferred. |
| Personnel: Riders and Hub Staff | Keep | Different approval requirements justify role tabs within one Personnel area. Existing managed creation/completion, interviews, reasons and append-only decisions should not become another onboarding module. Staff cannot manage personnel. |
| Old /accounts list | Merge | Already redirects to role-specific personnel lists. Keep compatibility until callers are audited; no need for another visible account-management page. |
| Coverage Hubs, hiring capacity, company policy | Keep | Admin manages operational coverage/hiring inputs. Platform Admin retains policy review. Coverage configuration does not attest receipt. |
| Vehicle Fleet | Keep | Admin submits/manages fleet under Platform approval. Staff controls approved own-hub availability; riders use eligibility checks. These responsibilities are complementary. |
| Hub Vehicles | Move to Another Role | Already Staff-only, correctly. Keep daily physical availability/maintenance there rather than adding an Admin impersonation control. No role migration is needed. |
| Finance Overview & Settings | Improve | Keep prospective rates, shipping configuration/obligations. Repeated read-only collections/compensation lists overlap dedicated pages: prefer concise summaries and links. Never confuse pending snapshots with earnings or collected cash with available payout funds. |
| Seller Settlements / treasury | Keep | Eligibility, treasury qualification, instruction reservation, actual payout and acknowledgement are distinct. GCash buyer direct payment must not create another Seller payout. Admin treasury receipt is separately evidenced company-account receipt, not Staff physical cash count. |
| Rider Earnings & Payouts | Keep | Separate evidenced payroll funding and immutable earned sources; Admin pays, Rider acknowledges. Whole batch pay is not per parcel. No deduction/offset expansion. |
| Reconciliation & Reports | Keep | Financial snapshot, funds/custody/reservations/provenance and exception review; different purpose from operational shipment Reports. Do not sum mirror entries twice or merge this into an editable ledger. |
| Cash Desk | Keep | Admin overview/cutoff/transfer authorization/discrepancy review; Staff physical count/release/receipt; Rider submission/withdrawal. Current balance hints do not reserve funds. Single-hub transfer unavailability is appropriate, not a missing cash button. |
| Activity Logs | Keep | Company audit separate from parcel result history and financial source events. Preserve transactional failure behavior and legacy context distinctions. |
| Messages / reports of messages | Keep | Scoped contact/conversation coordination. Messages never replace structured custody, reasons, proof or payment records. |
| My Account / update requests / password / address | Keep | Own-account maintenance with existing review boundaries; does not grant arbitrary approval, document replacement or company transfer. |
| Unused parent LogisticsController::issues | Remove | Candidate for later dead-code cleanup: active Issues routes point to LogisticsManagementController::issues; no parent invocation found in searched app/routes/resources/tests. Remove only this unused implementation after reference verification, not the Issues page or records. |

## Physical custody and role findings

No Admin physical origin/batch receipt or final Delivered bypass was reproduced by the selected tests. LogisticsAccess::receivingStaff requires fresh approved exact-hub Staff; HubTransferWorkflow::receive repeats destination Staff checks. RiderController owns normal pickup confirmation, departure and final delivery/COD. LogisticsCashDesk::staff restricts physical count/release/receipt. Admin form visibility is not the sole authorization control.

Admin and destination Staff can currently mark delivery failure/retry through LogisticsController::updateStatus. This is operational exception authority, not evidence of physical parcel location. Do not automatically move it or remove it merely because Rider also reports failure. If the owner wants only eyewitness failure reports, that is a separately approved permission-policy decision.

The same generic endpoint permits Delivered → Completed after real Rider delivery for scoped logistics users. This should be clearly labelled administrative closure rather than buyer acknowledgement. CodSellerPayouts::eligibility separately checks buyer_confirmed_at or a completion history event actually authored by the Buyer, otherwise 168 hours; logistics closure alone is not the buyer-confirmation payout shortcut. Restricting administrative closure would need an explicit decision.

The scanner's normal origin receipt and transfer can_complete are correctly hidden from Admin. Monitor's offered Out for delivery option is a concrete UI/server mismatch: STATUS_TRANSITIONS still contains it, monitor renders it, updateStatus denies it. Scanner JS already displays a waiting-for-Rider note for that transition. This is a misleading action, not successful Admin physical departure.

## Necessary improvements versus deferred design

Prioritize the Monitor action/queue mismatch and per-parcel rider-option filtering. Then consolidate transfer entry points, clarify operational metric/closure labels and reduce finance-overview list duplication. Keep separate financial facts and the specialized services.

Actual missing exception completion remains approved-scope limited: cross-hub reverse return dispatch, partial/damaged Seller restock, recovered-parcel redispatch and corrective external-payment exceptions. These require policy/design; do not fill them with generic status flips, automatic refunds, wage deductions or custody overrides. A GPS dispatch dashboard, extra role, mandatory photo for every note or automatic refund is not an established requirement.

Existing rollout limitation remains: migrations 000006–000009 were pending at the preceding read-only check. This source review does not install them or certify live writes. No configured business account was exercised here.

## Evidence and source map

Fresh existing tests: **82 tests / 1,928 assertions passed**, zero failures/errors, from LogisticsAuthorizationTest, HubTransferWorkflowTest, NavigationTest and LogisticsCashDeskTest. Results: private `admin-feature-review.xml/json`. Synthetic SQLite only; no new tests, real transfers, full device acceptance or full-repository-green claim. Existing relevant tests include all-six-role route denial, foreign hub/company scope, Admin versus Staff physical receipt, duplicate transfer/receipt, audit rollback and cash permissions.

Primary implementation references:

- `routes/web.php`: logistics shared routes, nested logistics.admin group, Rider routes and protected evidence endpoints.
- `resources/views/logistics/partials/sidebar.blade.php`: actual Admin versus Staff navigation.
- `app/Http/Middleware/LogisticsMiddleware.php`, `LogisticsAdminMiddleware.php`, `LogisticsActivity.php`; `app/Services/LogisticsAccess.php`.
- `app/Http/Controllers/LogisticsController.php`: dashboard/requests/assignments/scanLookup/receiveAtSortingCenter/monitor/updateStatus/history/reports/exportReport; personnel/hubs/fleet/account and compatibility transfer actions.
- `app/Http/Controllers/HubTransferController.php`, `app/Services/HubTransferWorkflow.php`: batch request/decision/dispatch/confirmPickup/receive/discrepancy.
- `LogisticsOperationsController`, `AssignmentReassignment`, `AssignmentWork`, `LogisticsRecovery`, `LogisticsRts`: evidence, owner changes and exception custody.
- `LogisticsManagementController::issues/storeIssue/updateIssue`, account/hub/activity methods; `HubHiring`, `PersonnelApplication`, `PersonnelReasons`.
- `LogisticsCashDeskController`, `LogisticsCashDesk`, `LogisticsFinanceController`, `LogisticsShippingController`, `SellerPayoutController`, `RiderPayoutController`, `LogisticsReconciliationController` and their existing financial services.
- `RiderController::acceptPickupRequest/confirmPickupReceipt/confirmHubTransferPickup/acceptRequest/advance/markFailed`; `PhoneScannerController`, `PhoneScanner`; `public/js/parcel-scan.js`; corresponding logistics/rider views.

Only this report and private generated test artifacts were written for this review. No removal or permission change has been implemented.
