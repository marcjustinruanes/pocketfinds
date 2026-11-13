# Hub Staff: complete function inventory

Audited 11 November 2026 against the current route registry, Hub Staff navigation/dashboard, controllers and service-level permission checks. “Hub” here means the **Hub Staff module**, following the Logistics Admin inventory. This is a read-only code/permission audit, not live execution of every parcel or financial transaction. No application behavior or business records were changed.

## Scope and access

- Hub Staff use an approved logistics account with `logistics_role=hub_staff`, company membership and an assigned operational hub in that company.
- Of **148 logistics route entries**, **87 are explicitly Logistics Admin-only**. The remaining **61 shared logistics entries** still have service/state restrictions: they are not 61 unconditional Hub Staff permissions.
- All selected logistics, mobile and shared evidence/account handlers exist. The route-by-route appendix below includes all 61 shared logistics entries, the ten mobile scanner entries and ten shared account/evidence entries, plus the excluded admin route names.
- Operational parcel visibility covers same-company shipments touching the assigned hub as origin, destination or an intermediate route leg. Physical receipt/assignment permission is narrower: it must be the actual receiving/origin/destination hub required by that operation.
- Seeing a parcel in a monitor does not authorize dispatching or receiving it at a different hub.

## Complete functions

### 1. My Hub dashboard

- View the assigned hub's municipality/province and local/regional identity.
- View approved seller pickups awaiting a rider and each parcel's current/previous pickup offer.
- View incoming seller pickups, their rider and seller-confirmed pickup state.
- View parcels ready for a next-hop transfer request, grouped by destination.
- Track requests awaiting Logistics Admin approval and approved requests awaiting admin dispatch.
- View inbound transfer legs and riders.
- Open custody information, transfer details and paginated operational queues.

### 2. Seller pickup rider offers

- Work only with pickups originating at the assigned hub that Logistics Admin already approved.
- Choose a rider covering the seller's pickup barangay; current company, hub/province, approval, online availability, two-wheel vehicle and combined three-job checks also apply.
- Send or replace a personal 60-second pickup offer. A repeat live offer to the same rider is idempotent.
- See a clear no-eligible-rider message when coverage, address or eligibility is missing; sending is disabled when the list is empty.
- Manually retry after declined/expired automatic offers or exhausted automatic retries.
- Rider acceptance is required before assigning pickup ownership. Rider receipt and seller handover confirmation are separate steps; Hub Staff do not confirm them on the parties' behalf.
- Cannot approve/reject the seller's original pickup request or change the selected logistics company/order price.

### 3. Origin hub receipt and sorting

- Scan/look up a parcel arriving from a seller after the pickup handover has advanced to `picked_up`.
- As assigned origin Hub Staff, attest actual physical receipt.
- For a local shipment needing no hub transfer, receipt also sorts the parcel and makes it available for delivery offers.
- For a shipment needing transfer, receipt records `at_sorting_center` and builds its hub-leg route.
- Existing assignment closure/compensation hooks run from authoritative receipt evidence.
- Cannot receive a parcel early, receive at another hub, or use a generic status change to skip the receipt workflow.

### 4. Hub-transfer requests

- View batches involving the assigned hub and inspect members, route, status and event history.
- Create a request from the assigned origin hub using eligible parcels/next legs and a valid company destination hub.
- Use the dashboard's grouped next-hop Request Transfer action or the transfer-request screen.
- Track pending approval, rejection, approval and dispatch separately.
- Cancel an eligible **pending** request from the assigned origin hub, supplying a reason. Staff cannot cancel an already approved batch through this action.
- Cannot approve/reject requests or assign/dispatch their four-wheel transfer rider/company vehicle; Logistics Admin performs those decisions.

### 5. Incoming hub-transfer receipt

- Monitor batches/legs traveling to the assigned hub.
- After the assigned transfer rider confirms physical pickup, receive eligible parcels at the destination hub.
- Receive a subset of batch parcels or all remaining eligible members; partial receipt does not falsely complete the whole batch.
- Complete the current leg and record the actual receiver/time.
- At an intermediate hub, continue the next-hop request process; at the final hub, completed transfer receipt advances the shipment/order to sorted readiness.
- Report dispatched/completed transfer discrepancies at the destination hub, for a batch or a particular member.
- Cannot attest the transfer rider's own pickup or receive a leg out of sequence.

