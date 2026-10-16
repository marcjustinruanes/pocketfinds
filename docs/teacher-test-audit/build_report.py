"""Produce a conservative case inventory: a related passing test is not a full case pass."""
import csv
import json
import pathlib
import re
import xml.etree.ElementTree as ET
from collections import Counter

ROOT = pathlib.Path(__file__).resolve().parent
books = json.loads((ROOT / 'workbook-extract.json').read_text(encoding='utf-8'))
cases = {}
for book in books:
    for sheet in book['sheets']:
        if not sheet['rows'] or next(iter(sheet['rows'][0].values())) != 'Test Case ID':
            continue
        for row in sheet['rows'][1:]:
            cells = {re.sub(r'\d+', '', cell): value for cell, value in row.items()}
            case_id = cells['A']
            assert case_id not in cases, case_id
            cases[case_id] = {'id': case_id, 'workbook': book['file'], 'sheet': sheet['name'],
                'scenario': cells.get('C', ''), 'preconditions': cells.get('F', ''), 'steps': cells.get('G', ''),
                'data': cells.get('H', ''), 'expected': cells.get('I', ''), 'assumptions': cells.get('J', ''),
                'status': 'UNVERIFIED', 'actual': 'No complete execution of this workbook scenario has been recorded.', 'evidence': []}

assert len(cases) == 469, len(cases)

def attach(ids, source, detail, status='PARTIAL'):
    for case_id in ids:
        if case_id not in cases:
            continue
        case = cases[case_id]
        case['evidence'].append({'source': source, 'detail': detail})
        if case['status'] == 'FAIL' and status != 'FAIL':
            continue
        if case['status'] != 'PASS' or status in ['PASS', 'FAIL']:
            case['status'] = status
            case['actual'] = detail

# Only record assertions from successful test cases in the latest complete JUnit run.
suite_path = ROOT / 'regression-final.xml'
if suite_path.exists():
    for test in ET.parse(suite_path).getroot().iter('testcase'):
        name = test.attrib['name']
        source = f"regression-final.xml: {test.attrib.get('classname', '')}::{name}"
        if test.find('failure') is not None or test.find('error') is not None:
            module = re.match(r'test_(buy|sel)_([a-z]+)_(\d{3})_', name)
            registration = re.match(r'test_tc_(buy|rid)_', name)
            ids = ['-'.join(part.upper() for part in module.groups())] if module else []
            if registration:
                ids += [f'TC-{registration[1].upper()}-{number}' for number in re.findall(r'(?:^|_)(\d{3})(?=_|$)', name)]
            attach(ids, source, 'Latest related regression test failed; inspect the JUnit failure/error before certifying this case.', 'FAIL')
            continue
        match = re.match(r'test_tc_(buy|rid)_', name)
        if match:
            numbers = re.findall(r'(?:^|_)(\d{3})(?=_|$)', name)
            attach([f'TC-{match[1].upper()}-{n}' for n in numbers], source,
                   'Related HTTP/database assertions passed on isolated SQLite fixtures. Full workbook UI steps and all stated data variants are not certified by this result.')
        module = re.match(r'test_(buy|sel)_([a-z]+)_(\d{3})_', name)
        if module:
            attach(['-'.join(part.upper() for part in module.groups())], source,
                   'Related HTTP/database assertions passed. The complete workbook scenario, including its UI and all side effects, is not yet certified.')
        if name == 'test_all_roles_save_unverified_and_only_transition_after_correct_email_code':
            attach(['TC-BUY-001', 'TC-SEL-001', 'TC-LOG-001'], source,
                   'Buyer, seller and logistics accounts saved unverified; numeric OTP was captured by the mail double; correct OTP changed buyers to approved and business accounts to pending. External email delivery is unverified.')
        business = {
            'test_seller_and_logistics_each_required_field_blocks_registration_without_sending_email': {'SEL': [2, 3], 'LOG': [2, 3]},
            'test_seller_and_logistics_names_enforce_format_and_two_to_fifty_characters': {'SEL': [4, 5, 6], 'LOG': [4, 5, 6]},
            'test_business_role_email_phone_password_and_terms_rules': {'SEL': list(range(7, 20)), 'LOG': list(range(7, 20))},
            'test_shop_name_length_and_case_insensitive_unique_business_identifiers': {'SEL': [21, 22, 26], 'LOG': [21, 23, 26]},
            'test_tin_postal_and_sec_formats_and_multiple_service_areas': {'SEL': [25, 28], 'LOG': [22, 24, 25, 28]},
            'test_seller_document_requirements_change_with_business_type': {'SEL': [23, 24]},
            'test_business_documents_accept_supported_types_and_enforce_five_mb_limit': {'SEL': [29, 30, 31], 'LOG': [29, 30, 31]},
            'test_business_pending_approval_rejection_reason_and_resubmission': {'SEL': list(range(38, 43)), 'LOG': list(range(38, 43))},
        }
        for role, numbers in business.get(name, {}).items():
            attach([f'TC-{role}-{n:03}' for n in numbers], source,
                   'Related business-registration HTTP assertions passed; complete browser execution of every specified input and side effect remains unverified.')
        if name == 'test_buy_trk_005_completion_occurs_at_168_hours_after_delivery_despite_other_order_updates':
            attach(['BUY-TRK-005'], source, 'The delivered order stayed open at 167:59:59, completed at 168 hours, notified both parties, and did not complete twice. Scheduler operation remains unverified.')

