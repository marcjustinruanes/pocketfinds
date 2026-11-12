# Logistics Admin: complete function inventory

Audited 11 November 2026 against the current application route registry, controllers, service authorization, Logistics Blade navigation/forms, scanner API and read-only configuration counts. This is a code/function/permission inventory, **not an end-to-end execution of every transaction**. No orders, payments, messages, personnel or configuration were changed.

## What was checked

- All **148 named `logistics.*` route entries**, including entries physically guarded for Hub Staff rather than company admins.
- All **22 shared `commissions.*` entries**, distinguishing Logistics Admin from Platform Admin and Seller permissions.
- All **10 mobile scanner API entries** and **10 shared account/evidence/media entries**.
- Every inventoried controller handler exists. All **143 distinct literal route names referenced by Logistics views resolve**. This checks route wiring, not browser behavior or business success for every state.
- **87 logistics entries have explicit `logistics.admin` middleware**. Other entries require their controller/service guards to determine whether the company admin can perform the action.
- The admin is an approved `account_type=logistics` company administrator, not the platform-wide `is_admin` role. Company ownership and scope restrictions apply throughout. Some records additionally require an operational hub, supporting evidence, financial eligibility, a revision/version, or a particular workflow state.

## Complete user-facing functions

### 1. Company dashboard

- View company shipment pipeline counts: pickup approval, pickup assignment, transit, sorting, delivery assignment, delivery progress, delivery failures and completed work.
- View pending personnel, vehicle and hub-transfer indicators.
- Open the corresponding operational queues and filtered monitor views from dashboard cards.
- Review company-level operational workload; Hub Staff instead receive their own hub dashboard.

### 2. Seller pickup requests

- List new seller handoff requests and the separate approved-but-unassigned pickup queue.
- Inspect order, seller, buyer, parcel and shipping-payment context.
- Approve pickup requests; GCash shipping obligations must pass the existing payment gate.
- Reject a pending request with a reason. The existing cancellation/refund/stock workflow handles its consequences.
- Select an eligible origin-hub pickup rider and send a personal offer.
- See pending/previous offer state and an explicit message if nobody qualifies.
- Rider acceptance remains separate; approval or offering a job does not attest physical pickup.

### 3. Buyer delivery assignment

- List sorted parcels awaiting riders and parcels with assignments/transfers already in progress.
- View buyer, tracking, shipment stage, rider/offer, assignment status and acceptance time.
- Send or replace a delivery offer for an unclaimed sorted parcel using an eligible destination-hub rider.
- Retry manually after automatic offers expire/are declined or the automatic retry limit is exhausted.
- Enforce approval, online availability, company/hub, vehicle, barangay coverage and three combined active pickup/delivery jobs through the initial-assignment service.
- Observe rider acceptance before shipment/order ownership advances to `assigned_to_rider`.

### 4. Assignment evidence and possession-based reassignment

- View assignment evidence and work classifications, including active, completed, ambiguous or otherwise blocked work.
- Request replacement of an eligible uncollected pickup/delivery assignment, supplying the current owner, replacement, revision, idempotency key and reason.
- Cancel a pending reassignment where authorized.
- Track possession-review progress and notifications.
- **Cannot personally confirm possession**: the owning seller confirms pickup possession; the assigned destination Hub Staff confirms delivery-hub possession. A company admin cannot substitute for those witnesses.
- Financial snapshots, physical custody evidence and earned work constrain reassignment.

### 5. Hub transfers and relay dispatch

- List transfer batches, open a batch and inspect its members, route, status and event history.
- Create a company transfer request between eligible hubs using eligible parcels.
- Review pending route-level/hub-transfer requests.
- Approve or reject requests/batches, with the required reason where applicable.
- Dispatch an approved batch to an approved four-wheel rider at its origin hub, selecting a matching company vehicle when required.
- Use the existing legacy per-shipment assignment adapter for eligible hub-transfer legs.
- Cancel an eligible undispatched request/batch.
- Report a discrepancy for dispatched/completed batches, optionally against an individual member.
- Monitor partial and complete receipt, intermediate hops and final sorting readiness.
- **Cannot receive physical parcels on behalf of the destination Hub Staff** or confirm transfer-rider pickup.

### 6. Live shipment monitoring and permitted status changes

- Search company shipments and inspect order/buyer details, assigned pickup/delivery/transfer riders, shipping status and custody history.
- Filter transit versus final-delivery work and other existing monitor filters.
- Record allowed delivery failures with reasons.
- Retry a failed delivery through the existing eligible-rider redelivery transition.
- Complete an already delivered order where the existing workflow permits it.
- Invalid stage skips are rejected. The assigned rider initiates out-for-delivery and confirms delivery; seller receipt is required for a completed physical return.

### 7. Parcel lookup and scanner utilities

- Open the scanner/lookup page directly at `/logistics/scan`; it is not an admin Operations sidebar item.
- Look up a tracking/order number and see parcel details, custody, current/next permitted stage and delivery rider choices.
- Send an eligible delivery offer through the scanner's dispatch control.
- Create a short-lived phone/browser scanner connection, open the paired phone page, poll/relay scanned codes, acknowledge codes and disconnect.
- Phone relay is lookup input, not unrestricted status-writing authority.
- Physical sorting receipt and hub-transfer receipt remain Hub Staff-only despite shared URLs.

### 8. Mobile scanner API

