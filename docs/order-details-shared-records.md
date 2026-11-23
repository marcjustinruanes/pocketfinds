# Order details backed by website records

The native order screen reads a buyer-owned presentation from `buyer-api/order`.
`api/OrderDetails.php` uses the website's existing database and models; it does
not create another order, shipment, payment or refund ledger.

- Items, variations, images, quantities and prices come from `orders.items`.
  Product listing edits do not reprice an existing order. Old color/size keys
  remain readable alongside website variation_group/variation_value.
- Recipient, contact, full address and instructions use `shipping_address`.
- Product/shipping discounts use the saved commerce voucher allocation.
  Historical orders lacking that allocation show their combined discount and
  an explicit unavailable message. The screen displays the saved delivery
  charge alongside the current ₱50-per-seller-parcel policy.
- `commerce_checkouts` links separate seller orders from one checkout. Every
  linked-order query is scoped to the authenticated buyer.
- Verified GCash amounts, references and dates use `gcash_purchases` or the
  existing platform-payment verification record. A screenshot submission never
  counts as verified money. COD uses the recorded shared collection; hub cash
  verification remains separate. Missing historical amounts are not invented.
- Carrier, tracking number, delivery date and private proof use `shipments`.
  Tracking merges order status history and actual hub-transfer timestamps.
  Delivery estimates use the order's saved guarantee window when available.
- Completion history distinguishes a buyer confirmation from an automatic
  completion. The timestamp is a recorded event, not an inferred delivery time.
- Return details use `commerce_returns`; cancellation refunds use
  `cancellation_refunds` or the existing platform-payment refund record.
  Repayment dates are labeled as recording dates. Approval is not presented
  as payment, and missing references/dates remain unavailable.
- Message Seller is available at earlier order stages. Track Delivery scrolls
  to the carrier, tracking number and dated history. Existing eligibility rules
  still govern cancellation, proof submission, receipt, return and Buy Again.
- Shop/rider review forms in aftercare call the website `CommerceController`
  and store ratings, comments and supported photos in `order_service_reviews`.
  Website ownership, completion, review-window and update rules apply.

Deploy the updated API and Flutter app together. The installed website schema,
database, Laravel key and public/private storage must remain shared. No new
business tables or database migration are required by this change. This work
does not recover historical details that were never recorded, or implement
splitting a single seller order into multiple physical shipments.

Validation covers snapshot preservation, complete address data, payment proof
versus verification, shared refund details, buyer isolation, service review
ownership/replays, and narrow-screen rendering. Live handover and bank/GCash
repayment still require operational verification.

Validation results: 48 shared buyer workflow checks, 39 native commerce/payment
checks, 96 existing Flutter tests and three new snapshot/narrow-screen tests
passed. PHP syntax and whitespace checks passed. Database fixtures and temporary
evidence were rolled back or removed.