for path in sorted(ROOT.glob('*-browser.json')):
    filename = path.name
    if not path.exists():
        continue
    for row in json.loads(path.read_text(encoding='utf-8')):
        attach(row.get('cases', [row.get('case')]), filename, row.get('evidence', ''),
               row['status'] if row.get('status') in ['PASS', 'FAIL'] else 'PARTIAL')

race_path = ROOT / 'mysql-checkout-concurrency.json'
if race_path.exists():
    race = json.loads(race_path.read_text(encoding='utf-8'))
    if race.get('status') == 'PASS':
        attach(['SEL-INV-006'], race_path.name,
               'Two concurrent Laravel checkout requests ran against MySQL with one unit left. Exactly one order committed; the loser received a sold-out error; stock ended at zero and the losing cart remained.', 'PASS')

counts = Counter(case['status'] for case in cases.values())
browser_runs = json.loads((ROOT / 'browser-run-summary.json').read_text(encoding='utf-8')) if (ROOT / 'browser-run-summary.json').exists() else []
regressions = list(ET.parse(suite_path).getroot().iter('testcase')) if suite_path.exists() else []
failed = sum(test.find('failure') is not None or test.find('error') is not None for test in regressions)
(ROOT / 'case-results.json').write_text(json.dumps(list(cases.values()), ensure_ascii=False, indent=2), encoding='utf-8')
with (ROOT / 'case-results.csv').open('w', newline='', encoding='utf-8-sig') as stream:
    writer = csv.writer(stream)
    writer.writerow(['Case ID', 'Workbook', 'Sheet', 'Scenario', 'Expected result', 'Status', 'Actual result / limitation', 'Evidence'])
    for case in cases.values():
        writer.writerow([case['id'], case['workbook'], case['sheet'], case['scenario'], case['expected'], case['status'], case['actual'],
                         '\n'.join(item['source'] for item in case['evidence'])])

summary = ['# Teacher test audit — incomplete', '',
    '**PocketFinds is not certified ready against all 469 workbook cases.**', '',
    '| Status | Cases |', '| --- | ---: |']
