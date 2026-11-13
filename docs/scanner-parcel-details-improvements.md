# Hub Scanner parcel details and usability

12 November 2026. The standalone Android parcel card now presents the existing order number, transfer progress when present, and the authorized custody service's handling status, hub/rider responsibility and next-step guidance. Existing status, route and receipt confirmation remain in place.

The API supplies the custody description already returned by the scoped website parcel lookup. It continues to omit buyer contact and full street address from the mobile parcel payload. Missing or inconsistent evidence is displayed using the existing custody service's review message; the UI does not infer a physical handover from shipment status.

On the first physical review, the user found “Custody” confusing. The labels were changed to **Parcel handling**, **Hub or rider** and **Next step**. The user scanned the synthetic parcel again on the physical Infinix X695 and confirmed the labels were clearer. The app displayed `Parcel handling: Awaiting Final Delivery`, `Hub or rider: Pila, Laguna` and the existing next action. The synthetic database still showed `sorted`, two status-history rows and one receipt activity after the additional scans.

## Modified files

- [MobileScannerController.php](../app/Http/Controllers/MobileScannerController.php): add existing scoped custody description to mobile lookup output. The previously approved concurrency fix in this file remains intact.
- [scanner_ui.dart](../mobile/pocketfinds_scanner/lib/scanner_ui.dart): display optional order number, transfer progress and handling guidance, with safe missing-data behavior.
- [MobileScannerTest.php](../tests/Feature/MobileScannerTest.php): assert scoped lookup returns order/custody and continues omitting contact/address.
- [ui_test.dart](../mobile/pocketfinds_scanner/test/ui_test.dart): assert new information and preserve the pending-action guard.

No migration or financial, eligibility, custody or role policy was changed. The existing production-configured phone app was not overwritten: the physical check used the separate `com.pocketfinds.scannerqa` debug package and an explicit loopback-only API with synthetic SQLite data. Its owned test server and adb reverse tunnel were stopped afterward; the separate test app remains installed and stopped.

## Verification

| Check | Result |
|---|---|
| `MobileScannerTest` | 9 PASS, 136 assertions |
| `PhoneScannerTest`, `ParcelCustodyTest`, `HubTransferWorkflowTest` combined | 61 PASS, 832 assertions |
| Flutter complete test suite | 24 PASS |
| Flutter UI suite after wording adjustment | 7 PASS |
| Flutter analyzer | No issues found before the final text-only label adjustment |
| Workstation JavaScript scanner tests | 5 PASS |
| Physical phone scan of revised card | PASS; user confirmed wording is clearer |
| Physical phone transfer-progress display | NOT TESTED; widget test covers a synthetic transfer value |
| Physical USB scanner / workstation camera | NOT TESTED; equipment unavailable |
| Dedicated HTTPS staging / production | NOT TESTED; no deployment or production-data access |

Flutter reported the existing `path_provider_windows` default-plugin warning. A first APK build command was issued from the repository root and failed because no Flutter `pubspec.yaml` exists there; rerunning from the private Flutter copy succeeded. This was a command-location error, not an application failure. The first on-device label review was confusing; it was revised and confirmed clearer by the user.

The API/Flutter tests use isolated fixtures. Prior [physical device](scanner-physical-device-verification.md) and [MySQL concurrency](scanner-mysql-idempotency-fix.md) reports cover the existing workflows and their limits; they were not rerun as a full-system suite for this presentation change. The earlier frozen release candidate does not contain these changes and has not been replaced. Hosted acceptance and the remaining hardware checks still apply.