### 6. Buyer delivery rider offers

- View sorted parcels, pending/previous offers and active delivery assignments.
- At the parcel's destination hub, choose a rider covering the **order's saved destination barangay**, not the buyer's potentially changed profile address.
- Send/replace/retry a personal delivery offer from Delivery Assignments or the scanner.
- Maintain the same initial eligibility, vehicle, online availability and combined three-job checks used by automatic offers.
- See no-eligible-rider guidance and disabled send controls when coverage/eligibility is absent.
- Rider acceptance advances ownership/status; Hub Staff sending the offer alone leaves the parcel sorted and unclaimed.
- Origin/intermediate staff may retain route visibility but cannot assign a final-delivery rider at a different destination hub.
- Staff cannot edit rider barangay coverage; Logistics Admin configures it.

### 7. Assignment evidence and possession review

- Inspect supported assignment evidence and work classifications for scoped shipments.
- Request a replacement rider for eligible uncollected pickup/delivery work, with owner, replacement, revision, request key and reason.
- Cancel a pending request when authorized by its workflow.
- Confirm actual possession of an uncollected final-delivery parcel at the assigned destination hub, when all evidence/state conditions allow it.
- Pickup possession must be confirmed by the owning seller; Hub Staff cannot substitute for the seller.
- Completed/earned/recovered or already released work has existing restrictions; this is not arbitrary reassignment of a parcel already in transit.
- **Implementation distinction:** this service uses separate replacement eligibility from the newer initial-offer/barangay service; see findings below.

### 8. Scan Parcel and phone relay

- Look up a tracking/order number and inspect order, buyer, route, rider, parcel status, custody and next permitted actions.
- Receive seller pickups, receive eligible hub-transfer parcels and send eligible final-delivery offers through guarded scanner controls.
- Use supported camera/USB/manual input controls and scanner error/empty-state feedback.
- Create a short-lived phone connection, open the phone scanner, relay codes, poll results, acknowledge scanned events and disconnect.
- Phone relay sessions are tied to the same account/company/hub/role/browser context. Relaying a code does not itself grant unrestricted shipment writes.

### 9. Mobile scanner API

- Authenticate for scanner access; inspect the current scanner account; log out/revoke access.
- Look up scoped parcels and synchronize scan submissions.
- Pair with a browser scanner session, inspect pair status, relay codes and disconnect.
- Submit supported `receive` / `receive_transfer` operations using operation IDs and expected statuses. Existing guards enforce assigned-hub physical receipt and protect retries/stale state.
- API support is not a claim that scanner hardware or a particular installed mobile app build was tested in this audit.

### 10. Live Monitor and limited status operations

- Search/filter company parcels touching the assigned hub and view pickup, transfer and delivery progress/riders.
- View custody explanations rather than treating an assignment as proof of physical collection.
- At the permitted destination hub, record allowed delivery failure with a reason, use the existing eligible failed-delivery retry transition, or complete an already delivered order where permitted.
- The rider marks out-for-delivery and physical delivery. Hub Staff cannot force those states or skip receipt/transfer stages.
- The legacy redelivery transition is distinct from initial personal-offer dispatch.

### 11. Delivery History

- Browse scoped delivered/completed, failed, returned and cancelled shipments.
- Search tracking/order numbers; filter by supported date range, status and permitted hub.
- Inspect associated buyer/rider and delivery/custody details.
- Company-wide operational reports and their CSV export are Logistics Admin functions; no separate Hub Staff report-export endpoint is provided.

### 12. Parcel issues and investigations

- View issues at the assigned hub and scoped failed/returned/cancelled shipments.
- Create delivery-failure, lost, damaged, transfer-problem or other issues for a parcel/hub on its route.
- Supply descriptions and required damage proof; retain investigation/event history.
- Assign an investigation to eligible approved personnel at the responsible hub.
- Update open/in-progress/resolved state, supported outcomes, custody notes and resolution with revision protection.
- Supported outcomes include located, damage documented, escalated and documented only.
- Public participant notifications for resolved lost/damaged milestones require Logistics Admin confirmation; staff cannot enable that admin-only notification action.
- Resolving an issue does not itself manufacture parcel receipt, restore stock or settle a payment.

