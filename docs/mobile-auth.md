# Mobile login and registration

The API reuses the adjacent Laravel website's validation helpers,
AccountVerification service, MySQL users, review queues and SMTP configuration. Private SMTP
and Google secrets stay in the website environment, never in the Flutter bundle.

Buyers enter their details, accept the website terms, and receive the website's
six-digit verification email. Codes expire after five minutes. Resending has a
60-second cooldown. An unverified account cannot obtain a shopping session;
signing in resumes verification. Approved accounts can sign in with email or
username and their existing password. Per the required ERP documents, verified
mobile buyer applications remain pending administrator approval. Rider
applications collect the complete identity/vehicle documents and remain pending
logistics-center approval. Company-created rider invitation activation is also
retained. See `erp-requirements-audit.md`.

Continue with Google supports existing accounts matched by verified email or
Google identity. For new buyers, it prefills the form; the buyer still completes
the website's required details and email verification. The API checks Google's
JWT signature, issuer, audience, expiry and verified email before trusting it.

## Android Google Cloud setup still required

In Google Cloud Console, select the project containing the website's Web OAuth
client. Under APIs & Services > Credentials, create an OAuth client ID with
application type **Android**, using:

- Package: `com.pocketfinds.pocketfinds`
- Local debug SHA-1: `B0:55:06:05:AE:4B:2A:D7:CD:48:45:C6:2D:55:A7:8E:C4:3B:1A:9C`

Keep the website Web client as the server client ID. Do not put its client secret
in the app. If the consent screen is in testing, add the Google accounts that
will test it as test users. Register the release signing certificate separately
when producing a release. Then run the Android app and try Continue with Google.
The backend token checks are tested; interactive Google consent needs this
external configuration and a device test.

iOS needs a separate iOS OAuth client for the app's bundle identifier, its client
ID in the native configuration, and the matching reversed client ID URL scheme
in `ios/Runner/Info.plist`. Replace the existing placeholder before testing iOS.

## Email and checks

Use the website's SMTP environment settings. After changing them, clear any
Laravel configuration cache in the website project and restart the API.

```powershell
php api/doctor.php --smtp
php api/tests/registration.php
php api/tests/integration.php
flutter analyze
flutter test
```

The doctor authenticates with SMTP without sending a message. Automated tests
fake mail and roll back fixture records. For an explicitly authorized recipient,
`php api/mail-test.php recipient@example.com` sends one real registration email.
SMTP acceptance confirms handoff, not arrival in the inbox. The diagnostic email
does not create or activate an account. Production API traffic must use HTTPS.
