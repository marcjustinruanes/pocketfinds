# Rider flow alignment

The mobile application retains the shared website's operational records and
independent handover confirmations.

## Approved daily delivery pay and COD distribution

The mobile API loads tracked overrides of LogisticsFinance, RiderCompensation,
CodSellerPayouts and CommissionLedger through BuyerWebsiteRuntime.php. New delivery
snapshots and pending delivery work recognized as earned use PHP 10 per successful
delivery. Following the website, pickups and transfers do not create new pay snapshots. Recognition does not wait for COD remittance, buyer receipt confirmation
or a return-window timeout. Existing earned and paid amounts are not rewritten.

The overview groups delivery earnings, actual recorded payments and unpaid amounts
by the completion day in Asia/Manila. Logistics is responsible for daily payout;
this is not an automatic bank transfer or a fabricated paid status. Existing
funding, verification, transaction references and receipt acknowledgement remain.

COD remains full buyer cash including shipping. Hub-confirmed cash receipt clears
the rider balance; merely submitting a handover does not. Product distribution
requires explicit buyer Order Received confirmation, never automatic completion
after a timeout. Existing refund/dispute checks remain. The configured commission
is 10% of product proceeds excluding shipping; shipping remains logistics revenue.
Rider delivery pay is separate and must not be deducted from collected COD.

These overrides apply to the mobile API's embedded website runtime. The local
website's CodSellerPayouts and CommissionLedger now enforce explicit buyer
confirmation as well. A separately hosted website still requires deployment.

Implemented in this update:

- Dashboard work groups separate seller pickups, hub handoffs, active deliveries
  and jobs needing attention. Availability explains that current jobs remain
  accessible while offline.
- Earnings totals and charts read website-earned compensation. Payout stages
  distinguish pending work, earned compensation awaiting preparation, prepared,
  verified, held, paid, and acknowledged receipts. Earlier app records remain
  accessible separately rather than being added to website totals.
- COD balances use website collection allocations: collected, confirmed,
  submitted, outstanding and available. Per-collection physical handover uses
  the website cash desk service, retaining its ownership, hub, amount and retry
  checks. Submitting never substitutes for hub counting.
- Assigned deliveries show a persisted parcel verification checklist. History
  provides pickup, delivery, attempt, return and cancellation filters with the
  rider's recorded app actions expanded per parcel.

- Seller pickup now exposes Assigned, Accepted, Arrived at seller, Awaiting seller
  handover confirmation, Picked up, and Received at sorting center stages.
- The assigned rider records arrival before scanning or entering the exact parcel
  tracking number and confirming physical receipt. Arrival is an idempotent shared
  delivery event; it does not change parcel custody or mark hub receipt.
- Starting a mobile final delivery requires barcode verification by that rider.
  Verification does not complete buyer handover. Existing OTP, photographic proof,
  COD amount checks and logistics failure decisions remain in place.
- The legacy app payout ledger now checks the website return window, website and
  legacy return records, verified prepaid payment, and verified COD reconciliation
  before approval or release. Reversal/failure reporting remains possible when a
  dispute is opened after release.

Policy differences that must remain explicit:

- PocketFinds currently supports seven-day returns, rather than the supplied
  example's three days. This update preserves the website policy.
- Website logistics compensation and its funded payout process are separate from
  the legacy app credit ledger; this update does not replace that shared process.
- Verification is per assigned parcel. There is no final-delivery run manifest
  in the current integration; a whole-run verification gate is not implemented.
- Refund cash handoff assignments and rider QR attendance are not exposed by the
  current shared integration. No synthetic custody or attendance records are
  created. Availability remains independent of attendance.

Validation: `php api/tests/rider-website-compatibility.php` uses transaction-scoped
fixtures, tests ownership, safe arrival retries, required parcel verification,
shared OTP/proof/COD handling and failed-delivery behavior, and rolls back its
database writes. Live physical deliveries and financial disbursements are not
performed by this test.
