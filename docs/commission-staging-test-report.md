# Commission synthetic staging test report

7 November 2026. Tests use isolated local Laravel HTTP requests with synthetic accounts, in-memory SQLite databases and fake evidence files. The separate MySQL runner creates randomly named scratch databases, copies schema only and removes the scratch databases after its checks. No external payment is sent. These checks do not provision or certify the hosted staging website.

## Scenarios

| Check | Result |
| --- | --- |
| COD payout, withholding, remittance and Platform receipt verification through Laravel HTTP | Passed |
| Seller GCash commission submission, Platform verification, excess credit and partial-refund repayment through Laravel HTTP | Passed |
| Concurrent receipts, refund reversals, remittance reservation and reference recovery on isolated MySQL | Eight groups passed |
| COD and GCash checkout, fulfillment, reporting, permissions, balances, refunds and residual settlements | 254 executions / 2,751 assertions passed; zero failures/errors |
| Seller, Platform Admin and Logistics controller pages | One rendering test / 13 assertions passed |
| Desktop/mobile synthetic page previews | Six passed; zero page overflow, unlabelled inputs or writes; all six cancelled confirmations prevented submission |

The two new HTTP scenarios passed 60 assertions. Together with the regression and rendering selections, **257 PHP test executions / 2,824 assertions passed, zero failures/errors**. Counts include inherited and repeated cases, not 257 distinct new scenarios. No failing test was observed in these selections; this is not a whole-repository release certification.

The new HTTP cases are in `tests/Feature/CommissionStagingEndToEndTest.php`. They block stray HTTP client requests and fake mail. Run only their two scenarios with `php vendor/phpunit/phpunit/phpunit tests/Feature/CommissionStagingEndToEndTest.php --filter=test_staging_`. Existing checkout, fulfillment, return and commission regression suites supply the other workflow segments; not every scenario is one uninterrupted browser journey from login to settlement.

Example verified: PHP 1,000 merchandise plus PHP 100 shipping yields PHP 100 commission and PHP 900 COD Seller transfer. The shipping PHP 100 stays separate. Withholding and remittance transit count as zero collected until Platform Admin verifies the simulated receipt. For GCash, a simulated PHP 120 commission receipt allocates PHP 100 and preserves PHP 20 as unapplied Seller credit. Refunding the PHP 600 item reverses PHP 60 commission; its repayment does not consume the separate PHP 20 credit.

## Safety and limits

The configured application's enrollment remains false, activation timestamp null, and all 15 commission/refund tables empty on the read-only check. No configured-database migration, real receiving-account publication, payment, refund or historical-order update was executed. Commission is enabled only inside isolated test processes.

Laravel feature requests use authenticated test actors rather than interactive hosted login. Receipt attestations and proof files are simulated. Actual GCash delivery, production CSRF/session behavior, scheduler/worker supervision and production operations remain acceptance tasks. This report does not approve live activation.

Private evidence: `storage/app/private/commission-staging-http.xml/txt`, `commission-staging-regression.xml/txt`, `commission-staging-pages.xml/txt`, `commission-staging-mysql.txt`, `commission-staging-browser.txt` and `commission-staging-state.json`. Synthetic screenshots and browser details are under `storage/app/private/commission-ui`.