### 13. Suspended-rider physical parcel recovery

- Record supported recovery of incomplete parcels from a suspended same-company rider at a hub on the relevant leg.
- Supply tracking identifiers, leg, observed time, condition, reason, reference, confirmation and required evidence.
- Record recovered custody separately from normal completion and financial settlement.
- Respect existing assignment/route/recovery conflicts and idempotency controls.
- Uncollected work uses possession review rather than invented physical recovery; an active rider is not treated as a suspended recovery holder.

### 14. Return-to-seller custody

- Open an eligible return request for a genuinely failed, undelivered assignment within scope.
- At the actual destination hub, attest receipt after the assigned rider's return-to-hub step.
- Release a supported local return from that hub after its required custody checks.
- Track the return's revision/state and recorded actions through its issue/custody context.
- The assigned rider records rider transport; only the owning seller attests final sellable receipt and triggers stock restoration.
- The local return shortcut does not provide unrestricted cross-hub reverse dispatch; unsupported cross-hub returns remain held for separate approval/workflow.

### 15. Cash Desk and actual rider remittance counts

- View assigned-hub COD collections, balances, outstanding/overdue amounts and linked handovers.
- Review count, treasury and discrepancy queues, allocation records, handover events, hub custody and in-transit cash.
- Count a submitted rider remittance physically at the assigned hub.
- Record actual received cash, allocations against selected collections, notes and discrepancies.
- Preserve shortfalls, overages and unallocated amounts rather than marking the expected sum received automatically.
- Recorded cash receipt/verification is separate from the rider's initial collection or submission.
- Cannot change company remittance cutoff, authorize transfers or administratively resolve discrepancies; those Cash Desk actions require Logistics Admin.

### 16. Suspended-rider cash recovery submissions

- Prepare a recovery remittance for a suspended same-company rider's supported collections at the assigned hub.
- Record the actual cash holder, submitting staff, custody reference/reason and collection allocations.
- Keep the submission separate from the physical count/verification step.
- Staff recovery is not a rider self-submission and cannot be withdrawn through the rider withdrawal path.

### 17. Authorized cash transfers and treasury release

- Dispatch an **already admin-authorized** inter-hub cash transfer from the assigned origin hub.
- Receive/count an incoming authorized transfer at the assigned destination hub.
- Record the required actual amount, COD portion and notes, preserving transit and discrepancy accounting.
- Release authorized verified hub COD cash to the company's treasury transfer, subject to exact amount/available qualified cash checks.
- Cannot authorize the transfer, create treasury accounts, record the final treasury-account receipt or spend treasury funds on payouts. These are company-admin responsibilities.

### 18. Hub Vehicles

- View vehicles assigned to the staff member's hub.
- Inspect vehicle/hub approval and availability information.
- Toggle availability for an approved vehicle at that hub.
- Cannot create/delete company fleet records, assign vehicles to another hub or approve a pending platform vehicle submission.

### 19. My Attendance

- View/filter own attendance and correction history and inspect own record/event details.
- Clock in using server-recorded Philippine time; only one active shift is allowed and documented leave/absence can block clock-in.
- Clock out from the active shift with chronological protections, including supported overnight shifts.
- Request a correction for a supported existing shift or missing day, with requested past times and a reason; duplicate pending requests are blocked.
- View administrator decisions on correction requests.
- Cannot approve own corrections, mark colleagues absent/on leave, void another person's records or use company attendance exports.

### 20. My Salary and receiving account

- View own current salary terms/history, salary periods, obligations, due/upcoming pay dates and payout history.
- Review payout status, destination snapshot, payment reference/evidence and acknowledgement state.
- Maintain the supported own receiving-account instructions through the salary destination form.
- Acknowledge actual recorded **paid** salary receipt; acknowledgement is not allowed for a merely prepared/unpaid payout.
- Cannot set salary rates, generate company payroll, add funding, approve/pay own salary or acknowledge someone else's salary.

### 21. Messages, attachments and notifications

- View/poll conversations with permitted Platform Admins, approved riders at the assigned hub and approved sellers associated with scoped shipments.
- Send supported text/attachments and report messages.
- Read private message media only as a conversation participant and complaint evidence only as an authorized party.
- View own notifications, open their allowed destinations and mark own notifications/all read through shared account utilities.
- Cannot use the company commission portal or its payment-conversation workflow; its actor guard excludes Hub Staff.
- No unrestricted staff-to-all-buyers/all-company-personnel messaging capability is provided by the current contact policy.