summary += [f'| {status} | {counts[status]} |' for status in ['PASS', 'FAIL', 'PARTIAL', 'UNVERIFIED']]
summary += ['', 'PASS is reserved for a fully checked, narrowly defined workbook case. PARTIAL means related assertions or browser steps passed, but the full expected result has not been certified. UNVERIFIED means no complete execution has been recorded. Passing the regression suite is not the same as passing all teacher cases.', '',
    'The original Excel workbooks were read without modification. Their hashes and complete extracted cells are in `workbook-extract.json`. Workbook notes were treated as test specifications and reference data, not as independent instructions.', '',
    '## Environment and evidence', '',
    f'- Latest complete PHP regression run: {len(regressions)} tests, {failed} failures/errors. This is a separate count from the 469 teacher cases.',
    f'- Browser rerun: {len(browser_runs)}/{len(list((ROOT.parents[1] / "tests" / "Browser").glob("teacher-*.mjs"))) + 1} scripts completed; {sum(row["status"] == "FAIL" for row in browser_runs)} reported failure. This does not cover all workbook scenarios.',
    '- The 19 existing JavaScript tests also passed. Earlier XML files record prior runs; `regression-final.xml` is the current complete run.',
    '- The existing `pocketfinds` MySQL database is connected at `127.0.0.1:3307`.',
    '- Fresh MySQL migrations and browser workflows ran in the separate database recorded in `mysql-audit-database.json`.',
    '- The existing database was privately backed up before pending migrations were applied. The earlier backup was preserved.',
    '- Existing users, products, orders and shipments matched their pre-migration counts and checksums; see `existing-records-before.json` and `existing-records-after.json`.',
    '- PHP HTTP tests use isolated SQLite fixtures. The separate MySQL race test uses two actual concurrent PHP/Laravel request processes.',
    '- Browser registration and rider activation use Chrome and the local mail log. The authorized external email test was accepted by SMTP, but the recipient reported it had not arrived; inbox delivery remains unverified. See `external-mail-test.json`.',
    '- No external payment was charged or verified. The user retained local payment doubles; external provider success, failure, cancellation, timeout and refund verification remains out of scope until sandbox access is provided.', '',
    'Latest browser runner: `browser-run-summary.json` records each script, execution time and exit status; corresponding `*-run.log` files preserve its output. A browser-run failure must be investigated even when some individual case assertions passed. Failure screenshots from earlier attempts are retained for diagnosis; use the latest JSON results and run summary to identify current outcomes.', '',
    '## Implemented changes', '',
    '- Added buyer/seller/logistics registration with general email addresses, normalized Philippine phone numbers, name/age/password validation, terms acceptance, conditional business documents, and post-registration five-minute email verification.',
    '- Added company-created rider invitations with 24-hour, single-use activation links; disabled public rider signup; retained hub staff registration and legacy personnel management.',
    '- Prevented inactive buyer/seller accounts and inactive Google accounts from accessing working portals; prevented admin approval before email verification.',
    '- Enforced product price/stock precision and bounds, product name/description limits and image constraints, voucher code format, and stronger password changes.',
    '- Made buyer name/photo edits immediate, with name validation and content-checked JPG/PNG photos up to 5 MB; the header now shows the saved photo.',
    '- Added verified email/phone changes, five-minute single-use codes, case-insensitive cross-role email uniqueness, phone normalization, resend invalidation and attempt limits. Phone changes are confirmed through the registered email; no SMS delivery is claimed.',
    '- Added saved-address editing, required address fields, both Philippine phone formats, a local PSGC location snapshot and region/province/city/barangay cascading. Retained the ten-address limit and default selection.',
    '- Preserved order address snapshots after address editing/deletion and used snapshot recipient details in seller views and waybills.',
    '- Added store categories with per-store case-insensitive names, two levels, ordering, visibility, up to three assignments per product, storefront filters, deletion confirmation and product preservation.',
    '- Added seller vacation mode: products remain visible with a label, purchase buttons are disabled, and COD/GCash new-order paths reject vacation stores while existing fulfillment remains available.',
    '- Added unified store settings with validated unique names, description, pickup address, contact details and 5 MB logo/banner uploads; buyer storefronts and store-name search reflect changes.',
    '- Added private product drafts, immediate publishing for approved sellers, duplicate-submit protection, copies, bulk actions, unfinished-order deletion guards, bounded stock editing and threshold-crossing low-stock alerts.',
    '- Added literal product search, typo suggestions, rating/location/price filters, stable pagination and completed-unit best-selling sorting; product prices retain cents.',
    '- Added persistent buyer wishlists with explicit save/remove states, deleted-product snapshots, sold-out availability, a Following list and guest login links.',
    '- Refreshed cart prices from current listings and disabled unavailable, sold-out, vacation and insufficient-stock cart lines while preserving checkout of available items.',
    '- Reserved COD stock transactionally at checkout, prevented a second deduction at seller confirmation, restored stock on cancellation before confirmation, and ignored duplicated cart IDs.',
    '- Changed automatic completion to seven days and measured it from the shipment delivery timestamp rather than unrelated later order updates.',
    '- Sanitized GCash database-failure JSON responses so they do not contain exception details.', '',
    '## Remaining work', '',
    'Every unresolved case is individually listed in `case-results.csv` and `case-results.json`. Important remaining areas include remaining product-edit, bulk and inventory browser scenarios; matrix variants; scheduled discounts, flash sales and bundles; voucher wallets, stacking and funding; shipping-rate calculations; payment-provider lifecycle; return/refund/dispute workflows; per-item review media and editing; automatic dispatch and timed job offers; failed-delivery attempts and delivery OTP; COD remittance and earnings/payouts; and report exports. These have not been marked passed based on source inspection or related regression tests.', '',
    '## Reproduce', '',
    '```powershell',
    'php -d memory_limit=512M vendor/bin/phpunit --no-progress --log-junit docs/teacher-test-audit/regression-final.xml',
    'node --test tests/JavaScript/*.test.cjs',
    'node tests/Browser/run-teacher-audit.mjs # requires the isolated MySQL audit server on port 8020',
    'php tests/Browser/check_mysql.php',
    'python docs/teacher-test-audit/build_report.py',
    '```', '',
    'Browser scripts require `playwright@1.64.1` (installed locally with `npm install --no-save --package-lock=false --ignore-scripts playwright@1.64.1`) and Chrome. Prepare a separate MySQL audit database with `php tests/Browser/prepare_mysql_audit.php`, run `tests/Browser/serve_mysql_audit.ps1` in another terminal, set `$env:AUDIT_URL="http://127.0.0.1:8020"`, then run the browser scripts under `tests/Browser`. They create fictional audit accounts and do not target the existing database. The separate audit databases are retained for inspection.']
