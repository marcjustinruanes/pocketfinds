# Native Rider App review

The native rider screens use the existing pink brand accent, bundled Inter typography, compact app bars, flat divided sections, and consistent 48 px actions. Website layouts are not part of this change.

## Data and workflows

| Section | Existing data source |
| --- | --- |
| Home | Assigned shipments, delivery offers, seller pickups, hub transfers, chat unread counts, shared daily compensation |
| Tasks | Rider-scoped shipments and delivery assignments; existing claim/decline endpoints |
| Delivery details | Verified delivery details, recipient contact, events and attempts; existing arrival, parcel scan, start, completion and failure actions |
| Earnings | Shared logistics compensation, payout stages and COD custody; historical app records remain separately accessible |
| History | Final-delivery outcomes, previous failed attempts, and the existing pickup/delivery activity log |
| My Account | Authenticated profile, assigned logistics hub, completed jobs, vehicle documents, existing profile/address/settings actions |

The profile RPC now returns the rider's actual hub municipality, province and company. Completed-job totals exclude failed and unfinished deliveries. Document links resolve existing separate OR and CR records through the existing authenticated media route.

Offer deadlines, assignment permissions, delivery verification, COD amounts, cash confirmation, compensation policies, payout approvals and buyer receipt confirmation retain their existing backend rules. No schema migration or new workflow is required.

## Reliability

- Visited tabs retain state; unopened tabs load on demand.
- Visible pages refresh after actions, on resume, and through the existing polling mechanism.
- Refreshes retain loaded content while waiting. Errors have retry actions; short and empty lists support pull-to-refresh.
- Assignment actions prevent duplicate taps. A missing decline offer produces a recoverable error instead of silently succeeding.
- Delivery details keep the next allowed action accessible at the bottom. Completed parcels display COD collected, rather than asking for another collection.
- Proof photo drafts remain scoped to the signed-in rider and shipment. The mobile completion flow does not ask for a six-digit buyer code.

## Verification

From the mobile project:

```powershell
flutter test --no-pub test/widgets/rider_redesign_test.dart test/widgets/rider_workflow_test.dart test/widgets/rider_requirements_test.dart test/widgets/rider_web_module_test.dart test/widgets/mobile_layout_consistency_test.dart test/widgets/rider_inbox_layout_test.dart test/widgets/delivery_refresh_test.dart
php api/tests/rider-website-compatibility.php
php api/tests/rider-web-module.php
php api/tests/rider-profile-documents.php
php scripts/audit-rider-data.php
```

Widget checks cover all six sections at 320, 400 and 768 px, including large text; network error/retry; tab state retention; confirmation actions; and secure draft recovery. API integration fixtures roll back without sending real deliveries or changing account balances.

Optional visual review uses test fixtures exclusively and writes previews to the ignored `.tmp/rider-previews` directory:

```powershell
flutter test --no-pub --dart-define=RIDER_VISUAL_REVIEW=true test/widgets/rider_visual_review_test.dart
```

The current Android build uses the established local API at `http://192.168.254.103:8081`. The phone must reach this server on the same network. Physical-device installation and a live camera/handover check require reconnecting the phone; no real delivery is submitted by the regression checks.