- Sign in to obtain scanner access, inspect the current account and sign out/revoke access.
- Look up company parcels and synchronize scan submissions.
- Pair with a browser scanner session, inspect pairing status, relay codes and disconnect.
- API operation endpoints retain the same receiving permissions: Logistics Admin cannot use them to impersonate physical Hub Staff receipt.
- This describes backend API support, not proof that a particular mobile app build has been installed/tested.

### 9. Rider personnel management

- Search/filter the company's rider roster by supported account statuses.
- Review application details and authorized private documents, vehicle information, hub assignment and activation state.
- Create rider accounts through the existing onboarding/activation process.
- Edit managed rider account/application fields, onboarding documents and hub assignment subject to current personnel rules.
- Resend activation/correction invitations when eligible.
- Approve pending rider applications; reject with the existing reason/correction controls; suspend approved riders; reactivate suspended riders.
- Review assignment/delivery history and hiring/application decision history on the managed account page.
- Suspension preserves actual parcel custody and follows the existing release/recovery rules; it is not a blanket deletion of work/history.

### 10. Rider barangay routes

- View each rider's assigned current-hub barangays on the roster.
- Open route management from the rider list or account management page.
- Assign one or several official barangays within the rider's operational hub municipality.
- Edit selections or clear coverage entirely.
- Validate geographic choices through the cached HubAddress/PSGC source, including region-only NCR hubs.
- Reject foreign-company, foreign-hub, unknown or duplicate submitted codes and stale edits.
- Old coverage is not reused after a hub move. Missing coverage does not fall back to all riders.

### 11. Hub Staff personnel management

- Search/filter and review company Hub Staff applications and documents.
- Create and edit staff accounts, role-appropriate onboarding fields and hub assignments.
- Invite pending applicants to an interview, record the scheduling/location details, and confirm final approval after the required interview checks.
- Reject applications with existing correction/resubmission controls, suspend staff and reactivate eligible suspended staff.
- Review hiring/application history and managed-account details.
- Company admin does not receive a Hub Staff personal salary/clock-in identity simply because it manages those accounts.

### 12. Coverage hubs and hiring

- List/search hubs and filter hiring status; review province grouping, regional-hub and personnel/hiring counts.
- Create a geographically validated municipal hub within the company.
- Edit supported hub identity/contact/address/regional/operational details; an existing hub's province and municipality are not silently relocated.
- Close/reopen a hub subject to operational restrictions; closing a hub with active routed parcels is blocked.
- Configure rider and Hub Staff hiring separately: open/closed, capacity limits, closing date/time and revision protection.
- Read geographic location choices through the admin hub-location endpoint.
- The company policy form is submitted for Platform Admin review rather than published immediately.

### 13. Company vehicle fleet

- List/filter the company's submitted fleet and see platform-review statuses and hub assignments.
- Submit a vehicle of a supported type linked to a company hub.
- Update availability and maintenance/status notes.
- Delete a submitted vehicle only while platform approval is still pending.
- View fleet summaries in reports.
- Platform Admin approves/rejects fleet eligibility; Logistics Admin cannot self-approve a vehicle.
- Hub Staff have separate own-hub vehicle controls; those particular endpoints are not company-admin actions.

### 14. Parcel issues, investigations and return oversight

- View company issues and failed/returned/cancelled parcels.
- Create an issue using a tracking number and a hub on the parcel route.
- Supported kinds: delivery failure, lost, damaged, transfer problem and other. Damage evidence is required where specified.
- Assign an issue to an approved eligible person at the responsible hub.
- Change open/in-progress/resolved status; record custody notes, resolution, outcome and supporting evidence; review event history.
- Supported investigation outcomes: located, damage documented, escalated or documented only.
- Confirm and notify relevant participants about eligible resolved lost/damaged investigation milestones.
- Open a return-to-seller request for an eligible failed delivery and oversee its custody progress.
- Physical recovery from suspended riders, hub return receipt/release and seller receipt have separate witnesses; the company admin cannot bypass them. Cross-hub reverse return movement is not automatically implemented by the local RTS shortcut.

### 15. Delivery history and operational reports

- Browse historical delivered/completed, failed, returned and cancelled shipments.
- Filter by tracking/order search, creation-date range, company hub and supported final statuses.
- Review shipment totals, completion/success figures, cancellations and failures.
- Compare top courier delivery performance and hub/province coverage workload.
- Review fleet platform-status/availability summaries.
- Export the operational shipment report to CSV with tracking, status, origin/destination, rider and timestamp columns.
- Parcel/performance date filters use parcel creation dates; fleet availability is the current state.

### 16. Finance overview and compensation settings

- Review company rider compensation snapshots and COD collection records.
- Configure future pickup, hub-transfer and delivery pay rates using revision checks.
- Existing assignment snapshots are preserved when rates change.
- Review company shipping-account instructions, active regional shipping rates, shipping obligations and payment verification states.

### 17. Shipping fees and GCash shipping settlement

- Publish/update the company's GCash receiving instructions for shipping.
- Publish approved destination-region first-kilogram and additional-kilogram rates.
- Inspect individual obligations, payment submissions and proof.
- Verify or reject submitted shipping payments using the expected amount/reference/revision and review notes.
- Enforce settlement before approving a new prepaid pickup when required.
- Shipping charges already snapshotted on an order are not silently rewritten by these screens.

### 18. Cash Desk oversight

