# Platform GCash payment review

New purchases created after `2026_11_08_120000_add_admin_gcash_review_flow` use the `admin` review flow. Existing purchases remain in the `seller` flow with their original instructions, receipts and verifier ownership.

1. An approved platform admin configures the receiving account in **Finance & Reports → GCash payments**. Checkout requires a configured name and valid Philippine mobile number.
2. The buyer explicitly places the order at checkout. The server snapshots prices, shipping, discounts and platform payment details, reserves each exact product option, creates one linked order per seller, and removes only the unchanged selected cart rows. Checkout retries reuse those orders.
3. The buyer pays the displayed platform account and uploads private evidence. Submitting evidence never approves payment.
4. An approved admin opens **GCash payments → Pending review**, checks the actual platform GCash transaction, and records the exact amount and unique transaction reference. Rejections require a reason and allow the buyer to submit corrected proof.
5. Approval records the admin verifier and timestamp, adds a history event, and notifies buyer and seller. Seller confirmation/preparation/pickup remain blocked by server checks until payment approval.
6. The seller confirms the order and continues the existing fulfillment workflow without deducting the already allocated stock again.

Payment state and order state are separate: an order can be `placed` while its purchase is `awaiting_payment`, `proof_submitted` or `rejected`. A platform purchase becomes `order_placed` only after admin payment approval, preserving the existing fulfillment guard. These orders do not use the old one-hour checkout reservation expiry; cancellation releases their allocated stock.

Payment destinations are immutable for existing purchases. Editing the platform account affects only future purchases. Cancelling a verified platform-paid order creates a refund owed by the platform; only an approved platform admin can record its actual repayment. Cancelling an unverified order does not claim that funds were received or refunded. Late or disputed payments use manual resolution.

Approval records a manually verified payment; it does not initiate a GCash transfer or automatically send seller payouts. Existing commission and settlement accounting is not automatically rewritten.
