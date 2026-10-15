# Scanner automatic address — October 14, 2026

The Android app now obtains its address from one build configuration and opens the login form without server-address setup. The verified default is **`https://pocketfindsph.shop/api/v1/mobile`**.

## Live verification

Initial HTTPS requests with normal certificate validation returned **HTTP 530 / Cloudflare error 1033** from the public `/me`, empty `/login`, and `/up`. Cloudflare [documents 1033](https://developers.cloudflare.com/support/troubleshooting/http-status-codes/cloudflare-1xxx-errors/error-1033/) as the absence of a healthy tunnel connector. The address was not enabled while these checks failed.

No `cloudflared` process, Windows service, or existing certificate/config was initially found on this laptop. The user identified the connector as running on another machine, then authorized running it here. Cloudflare's official Windows connector 2026.10.0 was downloaded into ignored private workspace storage. Browser authorization succeeded; the CLI certificate callback timed out, but a subsequent direct IPv4 request retrieved the certificate successfully. The existing tunnel is `pocketfinds-local`, UUID `865179be-6997-478f-9240-b14ac2418739`, managed through Cloudflare. Its existing route was privately snapshotted before editing. No token or certificate contents were printed or put in app source.

Automatic approval review initially rejected changing the hostname route and exposing a local origin because of the earlier no-deployment constraint. The user was told the exact action and explicitly approved it. After that approval, only the existing `pocketfindsph.shop` origin was changed from `http://127.0.0.1:8000` to `http://127.0.0.1:8002`; the fallback and other configuration were preserved. A separate hidden Laravel origin was started with process-only `APP_DEBUG=false` and `APP_URL=https://pocketfindsph.shop`. `.env` and the other local listeners were left unchanged. A JSON 404 probe confirmed that exception/trace debug details were absent on the new origin.

Final public checks succeeded with normal HTTPS validation: `/api/v1/mobile/me` returned **401** with Laravel's unauthenticated JSON; empty POST `/api/v1/mobile/login` returned **422** with email/password validation errors; `/up` returned **200**. The local connector's `/ready` returned **200 / four ready connections**. These prove public routing, tunnel connectivity and unauthenticated API entry points, not a real-user login, camera, or parcel operation. No account credentials or business mutation payloads were submitted by these probes.

## App behavior

- `scanner_config.dart` is the single address source, using `String.fromEnvironment('API_BASE_URL')` with the verified HTTPS default. Missing/invalid configuration gives an error on the login form rather than a setup screen or another-server fallback.
- A developer can use `--dart-define=API_BASE_URL=http://192.168.1.47:8001/api/v1/mobile` in a debug build. HTTP is rejected in release configuration; no local IP is the production default. There is no runtime address-editing screen.
- Saved secure-storage credentials are considered only when their recorded server matches the configured server. Expired sessions go to login. Valid sessions are checked against `/me`; revoked/unauthorized credentials are removed, with offline barcode data retained. A known unexpired session can retain offline access when the service is unreachable. Legacy sessions lacking an expiry must be validated online before restoration.
- Login now records the server-issued expiry alongside the token in secure storage. Passwords remain in memory only and are cleared on successful login. No preferences, logs, or server-address fallback store credentials.
- A clear connection message and Retry button are shown for service/network failures. Retry checks the same configured address; it does not submit the password automatically. Existing authenticated sync/queue and pairing paths remain in use.
- No HTTPS bypass, production cleartext permission, database schema change, recruitment change, or parcel workflow change was made.

## Changed files

Created:

- `mobile/pocketfinds_scanner/lib/scanner_config.dart`
- `mobile/pocketfinds_scanner/lib/session_startup.dart`
- `mobile/pocketfinds_scanner/test/config_startup_test.dart`
- `docs/scanner-automatic-address.md`

Modified:

- `mobile/pocketfinds_scanner/lib/main.dart`
- `mobile/pocketfinds_scanner/lib/api.dart`
- `mobile/pocketfinds_scanner/lib/scanner_ui.dart`
- `mobile/pocketfinds_scanner/test/ui_test.dart`
- `mobile/pocketfinds_scanner/README.md`
- `docs/mobile-scanner.md` — updated setup instructions for automatic addressing.

Private connector/download/probe artifacts remain ignored. No `.env`, Laravel source, dependency manifest, or Android release network policy was changed by this task.

## Actual checks so far

`flutter test` passed **23 tests**; `flutter analyze` reported **No issues found**. Coverage includes empty/valid/invalid configuration, explicit debug HTTP versus release HTTPS, no-session/expired/revoked startup, server-mismatch token isolation, known-expiry offline restoration, legacy-session failure, same-server retry, and removal of address fields. Existing camera, queue, and mode tests remain passing. Four missing-brace lint findings in the first analysis were corrected before the clean run.

An explicitly configured local debug APK built successfully during preparation. Final `flutter test` passed **23 tests**; final `flutter analyze` reported **No issues found**; `flutter build apk --debug` succeeded with the verified public HTTPS default and no `API_BASE_URL` override. `git diff --check` passed with existing line-ending notices. Final source inspection found one `API_BASE_URL` read, no server-address field, no certificate bypass, and cleartext permission confined to the existing debug manifest.

The updated APK was installed successfully on the connected Infinix X695 using `adb install -r` and launched. Installation preserves application storage; a saved local-server session will return to login under the new configured public-server namespace, while its offline records remain stored under their original namespace. No real-user login, physical-camera or paired parcel mutation test is claimed.

## Running the domain

The origin and connector are running as hidden processes (origin launcher PID 20992, connector PID 8176 at initial launch), not installed Windows startup services. The laptop must remain powered on, awake and connected to the network. After reboot these processes must be started again. Credentials, certificate, remote-route snapshot, and runtime logs are under ignored `storage/app/private/cloudflare`; do not commit or share them.

For manual restart from the workspace, use a dedicated terminal with process-local environment overrides:

```powershell
$env:APP_DEBUG = 'false'
$env:APP_URL = 'https://pocketfindsph.shop'
php artisan serve --host=127.0.0.1 --port=8002
```

In another dedicated terminal, clear stale proxy environment variables for that process if needed and run the existing connector with its private credential file:

```powershell
Remove-Item Env:HTTPS_PROXY,Env:HTTP_PROXY,Env:ALL_PROXY -ErrorAction SilentlyContinue
$env:NO_PROXY = '*'
& './storage/app/private/cloudflare/cloudflared.exe' tunnel --credentials-file './storage/app/private/cloudflare/tunnel-credentials.json' --edge-ip-version 4 --no-autoupdate --metrics 127.0.0.1:20245 --loglevel warn run 865179be-6997-478f-9240-b14ac2418739
```

Do not start duplicate listeners/connectors while the current processes are running. No autostart service, DNS replacement, new tunnel, release signing, or production-readiness certification was added. The existing dependency and authentication limitations in the earlier implementation report remain applicable.
