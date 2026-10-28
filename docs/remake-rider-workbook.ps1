param(
    [string]$OutputName = 'Mobile_Rider_Test_Cases_System_Review.xlsx',
    [string]$ResultsPath = ''
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$source = 'C:\Users\Administrator\Downloads\Mobile_Blackbox_Test_Cases_Account_Creation-Rider (1).xlsx'
$output = Join-Path $PSScriptRoot $OutputName
Copy-Item -LiteralPath $source -Destination $output -Force
$zip = [IO.Compression.ZipFile]::Open($output, [IO.Compression.ZipArchiveMode]::Update)
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
function Read-Xml($path) {
    $reader = [IO.StreamReader]::new($zip.GetEntry($path).Open())
    try { [xml]$reader.ReadToEnd() } finally { $reader.Dispose() }
}
function Save-Xml($path, $doc) {
    $zip.GetEntry($path).Delete()
    $entry = $zip.CreateEntry($path)
    $stream = $entry.Open()
    try { $doc.Save($stream) } finally { $stream.Dispose() }
}
function Set-Cell($doc, $ref, $value) {
    $rowNumber = $ref -replace '\D', ''
    $row = $doc.worksheet.sheetData.row | Where-Object { $_.r -eq $rowNumber }
    $cell = $row.c | Where-Object { $_.r -eq $ref }
    if (!$cell) {
        $cell = $doc.CreateElement('c', $ns)
        $cell.SetAttribute('r', $ref)
        $cell.SetAttribute('s', '23')
        [void]$row.AppendChild($cell)
    }
    $style = $cell.GetAttribute('s')
    $cell.RemoveAll()
    $cell.SetAttribute('r', $ref)
    $cell.SetAttribute('s', $style)
    $cell.SetAttribute('t', 'inlineStr')
    $inline = $doc.CreateElement('is', $ns)
    $text = $doc.CreateElement('t', $ns)
    $text.InnerText = $value
    [void]$inline.AppendChild($text)
    [void]$cell.AppendChild($inline)
}
try {
    $strings = Read-Xml 'xl/sharedStrings.xml'
    $shared = @($strings.sst.si | ForEach-Object { $_.InnerText })
    $rider = Read-Xml 'xl/worksheets/sheet5.xml'
    $findings = @{}
    function Finding($ids, $classification, $result, $evidence) {
        foreach ($id in $ids) { $findings[$id] = @($classification, $result, $evidence) }
    }
    $controller = 'app/Http/Controllers/LogisticsManagementController.php: accountData / storeAccount'
    $form = 'resources/views/logistics/partials/account-form.blade.php'
    Finding @(1,3) 'PARTIAL / DIFFERENT' 'Logistics can create a rider, but requires additional fields (username, sex, barangay, operational hub, password and vehicle ownership). License number, plate and document uploads are absent from this form. Account starts pending recruitment approval; no activation email is sent. Multi-word first names are rejected.' "$controller; $form"
    Finding @(2) 'IMPLEMENTED IN CODE - VERIFY' 'Required-field validation exists. Empty submission should be rejected; exact messages and absence of a created record require execution.' "$controller; $form"
    Finding @(4,5) 'PARTIAL / DIFFERENT' 'Name length is 2-50 characters and numbers/symbols are rejected, but regex permits only letters and hyphens. Spaces, apostrophes and periods allowed by workbook A2 are rejected.' $controller
    Finding @(6) 'IMPLEMENTED IN CODE - VERIFY' 'Rider birthday validation requires age 18 or older. Recalculate boundary dates for the actual test day; cached Excel dates are not current.' $controller
    Finding @(7,8) 'IMPLEMENTED IN CODE - VERIFY' 'Email format validation and uniqueness across the users table exist. Execute the exact workbook examples to confirm outcomes.' $controller
    Finding @(9,10,11) 'IMPLEMENTED IN CODE - VERIFY' 'Philippine number normalization, 09 plus nine-digit server validation and duplicate checking exist. UI uses +63 with ten national digits. Test malformed input and paste behavior on a phone.' "$controller; app/Support/PhilippineMobile.php; $form"
    Finding @(12,13) 'MISSING IN LOGISTICS CREATE FORM' 'No license-number input, format rule or uniqueness validation in logistics-created rider accounts. Public rider self-registration has separate license checks; it does not cover this flow.' "$controller; $form; app/Http/Controllers/RegisterController.php"
    Finding @(14) 'IMPLEMENTED IN CODE - VERIFY' 'License expiry is required and must be today or later. Recalculate yesterday/today/tomorrow on the execution date.' $controller
    Finding @(15) 'MISSING IN LOGISTICS CREATE FORM' 'No plate-number input or duplicate-plate validation in this account creation form. Company vehicle registration and public rider registration are separate flows.' "$controller; $form"
    Finding @(16,17,18) 'MISSING IN LOGISTICS CREATE FORM' 'No Driver License or Vehicle OR/CR file inputs or upload processing in this form. Workbook JPG/PNG/PDF and 5 MB tests cannot run against this flow.' "$controller; $form"
    Finding @(19) 'PARTIAL / REQUIRES EXECUTION' 'Unique username/email rules exist, but no explicit form submission lock or request idempotency was found here. Exactly-once creation under rapid taps needs execution. Activation email behavior is absent.' "$controller; $form"
    Finding @(20,21,22,23,24,25) 'MISSING ACTIVATION FLOW' 'No emailed rider activation/set-password flow, 24-hour activation expiry, resend or one-time activation token handling was found. Logistics sets a confirmed password (12-128 characters); workbook requires rider-set minimum 8 with uppercase, lowercase, number and special character.' "$controller; $form; routes/web.php"
    Finding @(26) 'DIFFERENT ACCOUNT FLOW' 'Pending riders are blocked by RiderMiddleware because only approved riders may enter rider routes. This is recruitment approval, not Pending Activation. Full login behavior needs execution.' 'app/Http/Middleware/RiderMiddleware.php; app/Http/Controllers/LoginController.php'
    Finding @(27) 'DIFFERENT ACCOUNT FLOW' 'Rider dashboard routes exist for approved riders. No activation-link-to-Active transition exists. Confirm displayed rider and company names during live testing.' 'routes/web.php; app/Http/Middleware/RiderMiddleware.php'
    Finding @(28) 'IMPLEMENTED IN CODE - VERIFY' 'Managed rider accounts are filtered by the logged-in logistics company business_name. Confirm with two company sessions.' "${controller}: managedUsers"
    Finding @(29) 'DIFFERENT / PUBLIC SIGNUP EXISTS' 'Public rider self-registration is available at /register/rider, contrary to the workbook assumption that logistics alone creates riders.' 'routes/web.php: register.rider; app/Http/Controllers/RegisterController.php'
    Finding @(30,31,32) 'IMPLEMENTED IN CODE - VERIFY' 'Role and approved-logistics middleware checks exist. Validate actual deep links, requests and redirects with buyer, seller, pending logistics and rider sessions.' 'routes/web.php; app/Http/Middleware/LogisticsMiddleware.php; app/Http/Middleware/RiderMiddleware.php'
    Finding @(33) 'PARTIAL / DEVICE TEST NEEDED' 'Email and numeric telephone input types exist. License and plate fields are absent from this form; name capitalization and keyboard behavior need physical-device testing.' $form
    Finding @(34,35,36,43) 'DEVICE TEST REQUIRED' 'Keyboard visibility, background/lock restoration, rotation and largest-font layout were not exercised. Installed Flutter app is a logistics scanner without this account form; test the web form separately if in scope.' "$form; mobile/pocketfinds_scanner/README.md"
    Finding @(37,38) 'NOT VERIFIED / PARTIAL' 'Create Rider uses a normal server-posted web form. No custom offline retry, persistent draft or request idempotency was found in this form. Network-loss behavior requires phone testing. Scanner offline barcode queue is a different feature.' "$controller; $form; mobile/pocketfinds_scanner/README.md"
    Finding @(39) 'MISSING FORM GUARD' 'No unsaved-changes confirmation or Back-navigation guard was found in the logistics account form. Verify browser behavior on a phone.' $form
    Finding @(40,41,42) 'MISSING IN LOGISTICS CREATE FORM' 'This form has no rider document uploads, camera/gallery/Files chooser, related permission-denial handling or photo size/compression handling.' $form
    Finding @(44,45) 'MISSING ACTIVATION FLOW' 'No rider activation email link or mobile set-password fallback flow was found. Existing Flutter scanner does not provide rider activation screens.' 'routes/web.php; mobile/pocketfinds_scanner/README.md; app/Http/Controllers/LogisticsManagementController.php'
    Finding @(46,47) 'RIDER MOBILE APP NOT IMPLEMENTED / VERIFY WEB' 'Existing installed Flutter app supports logistics scanning, not a rider dashboard/session. Rider web session persistence and logout/Back behavior require separate execution on a phone.' 'mobile/pocketfinds_scanner/README.md; app/Http/Controllers/LoginController.php; routes/web.php'
    for ($id=1; $id -le 47; $id++) {
        $row = $id + 1
        $f = $findings[$id]
        if (!$f) { throw "Missing assessment for case $id" }
        Set-Cell $rider "K$row" ("CODE REVIEW (2026-10-24): " + $f[1])
        Set-Cell $rider "L$row" 'Not Run'
        Set-Cell $rider "M$row" ($f[0] + '. Evidence: ' + $f[2] + '. Static assessment only; no live test executed.')
    }
    Save-Xml 'xl/worksheets/sheet5.xml' $rider
    $assumptions = Read-Xml 'xl/worksheets/sheet4.xml'
    $actual = @{
        A1='Logistics-created riders additionally require username, sex, barangay, operational hub, logistics-set password and vehicle ownership. License number, plate and document uploads are absent from that form.'
        A2='Logistics account form: 2-50 characters; letters and hyphens only. Spaces, apostrophes and periods are rejected.'
        A3='Email format and users-table uniqueness validation exist; exact case/whitespace behavior requires execution.'
        A4='Philippine mobile normalization and users-table uniqueness exist. UI displays +63 and accepts ten national digits; server normalizes to 09 format.'
        A5='Logistics-created account passwords: 12-128 characters, confirmed. No uppercase/lowercase/number/special composition rule in accountData. No rider activation password form.'
        A6='Rider account creation requires age 18 or older.'
        A10='No rider license/OR-CR upload support in logistics account creation. Do not infer support from other registration/upload flows.'
        A13='Public rider self-registration exists. Logistics can also create riders with a password. New accounts start pending and use recruitment approval; no emailed Pending Activation-to-Active flow.'
        A14='Email/mobile uniqueness exists in logistics account creation. License and plate fields/checks are absent there; separate public registration has checks.'
        A15='Logistics account creation checks expiry today or later, but has no license-number input or format validation.'
        A16='Server role and approval checks exist; direct-request and deep-link execution is still required.'
        A17='Outside the 47-case Rider sheet review. Courier references in shared supporting sheets are not assessed as a separate role.'
        A18='Outside the 47-case Rider sheet review; no Courier test sheet supplied.'
        M1='Current Flutter mobile app is a logistics scanner for approved logistics admin/hub staff. It does not contain Rider account creation, activation or dashboard screens.'
        M2='Web form has email/tel input types; remaining keyboard/visibility behavior requires physical-device testing.'
        M4='Not exercised on a physical device.'
        M5='No custom offline handling/idempotency found in Create Rider web form. Existing scanner queue applies to barcode scans only.'
        M6='No rider-document upload permission flow in logistics Create Rider form.'
        M7='No camera/gallery/Files rider document upload in logistics Create Rider form.'
        M8='No unsaved-form navigation guard found in account form. Logout/Back behavior requires device testing.'
        M9='No Rider session in current Flutter scanner app. Rider web login persistence and logout require execution.'
        M11='Server role guards exist. No rider activation deep link or fallback set-password page found.'
        M12='Small/large screens, theme and largest system font were not tested.'
    }
    foreach ($row in $assumptions.worksheet.sheetData.row) {
        $cell = $row.c | Where-Object { $_.r -eq ('A'+$row.r) }
        if ($cell -and $cell.t -eq 's') {
            $key = $shared[[int]$cell.v]
            if ($actual.ContainsKey($key)) { Set-Cell $assumptions ('D'+$row.r) ('Code review 2026-10-24: '+$actual[$key]) }
        }
    }
    Save-Xml 'xl/worksheets/sheet4.xml' $assumptions
    $summary = Read-Xml 'xl/worksheets/sheet1.xml'
    Set-Cell $summary 'A1' 'Rider Account Creation - System Code Review'
    Set-Cell $summary 'B5' 'PocketFinds current workspace; logistics Create Rider web flow and existing Flutter scanner reviewed'
    Set-Cell $summary 'B6' 'Local source code review; app build/version not verified'
    Set-Cell $summary 'B7' 'No physical device or live test run'
    Set-Cell $summary 'B8' 'Codex - static code review'
    Set-Cell $summary 'B9' '2026-10-24 (Asia/Singapore)'
    Set-Cell $summary 'A20' 'Review edition: Original test cases, expected results, data, formulas and formatting retained. Actual Result and Remarks contain source-code findings, not observed test results.'
    Set-Cell $summary 'A21' 'All 47 cases remain Not Run. IMPLEMENTED IN CODE means validation/guards were found; it does not mean a passing live test. MISSING and DIFFERENT identify gaps against workbook assumptions.'
    Set-Cell $summary 'A22' 'Assumptions column D records reviewed system behavior. Shared Buyer/Seller/Logistics/Courier supporting data is retained from the source and is not a completed review of those roles.'
    Set-Cell $summary 'A24' 'Before live testing, recalculate date boundaries using the actual test date. Cached original examples used 2026-10-21 and must not be treated as current.'
    Save-Xml 'xl/worksheets/sheet1.xml' $summary
    $workbook = Read-Xml 'xl/workbook.xml'
    $workbook.workbook.calcPr.SetAttribute('fullCalcOnLoad','1')
    $workbook.workbook.calcPr.SetAttribute('forceFullCalc','1')
    Save-Xml 'xl/workbook.xml' $workbook
    if ($ResultsPath) {
        [xml]$results = Get-Content -LiteralPath $ResultsPath -Raw
        $counts = @{Pass=0; Fail=0; Blocked=0; 'Not Run'=0}
        $rider = Read-Xml 'xl/worksheets/sheet5.xml'
        foreach ($case in $results.SelectNodes('//testcase')) {
            if ($case.name -notmatch 'TC-RID-(\d+)') { continue }
            $id = [int]$Matches[1]
            $row = $id + 1
            $status = 'Pass'
            $detail = 'All implemented HTTP assertions passed for the server-side equivalent. Phone UI and API proxy replay were not tested.'
            if ($case.SelectSingleNode('failure')) {
                $status = 'Fail'
                $detail = $case.failure.InnerText
            } elseif ($case.SelectSingleNode('error')) {
                $status = 'Blocked'
                $detail = $case.error.InnerText
            } elseif ($case.SelectSingleNode('skipped')) {
                $status = 'Blocked'
                $detail = $case.skipped.InnerText
            }
            $counts[$status]++
            Set-Cell $rider "K$row" ("EXECUTED 2026-10-24 - $status (server HTTP test): " + $detail)
            Set-Cell $rider "L$row" $status
            $note = 'Automated server-side adaptation, not a physical-device black-box run. In-memory SQLite fixtures; PHP 8.5.10; mail faked; no live MySQL or delivered email. Baseline supplies system-required username/sex/barangay/hub/password/ownership; uses Pedro except where testing multi-word names. Vehicle Motorcycle mapped to two_wheels. Boundaries calculated at runtime. See tests/Feature/RiderWorkbookTest.php and docs/rider-workbook-test-results.xml. '
            if ($status -eq 'Fail') { $note += 'Failure is against workbook expectations; original assumptions may differ from intended requirements. ' }
            if ($id -eq 3) { $note += 'Stops at first failed assertion: absent license_number accepted. Remaining required-document omissions not executed after that failure. ' }
            if ($id -eq 7) { $note += 'Email pedrojose.garcia@example accepted; workbook expects rejection. ' }
            if ($id -eq 12) { $note += 'Stops at first invalid license example; later variants not executed. ' }
            if ($id -eq 28) { $note += 'Tested company Accounts list, not every Rider-list screen. ' }
            if ($id -eq 32) { $note += 'Tested logistics GET/POST plus seller/admin dashboard denial; mobile menu visibility not tested. ' }
            Set-Cell $rider "M$row" $note
        }
        if (($counts.Pass+$counts.Fail+$counts.Blocked+$counts['Not Run']) -ne 47) { throw 'JUnit report must contain all 47 workbook cases.' }
        Save-Xml 'xl/worksheets/sheet5.xml' $rider
        $summary = Read-Xml 'xl/worksheets/sheet1.xml'
        Set-Cell $summary 'A1' 'Rider Account Creation - Automated Server Test Results'
        Set-Cell $summary 'B6' 'Current local source; PHP 8.5.10; Laravel HTTP tests; SQLite :memory:'
        Set-Cell $summary 'B8' 'Codex - automated server-side workbook adaptation'
        Set-Cell $summary 'A20' ("47 cases accounted for: $($counts.Pass) Pass, $($counts.Fail) Fail, $($counts.Blocked) Blocked. These are automated server equivalents; no phone UI run was performed.")
        Set-Cell $summary 'A21' 'Pass means the executed server assertions matched workbook expectations. Blocked means missing activation/upload prerequisites or a required mobile/device/network test. See Actual Result and Remarks for exact scope.'
        Set-Cell $summary 'A22' 'Original test steps/data/expected results retained. Automated baseline supplies extra required system fields, maps Motorcycle to two_wheels, and uses a single-word name outside name tests. SQLite fixtures and fake mail prevent changes to live records and email delivery.'
        Set-Cell $summary 'A24' 'Boundary dates calculated at test runtime (2026-10-24); original cached date examples are stale. Supporting existing suite: 44/46 passed, 1 assertion failure and 1 error from incomplete/conflicting fixtures. See rider-existing-tests.xml.'
        foreach ($rowNum in @(16,17)) {
            foreach ($pair in @(@('D',$counts.Pass),@('E',$counts.Fail),@('F',$counts.Blocked),@('G',0),@('H',($counts.Pass/47.0)))) {
                $cell = $summary.SelectSingleNode("//*[local-name()='c' and @r='$($pair[0])$rowNum']")
                if ($cell -and $cell.v) { $cell.v = ([string]$pair[1]).Replace(',','.') }
            }
        }
        Save-Xml 'xl/worksheets/sheet1.xml' $summary
        Write-Output ('Results: '+($counts | ConvertTo-Json -Compress))
    }
} finally { $zip.Dispose() }
$check = [IO.Compression.ZipFile]::OpenRead($output)
try {
    foreach ($entry in $check.Entries | Where-Object { $_.FullName -match '\.xml$|\.rels$' }) {
        $reader = [IO.StreamReader]::new($entry.Open())
        try { [xml]$validated = $reader.ReadToEnd() } finally { $reader.Dispose() }
    }
    $reader = [IO.StreamReader]::new($check.GetEntry('xl/worksheets/sheet5.xml').Open())
    try { [xml]$sheet = $reader.ReadToEnd() } finally { $reader.Dispose() }
    $reviewed = @($sheet.worksheet.sheetData.row | Where-Object { [int]$_.r -gt 1 -and ($_.c | Where-Object { $_.r -eq ('K'+$_.ParentNode.r) }).InnerText -match '^(CODE REVIEW|EXECUTED)' }).Count
    if ($reviewed -ne 47) { throw "Expected 47 reviewed cases; got $reviewed" }
    Write-Output "Validated workbook XML and $reviewed reviewed cases: $output"
} finally { $check.Dispose() }
