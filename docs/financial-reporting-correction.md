# Seller and Platform Admin reporting correction

30 October 2026. Reporting only. No collection, transfer, payout, Rider earning, reconciliation, role, financial rule or business record was changed. No migration is required or was applied.

## Changes

- Removed Seller commission deductions and the unsupported Net Profit / After fees calculation. The existing completed-order date filter and presets remain. Sales now explicitly show item subtotal, quoted shipping, already-applied buyer discounts and buyer order value. Shipping/discounts are not deducted a second time.
- Added a shared read-only `PaymentReporting` snapshot using existing orders, verified GCash purchase records, collection ledger entries, physically counted remittance allocations, shipping receipts and Seller payout records. Integer-centavo parsing reuses the existing Cash Desk service. Existing reconciliation is untouched.
- Payment activity uses each receipt/count/payment timestamp, including payments for older orders. It separately shows direct buyer GCash, Rider COD collection, Staff-verified COD, actual Seller COD payouts, GCash shipping and retained COD shipping. Treasury movements and payout-ledger mirrors are not added as another payment.
- Pending COD instructions and their held subset are current all-history values, explicitly independent of the date range. They include only prepared/verified instructions, not unprepared orders or estimated entitlement. Unverified platform claims remain separate from received money. Gross recorded payment events are not profit or current provider balances; later refunds/returns still need separate review.
- Platform Admin Reports and sales CSV/PDF now use actual order/payment sources. The established commission-export URL remains compatible but generates a clearly labelled payment-summary CSV. Exports use proper CSV quoting/formula protection and private/no-store headers.
- The historical commission page/records remain preserved and are labelled as an archive. Stored legacy amounts are not called collected fees, current revenue or actual Seller payments. No current rate, deduction or earnings formula is applied to them.
- Missing financial sources display Unavailable instead of invented zero balances. Seller scoping comes from authentication, not URL parameters. New report tables wrap within desktop/mobile cards without splitting ordinary displayed amounts.

## Files

- New: `app/Services/PaymentReporting.php`, `resources/views/reports/payment-summary.blade.php`, `tests/Feature/PaymentReportingTest.php`.
- Controllers: `app/Http/Controllers/SellerController.php` (reports only), `AdminController.php` (reports, archive and exports only).
- Views: `resources/views/seller/reports.blade.php`, `admin/reports.blade.php`, `admin/reports-sales-pdf.blade.php`, `admin/commission.blade.php`, `admin/partials/sidebar.blade.php`.
- Documentation: this report and a follow-up reference in `financial-payment-workflows-verification.md`.

## Verified amounts

The COD fixture shows PHP 1,100 buyer order value, PHP 1,000 item sales and PHP 100 quoted shipping. Its PHP 1,100 collection/count is separate from the PHP 1,000 Seller payout and PHP 100 retained shipping. A prepared/held PHP 1,000 instruction stays pending until Paid; its ledger/treasury mirrors do not double the displayed payout.

The GCash/platform-voucher fixture shows PHP 1,080 directly verified buyer payment, PHP 100 separately paid logistics shipping and a PHP 20 unverified platform claim. It produces no logistics Seller payout. The old PHP 990 profit/commission result is no longer calculated or displayed.

Date tests confirm that a payment today for an older completed order appears in today's payment activity without changing today's sales cohort. Later Returned status does not erase an actual historical payout. Foreign Seller orders are excluded, including a forged Seller-ID query parameter. Legacy commission rows remain unchanged.

## Verification

- Selected reporting, finance, COD/GCash, payroll, commerce, fulfillment, navigation and Admin regression: **308 tests / 3,169 assertions passed**, zero failures/errors.
- Final report-specific checks: **5 tests / 94 assertions passed**, including mixed COD/GCash/vouchers, pending/held/paid amounts, date/scoping, read-only snapshots, unavailable sources, archived rows and real CSV/PDF responses. Counts overlap; do not add them.
- Synthetic Seller and Admin pages checked at 1440px and 390px in Chromium. Both retain active report navigation and have no document overflow. The first Admin mobile preview exposed a width issue; scoped wrapping corrected it. Desktop Seller and mobile Admin screenshots were visually inspected. No physical-device or full accessibility certification is claimed.
- PHP syntax and `git diff --check` passed. Private artifacts use `reporting-*` under `storage/app/private/logistics-ui-review/`.

Remaining limits: this is gross recorded activity, not cost-based profit, automated refund netting or proof of external GCash transfer/provider balance. Pending instruction totals deliberately exclude orders not yet prepared for payout. Existing deployment/missing-file/financial-exception limitations remain separate; no new financial policy or rollout was performed. Historical stored commission settings/records were not rewritten.
