# Combined Logistics Admin and Hub Staff release review

12 November 2026. **Recommendation: proceed to controlled staging acceptance. Unrestricted production activation: BLOCKED until the release gates below are accepted.**

This is a current source-and-evidence review. No application code, configuration, financial settings, migrations or production records were changed. No new tests, browser sessions, payments or production inventory queries were executed during this consolidation. Only this report was added.

The latest relevant regression result is **1,344 executions: 1,317 PASS, 0 FAIL, 0 ERROR, 27 existing SKIPPED; 15,608 assertions**. Latest Hub Staff verification records nine passing browser scenarios, seven passing new MySQL races and four passing existing MySQL verifiers. Earlier Admin verification separately records ten passing browser scenarios. These overlap in coverage and must not be added as distinct functions or independent end-to-end journeys.

## Combined functional assessment

| Item | Classification | Assessment |
|---|---|---|
| Admin/Staff authorization | **READY** | Approved company Admin performs company decisions; approved Staff requires an operational same-company assigned hub. Admin-only routes, private proof, vehicle ownership and scoped operations have regression coverage. Read visibility does not grant mutation authority. |
| Separation between hubs | **READY** | Origin Staff requests transfers; Admin approves/rejects and dispatches; assigned rider confirms pickup; destination Staff attests actual receipt. Partial receipt does not complete unreceived members. Unrelated hubs and other companies are denied. |
| Forward parcel flow | **READY** | Supported pickup approval → eligible rider offer/acceptance → Seller/Rider handover → origin receipt → approved four-wheel transfer where needed → destination receipt/sorting → eligible delivery offer/acceptance → Rider proof/OTP delivery is implemented and supported by recorded tests. Multi-hop and direct-transfer scenarios are covered; no new complete hosted/physical rehearsal is claimed here. |
| Reverse logistics and custody guards | **READY** | Shared actual-failure recording, one-count-per-attempt, stale-token rejection and three-attempt/refusal guards work within the tested rules. Returns require authoritative physical witnesses; assignment or investigation resolution alone does not establish receipt, restore stock or release holds. |
| Ordinary rider eligibility | **READY** | Manual offers, automatic offers, acceptance, retry and replacement use shared eligibility/capacity checks as appropriate: company/hub, approval, online availability, barangay and vehicle requirements. Ordinary work retains the three combined active-job limit. Four-wheel transfer rules remain separate. |
| COD/GCash/compensation/financial integrity | **READY** | Covered exact-money, snapshot, allocation, duplicate and rollback assertions pass. Collection, actual cash count, transfer authorization, treasury receipt and settlement remain separate duties. No financial policy/rate change is required by this review. Actual operator/provider acceptance remains pending. |
| Notifications, messages and audit | **READY** | Tested recipients, authorized transfer links, read ownership, reply IDs, inert filenames, private attachments and transactional audit rollback pass. Status PATCH uses HTTP 303 for correct scanner refresh. Hosted mail and complete UI acceptance remain pending. |
| Remaining browser/device acceptance | **NEEDS MANUAL VERIFICATION** | Complete the original inventories' pending controls and the 27 existing skipped cases, using separate Admin/Origin/Destination accounts and synthetic data. Cameras, USB/native scanners, hosted HTTPS/CDNs, external geography, mail and interrupted-session behavior are not certified by local automation. |

READY is limited to the stated implementation and recorded evidence. It is not permission to bypass an affected parcel hold or an unmet deployment prerequisite.

## Remaining cross-role policy distinctions

These are source-confirmed differences requiring operating-owner acceptance, not newly reproduced security defects or instructions to redesign the system.

| Finding | Classification | Required decision |
|---|---|---|
| Initial acceptance versus possession reassignment | **NEEDS REVIEW** | Initial offers require Rider acceptance. Existing pre-collection reassignment changes the accepted assignment after Seller/destination Staff possession confirmation; it does not create a fresh Rider acceptance step. Confirm that this distinction is accepted in the release SOP. Source: `AssignmentReassignment::decide()`. |
| Structured refusal versus Staff free-text failure | **NEEDS REVIEW** | Rider failure accepts structured reasons including `refused`; logistics failure preserves a free-text reason. Shared retry exhaustion recognizes the exact structured refusal and the attempt counter, not an interpretation of prose. Confirm how Staff-established refusals enter the existing return/hold procedure. Do not treat free text as automatic disposition authority. Sources: `RiderController::markFailed()`, `LogisticsController::updateStatus()`, `RiderDeliveryOffers::retryExhausted()`. |
| Unsupported dispositions | **BLOCKED** | Existing local RTS does not authorize cross-hub reverse transport, partial/unsellable Seller return completion, or redispatch of recovered/lost/damaged parcels without existing clearance. Exclude these operations from release scope and keep affected parcels under review. No new return module is proposed. |
| Legacy custody, counters, vehicle links and timestamps | **NEEDS REVIEW** | No census or automatic repair was performed. Missing witnesses and inconsistent records need authorized evidence-based review; unsupported transitions remain blocked. Loss liability, write-offs, return fees and new compensation entitlements must not be inferred. |