- View company COD collections, outstanding/overdue amounts, submitted handovers, custody by hub, cash in transit, handover history and discrepancies.
- Filter the count, treasury and discrepancy work queues and inspect allocation/event details.
- Authorize inter-hub cash transfers.
- Record administrative discrepancy resolution notes without manufacturing cash balance changes.
- Configure the remittance cutoff with revision protection.
- Oversee suspended-rider cash recovery submissions and actual receipt records.
- **Cannot physically count a rider remittance, submit Hub Staff recovery custody, dispatch/receive inter-hub cash, or attest a hub treasury cash release**; assigned Hub Staff perform those actions. Actual treasury-account receipt is a separate admin finance function below.

### 19. Treasury and seller settlements

- Create company treasury accounts and view COD, adjustment and unallocated balances/reservations.
- Authorize movement of hub cash into a treasury account; Hub Staff attest physical release.
- Record actual treasury receipt with amount, reference, notes and evidence.
- Record supporting adjustment funding with proof.
- Review eligible COD orders, deductions, collection/payment eligibility, payout destinations and existing seller settlements.
- Prepare seller payouts; verify, hold, release or cancel eligible preparations; record actual payment with proof/reference.
- Review payout evidence, event history and seller acknowledgement.
- Seller acknowledgement is performed by the seller. Admin payment is not seller acknowledgement.

### 20. Rider earnings and payouts

- Review compensation snapshots and completion evidence.
- Recognize a pending earning only when authoritative completion evidence qualifies it, without duplicate earning recognition.
- Record rider-pay funding with evidence.
- Prepare payouts from eligible earnings and available funding.
- Verify, hold, release or cancel eligible payouts; record actual payment proof/reference.
- Review payout destination details, history and acknowledgements.
- Rider receiving-account maintenance and receipt acknowledgement belong to the rider, not the company admin.

### 21. Hub Staff salaries and payroll workspace

- Configure hub monthly salary policy and inspect its history.
- Configure permitted individual salary terms and return a staff member to applicable hub salary terms.
- Generate supported half-month salary periods and inspect period history/pay dates.
- Filter/search the payroll workspace by staff, hub, period, status and blocker.
- Review missing salary/destination, suspension, overdue, funding, eligibility and hold blockers.
- Record salary funding, prepare payouts, verify/hold/release/cancel eligible payouts and record actual payments with proof.
- Preview and execute bulk period generation or payout preparation, for up to 25 selected staff at a time; bulk preview does not itself pay salaries.
- Inspect individual payout details, funding/evidence/audit history, salary history and acknowledgement state.
- Export payroll workspace data.
- Staff receiving-account maintenance and personal acknowledgement are staff functions; attendance summaries do not silently invent attendance-based pay deductions.

### 22. Hub Staff attendance oversight

- View/search/filter company staff shifts/day records by hub, staff, date, status and correction state.
- Inspect attendance event histories and corrections.
- Approve/reject correction requests with reasons.
- Record leave or confirmed absence with overlap/schedule protections.
- Mark missing-timeout cases or void supported records using revisions/reasons.
- Export attendance records.
- Staff clock themselves in/out and submit their own correction requests; admin does not use the staff self-service clock endpoints.

### 23. Financial reconciliation and exports

- View read-only company reconciliation metrics, hub custody, treasury account buckets/reservations, exceptions and conservation checks.
- Export the supported datasets: summary, financial transactions, COD collections, handovers/cash transfers, shipping obligations/vouchers, seller settlements, rider earnings, rider payouts, staff payouts, treasury movements, audit history and manual-review exceptions.
- Reconciliation distinguishes authoritative movements from mirrored ledger figures and does not auto-repair balances.

### 24. Platform commissions and COD refund settlement

- Open the shared commission portal for the company's authorized COD obligations/payments/credits.
- Review scoped balances, allocations, status/event history, receiving instructions, proofs and supported CSV exports.
- Prepare/reserve a company COD commission remittance; record the actual send with payment reference and evidence.
- Open the authorized payment conversation with Platform Admin, send messages/attachments, view conversation proof and read status updates.
- Repay eligible commission credits for which the logistics company is the responsible payer, using the seller destination and supporting proof.
- Record eligible COD buyer-refund reconciliation and company/seller refund reimbursement using the guarded refund workflow; view authorized refund evidence.
- **Cannot manage platform receiving accounts, verify platform commission receipts, allocate platform-controlled credit, submit seller-only payments/recoveries, or perform platform-only refund receipt verification.** Shared controller routes do not grant those permissions.

### 25. Activity logs and operational audit

- Browse/filter company activity logs and see actor, hub, action, record and time.
- Review operation-specific histories for hiring, assignments/transfers, investigations, finance, salary and attendance.
- Mutating logistics requests use the existing transaction/audit middleware; logs are not equivalent to physical custody or cash verification.

### 26. Messages, attachments and notifications

- List/search permitted conversation contacts and review thread previews.
- Message Platform Admins, approved company riders and approved sellers associated with the company's shipments.
- Send supported attachments, poll for messages and report messages through the existing reporting workflow.
- Access private message media only as a conversation participant.
- View own notification dropdown; open linked notifications and mark own notifications read/all read through shared account endpoints.
- Commission conversations are a separate financial-context messaging channel.
- The existing contact policy does **not** provide unrestricted messaging of every buyer or every staff account.

### 27. Own account and security