(ROOT / 'README.md').write_text('\n'.join(summary) + '\n', encoding='utf-8')
print(json.dumps({'total': len(cases), **counts}))

# Keep every unresolved ID visible, with the actual known limitation rather than an invented pass.
unresolved = ['# Unresolved teacher cases', '',
    'This list does not treat unexecuted work as a credential block. PARTIAL and UNVERIFIED are not passes. Each entry includes the recorded limitation and the work still needed.', '',
    '| Case ID | Status | Scenario | Recorded limitation | Needed |', '| --- | --- | --- | --- | --- |']
def cell(value):
    return str(value).replace('|', '\\|').replace('\n', '<br>')
for case in cases.values():
    if case['status'] == 'PASS':
        continue
    needed = 'Execute the remaining recorded workbook steps and every stated input in the running application; compare all expected side effects and retain browser/database evidence.'
    if case['status'] == 'FAIL':
        needed = 'Fix the recorded observed failure, then rerun this workbook scenario and affected regression checks.'
    if case['id'].startswith('BUY-PAY'):
        needed += ' Use the approved local payment double; external provider verification additionally requires a specified sandbox, merchant credentials and webhook access.'
    unresolved.append('| ' + ' | '.join(map(cell, [case['id'], case['status'], case['scenario'], case['actual'], needed])) + ' |')
(ROOT / 'unresolved-cases.md').write_text('\n'.join(unresolved) + '\n', encoding='utf-8')
with (ROOT / 'README.md').open('a', encoding='utf-8') as stream:
    stream.write('\n## Excel results and unresolved IDs\n\nUpdated review copies are in `excel-results/`; K:M contain actual result, status and evidence/remarks. PARTIAL and UNVERIFIED remain explicitly uncertified. The teacher originals are unchanged and hashes are recorded in `excel-results/manifest.json`. Regenerate copies after updating this ledger with `python docs/teacher-test-audit/export_excel_results.py`.\n\nEvery unresolved ID, recorded reason and required next work is listed in [unresolved-cases.md](unresolved-cases.md).\n')
