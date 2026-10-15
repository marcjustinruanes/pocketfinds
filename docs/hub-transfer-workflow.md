    # Hub transfer workflow — October 14, 2026

    This update supersedes earlier transfer behavior described in the logistics reports. It preserves the six account roles, existing Laravel/MySQL architecture, and parcel route legs. Authorization uses existing middleware and scoped queries; there is no Laravel authorization-policy directory.

    ## Permissions and transitions

    | Batch state | Permitted operation | Responsible account |
    |---|---|---|
    | Pending approval (`pending`) | Approve or reject with a required reason | Company Logistics Admin |
    | Pending approval | Cancel with a required reason | Origin Hub Staff or company Logistics Admin |
    | Approved | Dispatch with eligible transport | Company Logistics Admin |
    | Approved | Cancel with a required reason | Company Logistics Admin |
    | Dispatched (`in_transit`) | Scan individual parcels or explicitly confirm all remaining parcels | Destination Hub Staff or company Logistics Admin |
    | Received (`completed`) | View history; record a discrepancy | Destination Hub Staff or company Logistics Admin |
    | Rejected / cancelled | View retained history; create a new eligible request | Scoped logistics accounts |

    Approval does not dispatch. Rejected/cancelled batches cannot dispatch; undispatched batches cannot receive. Staff can request only from their assigned operational hub and cancel only pending outgoing requests. Staff cannot approve, reject, or dispatch through either new or legacy routes. No existing explicit central-operator dispatch permission was found, so only Logistics Admin can dispatch. Platform Admin remains separate.

    The existing rider pickup-confirmation requirement remains. Partial receipt records each parcel separately; a batch becomes received only when all members have arrived. Each hop requires its own request and approval. Intermediate receipt retains the existing `hub_transfer` parcel stage; final receipt sets `sorted`.

    ## Interface and integrity

    `/logistics/transfers` provides company/hub-scoped search, status/date/hub filters, staff incoming/outgoing filters, stable pagination, and details. Details show endpoints, parcel membership, requester/approval/dispatch/receipt metadata, transport, reasons, and paginated history. Staff controls omit approval/rejection/dispatch. Receipt supports tracking entry through the existing scanner and explicit whole-batch confirmation. Discrepancies are reasoned events, optionally associated with a member parcel; they do not silently change parcel status.

    Inside transactions, the workflow rechecks fresh account permissions, operational hubs, ownership, distinct endpoints, route sequence, parcel eligibility, membership, transport, and conflicting assignments. Relevant account/batch/hub/parcel/leg/member/rider/vehicle rows are locked. Unique route sequence and batch membership constraints protect duplicates. Busy company vehicles are excluded from rider availability without changing their maintenance availability flag.

    Transfer history, assignment/parcel/order changes, notifications, and existing activity records participate in database transactions. Tests verify event/activity insertion failure rolls changes back. Supplied tracking numbers, hubs, parcels, and transport IDs are verified against scoped records. Staff details require origin/destination hub ID membership; route matching uses municipality and province.

    ## Migration and local data

    New migration: `2026_10_14_000001_add_hub_transfer_batches.php`. It adds batch, member-history, and event tables plus the current batch link on shipment legs. It preflights duplicate route sequences and reuses an equivalent existing unique index.

    Legacy requested/approved/in-transit/completed legs are imported individually because old data does not establish historical batch grouping. Original leg values remain intact. Legacy approval is accepted only with a recorded same-company Logistics Admin approver; unverifiable approvals become pending at batch level. Missing historical actors/events/grouping/transport are not invented. Missing or ambiguous hubs require reconciliation before operational use.

    The migration was rehearsed in a disposable MySQL database using all **53 current local table definitions** and isolated fixtures. It was then applied locally without `--force`, after creating this private SQL backup:

    `storage/app/private/transfer-before-20261013-163204-8fce7f23.sql`

    The backup filename uses UTC. The apply helper snapshotted 532 rows across the 53 original tables, including the migration repository, and verified unchanged original business values across the other 52 tables. The normal migration record and additive transfer schema/data changed. Local import produced one completed legacy batch with no missing hub links. No live reset or rollback was performed.

    The migration intentionally refuses `down()` to preserve history. MySQL DDL is not transactional: deployment requires verified backups, target-schema rehearsal, and forward recovery for interrupted installation. The local apply helper refuses repeat installation; do not rerun it against this migrated database.

    ## Actual checks

    | Command/check | Actual result |
    |---|---|
    | `php artisan test` | Final exit 0: **139 passed, 1,719 assertions**, 16,957 ms; isolated SQLite fixtures. |
    | Targeted transfer workflow/migration suite | **18 passed, 224 assertions**: separate decisions/dispatch, partial/final receipt, rejection/cancellation, invalid/repeated transitions, IDOR, six roles, transport conflicts, rollback, uniqueness, pagination, multi-hop, and legacy migration. |
    | `php database/verify_hub_transfers.php` | Disposable baseline MySQL verification passed; database removed. |
    | `php database/verify_hub_transfers.php --live-schema` | Before installation: 53 local table definitions cloned; original fixture columns preserved. Two-process dispatch and receipt checks observed actual MySQL lock waits, one commit and one 422 rejection each. Full workflow and 14 role/page checks passed; disposable database removed. This option refuses an already installed transfer schema. |
    | `php database/apply_hub_transfers_local.php` | Exit 0: backup, additive migration, and original-business-value comparison passed. |
    | `php database/verify_logistics.php` | Exit 0: **52 page checks, zero failures**, all six roles; session/database writes rolled back. Representative pages, not every workflow. |
    | `php vendor/bin/pint --dirty` | Formatted changed PHP sources. |
    | `php vendor/bin/pint --dirty --test` | Final check passed. |
    | `git diff --check` | Passed; CRLF/LF notices are normalization warnings. |
    | `php artisan route:list --path=logistics/transfers` | Exit 0: ten new routes listed. |
    | `php artisan view:cache` | Exit 0: templates compiled. |
    | `php artisan migrate:status` | Exit 0: all ten migrations Ran; transfer migration batch 96. |
    | `npm run build` | Exit 0 outside sandbox; existing optional fontaine warning remains. No dependencies changed in this task. |
    | Rendered scanner JavaScript `node --check` | Passed using a rolled-back local page session; no hardware test. |

    Early checks exposed a legacy test fixture without a recorded approver and receipt validation incorrectly requiring whole-batch confirmation alongside a tracking scan. Both were corrected before successful reruns. Disposable verification setup needed corrections for the baseline count, Laravel request type, and schema-listing scope; those failed attempts are not reported as passes.

    ## Changed files in this task

    - New `HubTransferController`, `HubTransferWorkflow`, `HubTransferBatch`, `HubTransferMember`, and `HubTransferEvent`.
    - New transfer migration, local backup/application helper, and disposable MySQL verifier.
    - Existing `LogisticsController`, `RiderController`, `CompanyVehicle`, `Shipment`, `ShipmentHubLeg`, and `routes/web.php`.
    - New `transfers`, `transfer-create`, and `transfer-show` views; existing sidebar, hub dashboard, hub requests, assignment, and scanner views.
    - New `HubTransferWorkflowTest` and `HubTransferMigrationTest`; existing `LogisticsAuthorizationTest`, workflow schema, and local page verifier.
    - This report and a supersession note in the original implementation report. Earlier unrelated dirty workspace changes were preserved.

    ## Remaining checks and limits

    - Browser/responsive behavior, camera permissions, physical camera/USB scanning, and real SMTP require manual checks. Tests sent no real mail or payments.
    - MySQL contention verification covers duplicate dispatch/receipt, not every concurrent account/hub/vehicle modification or production deadlock scenario. Broader concurrency testing remains advisable.
    - Large batches still require atomic per-member processing. Pagination bounds displayed records, not whole-batch transaction work; representative load testing remains necessary.
    - Production legacy records may contain missing actors or hub ambiguities absent locally. Historical batch grouping cannot be reconstructed reliably.
    - Existing media/privacy/authentication findings in `logistics-follow-up.md` remain outside this task. No deployment was performed.