### 22. My Account, security and private evidence

- View own profile and assigned hub details.
- Change the profile picture immediately; submit supported personal/contact and address changes for Platform Admin review.
- Load geographic choices, request/verify a supported Gmail email-change code with current-password checks, change password and sign out.
- Read own authorized personnel documents and own salary payment evidence.
- Read authorized parcel issue/recovery financial evidence within the assigned hub and scoped parcel rules.
- Shared delivery-proof access currently checks same-company logistics membership rather than matching the assigned hub; see scope exception below.
- Cannot manage company policy, company receiving accounts, shipping rates/payment verification, personnel approvals, hubs/hiring, rider coverage, commissions or company payroll.

## Findings and limitations

1. **Do not equate visibility with custody authority.** Operations are scoped to origin/destination/intermediate hubs, and separate checks determine which specific hub may act.
2. **Confirmed shared-evidence scope exception:** `DeliveryProofController::show()` authorizes a logistics actor by company membership without requiring its assigned hub to touch the shipment. This endpoint is broader than the operational Hub Staff shipment screens. It was not modified during this inventory.
3. **Confirmed separate reassignment/redelivery paths:** possession reassignment uses its own replacement checks rather than the new initial-offer barangay/availability/combined-capacity validation. Failed-delivery retry also remains a separate transition. This inventory does not silently classify those paths as covered by initial-offer validation.
4. The latest read-only configuration audit recorded **zero carrier rates, zero carrier shipping GCash accounts and zero rider coverage rows**, plus **85 of 87 operational hubs without approved Hub Staff**. Required setup and staff assignments must exist before their corresponding operations can succeed; those counts were collected in the immediately preceding admin audit, not by creating new transactions here.
5. This audit checks code, routes and permissions. It does not certify camera/USB hardware, browser behavior, mail delivery, real cash custody or every concurrent transaction state. No live receipt, payout, message or role change was performed.

## Route-by-route appendix

These are routed capabilities and restrictions, not promises that every record/state qualifies. All Logistics Admin-only route names are listed separately to make the exclusion explicit.


### A. Shared logistics entries