- View account/company details, profile and relevant pending-change/policy status.
- Change the profile image immediately; submit supported name/contact/sex and address changes for Platform Admin review.
- Load validated geographic address choices.
- Request/verify the supported Gmail email-change code, with current-password checks for non-buyer accounts.
- Change password using the current password and configured strength/confirmation checks.
- Submit company terms/policy text for Platform Admin review.
- Use normal authentication/password recovery and sign out.
- Access only authorized personnel documents, financial evidence, shipping proofs, delivery proofs and own complaint/message evidence. Evidence URLs are guarded utilities, not unrestricted access to all records.

## Important boundaries and current findings

1. **Receipt authority is intentionally separated.** Shared logistics URLs exist for Hub Staff cash counts/physical parcel receipt, personal salary/attendance and own-hub fleet controls; a Logistics Admin is not automatically authorized to use them. The route appendix marks these.
2. **Only initial assignment currently uses the new shared barangay filter.** Possession-based reassignment in `AssignmentReassignment::replacement()` still has its own company/hub/vehicle/approval checks; it does not call the new barangay, availability or combined-capacity filter. The legacy failed-delivery retry transition also uses a separate check rather than the initial personal-offer flow. This is a confirmed code-path distinction, not a newly performed live reassignment or an assertion that those paths were covered by the initial-assignment patch. No changes were made during this inventory.
3. **Read-only configuration check:** zero active carrier shipping rates, zero carrier shipping GCash account records and zero rider barangay coverage rows. Admins must configure these for eligible new dispatch/payment operations.
4. **Staffing check:** 87 operational hubs, 85 without an approved Hub Staff account assigned. Marking a hub operational does not provide a physical receiver.
5. The code inventory and route-link checks do not establish SMTP delivery, physical cash handling, scanner hardware, every browser interaction or every production concurrent state. Previously documented unrelated test failures are not reclassified as passes by this audit.

## Route-by-route appendix

The following registry snapshot includes every selected route, its existing handler and an authorization note. **Route entry counts are not counts of distinct user features.** Authorization also depends on current record state and the service checks described above.


### A. All logistics route entries

