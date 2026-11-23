# Buyer and rider registration

Registration from the guest sign-in dialog and the login page opens the same Buyer / Rider account picker. Rider opens a public application; invitation activation is a separate link inside the rider form.

The mobile API calls the pinned website RegisterController::store for both roles. Its native validation, HubHiring registration, duplicate identity checks, private MediaLifecycle uploads, application history, pending status, and review queues are shared with the website. No alternate registration table or database is introduced. Legacy registration verification remains available for already-created unverified accounts; new applications use the website controller.

## Buyer

ID and selfie -> Personal -> Contact -> Address -> Account.

Fields match the website: ID type and evidence, selfie, given and last names, middle name or no-middle-name declaration, suffix, sex, date of birth and calculated age, Gmail address verified with an email code or Google, Philippine mobile number, PSGC region/province/municipality/barangay, house/unit, street/building, optional postal code, username availability, confirmed password, and published buyer terms.

Mobile buyers must be at least 16; riders must be at least 18. The pinned mobile controller and runtime update script enforce this policy. The sibling website currently uses its own age policy. The website controller resolves the postal code when available. Submitting creates a pending application and does not sign the applicant in.

## Rider

Address -> Company -> License -> Vehicle -> Personal -> Contact -> Account.

Company selection reads approved website logistics companies, operational hubs, current hiring availability, and available company vehicle types. Company and hub cards are filtered by the selected address province, matching the website. Closed hubs cannot be selected. Changing province clears the previous company/hub/vehicle selection. The website rechecks eligibility, capacity, company membership, and vehicle availability during submission.

Riders provide a license number and expiry (valid for at least one more month), license evidence and NBI clearance. The license is also the rider identity document; no separate government ID is requested. Own vehicles require brand, model, plate, and separate OR and CR evidence. Company vehicles offer only the selected hub's available types and do not require the applicant's own plate or vehicle documents. Personal/contact/account details follow the buyer requirements. A selfie holding the license and optional PDF resume are collected on the License step. Rider and company terms are included on Account.

Applications enter the website's native review records. The applicant follows the approval/activation instructions sent by the platform or logistics company. Invited riders can paste the current /rider/activate/{token} link and set a confirmed password. The API calls the website RiderActivation service, preserving expiration, one-use tokens, company checks and document requirements. The activation link is never fetched as a URL.

## Mobile layout

Rider registration uses numbered circles with step labels, company and hub cards, vehicle ownership/type cards, document image previews with enlargement, and a full-width form with labeled upload cards, step scrolling, preserved field values, and a discard confirmation when leaving entered details. Other authentication screens retain their existing card layout.

## Verification

- flutter test test/widgets/account_registration_navigation_test.dart: account chooser regression, both role routes, buyer completion, rider completion, state retention and terms.
- php api/tests/website-registration.php: native age/email/document rules, shared pending records, private uploads, replay prevention, rider application history, and activation. Fixtures roll back and mail is fake.

Deploy the API and mobile app together: the new forms and controller adapter share the same payload and current website requirements.

## Automatic postal code

The mobile address form calls `auth/registration-postal-code` after city/municipality selection. The mobile runtime resolves the same `RegistrationPostalCode` contract from a local provider snapshot in `api/data`, avoiding failures caused by slow external lookups. A found code is filled automatically and read-only; an unavailable lookup permits optional manual entry. Changing region, province or municipality clears the previous code, and outdated responses are ignored. The registration controller still resolves the final code at submission.

## Shared MySQL

The API reuses `../pocketfinds/.env` through Laravel bootstrap: MySQL `127.0.0.1:3307`, database `pocketfinds`. No second registration database is created. Start with `scripts/start-local-api.ps1`; `/health` confirms the live connection. Android emulator uses `10.0.2.2:8081`; physical phones require `API_BASE_URL` set to the PC's LAN address.

## Buyer layout parity

Buyer uses the website's numbered progress labels and personal field order (last name, given names, middle name, suffix, sex, birthday/age). The address shows postal code before house/unit and street. ID and selfie uploads sit side by side when the available form width is at least 440 logical pixels and stack on narrow screens; selfie capture stays disabled until ID evidence is selected. Username availability is checked automatically after a 500 ms pause, with stale responses ignored and availability checked again by the website at submission. The buyer Account step contains username, password confirmation and terms without the app's additional review panel.

Registration navigation places Previous and Continue on the same row. Short address selectors and name fields share rows; long fields retain full width. The company step offers retry after an options-loading failure and cannot proceed without a chosen hub. Driver's license input is uppercased, accepts one initial letter followed by ten digits, adds hyphens as `A12-34-123456`, and retains server validation.
