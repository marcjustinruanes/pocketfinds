# PocketFinds Scanner

Android scanner for approved PocketFinds Logistics Admin and Hub Staff accounts.

- Standalone Scanner: camera/manual lookup, confirmed hub receipt actions, persistent offline barcode queue.
- Pair with Computer: scan the computer's QR and send codes to its existing Scan Parcel page.
- Offline operational actions are disabled.

## Run

From this directory:

```powershell
flutter pub get
flutter run --dart-define=API_BASE_URL=http://192.168.1.47:8001/api/v1/mobile
flutter build apk --debug
flutter test
flutter analyze
```

Users no longer enter a server address at login. `lib/scanner_config.dart` reads the `API_BASE_URL` Dart environment setting once. Local HTTP is accepted only in explicit debug builds; release builds require HTTPS. Both devices must use the same reachable host/port for pairing. A phone's localhost is not the computer.

The verified default is `https://pocketfindsph.shop/api/v1/mobile`. A normal build uses it automatically. Verification returned `/me` 401, empty `/login` 422, `/up` 200, and four healthy tunnel connections. The current origin runs on the user's laptop, so that laptop and its connector must remain running for the domain to work. An explicit developer `API_BASE_URL` overrides the default without changing it. An invalid/missing address shows a clear error and Retry rather than selecting another server.

Saved sessions are restored only for the configured server. Expired/revoked sessions return to login without deleting pending scans; tokens stay in secure storage. Connection Retry checks the same configured server and does not resend a login password automatically.

APK: `build/app/outputs/flutter-apk/app-debug.apk`. Copy it to an Android phone and open it, or use `adb install -r` with authorized USB debugging.

The backend migration has been applied to the current local installation. For another installation, see the Laravel migration, backup/rehearsal, HTTPS and networking instructions in [the implementation report](../../docs/mobile-scanner.md).

No physical-device camera or pairing verification has been performed. Pending scans survive logout/restarts and are isolated by server and owner; paired sends and uncertain online actions retain retry identifiers only in memory. Refresh server state after terminating the app during an uncertain action.
