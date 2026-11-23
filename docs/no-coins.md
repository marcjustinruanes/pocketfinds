Coins have been retired from the native app and local pocketfinds database.

Removed: balance/history screens and requests, profile balance chip, Settings/account links, reward dialogs, review rewards, coin cashback vouchers, redemption and cancellation ledger writes. Reviews still save normally. Vouchers retain discount/free-shipping options.

scripts/remove-coins.php performs a dry run by default. --apply first makes a full private MySQL backup, preserves existing order totals, folds historical reward discounts into discount_amount, removes obsolete order columns and the ledger, disables old reward vouchers, and updates database-export/Dump20261113.sql. It does not initiate refunds, payouts or transfers.

Historical backups and archival source migrations are retained for recovery/audit; they do not enable a live coin feature. Use the updated MySQL export for new installations. Separately hosted databases require the same migration before deploying this API version.