## Release and security gates

| Gate | Classification | Required evidence before activation |
|---|---|---|
| Reproducible release candidate | **NEEDS REVIEW** | Current HEAD is `58aab9a79e0f0ce0993713a02150197f7415ba73`; the review observed 59 tracked changes and 35 untracked entries before adding this report. Tests describe the working tree, not HEAD alone. Freeze all approved changes into an identifiable candidate/build and ensure required new files are included. |
| Target schema and operational setup | **NEEDS REVIEW** | Verify installed schema and approved hub/company/rider/coverage/vehicle/rate/payment-account setup on the target. Disposable migrated schemas do not prove production schema equivalence. Earlier zero/missing-setup counts are historical, not current measurements. Review any pending existing migration separately with backup/rehearsal approval. |
| Hosting, authorization and private storage | **NEEDS MANUAL VERIFICATION** | Verify production environment/debug settings, HTTPS, cookies, CSRF/session behavior, durable private uploads, authenticated downloads and public-directory-only serving. SQL dumps, `.env`, repository metadata and private QA artifacts must not be web-accessible. |
| Proxy trust and service privileges | **NEEDS REVIEW** | `bootstrap/app.php` trusts all proxies: confirm origin access and forwarded headers are controlled by the intended proxy. Verify current DB/filesystem service privileges; prior root/ACL findings were not rechecked. No exploit or current misconfiguration is asserted. |
| Scheduler, queue and mail | **NEEDS REVIEW** | Verify supervised workers, persistent lock-capable cache, timezone/deadline handling and each scheduled job's side effects before blanket scheduler activation. Registered jobs include completion, GCash expiry, vouchers, commerce sweeps and commission reconciliation. Verify authoritative commission-enrollment settings and approval separately; no activation/backfill is implied. |
| Backup, restore and rollback | **NEEDS MANUAL VERIFICATION** | Rehearse restoring database, linked private files and the existing application key together in an independent environment. Demonstrate rollback, monitoring and ownership of failed jobs/held parcels/cash discrepancies. |
| Joint hosted acceptance and sign-off | **BLOCKED** | Execute and record a supported full journey with distinct Admin, Origin Staff, Destination Staff and rider test accounts, including a partial receipt, failed/retried delivery, supported return, simulated remittance and unauthorized-access attempts. Obtain QA, operations, finance and release-owner acceptance before production activation. |

Deploy the reviewed PHP, views and JavaScript together. Refresh open monitor/scanner/vehicle pages so they submit current attempt tokens and explicit availability. These fixes add no migrations. Do not use generic `composer setup` as an established-production deployment command: its current script generates an application key and runs migrations. Preserve the existing key and use the approved deployment procedure.

The November 11 [operational report](final-operational-readiness.md) is historical: its separate replacement-eligibility and broad proof-access findings were superseded by the November 12 corrections. Its unverified host, recovery, data and policy gates are not automatically closed. Use the latest correction reports and an explicit acceptance register for sign-off.

## Recommended next steps

1. Freeze the exact candidate and reconcile the current readiness register with the latest fixes and the two policy distinctions above.
2. Validate target schema/setup, private storage, proxy/service permissions, scheduler/queue/mail and recovery in isolated staging without real payments.
3. Complete the pending manual/device cases and a joint supported end-to-end rehearsal; record results and explicitly excluded operations.
4. Obtain release-owner sign-off, deploy the complete artifact with rollback ready, refresh stale pages, then perform a controlled operational rollout.

Evidence: [Admin corrections](logistics-admin-six-defect-fixes.md), [Hub Staff corrections](hub-staff-six-defect-fixes.md), [latest per-suite results](../storage/app/private/hub-staff-six-fixes/final-results.json), [latest browser results](../storage/app/private/hub-staff-six-fixes/browser-results.json), and the audit registers linked from those reports. No remaining confirmed defect in the twelve corrected findings was established by this combined review; the distinctions and release gates above still require acceptance.