| Method | Path | Route / handler | Hub Staff permission |
| --- | --- | --- | --- |
| GET | `/logistics/cash-desk` | `logistics.cash-desk`<br>`LogisticsCashDeskController@index` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/cash-desk/recovery` | `logistics.cash-desk.recovery`<br>`LogisticsCashDeskController@recoverySubmission` | Assigned hub suspended-rider recovery submission; count remains separate. |
| POST | `/logistics/cash-desk/treasury/{id}/release` | `logistics.cash-desk.treasury.release`<br>`SellerPayoutController@release` | Assigned origin Hub Staff release already-authorized verified COD cash. |
| POST | `/logistics/cash-desk/{id}/count` | `logistics.cash-desk.count`<br>`LogisticsCashDeskController@count` | Assigned hub actual cash count and allocations. |
| POST | `/logistics/cash-desk/transfers` | `logistics.cash-desk.authorize`<br>`LogisticsCashDeskController@authorize` | NO - Logistics Admin authorizes cash transfers. |
| POST | `/logistics/cash-desk/{id}/transfer/{action}` | `logistics.cash-desk.transfer`<br>`LogisticsCashDeskController@transfer` | Only the assigned origin/destination staff dispatch/receive an authorized transfer. |
| POST | `/logistics/cash-desk/{id}/resolve` | `logistics.cash-desk.resolve`<br>`LogisticsCashDeskController@resolve` | NO - Logistics Admin records administrative discrepancy resolution. |
| POST | `/logistics/cash-desk/cutoff` | `logistics.cash-desk.cutoff`<br>`LogisticsCashDeskController@cutoff` | NO - Logistics Admin changes cutoff settings. |
| POST | `/logistics/logout` | `logistics.logout`<br>`AdminController@logout` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/dashboard` | `logistics.dashboard`<br>`LogisticsController@dashboard` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/transfers` | `logistics.transfers`<br>`HubTransferController@index` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/transfers` | `logistics.transfers.store`<br>`HubTransferController@store` | Request only from assigned origin hub. |
| GET | `/logistics/transfers/request` | `logistics.transfers.create`<br>`HubTransferController@create` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/transfers/{id}` | `logistics.transfers.show`<br>`HubTransferController@show` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| PATCH | `/logistics/transfers/{id}/cancel` | `logistics.transfers.cancel`<br>`HubTransferController@cancel` | Only a pending request at assigned origin hub. |
| PATCH | `/logistics/transfers/{id}/receive` | `logistics.transfers.receive`<br>`HubTransferController@receive` | Actual assigned destination hub; dispatched batch and rider pickup evidence required. |
| POST | `/logistics/transfers/{id}/discrepancy` | `logistics.transfers.discrepancy`<br>`HubTransferController@discrepancy` | Assigned destination hub, dispatched/completed batch. |
| GET | `/logistics/assignments` | `logistics.assignments`<br>`LogisticsController@assignments` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/assignments/review` | `logistics.assignments.review`<br>`LogisticsOperationsController@index` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/assignments/{id}/reassignment` | `logistics.assignments.reassign`<br>`LogisticsOperationsController@requestReassignment` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/assignments/{id}/reassignment/decision` | `logistics.assignments.decide`<br>`LogisticsOperationsController@decideReassignment` | Final-delivery possession confirmation at own destination hub; pickup confirmation remains Seller-only; guarded cancellation. |
| PATCH | `/logistics/assignments/{id}/assign` | `logistics.assignments.assign`<br>`LogisticsController@assignCourier` | Send initial offer only at assigned destination hub using eligible barangay coverage. |
| GET | `/logistics/scan` | `logistics.scan`<br>`LogisticsController@scan` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/scan/lookup` | `logistics.scan.lookup`<br>`LogisticsController@scanLookup` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/scan/phone-sessions` | `logistics.scan.phone-sessions`<br>`PhoneScannerController@create` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/scan/phone/{token}` | `logistics.scan.phone`<br>`PhoneScannerController@phone` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/scan/phone/{token}/events` | `logistics.scan.phone.events`<br>`PhoneScannerController@poll` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/scan/phone/{token}/codes` | `logistics.scan.phone.codes`<br>`PhoneScannerController@send` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/scan/phone/{token}/ack` | `logistics.scan.phone.ack`<br>`PhoneScannerController@acknowledge` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| DELETE | `/logistics/scan/phone/{token}` | `logistics.scan.phone.disconnect`<br>`PhoneScannerController@disconnect` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| PATCH | `/logistics/scan/{id}/receive` | `logistics.scan.receive`<br>`LogisticsController@receiveAtSortingCenter` | Physical receipt only at assigned origin hub. |
| PATCH | `/logistics/scan/{id}/complete-hub-transfer` | `logistics.scan.complete-hub-transfer`<br>`LogisticsController@completeHubTransfer` | Physical receipt only at assigned destination hub. |
| POST | `/logistics/hub/request-transfer` | `logistics.hub.request-transfer`<br>`LogisticsController@requestHubTransfer` | Request eligible next-hop transfer from own hub. |
| GET | `/logistics/monitor` | `logistics.monitor`<br>`LogisticsController@monitor` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| PATCH | `/logistics/status/{id}` | `logistics.status.update`<br>`LogisticsController@updateStatus` | Only guarded destination-hub failure/redelivery/completion transitions; not rider delivery confirmation. |
| POST | `/logistics/issues/rts/{id}` | `logistics.rts`<br>`LogisticsManagementController@rts` | Request and permitted local hub custody steps; not rider transport or Seller receipt. |
| GET | `/logistics/issues` | `logistics.issues`<br>`LogisticsManagementController@issues` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/issues/recovery` | `logistics.issues.recovery`<br>`LogisticsOperationsController@receiveRecovery` | Actual scoped physical recovery from suspended rider. |
| POST | `/logistics/issues` | `logistics.issues.store`<br>`LogisticsManagementController@storeIssue` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| PATCH | `/logistics/issues/{id}` | `logistics.issues.update`<br>`LogisticsManagementController@updateIssue` | Own-hub investigation; admin-only public notification option is forbidden. |
| GET | `/logistics/history` | `logistics.history`<br>`LogisticsController@history` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/messages` | `logistics.messages`<br>`LogisticsController@messages` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/messages/poll` | `logistics.messages.poll`<br>`LogisticsController@messagesPoll` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/messages/report` | `logistics.messages.report`<br>`LogisticsController@reportMessage` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/messages/send` | `logistics.messages.send`<br>`LogisticsController@messagesSend` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/messages/{userId}` | `logistics.messages.thread`<br>`LogisticsController@messagesThread` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/account` | `logistics.account`<br>`LogisticsController@account` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/account/update` | `logistics.account.update`<br>`LogisticsController@accountUpdate` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/account/address` | `logistics.account.address`<br>`LogisticsController@accountAddressUpdate` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/address-locations/{level}/{code?}` | `logistics.address.locations`<br>`LogisticsController@addressLocations` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/account/password` | `logistics.account.password`<br>`LogisticsController@passwordUpdate` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/my-attendance` | `logistics.my-attendance`<br>`StaffAttendanceController@mine` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/my-attendance/clock/{action}` | `logistics.my-attendance.clock`<br>`StaffAttendanceController@clock` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/my-attendance/corrections` | `logistics.my-attendance.correction`<br>`StaffAttendanceController@requestCorrection` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/logistics/my-attendance/records/{id}` | `logistics.my-attendance.show`<br>`StaffAttendanceController@show` | Own attendance detail; route selects staff self-service authorization. |
| GET | `/logistics/my-salary` | `logistics.my-salary`<br>`StaffSalaryController@myIndex` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/my-salary/destination` | `logistics.my-salary.destination`<br>`StaffSalaryController@destination` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/logistics/my-salary/{id}/acknowledge` | `logistics.my-salary.acknowledge`<br>`StaffSalaryController@acknowledge` | Own actual paid salary only. |
| GET | `/logistics/hub/vehicles` | `logistics.hub.vehicles`<br>`LogisticsController@hubVehicles` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| PATCH | `/logistics/hub/vehicles/{id}/toggle` | `logistics.hub.vehicles.toggle`<br>`LogisticsController@toggleVehicleAvailability` | Only an approved vehicle at own hub. |
| PATCH | `/logistics/requests/{id}/assign-pickup` | `logistics.requests.assign-pickup`<br>`LogisticsController@assignPickupRider` | Send initial offer only for admin-approved pickup at own origin hub. |

### B. Mobile scanner API

| Method | Path | Route / handler | Hub Staff permission |
| --- | --- | --- | --- |
| POST | `/api/v1/mobile/login` | `mobile.login`<br>`MobileScannerController@login` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/api/v1/mobile/logout` | `mobile.logout`<br>`MobileScannerController@logout` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/api/v1/mobile/me` | `mobile.me`<br>`MobileScannerController@me` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/api/v1/mobile/parcels/lookup` | `mobile.lookup`<br>`MobileScannerController@lookup` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/api/v1/mobile/scans` | `mobile.scans`<br>`MobileScannerController@syncScan` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/api/v1/mobile/operations` | `mobile.operations`<br>`MobileScannerController@operate` | Assigned-hub receive/receive_transfer with operation ID and expected state. |
| POST | `/api/v1/mobile/pair/{token}` | `mobile.pair`<br>`MobileScannerController@pair` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/api/v1/mobile/pair/{token}/status` | `mobile.pair.status`<br>`MobileScannerController@pairStatus` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/api/v1/mobile/pair/{token}/disconnect` | `mobile.pair.disconnect`<br>`MobileScannerController@disconnectPair` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/api/v1/mobile/pair/{token}/codes` | `mobile.relay`<br>`MobileScannerController@relay` | Own account/hub or scoped parcel/record, with role and workflow validation. |

### C. Shared account and evidence utilities

| Method | Path | Route / handler | Hub Staff permission |
| --- | --- | --- | --- |
| GET | `/private-documents/{token}` | `sensitive-documents.show`<br>`SensitiveDocumentController@show` | Own authorized personnel documents, not company-wide personnel records. |
| POST | `/account/notifications/read-all` | `account.notifications.read-all`<br>`CommerceController@notificationsReadAll` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/account/notifications/{id}` | `account.notifications.open`<br>`CommerceController@notificationOpen` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/account/security/email/code` | `account.email.code`<br>`CommerceController@emailCode` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| POST | `/account/security/email/verify` | `account.email.verify`<br>`CommerceController@emailVerify` | Own account/hub or scoped parcel/record, with role and workflow validation. |
| GET | `/message-media/{path}` | `message.media`<br>`Closure` | Conversation participants only. |
| GET | `/report-evidence/{path}` | `report.evidence`<br>`Closure` | Own authorized complaint/participant evidence. |
| GET | `/shipping-payments/{id}/proof` | `shipping.proof`<br>`LogisticsShippingController@proof` | NO - Shipping participant guard permits owning Seller/company admin, not Hub Staff. |
| GET | `/delivery-proofs/{shipment}` | `delivery-proofs.show`<br>`DeliveryProofController@show` | Company logistics membership check; no assigned-hub condition in this shared handler. |
| GET | `/finance-evidence/{id}` | `finance.evidence`<br>`SellerPayoutController@evidence` | Own salary payout or eligible own-hub parcel issue/recovery evidence only. |

### D. Logistics Admin-only entries: Hub Staff denied

- `PATCH /logistics/transfers/{id}/approve` - `logistics.transfers.approve`
- `PATCH /logistics/transfers/{id}/reject` - `logistics.transfers.reject`
- `PATCH /logistics/transfers/{id}/dispatch` - `logistics.transfers.dispatch`
- `PATCH /logistics/assignments/{id}/assign-hub-transfer` - `logistics.assignments.assign-hub-transfer`
- `GET /logistics/requests` - `logistics.requests`
- `PATCH /logistics/requests/{id}/approve` - `logistics.requests.approve`
- `PATCH /logistics/requests/{id}/reject` - `logistics.requests.reject`
- `GET /logistics/hub-requests` - `logistics.hub-requests`
- `PATCH /logistics/hub-requests/approve` - `logistics.hub-requests.approve`
- `PATCH /logistics/hub-requests/reject` - `logistics.hub-requests.reject`
- `PATCH /logistics/hub-requests/admin-request` - `logistics.hub-requests.admin-request`
- `GET /logistics/reports` - `logistics.reports`
- `GET /logistics/reports/export` - `logistics.reports.export`
- `GET /logistics/accounts` - `logistics.accounts`
- `POST /logistics/accounts` - `logistics.accounts.store`
- `POST /logistics/riders/accounts` - `logistics.riders.accounts.store`
- `POST /logistics/staff/accounts` - `logistics.staff.accounts.store`
- `GET /logistics/accounts/{id}/edit` - `logistics.accounts.edit`
- `GET /logistics/riders/{id}/coverage` - `logistics.riders.coverage.edit`
- `PUT /logistics/riders/{id}/coverage` - `logistics.riders.coverage.update`
- `PATCH /logistics/accounts/{id}` - `logistics.accounts.update`
- `GET /logistics/activity` - `logistics.activity`
- `GET /logistics/finance` - `logistics.finance`
- `GET /logistics/finance/reconciliation` - `logistics.finance.reconciliation`
- `GET /logistics/finance/reconciliation/export` - `logistics.finance.reconciliation.export`
- `GET /logistics/finance/riders` - `logistics.finance.riders`
- `POST /logistics/finance/riders/funding` - `logistics.finance.riders.funding`
- `POST /logistics/finance/riders/prepare` - `logistics.finance.riders.prepare`
- `POST /logistics/finance/riders/{id}/recognize` - `logistics.finance.riders.recognize`
- `POST /logistics/finance/riders/{id}/action` - `logistics.finance.riders.action`
- `POST /logistics/finance/riders/{id}/pay` - `logistics.finance.riders.pay`
- `GET /logistics/finance/payouts` - `logistics.finance.payouts`
- `POST /logistics/finance/payouts/prepare` - `logistics.finance.payouts.prepare`
- `POST /logistics/finance/payouts/{id}/action` - `logistics.finance.payouts.action`
- `POST /logistics/finance/payouts/{id}/pay` - `logistics.finance.payouts.pay`
- `POST /logistics/finance/treasury/accounts` - `logistics.finance.treasury.account`
- `POST /logistics/finance/treasury/transfers` - `logistics.finance.treasury.transfer`
- `POST /logistics/finance/treasury/{id}/receipt` - `logistics.finance.treasury.receipt`
- `POST /logistics/finance/treasury/funding` - `logistics.finance.treasury.funding`
- `GET /logistics/finance/staff/hubs/{hub}` - `logistics.finance.staff.hub-history`
- `POST /logistics/finance/staff/hub-salary` - `logistics.finance.staff.hub`
- `POST /logistics/finance/staff/use-hub` - `logistics.finance.staff.use-hub`
- `GET /logistics/attendance` - `logistics.attendance`
- `GET /logistics/attendance/export` - `logistics.attendance.export`
- `POST /logistics/attendance/marks` - `logistics.attendance.mark`
- `POST /logistics/attendance/corrections/{id}` - `logistics.attendance.decide`
- `POST /logistics/attendance/records/{id}` - `logistics.attendance.review`
- `GET /logistics/attendance/records/{id}` - `logistics.attendance.show`
- `GET /logistics/finance/staff/export` - `logistics.finance.staff.export`
- `GET/POST /logistics/finance/staff/bulk/preview` - `logistics.finance.staff.bulk.preview`
- `POST /logistics/finance/staff/bulk/execute` - `logistics.finance.staff.bulk.execute`
- `GET /logistics/finance/staff/salaries/{staff}` - `logistics.finance.staff.history`
- `GET /logistics/finance/staff/payouts/{id}` - `logistics.finance.staff.show`
- `GET /logistics/finance/staff` - `logistics.finance.staff`
- `POST /logistics/finance/staff/configure` - `logistics.finance.staff.configure`
- `POST /logistics/finance/staff/periods` - `logistics.finance.staff.periods`
- `POST /logistics/finance/staff/funding` - `logistics.finance.staff.funding`
- `POST /logistics/finance/staff/prepare` - `logistics.finance.staff.prepare`
- `POST /logistics/finance/staff/{id}/action` - `logistics.finance.staff.action`
- `POST /logistics/finance/staff/{id}/pay` - `logistics.finance.staff.pay`
- `GET /logistics/finance/shipping/{id}` - `logistics.finance.shipping.show`
- `POST /logistics/finance/shipping/{id}/review` - `logistics.finance.shipping.review`
- `POST /logistics/finance/shipping-account` - `logistics.finance.shipping.account`
- `POST /logistics/finance/shipping-rate` - `logistics.finance.shipping.rate`
- `POST /logistics/finance/settings` - `logistics.finance.settings`
- `POST /logistics/hubs` - `logistics.hubs.store`
- `GET /logistics/hub-locations/{level}/{code?}` - `logistics.hubs.locations`
- `PATCH /logistics/hubs/{id}` - `logistics.hubs.update`
- `PATCH /logistics/vehicles/{id}` - `logistics.vehicles.update`
- `GET /logistics/riders` - `logistics.riders`
- `POST /logistics/riders/{id}/activation-email` - `logistics.riders.activation.resend`
- `PATCH /logistics/riders/{id}/approve` - `logistics.riders.approve`
- `PATCH /logistics/riders/{id}/reject` - `logistics.riders.reject`
- `PATCH /logistics/riders/{id}/activate` - `logistics.riders.activate`
- `PATCH /logistics/riders/{id}/suspend` - `logistics.riders.suspend`
- `GET /logistics/staff` - `logistics.staff`
- `PATCH /logistics/staff/{id}/approve` - `logistics.staff.approve`
- `PATCH /logistics/staff/{id}/confirm` - `logistics.staff.confirm`
- `PATCH /logistics/staff/{id}/reject` - `logistics.staff.reject`
- `PATCH /logistics/staff/{id}/activate` - `logistics.staff.activate`
- `PATCH /logistics/staff/{id}/suspend` - `logistics.staff.suspend`
- `GET /logistics/hubs` - `logistics.hubs`
- `PATCH /logistics/hubs/{id}/toggle-hiring` - `logistics.hubs.toggle-hiring`
- `POST /logistics/company-policy` - `logistics.company-policy.update`
- `GET /logistics/vehicles` - `logistics.vehicles`
- `POST /logistics/vehicles` - `logistics.vehicles.store`
- `DELETE /logistics/vehicles/{id}` - `logistics.vehicles.destroy`

All 22 shared commission routes are also excluded for Hub Staff by the commission actor guard, regardless of their URL prefix.
