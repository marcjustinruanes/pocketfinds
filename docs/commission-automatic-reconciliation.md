# Automatic commission reconciliation

Later 8 November update: [database-backed enrollment controls](commission-enrollment-toggle.md) now supply effective enrollment status and activation time. They remain disabled; the scheduler continues independently.

8 November 2026 (PHT). Commission enrollment remains disabled and the activation timestamp remains null. Existing obligations continue to reconcile; disabling enrollment never creates historical commissions.

`commissions:reconcile` services existing COD and GCash snapshots at the fixed 10% product commission. Delivery plus buyer confirmation, or exactly 168 elapsed hours from recorded delivery, makes an otherwise eligible obligation earned. Unresolved complaints and active returns hold eligibility. Rejected returns retain their 72-hour appeal hold. GCash also requires verified buyer payment and a payment reference. Cancellation and recorded product refunds reverse the frozen line amounts idempotently. Earning does not collect money: Seller payments and Logistics remittances remain manual, and only Platform Admin actual-receipt verification collects commission.

The existing Windows task `PocketFinds-Commission-Reconcile` was changed from Interactive to S4U under the existing user with limited privileges. Its daily trigger repeats every five minutes, starts missed work when available, permits battery operation, retries failures and ignores overlapping task instances. S4U supports execution without an interactive login and stores no password; it is restricted to local resources ([Microsoft documentation](https://learn.microsoft.com/en-us/windows/win32/taskschd/security-contexts-for-running-tasks)). The configured MySQL connection is local and `MySQL80` is already an automatic Windows service. No database service configuration was changed.

The task invokes only the commission runner, not the full Laravel scheduler. It does not depend on a browser, Laravel web server, queue worker or Cloudflare Tunnel. The computer must be powered on and awake, with local files and MySQL available. Actual Windows logout was not performed because it would interrupt the user's session; the noninteractive principal and background task execution are checked instead.

Automatic task runs at 00:25, 00:30 and 00:35 PHT on 8 November completed successfully. Both the 00:30 and 00:35 runner logs record Windows Session 0 and exit code 0; Task Scheduler independently reports success and the next five-minute trigger. These live runs used the empty-ledger read-only guard and processed no real obligations. Financial transitions were exercised only with synthetic transactions.

Changes include accurate transition counting after concurrent sweeps or transaction retries, retained Windows child-process handles for reliable exit codes, validation of structured child results, and private task logs with Windows session IDs. Registration explicitly backs up an existing commission task definition before replacement and refuses an unexpected action. The disabled verification task remains unchanged.

The regression also exposed an inherited legacy voucher test that expected a gross Seller payout while its child class enabled commissions. That baseline now explicitly retains disabled enrollment; separate enrolled tests cover commission deductions and funded platform subsidies. Production net-payout validation was preserved.

Reinstallation from an elevated Windows PowerShell prompt:

```powershell
.\scripts\windows\register-commission-verification.ps1 -Register -UpdateExisting -Mode CommissionOnly
```

Use this only for the existing local destination; the PHP runner refuses an unexpected database. This command does not enable commission enrollment. Original task XML backups and execution logs are retained in `storage/app/private/commission-scheduler`.

Final validation: **485 tests / 5,780 assertions passed**, including inherited/repeated workflows; **11 isolated MySQL groups passed**. Five focused boundary tests separately passed 66 assertions. PHP syntax, PowerShell parsing and changed tracked-file whitespace checks passed. The final 130-table preservation comparison matched every table except live session activity; `.env` matched its original hash, enrollment remained false, activation remained null and the configured ledger remained empty. All eight complete SQL backups matched their existing verified checksums.

Validation evidence is private: `commission-automatic-boundaries.xml/txt`, `commission-automatic-final.xml/txt`, `commission-automatic-buyer.xml/txt`, `commission-automatic-mysql.txt`, `commission-automatic-task.json`, `commission-automatic-backups.json`, and `commission-automatic-{before,after}.json`. The earlier `commission-automatic-regression.xml/txt` retains the initial legacy-fixture failure; the final report supersedes it. Synthetic HTTP tests use in-memory SQLite; MySQL concurrency tests copy schema into a uniquely named scratch database, use synthetic data only and remove that database afterward. No real financial record, `.env`, hosting configuration, tunnel or existing backup was changed. Runtime session activity is excluded from the financial preservation claim.
