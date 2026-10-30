# Financial navigation and usability

30 October 2026. Presentation changes only. Financial calculations, controllers/services, role permissions, transactions and source records are unchanged. No migration or deployment.

Admin financial pages now share five workspace links with current-page state. Context guidance distinguishes physical count, treasury receipt, eligibility, reservation, verified instruction, actual payment and recipient acknowledgement. Cash Desk and Seller Settlements link directly to each other's relevant handover/treasury sections; Rider payroll remains separately funded; Reconciliation remains read-only review.

Finance Overview retains shipping obligations for review. Shipping configuration, compensation settings and duplicated reference tables are collapsed rather than removed; existing inputs, rate snapshots and pagination remain available. Settings errors and reference-table pagination open their respective sections. Seller eligibility/payment empty states explain the next step and why buyer GCash does not create a second Seller payout. The Admin settlement page uses the existing layout success message instead of displaying it twice.

Added restrained pink navigation, readable labels, responsive wrapping, and contextual confirmations/loading protection for Admin financial forms. Preparation is explicitly a reservation; actual-payment confirmation records externally sent GCash. Cancel warns against cancelling an already-sent payment. Browser back navigation restores submit controls. Server checks remain authoritative. Staff and recipient pages do not receive Admin financial navigation or the new Admin form script.

Files: finance, cash-desk, seller-payouts, rider-payouts and reconciliation Blade views; shipping-finance partial; new finance-navigation partial; new logistics-finance-navigation.css and logistics-finance-feedback.js; extended LogisticsFinanceFoundationTest. No route, model, controller or service changes.

Selected finance/Cash Desk/Seller/Rider/shipping/reconciliation/navigation regression: **130 tests / 1,140 assertions passed**, zero failures/errors. Final focused foundation/navigation check: **10 tests / 56 assertions passed**, overlapping that run. Synthetic Chromium views of all five pages at 1440px/390px: no document overflow or unlabelled inspected visible fields; inspected controls at least 44px. Actual-payment prompt cancellation, loading and back-navigation reset passed. Mobile Overview and desktop Settlements visually inspected. No real money, provider checks, physical devices, fresh MySQL race run or complete accessibility acceptance claimed; financial services are untouched.

Evidence: private finance-navigation.xml/json, finance-navigation-focused.xml/json and finance-navigation-browser.txt/screenshots. JS syntax and git diff whitespace checks passed. Existing pending migration rollout and manual external-payment exception boundaries remain separate; no new financial rules were introduced.
