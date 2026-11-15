# Rider requirements implementation — November 4, 2026

This update implements rider functions in Flutter and the app-local PHP API.
Website source was not edited. Existing branding, navigation tabs and visual
theme are retained; functional forms and controls were added where required.

## Account flow

Public rider application is blocked at `MobileRegistration::register` and the
application button has been removed from activation. Logistics-created native
invitations remain the supported route: Pending Activation → set password →
Active/approved. Native one-use 24-hour invitation validation and password
confirmation are retained. Buyer minimum age stays 16; manual GCash is unchanged.
Old internal ERP application code is retained for compatibility but is not a
public rider registration API. Its regression checks are explicitly legacy checks.

Activation links prefill the app form, including app launches from a closed state.
The existing website owns invitation creation/resending and the no-app web page.
HTTPS association still needs the domain owner's assetlinks/AASA configuration.
The custom scheme `pocketfinds://rider/activate/{user}/{token}` is handled locally.

## Implemented rider functions

| Previously missing/incomplete | Implementation |
| --- | --- |
| Vehicle edits and re-verification | Profile opens Vehicle and documents; private JPG/PNG/PDF uploads, camera/gallery/Files, expiry picker, identifier reservation and owned pending review. Logistics approval updates approved vehicle details; old approved details remain valid until approval. |
| Document viewing | Authenticated original license/OR/CR viewing and approved replacements; license expiry is shown. |
| Online/Offline | Explicit control, GPS permission/settings recovery, current coordinates, foreground heartbeat and manual Offline that preserves existing tasks. |
| Offline timeout | Backend rejects new offers after 290 seconds without heartbeat; ten-second worker updates the shared Online flag within five minutes. Logout also clears presence. |
| Timed offers | Native eligible parcels get 60-second server offers and elapsed-time countdowns. Acceptance checks expiry and assignee under locks. |
| Decline and dispatch | Declines persist, next eligible rider is selected, and three declines/timeouts leave the parcel unassigned. Distance selection uses available job/seller coordinates or a configured hub dispatch point. Coordinates must be configured, never invented. |
| Job cap and manual assignment | Three active jobs maximum across pickup/delivery; app staff endpoint supports assignment/reassignment before verified pickup. Disabled riders' unpicked assignments are released; carried parcels remain assigned. |
| Notifications | Rider notification list, task/chat/profile/earnings routing, high-priority offer push with sound and expiration TTL. Firebase configuration remains required for actual push. |
| Delivery evidence | Live camera capture challenge, private immutable photo upload, GPS, exact COD amount, OTP and timestamp; no gallery choice in the handover form. API cannot independently attest the physical source of a modified client's image. |
| Buyer OTP and photo | Owned buyer order actions show the current encrypted-at-rest code while Out for Delivery and the proof photo/time after delivery. Other users/riders cannot retrieve the buyer code. Email OTP remains available as well. |
| OTP lock | Five wrong attempts lock verification and resend for 15 minutes. Expired lock permits another attempt; code expiry remains enforced. |
| Interrupted delivery | Secure per-account draft stores photo reference, code, COD, coordinates and immutable request ID; app-private photo survives reopening. Unknown network outcomes retry the same delivery, and validation rejection permits correction. No automatic offline delivery success is shown. |
| Failed delivery | Refusal and third failure initiate Return in Transit automatically. Rescheduling/attempts retain history; only authorized physical receipt operations can finalize Returned. |
| Pay and history | Unique pickup/delivery ledger keys prevent double credits: ₱30 completed pickup, ₱50 verified delivery, ₱0 failed attempt. Combined history is available from History. Earnings totals and COD use the backend ledger. |
| COD remittance | Full collected COD remains outstanding while submission is Pending. Only company logistics/admin can confirm actual receipt; partial confirmation leaves the shortfall outstanding. Repeated submission/confirmation cannot double-debit. Staff overview provides reconciliation balances and submitted references. |
| Camera recovery | Scanner has Settings and manual tracking fallback; flashlight retained. Vehicle and delivery forms offer permission recovery. |

Pickup retains the existing **two-sided seller handover** rule: rider scan plus
seller confirmation. Pickup credit is issued after native physical handover is
complete. Workbook cases assuming rider scan alone completes handover need this
existing ERP rule recorded as an agreed difference. Likewise, vehicle edits
await re-verification rather than immediately replacing approved working details.

## Setup

```powershell
php api/install-rider-requirements.php
powershell -File scripts/start-rider-worker.ps1
```

SQL: `sql/mysql/2026_11_04_rider_requirements.sql`. Tables are additive and use
native users/orders/shipments. `api/rider-worker.php` without `--run` only prints
usage. The worker maintains presence, offers and completed pickup credits.
`-EnablePush` enables dispatch only after Firebase is configured. No real push,
mail, COD payment or payout is performed by automated tests.

Native Firebase client configuration and server `FIREBASE_SERVICE_ACCOUNT` are
still required. In-app notifications work independently. The private service
account JSON belongs on the server and must not be bundled into Flutter.

## Website-team integration — endpoints available, source unchanged

All routes are JSON POST requests with the existing mobile bearer authorization.

| Endpoint | Required input / purpose |
| --- | --- |
| `rider/staff-overview` | Company riders/presence, pending vehicle requests, remittances, outstanding COD and unassigned native parcels |
| `rider/dispatch-point` | `hub_id`, `latitude`, `longitude`; company-scoped hub dispatch coordinates |
| `rider/assign` | Shipment `id`, `rider_id`, `leg` (`pickup`/`delivery`), reason 10–300 characters |
| `rider/vehicle-decision` | Request `id`, boolean `approve`, reason 10–300 characters |
| `rider/confirm-remittance` | Remittance `id`; company-scoped confirmation, rider access denied |

Staff actions are audited with actor/time/old/new values. Existing website
assignment and approval buttons do not automatically call these endpoints;
groupmates must connect them to enforce the same new limits across interfaces.
Configured coordinates are needed for proximity dispatch; without them, manual
assignment remains available. Native access rules still limit eligible riders
to the installed website's supported vehicle/company/hub restrictions.

## Validation

The rider workbooks contain 47 account cases and 53 module cases. Their example
assumptions are test expectations, not proof that every case has passed.

- `api/tests/rider-requirements.php`: 39 installed-protocol checks passed.
- `api/tests/rider.php`: original-protocol regression and concurrency checks use
  a disposable schema without `mobile_rider_*` tables; they do not substitute for
  installed-protocol coverage.
- Existing buyer/account/integration tests remain available; integration fixtures
  now supply mandatory delivery photo, GPS and exact COD evidence.
- Camera/GPS permissions, live image capture, secure storage after force-close,
  push on a locked phone, map/dialer transitions and HTTPS association still need
  actual device tests. No complete pass claim is made for the 100 workbook cases.

Flutter analysis reported no issues; all 67 Flutter tests passed, including
invitation prefill, offer expiry and account-scoped secure draft recovery.
The Android debug APK built successfully. The 39 new rider checks complement
the existing buyer/account/integration and legacy-protocol regressions. Final
suite summaries are kept in the workspace's ignored `.tmp/final-*.log` files.