| Method | Path | Route name / handler | Logistics Admin permission |
| --- | --- | --- | --- |
| GET | `/logistics/cash-desk` | `logistics.cash-desk`<br>`LogisticsCashDeskController@index` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/cash-desk/recovery` | `logistics.cash-desk.recovery`<br>`LogisticsCashDeskController@recoverySubmission` | **No** - Hub Staff suspended-rider cash recovery attestation. |
| POST | `/logistics/cash-desk/treasury/{id}/release` | `logistics.cash-desk.treasury.release`<br>`SellerPayoutController@release` | **No** - Assigned Hub Staff attest physical hub cash release. |
| POST | `/logistics/cash-desk/{id}/count` | `logistics.cash-desk.count`<br>`LogisticsCashDeskController@count` | **No** - Assigned Hub Staff count actual cash. |
| POST | `/logistics/cash-desk/transfers` | `logistics.cash-desk.authorize`<br>`LogisticsCashDeskController@authorize` | Admin-only authorization enforced in service. |
| POST | `/logistics/cash-desk/{id}/transfer/{action}` | `logistics.cash-desk.transfer`<br>`LogisticsCashDeskController@transfer` | **No** - Origin/destination Hub Staff dispatch/receive actual cash. |
| POST | `/logistics/cash-desk/{id}/resolve` | `logistics.cash-desk.resolve`<br>`LogisticsCashDeskController@resolve` | Admin-only administrative discrepancy resolution. |
| POST | `/logistics/cash-desk/cutoff` | `logistics.cash-desk.cutoff`<br>`LogisticsCashDeskController@cutoff` | Admin-only cutoff settings. |
| POST | `/logistics/logout` | `logistics.logout`<br>`AdminController@logout` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/dashboard` | `logistics.dashboard`<br>`LogisticsController@dashboard` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/transfers` | `logistics.transfers`<br>`HubTransferController@index` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/transfers` | `logistics.transfers.store`<br>`HubTransferController@store` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/transfers/request` | `logistics.transfers.create`<br>`HubTransferController@create` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/transfers/{id}` | `logistics.transfers.show`<br>`HubTransferController@show` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/transfers/{id}/cancel` | `logistics.transfers.cancel`<br>`HubTransferController@cancel` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/transfers/{id}/receive` | `logistics.transfers.receive`<br>`HubTransferController@receive` | **No** - Assigned destination Hub Staff receipt. |
| POST | `/logistics/transfers/{id}/discrepancy` | `logistics.transfers.discrepancy`<br>`HubTransferController@discrepancy` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/transfers/{id}/approve` | `logistics.transfers.approve`<br>`HubTransferController@approve` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/transfers/{id}/reject` | `logistics.transfers.reject`<br>`HubTransferController@reject` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/transfers/{id}/dispatch` | `logistics.transfers.dispatch`<br>`HubTransferController@dispatch` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/assignments` | `logistics.assignments`<br>`LogisticsController@assignments` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/assignments/review` | `logistics.assignments.review`<br>`LogisticsOperationsController@index` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/assignments/{id}/reassignment` | `logistics.assignments.reassign`<br>`LogisticsOperationsController@requestReassignment` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/assignments/{id}/reassignment/decision` | `logistics.assignments.decide`<br>`LogisticsOperationsController@decideReassignment` | Cancel where permitted; possession confirmation is Seller/Hub Staff-only. |
| PATCH | `/logistics/assignments/{id}/assign` | `logistics.assignments.assign`<br>`LogisticsController@assignCourier` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/assignments/{id}/assign-hub-transfer` | `logistics.assignments.assign-hub-transfer`<br>`LogisticsController@assignHubTransferRider` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/scan` | `logistics.scan`<br>`LogisticsController@scan` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/scan/lookup` | `logistics.scan.lookup`<br>`LogisticsController@scanLookup` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/scan/phone-sessions` | `logistics.scan.phone-sessions`<br>`PhoneScannerController@create` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/scan/phone/{token}` | `logistics.scan.phone`<br>`PhoneScannerController@phone` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/scan/phone/{token}/events` | `logistics.scan.phone.events`<br>`PhoneScannerController@poll` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/scan/phone/{token}/codes` | `logistics.scan.phone.codes`<br>`PhoneScannerController@send` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/scan/phone/{token}/ack` | `logistics.scan.phone.ack`<br>`PhoneScannerController@acknowledge` | Company/record-scoped; controller/service validation applies. |
| DELETE | `/logistics/scan/phone/{token}` | `logistics.scan.phone.disconnect`<br>`PhoneScannerController@disconnect` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/scan/{id}/receive` | `logistics.scan.receive`<br>`LogisticsController@receiveAtSortingCenter` | **No** - Assigned origin Hub Staff receipt. |
| PATCH | `/logistics/scan/{id}/complete-hub-transfer` | `logistics.scan.complete-hub-transfer`<br>`LogisticsController@completeHubTransfer` | **No** - Assigned destination Hub Staff receipt. |
| POST | `/logistics/hub/request-transfer` | `logistics.hub.request-transfer`<br>`LogisticsController@requestHubTransfer` | **No** - Hub Staff legacy request endpoint; admin has separate batch/admin request endpoints. |
| GET | `/logistics/monitor` | `logistics.monitor`<br>`LogisticsController@monitor` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/status/{id}` | `logistics.status.update`<br>`LogisticsController@updateStatus` | Only guarded failure, redelivery and delivered-to-completed transitions. |
| POST | `/logistics/issues/rts/{id}` | `logistics.rts`<br>`LogisticsManagementController@rts` | Admin may request RTS; physical return actions belong to rider/Hub Staff/seller. |
| GET | `/logistics/issues` | `logistics.issues`<br>`LogisticsManagementController@issues` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/issues/recovery` | `logistics.issues.recovery`<br>`LogisticsOperationsController@receiveRecovery` | **No** - Assigned Hub Staff attest recovered physical parcel custody. |
| POST | `/logistics/issues` | `logistics.issues.store`<br>`LogisticsManagementController@storeIssue` | Company/record-scoped; controller/service validation applies. |
| PATCH | `/logistics/issues/{id}` | `logistics.issues.update`<br>`LogisticsManagementController@updateIssue` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/history` | `logistics.history`<br>`LogisticsController@history` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/messages` | `logistics.messages`<br>`LogisticsController@messages` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/messages/poll` | `logistics.messages.poll`<br>`LogisticsController@messagesPoll` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/messages/report` | `logistics.messages.report`<br>`LogisticsController@reportMessage` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/messages/send` | `logistics.messages.send`<br>`LogisticsController@messagesSend` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/messages/{userId}` | `logistics.messages.thread`<br>`LogisticsController@messagesThread` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/account` | `logistics.account`<br>`LogisticsController@account` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/account/update` | `logistics.account.update`<br>`LogisticsController@accountUpdate` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/account/address` | `logistics.account.address`<br>`LogisticsController@accountAddressUpdate` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/address-locations/{level}/{code?}` | `logistics.address.locations`<br>`LogisticsController@addressLocations` | Company/record-scoped; controller/service validation applies. |
| POST | `/logistics/account/password` | `logistics.account.password`<br>`LogisticsController@passwordUpdate` | Company/record-scoped; controller/service validation applies. |
| GET | `/logistics/requests` | `logistics.requests`<br>`LogisticsController@requests` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/requests/{id}/approve` | `logistics.requests.approve`<br>`LogisticsController@approveRequest` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/requests/{id}/reject` | `logistics.requests.reject`<br>`LogisticsController@rejectRequest` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/hub-requests` | `logistics.hub-requests`<br>`LogisticsController@hubRequests` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/hub-requests/approve` | `logistics.hub-requests.approve`<br>`LogisticsController@approveHubTransferRequest` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/hub-requests/reject` | `logistics.hub-requests.reject`<br>`LogisticsController@rejectHubTransferRequest` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/hub-requests/admin-request` | `logistics.hub-requests.admin-request`<br>`LogisticsController@adminRequestHubTransfer` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/reports` | `logistics.reports`<br>`LogisticsController@reports` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/reports/export` | `logistics.reports.export`<br>`LogisticsController@exportReport` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/accounts` | `logistics.accounts`<br>`LogisticsManagementController@accounts` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/accounts` | `logistics.accounts.store`<br>`LogisticsManagementController@storeAccount` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/riders/accounts` | `logistics.riders.accounts.store`<br>`LogisticsManagementController@storeRiderAccount` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/staff/accounts` | `logistics.staff.accounts.store`<br>`LogisticsManagementController@storeStaffAccount` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/accounts/{id}/edit` | `logistics.accounts.edit`<br>`LogisticsManagementController@editAccount` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/riders/{id}/coverage` | `logistics.riders.coverage.edit`<br>`RiderCoverageController@edit` | Admin-only route; company scope and workflow validation still apply. |
| PUT | `/logistics/riders/{id}/coverage` | `logistics.riders.coverage.update`<br>`RiderCoverageController@update` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/accounts/{id}` | `logistics.accounts.update`<br>`LogisticsManagementController@updateAccount` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/activity` | `logistics.activity`<br>`LogisticsManagementController@activity` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance` | `logistics.finance`<br>`LogisticsFinanceController@index` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/reconciliation` | `logistics.finance.reconciliation`<br>`LogisticsReconciliationController@index` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/reconciliation/export` | `logistics.finance.reconciliation.export`<br>`LogisticsReconciliationController@export` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/riders` | `logistics.finance.riders`<br>`RiderPayoutController@index` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/riders/funding` | `logistics.finance.riders.funding`<br>`RiderPayoutController@funding` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/riders/prepare` | `logistics.finance.riders.prepare`<br>`RiderPayoutController@prepare` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/riders/{id}/recognize` | `logistics.finance.riders.recognize`<br>`RiderPayoutController@recognize` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/riders/{id}/action` | `logistics.finance.riders.action`<br>`RiderPayoutController@action` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/riders/{id}/pay` | `logistics.finance.riders.pay`<br>`RiderPayoutController@pay` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/payouts` | `logistics.finance.payouts`<br>`SellerPayoutController@index` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/payouts/prepare` | `logistics.finance.payouts.prepare`<br>`SellerPayoutController@prepare` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/payouts/{id}/action` | `logistics.finance.payouts.action`<br>`SellerPayoutController@action` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/payouts/{id}/pay` | `logistics.finance.payouts.pay`<br>`SellerPayoutController@pay` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/treasury/accounts` | `logistics.finance.treasury.account`<br>`SellerPayoutController@treasuryAccount` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/treasury/transfers` | `logistics.finance.treasury.transfer`<br>`SellerPayoutController@transfer` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/treasury/{id}/receipt` | `logistics.finance.treasury.receipt`<br>`SellerPayoutController@receipt` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/treasury/funding` | `logistics.finance.treasury.funding`<br>`SellerPayoutController@funding` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/staff/hubs/{hub}` | `logistics.finance.staff.hub-history`<br>`StaffSalaryController@hubHistory` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/hub-salary` | `logistics.finance.staff.hub`<br>`StaffSalaryController@configureHub` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/use-hub` | `logistics.finance.staff.use-hub`<br>`StaffSalaryController@useHub` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/attendance` | `logistics.attendance`<br>`StaffAttendanceController@index` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/attendance/export` | `logistics.attendance.export`<br>`StaffAttendanceController@export` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/attendance/marks` | `logistics.attendance.mark`<br>`StaffAttendanceController@mark` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/attendance/corrections/{id}` | `logistics.attendance.decide`<br>`StaffAttendanceController@decide` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/attendance/records/{id}` | `logistics.attendance.review`<br>`StaffAttendanceController@review` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/attendance/records/{id}` | `logistics.attendance.show`<br>`StaffAttendanceController@show` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/staff/export` | `logistics.finance.staff.export`<br>`StaffSalaryController@export` | Admin-only route; company scope and workflow validation still apply. |
| GET / POST | `/logistics/finance/staff/bulk/preview` | `logistics.finance.staff.bulk.preview`<br>`StaffSalaryController@bulkPreview` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/bulk/execute` | `logistics.finance.staff.bulk.execute`<br>`StaffSalaryController@bulkExecute` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/staff/salaries/{staff}` | `logistics.finance.staff.history`<br>`StaffSalaryController@salaryHistory` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/staff/payouts/{id}` | `logistics.finance.staff.show`<br>`StaffSalaryController@show` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/staff` | `logistics.finance.staff`<br>`StaffSalaryController@index` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/configure` | `logistics.finance.staff.configure`<br>`StaffSalaryController@configure` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/periods` | `logistics.finance.staff.periods`<br>`StaffSalaryController@generatePeriod` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/funding` | `logistics.finance.staff.funding`<br>`StaffSalaryController@funding` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/prepare` | `logistics.finance.staff.prepare`<br>`StaffSalaryController@prepare` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/{id}/action` | `logistics.finance.staff.action`<br>`StaffSalaryController@action` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/staff/{id}/pay` | `logistics.finance.staff.pay`<br>`StaffSalaryController@pay` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/finance/shipping/{id}` | `logistics.finance.shipping.show`<br>`LogisticsShippingController@show` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/shipping/{id}/review` | `logistics.finance.shipping.review`<br>`LogisticsShippingController@review` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/shipping-account` | `logistics.finance.shipping.account`<br>`LogisticsShippingController@shippingAccount` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/shipping-rate` | `logistics.finance.shipping.rate`<br>`LogisticsShippingController@rate` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/finance/settings` | `logistics.finance.settings`<br>`LogisticsFinanceController@settings` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/hubs` | `logistics.hubs.store`<br>`LogisticsManagementController@storeHub` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/hub-locations/{level}/{code?}` | `logistics.hubs.locations`<br>`LogisticsManagementController@hubLocations` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/hubs/{id}` | `logistics.hubs.update`<br>`LogisticsManagementController@updateHub` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/vehicles/{id}` | `logistics.vehicles.update`<br>`LogisticsManagementController@updateVehicle` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/riders` | `logistics.riders`<br>`LogisticsController@riders` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/riders/{id}/activation-email` | `logistics.riders.activation.resend`<br>`LogisticsManagementController@resendRiderActivation` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/riders/{id}/approve` | `logistics.riders.approve`<br>`LogisticsController@approveRider` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/riders/{id}/reject` | `logistics.riders.reject`<br>`LogisticsController@rejectRider` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/riders/{id}/activate` | `logistics.riders.activate`<br>`LogisticsController@activateRider` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/riders/{id}/suspend` | `logistics.riders.suspend`<br>`LogisticsController@suspendRider` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/staff` | `logistics.staff`<br>`LogisticsController@staff` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/staff/{id}/approve` | `logistics.staff.approve`<br>`LogisticsController@inviteStaffInterview` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/staff/{id}/confirm` | `logistics.staff.confirm`<br>`LogisticsController@confirmStaffApproval` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/staff/{id}/reject` | `logistics.staff.reject`<br>`LogisticsController@rejectStaff` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/staff/{id}/activate` | `logistics.staff.activate`<br>`LogisticsController@activateStaff` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/staff/{id}/suspend` | `logistics.staff.suspend`<br>`LogisticsController@suspendStaff` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/hubs` | `logistics.hubs`<br>`LogisticsController@hubs` | Admin-only route; company scope and workflow validation still apply. |
| PATCH | `/logistics/hubs/{id}/toggle-hiring` | `logistics.hubs.toggle-hiring`<br>`LogisticsController@toggleHubHiring` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/company-policy` | `logistics.company-policy.update`<br>`LogisticsController@updateCompanyPolicy` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/vehicles` | `logistics.vehicles`<br>`LogisticsController@fleet` | Admin-only route; company scope and workflow validation still apply. |
| POST | `/logistics/vehicles` | `logistics.vehicles.store`<br>`LogisticsController@storeVehicle` | Admin-only route; company scope and workflow validation still apply. |
| DELETE | `/logistics/vehicles/{id}` | `logistics.vehicles.destroy`<br>`LogisticsController@destroyVehicle` | Admin-only route; company scope and workflow validation still apply. |
| GET | `/logistics/my-attendance` | `logistics.my-attendance`<br>`StaffAttendanceController@mine` | **No** - Hub Staff own attendance. |
| POST | `/logistics/my-attendance/clock/{action}` | `logistics.my-attendance.clock`<br>`StaffAttendanceController@clock` | **No** - Hub Staff clock themselves in/out. |
| POST | `/logistics/my-attendance/corrections` | `logistics.my-attendance.correction`<br>`StaffAttendanceController@requestCorrection` | **No** - Hub Staff own correction request. |
| GET | `/logistics/my-attendance/records/{id}` | `logistics.my-attendance.show`<br>`StaffAttendanceController@show` | NO - This self-service route selects Hub Staff authorization; company admin uses logistics.attendance.show. |
| GET | `/logistics/my-salary` | `logistics.my-salary`<br>`StaffSalaryController@myIndex` | **No** - Hub Staff own salary. |
| POST | `/logistics/my-salary/destination` | `logistics.my-salary.destination`<br>`StaffSalaryController@destination` | **No** - Hub Staff own receiving account. |
| POST | `/logistics/my-salary/{id}/acknowledge` | `logistics.my-salary.acknowledge`<br>`StaffSalaryController@acknowledge` | **No** - Hub Staff acknowledge own receipt. |
| GET | `/logistics/hub/vehicles` | `logistics.hub.vehicles`<br>`LogisticsController@hubVehicles` | **No** - Hub Staff own-hub fleet screen; admin uses company fleet. |
| PATCH | `/logistics/hub/vehicles/{id}/toggle` | `logistics.hub.vehicles.toggle`<br>`LogisticsController@toggleVehicleAvailability` | **No** - Hub Staff own-hub toggle; admin uses vehicle update. |
| PATCH | `/logistics/requests/{id}/assign-pickup` | `logistics.requests.assign-pickup`<br>`LogisticsController@assignPickupRider` | Company/record-scoped; controller/service validation applies. |

### B. Shared commission routes

| Method | Path | Route name / handler | Logistics Admin permission |
| --- | --- | --- | --- |
| POST | `/commissions/recoveries` | `commissions.recoverySubmit`<br>`CommissionController@recoverySubmit` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/recoveries/{id}/review` | `commissions.recoveryReview`<br>`CommissionController@recoveryReview` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/recoveries/{id}/allocate` | `commissions.recoveryAllocate`<br>`CommissionController@recoveryAllocate` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/recoveries/{id}/refund` | `commissions.recoveryRefund`<br>`CommissionController@recoveryRefund` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/cod-refunds/{id}/reconcile` | `commissions.codRefund`<br>`CommissionController@codRefund` | Company-scoped guarded refund reconciliation. |
| POST | `/commissions/cod-refunds/{id}/reimburse` | `commissions.codReimburse`<br>`CommissionController@codReimburse` | Guarded reimbursement where the company is authorized. |
| POST | `/commissions/cod-refunds/{id}/verify` | `commissions.codVerify`<br>`CommissionController@codVerify` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| GET | `/commissions` | `commissions.index`<br>`CommissionController@index` | Company/record-scoped; controller/service validation applies. |
| GET | `/commissions/export` | `commissions.export`<br>`CommissionController@export` | Company/record-scoped; controller/service validation applies. |
| GET | `/commissions/export/{kind}` | `commissions.money-export`<br>`CommissionController@moneyExport` | Authorized company COD payments/credits, not seller/platform recovery exports. |
| GET | `/commissions/evidence/{kind}/{id}` | `commissions.evidence`<br>`CommissionController@evidence` | Company/record-scoped; controller/service validation applies. |
| POST | `/commissions/accounts` | `commissions.accounts`<br>`CommissionController@account` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| GET | `/commissions/conversations/{kind}/{id}` | `commissions.conversation`<br>`CommissionController@conversation` | Own company COD payment conversations only. |
| POST | `/commissions/conversations/{kind}/{id}` | `commissions.message`<br>`CommissionController@message` | Own company COD payment conversations only. |
| GET | `/commissions/conversation-evidence/{message}` | `commissions.message-proof`<br>`CommissionController@messageProof` | Company/record-scoped; controller/service validation applies. |
| POST | `/commissions/payments` | `commissions.submit`<br>`CommissionController@submit` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/remittances` | `commissions.prepare-remittance`<br>`CommissionController@prepareRemittance` | Company/record-scoped; controller/service validation applies. |
| POST | `/commissions/remittances/{id}` | `commissions.send-remittance`<br>`CommissionController@sendRemittance` | Company/record-scoped; controller/service validation applies. |
| POST | `/commissions/payments/{id}/review` | `commissions.review`<br>`CommissionController@review` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/payments/{id}/allocate` | `commissions.allocate`<br>`CommissionController@allocate` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/payments/{id}/refund-credit` | `commissions.refund-credit`<br>`CommissionController@refundCredit` | **No** - Seller-only or Platform Admin action; shared routing does not authorize Logistics Admin. |
| POST | `/commissions/credits/{id}/repay` | `commissions.repay`<br>`CommissionController@repay` | Only a credit for which this company is the logistics payer. |

