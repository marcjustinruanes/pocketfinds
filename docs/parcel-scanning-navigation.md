# Parcel scanning navigation

30 October 2026. Admin's separate Scan Parcel sidebar entry is removed and its dashboard shortcut now opens Find parcel in Live Monitor. Existing Admin scanner bookmarks redirect to Monitor. The scanner route, lookup API, services and USB/camera/phone support remain for authorized existing workflows; Staff retains its scanner page and receiving controls.

Monitor adds scoped tracking/order-number lookup, including historical shipments when searching. Existing stage filters and pagination remain. Pickup Requests and Delivery Assignments tracking links open that lookup. Monitor no longer offers the server-denied Rider-only departure transition; approved pickup links reach the assignment queue. Staff is not linked to Admin-only pickup review. No physical-custody, Rider, financial or authorization rule changed.

Changed: LogisticsController (scanner entry/Monitor read queries), logistics sidebar/dashboard/monitor/requests/assign views, and authorization/navigation tests. No routes removed, migration added, business record edited or deployment performed.

Verification: 59 authorization/navigation/transfer tests passed (1,799 assertions), plus 18 phone/mobile scanner tests (273 assertions). New checks verify Admin redirect/navigation, historical lookup, foreign-company exclusion, unchanged scoped lookup API and retained Staff scanning. Chromium synthetic Admin Monitor and Staff scanner views at 1440px/390px had no document overflow; Admin search field labelled and 44px high. Mobile lookup screenshot visually inspected. Real camera/USB/phone hardware and comprehensive accessibility were not exercised.

Private artifacts: scanner-navigation.xml/json, scanner-support.xml/json, scanner-navigation-browser.txt and synthetic previews under storage/app/private/logistics-ui-review. Existing pending schema rollout remains separate; no source-database actions were performed.
