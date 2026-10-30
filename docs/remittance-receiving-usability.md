# Rider remittance receiving usability

30 October 2026. Reused the existing single Cash Desk count transaction. No financial service, calculation, permission, audit rule, payment rule, migration or source business record changed.

Company-scoped names now include full name and username; Rider account ID remains explicit. Pending receipts show submitted amount, receiving Staff identity and one Receive & Count Cash form. Staff enters actual counted cash; a client preview explains shortage/overage and required allocation without changing allocations automatically. The confirmation names Rider/Staff and submitted/actual amounts. The server remains authoritative and stamps successful receipt time.

Handover History now presents a structured receipt: actual counted amount, receiving Staff/account ID, Philippine date/time and explicit Shortage/Overage/Exact count. Existing allocations, notes, financial audit and discrepancy resolution remain visible. Recovery remittances retain the original cash-holder/actual-submitter distinction rather than falsely asserting personal delivery by the Rider.

Validation failures retain the affected form's actual amount, allocations and notes, using a presentation-only handover marker. This marker is not trusted for service authorization or transaction selection. Receipt success feedback directs users to the saved record. No separate Received action or duplicate financial record was added.

Changed files: LogisticsCashDeskController (display names/success message), cash-desk Blade, logistics-cash-desk.js/.css, and StaffCashNotificationsTest. The new synthetic HTTP flow verifies Rider submission, Staff receipt form, shortage recording, receipt history and retry without another cash movement.

Verification: **52 test executions / 345 assertions passed**, zero failures/errors, across Cash Desk and the notification class including inherited settlement tests. Focused new/notification cases also passed (overlapping totals). Chromium at 1440px/390px verified no document overflow, labelled visible controls at least 46px, shortage preview, required notes and confirmation cancellation without loading. Mobile receiving form visually inspected. PHP/JS syntax and git whitespace checks passed.

Private results: remittance-receiving.xml/json, remittance-receiving-focused.xml/json and remittance-receiving-browser.txt under the private review directory. No fresh MySQL concurrency run was necessary for these presentation changes; existing financial service transaction/duplicate guards were not modified. No real cash, actual customer, physical device or provider transfer verified.

Remaining boundaries: final count corrections still require authorized exception review; differences are not forgiven by documentation. Submission is not physical receipt. The recorded receipt is an authenticated Staff assertion, not independent proof. Existing pending migration rollout remains separate and was not performed here.