### C. Mobile scanner API

| Method | Path | Route name / handler | Logistics Admin permission |
| --- | --- | --- | --- |
| POST | `/api/v1/mobile/login` | `mobile.login`<br>`MobileScannerController@login` | Scanner account/token/pair scope required. |
| POST | `/api/v1/mobile/logout` | `mobile.logout`<br>`MobileScannerController@logout` | Scanner account/token/pair scope required. |
| GET | `/api/v1/mobile/me` | `mobile.me`<br>`MobileScannerController@me` | Scanner account/token/pair scope required. |
| POST | `/api/v1/mobile/parcels/lookup` | `mobile.lookup`<br>`MobileScannerController@lookup` | Scanner account/token/pair scope required. |
| POST | `/api/v1/mobile/scans` | `mobile.scans`<br>`MobileScannerController@syncScan` | Scanner account/token/pair scope required. |
| POST | `/api/v1/mobile/operations` | `mobile.operations`<br>`MobileScannerController@operate` | Receipt operation body is Hub Staff-only, even though admin may authenticate to API. |
| POST | `/api/v1/mobile/pair/{token}` | `mobile.pair`<br>`MobileScannerController@pair` | Scanner account/token/pair scope required. |
| GET | `/api/v1/mobile/pair/{token}/status` | `mobile.pair.status`<br>`MobileScannerController@pairStatus` | Scanner account/token/pair scope required. |
| POST | `/api/v1/mobile/pair/{token}/disconnect` | `mobile.pair.disconnect`<br>`MobileScannerController@disconnectPair` | Scanner account/token/pair scope required. |
| POST | `/api/v1/mobile/pair/{token}/codes` | `mobile.relay`<br>`MobileScannerController@relay` | Scanner account/token/pair scope required. |

