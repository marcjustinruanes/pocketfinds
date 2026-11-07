# Commission activation checks — 7 November 2026

**READY TO ACTIVATE: NO.** The commission-only task is configured and transaction tests pass. Database/file permission hardening and portable off-host recovery remain open. Enrollment is false and the activation timestamp is null.

8 November update: [automatic reconciliation verification](commission-automatic-reconciliation.md) supersedes the Interactive-logon limitation below. The commission-only task now uses unattended S4U. Enrollment remains disabled; the other activation gates still apply.

## Completed

- Confirmed all commission migrations 000001–000004 are installed and the active receiving destination is encrypted, nonempty and valid in format. Its full number is not included here. Existing active-only Seller destination checks remain valid; no new destination was created or activated.
- Verified manual GCash and COD flows with messages, notifications, proof, role authorization and actual-receipt verification in isolated synthetic requests: **12 executions / 377 assertions passed**. This includes a continuous buyer-to-Seller GCash checkout/verification/fulfillment path followed by reconciliation, Seller-to-Platform manual payment and a conversation before Admin verification. The COD path includes actual synthetic collection/turnover, withholding/net payout, manual remittance and messages before receipt verification. Earlier broader finance and race evidence was reused.
- Verified live anonymous conversation and correction-evidence requests return **401**. Public storage points only to `storage/app/public`; financial evidence is stored outside that public target. Existing authenticated isolation tests protect evidence from other Sellers, companies, Buyers, Riders, Hub Staff and suspended accounts. This is not a claim that a real hosted Seller login was used.
- Took a fresh complete SQL backup after messaging installation, restored it to a scratch database and matched **130 tables / 1,098 rows**. Private backup: `storage/app/private/database-backups/commission-complete-20261107-153815`. Temporary MySQL client credentials and the scratch restore database were removed.
- Rechecked the encrypted private-file backup: checksum and decryption passed, all **288** entries are present, and its backed-up application key matches the current key. The original creation test restored/hash-checked all 288 entries in memory. No plaintext archive was written or restored over live uploads.
- A subsequent preservation comparison matched **129 non-session tables / 1,040 rows** against that SQL backup. Only runtime session changes were observed. No historical order, financial record, receiving-account revision or audit row was changed by the checks.

## Scheduler safety and results

The full Laravel scheduler was **not started**. Its purchase expiration, order completion and return/voucher jobs use historical deadlines whose naive timestamp provenance is still unresolved. Current application/MySQL conventions are Asia/Manila and +08:00, and no required commission column is missing. The other **28 migrations remain pending**; no historical conversion was performed.

A commission-only Windows task avoids those unrelated jobs. The temporary verification task automatically executed the real `commissions:reconcile` command at **23:35, 23:40 and 23:45 Manila time**, exit code 0 each time. All three used an empty-ledger guard and a repeatable-read, read-only transaction; zero real orders or obligations were processed. That covers two full five-minute intervals. Task Scheduler status independently confirmed the cadence and successful results.

The operational task **`PocketFinds-Commission-Reconcile`** is now enabled with the same five-minute cadence. Its automatic runs at **23:50 and 23:55 Manila time** also exited 0 with an empty ledger/read-only transaction. The temporary verification task is disabled to avoid duplicate schedules. The operational task invokes only the existing reconciliation command: it never runs `schedule:run`, enrolls historical orders, verifies receipts or sends money. When real obligations exist after an approved activation, it retains the existing command's per-obligation transactions and financial safeguards.

Supervision: ignore overlapping instances, three failure restarts one minute apart, a 60-second PHP-child limit and a two-minute Windows task limit. Logs are under `storage/app/private/commission-scheduler` (`runs.jsonl`, `failures.jsonl`, timestamped child output/error files). The first verification log used an earlier generic source label; the stored Windows task metadata establishes it was a scheduled run. The task runs as **LENOVO with an Interactive logon token**, so it requires that user session. Unattended execution while logged off, reboot recovery, sleep/wake behavior and operator failure-alert delivery are not certified.

Scripts: `scripts/commission-reconcile-verify.php`, `scripts/windows/run-commission-verification.ps1`, `scripts/windows/register-commission-verification.ps1`. The PHP helper supports explicit verification and commission-only modes, serializes runs with a lock and makes empty-ledger runs read-only. It does not edit `.env` or commission policy. Existing hosting and Cloudflare processes were left unchanged.

## Failed or unverified

- Application database configuration still uses root, with global privileges, account-administration privileges and grant authority confirmed by a read-only privilege audit. A dedicated least-privilege application account has not been installed.
- `.env`, private storage and bootstrap cache retain broad Windows Users/Everyone grants. HTTP evidence authorization passed; filesystem least privilege has not passed. The earlier recursive ACL proposal was rejected by automatic approval review pending administrator-approved scope/service identities. No broad ACL rewrite or credential switch was performed.
- The private-file archive uses Windows DPAPI CurrentUser protection. It is useful for recovery with the existing Windows user/profile; it is **not** a verified portable disaster-recovery backup. No off-host destination, retention policy or independent key/profile recovery was provided or verified.
- The commission-only task depends on an interactive Windows session. A durable unattended account/task configuration and operational alerting still need operator acceptance. Full legacy scheduler safety remains unresolved.

## Operator actions before activation

1. Approve the Windows runtime/backup identities and exact permission scope, secure the affected paths, then install a least-privilege MySQL application account through a controlled credential switch. Do not change the application encryption key.
2. Supply an approved off-host backup destination and portable encryption/recovery method; verify restoring database, linked uploads and the application key on an independent recovery environment.
3. Accept or configure the required unattended commission-only task behavior and failure alerts. Keep the full legacy scheduler stopped until its timestamp/deadline review is complete. Do not run both task owners for the same command without reviewing duplicate scheduling.
4. Freeze/review the release and provide separate activation approval with a future timestamp and explicit Asia/Manila offset only after these gates pass. No activation procedure was executed by this task.

Evidence is private: `commission-final-flow-checks.xml/txt`, `commission-final-recovery-backup.txt`, `commission-final-private-recovery.json`, `commission-permission-check.json`, `commission-scheduler-task-state.json`, `commission-scheduler-task-final.json`, `commission-scheduler-production-task.json`, `commission-scheduler-production-final.json`, `commission-scheduler-preservation.json` and the scheduler child logs.
