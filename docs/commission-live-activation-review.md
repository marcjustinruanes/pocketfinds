# Live commission activation review

Later 8 November implementation: [database-backed enrollment controls](commission-enrollment-toggle.md) are installed and remain disabled. The proposed time below is still not approved or configured; the operational readiness blockers remain open.

8 November 2026, 00:42 PHT. **NOT READY TO ACTIVATE.** Commission remains disabled with a null activation timestamp. This review changed no receiving account, financial record, historical order, environment setting, hosting configuration or tunnel.

| Requirement | Result |
| --- | --- |
| Fixed 10% calculation and prospective enrollment | PASS: existing implementation and recent synthetic validation reviewed |
| Commission migrations 000001–000004 | PASS: installed in batches 110–113 |
| Automatic commission-only reconciliation | PASS: S4U, five-minute interval, last automatic run 00:40 PHT, result 0 |
| Eligibility, 168-hour timeout, disputes, cancellation, refunds and duplicate protection | PASS in recent synthetic tests: 485 executions / 5,780 assertions, plus 11 isolated MySQL groups; not rerun because code has not changed since that validation |
| Manual Seller/Logistics transfers and Platform Admin receipt verification | PASS in existing implementation and synthetic validation |
| Active Admin GCash destination | PASS for configuration: encrypted, valid-format number ending 3123, settings revision 3; creation and activation audit actors are currently approved Platform Admins |
| Official GCash ownership | UNVERIFIED by this review: stored destination and Admin activation history cannot independently establish external ownership or control of the GCash account |
| Production environment | PASS: production, debug false, Asia/Manila; configuration is not cached |
| Database least privilege | FAIL: application still uses root with global, account-administration and grant privileges |
| Filesystem least privilege | FAIL: Users has Modify access to `.env` and private storage; cache additionally grants Everyone FullControl |
| Existing local recovery backups | PASS within current scope: recent SQL restore evidence retained; all eight complete SQL checksums previously matched; encrypted private archive decrypts, has 288 entries and a matching application key |
| Independent/off-host recovery | UNVERIFIED: private archive uses CurrentUser DPAPI; no portable off-host database/upload/key recovery is established |
| Operational failure alerts and hosted authenticated acceptance | UNVERIFIED: existing synthetic HTTP checks are not real hosted role-session acceptance; no operator alert-delivery evidence was supplied |
| Preservation | PASS: all 130 tables compared with the existing baseline; only live sessions differ, `.env` hash unchanged, zero commission obligations |

Before activation, independently confirm that destination ending 3123 is the intended official account and that Platform Admin controls it. Establish the approved runtime identities and least-privilege database/filesystem permissions through a controlled change. Provide an approved off-host backup destination and independently test recovery of SQL, linked private uploads and the application key. Complete hosted authenticated acceptance and operator failure-alert acceptance. These requirements remain open; this review does not certify activation as safe.

No account, credential, ACL or backup-location changes were made. The task was an activation review, and the existing permission-hardening plan also requires approved identities/scope. Existing backups and all prior validation evidence were preserved. The full legacy Laravel scheduler remains outside the commission-only task and was not started.

Tentative planning time only: **9 November 2026 at 09:00 PHT**, `2026-11-09T09:00:00+08:00`. This is not an approved or configured cutoff. Use it only if every blocker is resolved and the user explicitly approves before that time; otherwise choose a new future cutoff. Never backdate activation or enroll historical orders. At an approved activation, verify effective configuration in both web and CLI processes; retain manual receipt verification.

Private evidence: `storage/app/private/commission-live-readiness.json`, refreshed `commission-activation-audit.json` and `commission-permission-check.json`, the private-file backup verification output, `commission-automatic-after.json`, the recent `commission-automatic-final.xml/txt` and MySQL results, and `commission-scheduler/runs.jsonl`. Full account details, credentials and encryption keys are not included in this report.