### D. Shared account, media and evidence utilities

| Method | Path | Route name / handler | Logistics Admin permission |
| --- | --- | --- | --- |
| GET | `/private-documents/{token}` | `sensitive-documents.show`<br>`SensitiveDocumentController@show` | Own or authorized company personnel document token. |
| POST | `/account/notifications/read-all` | `account.notifications.read-all`<br>`CommerceController@notificationsReadAll` | Own approved account only. |
| GET | `/account/notifications/{id}` | `account.notifications.open`<br>`CommerceController@notificationOpen` | Own approved account only. |
| POST | `/account/security/email/code` | `account.email.code`<br>`CommerceController@emailCode` | Own approved account only. |
| POST | `/account/security/email/verify` | `account.email.verify`<br>`CommerceController@emailVerify` | Own approved account only. |
| GET | `/message-media/{path}` | `message.media`<br>`Closure` | Only a conversation participant. |
| GET | `/report-evidence/{path}` | `report.evidence`<br>`Closure` | Only a complaint participant; not blanket Platform Admin authority. |
| GET | `/shipping-payments/{id}/proof` | `shipping.proof`<br>`LogisticsShippingController@proof` | Authorized company shipping payment evidence. |
| GET | `/delivery-proofs/{shipment}` | `delivery-proofs.show`<br>`DeliveryProofController@show` | Delivery proof for a company shipment. |
| GET | `/finance-evidence/{id}` | `finance.evidence`<br>`SellerPayoutController@evidence` | Company financial evidence, guarded by source/record scope. |
