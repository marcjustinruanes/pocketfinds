# Shared buyer workflows — November 14, 2026

Native buyer screens now use the installed website runtime for these workflows:

- Returns: `WebsiteAftercare` calls `CommerceController::requestReturn` and
  `returnAction`. Requests, decisions, physical return receipt, restocking,
  repayment references and commission reversals use `commerce_returns` and the
  website rules. The app reads those same decisions and private evidence.
  Eligible delivered and completed orders use the website seven-day window;
  rejected requests use its three-day appeal window. Receipt completion and
  unattended completion consult the shared return records.
- Reminders: `market/reminder` calls the website controller and writes
  `commerce_reminders`. `BuyerCommerce::sweep` sends due reminders once. The
  existing buyer worker runs that sweep; keep it scheduled on deployed servers.
- Deals: Home's offer icon and Deals → See All open a native paginated gallery
  of eligible markdowns and active/upcoming website flash promotions. Product
  pages also expose upcoming flash sales. Checkout remains authoritative for
  current prices and promotional stock.
- Cart editing: Change variation calls the website's `cartEdit` with an owned,
  locked cart row. Website option prices, stock limits and merge rules apply.
  Retrying a successful edit does not merge quantities twice.
- Logout: Both native buyer account entry points ask for confirmation.

The API, website and buyer worker must use the same database, installed website
runtime, Laravel key and private storage. No change to `api/config/gcash.php`
is required. This update does not change the separate product review workflow.

## Existing mobile records

Before updating an existing deployment, take old clients/workers offline and run:

```powershell
php scripts/align-buyer-commerce.php
php scripts/align-buyer-commerce.php --apply
```

The first command is read-only. The importer preserves return IDs, decisions,
amounts, evidence files and repayment references. Existing website records
are never overwritten; conflicting requests or missing evidence stop the
transaction. Mobile reminder IDs are matched by the full promotion definition,
not by coincidentally equal IDs. Original mobile records and files are retained
as history. Legacy `return_requested` orders recover their recorded delivered
or completed state, while the shared request independently tracks the return.

The local dry run and apply both found no legacy returns or reminders requiring import.

## Validation

`api/tests/buyer-shared-workflows.php` uses rollback-only database fixtures and
temporary evidence to exercise web/app decisions, ownership, seven/three-day
deadlines, repeat submissions, stock restoration, refund references, reminders,
cart edits/merges and legacy imports. Flutter tests cover Deals loading/retry,
upcoming reminders on narrow screens, and logout cancellation/confirmation.
Actual repayment, email/push delivery and physical seller receipt still require
operational testing.

Results: 34 shared-workflow checks, 37 existing native commerce checks and all
96 Flutter tests passed. PHP syntax checks passed. The broader legacy buyer
requirements suite reaches an existing missing `App\Support\AccountInput`
dependency during password tests and cannot finish; it is not counted as a
passing suite. Its return expectations and checkout fixtures now use the
shared website records and current shipping policy.
