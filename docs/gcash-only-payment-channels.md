# GCash-only electronic payments

29 October 2026. Implemented following the owner's instruction to remove bank support for now. COD cash collection/remittance remains available; electronic funding, transfers and payouts use GCash only.

- Treasury, Seller receiving and Rider receiving forms offer only GCash and require an 11-digit `09` mobile number. Backend validation rejects bank submissions and normalizes the provider to GCash.
- New treasury authorizations/releases, funding, payout preparation, instruction verification and actual payout recording reject historical bank accounts/destinations. Active dropdowns contain only GCash accounts. Shared global GCash reference protections remain.
- Existing bank accounts, balances, references, paid records, evidence and acknowledgements remain unchanged. Unsent legacy payout instructions may still be held or cancelled through existing audited controls. An externally sent transfer must remain an exception; never cancel it as unsent. No funds are automatically moved from a bank bucket/account to GCash.
- Buyer account/cart connection controls are GCash-only; PayMaya and bank connection choices are removed. Old cached bank verification codes cannot create a newly connected account. Email verification confirms the profile entry, not an actual payment.
- New COD return requests collect a GCash account name/mobile and store those details in the existing refund-destination field. GCash purchases retain their original-payment destination. Historical return destinations are not rewritten; no refund is triggered by these changes.
- Buyer-to-Seller GCash and Seller-to-logistics shipping already used GCash. No new provider integration, role, financial ledger or schema migration was needed.

Files changed:

- Services: `LogisticsTreasury.php`, `CodSellerPayouts.php`, `RiderCompensation.php`.
- Controllers: `BuyerController.php`, `CommerceController.php`.
- Views: `logistics/partials/finance-account-fields.blade.php`, `logistics/seller-payouts.blade.php`, `logistics/rider-payouts.blade.php`, `buyer/account.blade.php`, `buyer/cart.blade.php`, `buyer/partials/order-return.blade.php`.
- Tests: new `GcashOnlyPaymentsTest.php`; GCash fixture updates in `CodSellerPayoutTest.php`, `RiderCompensationTest.php`, `LogisticsReconciliationTest.php`, `BuyerCommerceTest.php`, and `tests/Support/cash-desk-mysql.php`.

Verification:

- Selected financial/fulfillment/commerce/security regression: **278 tests / 2,565 assertions passed**, no failures/errors. This includes inherited fixture tests.
- Final targeted follow-up after UI refinements: **18 tests / 237 assertions passed**, including bank rejection, old OTP denial, GCash account acceptance, blocked legacy payment, retained history and safe cancellation of unsent instructions. Runs overlap; do not add them.
- Existing real MySQL concurrency runner passed all 26 reported groups with GCash treasury/recipient fixtures, including funds reservations, actual-payment recording, shared reference ownership, recovery and consistent reconciliation snapshots. Only isolated random test schemas/files were created/removed.
- Four synthetic views (Admin settlements, Seller payouts, Rider earnings, Buyer account) at 1440px and 390px have no document overflow. No real device or actual provider transfer was tested.
- PHP syntax checks passed for the changed services/controllers/new test/MySQL helper; `git diff --check` passed.

No business records were changed or migration applied. The four previously pending migrations remain a separate deployment requirement; this channel change adds none. Earlier verification reports describe the pre-change bank-capable implementation and should be read with this follow-up.

Private test/browser artifacts use the `gcash-only-*` prefix under `storage/app/private/logistics-ui-review/`.
