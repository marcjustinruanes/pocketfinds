# Scanner Admin browser and MySQL verification

12 November 2026. The Admin browser rider-offer workflow passed using isolated synthetic data. **The requested scanner-specific MySQL concurrency tests remain NOT TESTED.** No release candidate was frozen and no application code, production configuration or production records were changed.

## Admin browser interaction — PASS

A disposable SQLite fixture provided an approved company Admin, an eligible covered Rider and a sorted parcel. A local PHP server used an isolated testing router, separate storage and array mail; Chromium blocked requests outside loopback. The process was stopped after the test.

1. Sign in as the synthetic Admin and open `/logistics/scan`. The application redirected to the Admin monitor.
2. Enter the tracking number in Find a parcel and submit. The matching parcel appeared. Database snapshots before and after lookup were identical.
3. Select **Assign a courier**, choose the displayed eligible Rider, and click **Send offer**.
4. Query the disposable database: exactly one delivery assignment exists with `status=offered`, `accepted_at=null`; shipment `courier_id=null` and status remains `sorted`. This is a pending offer, not rider acceptance or a custody transfer.

The actual Chromium test passed. Full before/after records: [Admin browser evidence](../storage/app/private/scanner-role-verification/admin-browser-result.json); [screenshot](../storage/app/private/scanner-role-verification/admin-browser.png); [fixture probe result](../storage/app/private/scanner-role-verification/ScannerAdminBrowserTest.txt).

Commands executed from the repository root:

```text
python storage/app/private/scanner-role-verification/make_admin.py
python storage/app/private/scanner-role-verification/prepare_admin_fixture.py
python storage/app/private/scanner-role-verification/run_admin_fixture.py
python storage/app/private/scanner-role-verification/prepare_admin_browser.py
python storage/app/private/scanner-role-verification/run_admin_browser.py
```

The fixture runner invoked PHPUnit for one test: PASS, 26 assertions. The browser runner starts `php -S 127.0.0.1:18849` against the dedicated private router and stops its own server in `finally`. It then uses actual Chromium; no production API is contacted.

## MySQL concurrency — NOT TESTED

The existing MySQL helpers were inspected. The available `cash-desk-mysql.php` includes a hub-transfer batch receipt race among broader financial scenarios, but it does not by itself establish the requested scanner-specific tests for two Staff accounts and partial receipt. It was not executed as a substitute. No new disposable MySQL database was prepared or raced during this turn.

The following remain required before drawing conclusions about MySQL concurrency:

- Simultaneous scanner receipt of one parcel, including duplicate requests and two distinct authorized Staff users at the receiving hub; record both responses, shipment status, receipt timestamp, custody witness, status history and activity count.
- Concurrent partial receipt of separate members of a multi-parcel transfer and duplicate receipt of the same member; check each member/leg receipt, batch status, destination custody and receipt/activity events before and after completion.
- Run the above only in an explicitly verified disposable MySQL schema with synthetic fixtures and no production API connections. Preserve evidence and clean up only the verified disposable schema.

The earlier [scanner functional report](scanner-functional-verification-report.md) provides passing SQLite sequential retry, rollback, partial receipt and custody evidence. It cannot establish MySQL race safety. Physical-device and staging requirements in [remaining verification](scanner-remaining-verification.md) also remain open.
