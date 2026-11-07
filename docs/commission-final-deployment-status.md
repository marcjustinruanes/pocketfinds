# Commission final deployment status

8 November update: [automatic reconciliation](commission-automatic-reconciliation.md) now documents unattended commission-only scheduling and synthetic validation. Enrollment remains disabled.

7 November 2026. **READY TO ACTIVATE: NO.** Collection remains disabled with no activation timestamp. The later checks verified the active encrypted GCash destination, installed messaging migration 000004 and configured commission-only scheduling. See [the current activation assessment](commission-activation-readiness-final.md) for verified runs and remaining permission/recovery gates. No real Seller was charged.

The sections below preserve the earlier deployment snapshot. Statements there about an absent receiving account, missing scheduler or pending file-recovery verification describe that earlier state; the linked assessment supersedes them.

## Completed

- Identified the Windows PHP origin on loopback port 8000 and the running Cloudflare connector. The origin uses the current workspace. Public HTTPS is reachable with certificate verification when checked outside the sandbox's broken proxy/TLS context. Public commission CSS matches the workspace file, and anonymous commission-workspace access returns 401.
- Reviewed and applied only `2026_11_08_000003_add_commission_account_management`, batch 112, to the verified `pocketfinds` destination. The original two commission migrations remain installed. Other pending migrations were not run. Account settings have revision 1 and a null active destination; receiving-account and account-event tables are empty.
- Took a fresh complete SQL backup, restored it to a scratch database and verified all 127 tables / 1,070 rows. The scratch database and temporary MySQL credential files were removed; backup checksum matched. Private location: `storage/app/private/database-backups/commission-complete-20261107-134242`.
- Compared original tables after migration, excluding only the added migration-history entry. Users, orders and financial tables matched. Other observed differences were runtime session activity and only `settings.updated_at`; these were preserved. No settings value was restored over current activity.
- Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `COMMISSION_ENABLED=false` and `COMMISSION_ACTIVATION_AT=null`. Verified the existing application key was unchanged. No credentials were printed or committed.
- Added `ProductionHttps` middleware and three isolated security tests (10 assertions passed). The new middleware redirects insecure application requests for the configured production hostname and supplies HSTS on secure responses. A controlled origin restart was needed because its old parent retained the local environment. Maintenance was ended and the public site returned HTTP 200 over HTTPS, HTTP 308 to HTTPS, HSTS and Secure/HttpOnly/SameSite cookies.
- Reviewed existing account-management, COD/GCash, refund, permissions, MySQL race and synthetic browser evidence without repeating the completed financial suites. Account controls were previously verified with synthetic HTTP actors. Only the new HTTPS tests were run for this deployment change.

## Still needs operator action

1. Platform Admin must independently verify the real official GCash account, add it in `/commissions` → Admin Commission Settings and explicitly activate that receiving account. This does not enable commission enrollment. No account number should be invented.
2. Choose and configure a dedicated least-privilege application database user. The current application still uses root. Choose the Windows service identities and restrict `.env`, private evidence/backups and `bootstrap/cache` to the required identities. Current ACLs include broad Users/Everyone grants; do not blindly revoke access before confirming service ownership.
3. Replace the temporary `artisan serve` origin with an approved, durable production web service, or otherwise establish supervised hosting with restart/boot recovery and monitoring. The replacement origin is hidden on the same loopback port and uses `--no-reload`; it is not a certified production service. Verify Cloudflare edge-wide HTTPS redirection for static paths as well as application paths.
4. Finish the data-owner review of legacy naive timestamps and destination migration history. Current application and MySQL session use Asia/Manila / +08:00; no historical conversion was run. Do not blindly apply the remaining migrations.
5. Configure the scheduler and queue under the approved service identities, with private logs and restart/failure alerts. No PHP queue worker, persistent scheduler or relevant Windows scheduled task was present during inspection. The full scheduler also expires purchases, completes orders and processes returns/vouchers; it was not started while legacy timestamp interpretation is unresolved. Actual production execution across two five-minute intervals is **not verified**. After that review, follow the deployment guide's every-minute scheduler and supervised queue instructions, and observe successful execution.
6. Perform authenticated acceptance on a separately isolated hosted staging deployment: Seller, Platform Admin, Logistics Admin and Hub Staff, including COD/GCash settlement, receipt verification, private evidence, refunds and residual payouts. Use synthetic records and simulated evidence. Existing Laravel HTTP tests are not interactive hosted sessions; no real role session or staging hostname was supplied for this task.
7. Finish off-host/encrypted backup retention and a restore drill for private uploads and the existing application key. The successful SQL restore is not evidence of file/key recovery. Review the complete release, including unrelated working-tree changes, before treating the served working tree as a frozen release.

## Activation remains blocked

Do not enable enrollment until the real receiving account and every operational acceptance gate above are verified. Then obtain separate business approval for a prospective timestamp with an explicit timezone offset. This task does not authorize activation. Never backdate enrollment or destructively roll back financial tables.

Private evidence: `commission-final-deployment-backup.txt`, `commission-final-deployment-check.json`, `commission-final-deployment-concurrent-differences.json`, `commission-final-environment.json`, `commission-final-routes.json`, `commission-final-https.xml/txt`, origin restart logs and backup manifests under `storage/app/private`.
