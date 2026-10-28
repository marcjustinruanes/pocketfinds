$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

$source = Join-Path $PSScriptRoot 'Mobile_Rider_Test_Cases_Executed_Results.xlsx'
$output = Join-Path $PSScriptRoot 'Mobile_Rider_Test_Cases_Executed_Results.updated.xlsx'
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
    $text.SetAttribute('xml:space', 'preserve')
    $text.InnerText = $value
    [void]$inline.AppendChild($text)
    [void]$cell.AppendChild($inline)
}

$results = @{
  1=@('Fail','I entered the valid rider details from the test sheet. The form rejected “Pedro Jose” and displayed a name-format error.','A valid first name containing a space could not be submitted. The website should allow multi-word names.')
  2=@('Pass','I submitted the Add Rider form with all fields empty. The website kept me on the form and showed required-field messages. No rider was added.','The empty-form validation worked as expected.')
  3=@('Pass','I advanced through the Rider registration steps and selected “My Own Vehicle.” License number, license expiry, license upload, plate number, OR, and CR then appeared. Submitting without each one produced a required-field message.','The required Rider fields are present in later steps and are correctly enforced.')
  4=@('Pass','Names containing numbers or unsupported symbols were rejected with a validation message.','Invalid name characters were blocked as expected.')
  5=@('Fail','Two-character and 50-character single-word names were accepted, but a valid multi-word name within the limit was rejected.','The length boundary works, but spaces in valid names are incorrectly blocked.')
  6=@('Pass','A rider exactly 18 years old and one day older were accepted. A rider one day younger than 18 was rejected.','The age boundary worked as expected using the test date.')
  7=@('Fail','Most malformed email addresses were rejected, but pedrojose.garcia@example was accepted.','The website accepted an address without a normal top-level domain.')
  8=@('Pass','I entered an email already used by another account. The website displayed that the email was already registered and did not add the rider.','Duplicate email protection worked as expected.')
  9=@('Pass','Letters, punctuation, a landline number, and other invalid mobile-number formats were rejected.','Invalid mobile-number formats were blocked.')
 10=@('Pass','A 10-digit mobile number was rejected, an 11-digit Philippine mobile number was accepted, and a 12-digit number was rejected.','The mobile-number length boundary worked as expected.')
 11=@('Pass','I tried a mobile number already used by another account in both 09 and +63 forms. Both attempts were rejected as duplicates.','Equivalent Philippine number formats were treated as the same number.')
 12=@('Fail','The Add Rider page has no driver’s license number field, so invalid license-number formats could not be entered or rejected.','The expected license-number validation is unavailable on this form.')
 13=@('Pass','I entered a driver’s license number already used by another account. The Rider registration page reported that it was unavailable and did not accept it.','Duplicate driver’s license checking worked after the License step was opened.')
 14=@('Pass','Yesterday was rejected as an expired license date. Today and tomorrow were accepted.','The license-expiry boundary worked as expected.')
 15=@('Pass','After selecting “My Own Vehicle,” I entered a plate number already used by another account. The Rider registration page reported that it was unavailable.','Duplicate plate-number checking worked in the revealed Vehicle step.')
 16=@('Pass','I opened the hidden License and Vehicle steps and submitted supported JPG, PNG, and PDF documents. Registration completed successfully and the new rider was saved as pending.','The upload controls work; they appear after the Rider advances to License and selects “My Own Vehicle.”')
 17=@('Pass','I tried to upload a TXT file as the driver’s license. Registration rejected it and stated that only JPG, JPEG, PNG, or PDF files are accepted.','Unsupported rider-document files were rejected as expected.')
 18=@('Fail','The upload flow accepts files within the limit, but a 5.1 MB image was compressed and accepted instead of being rejected with the maximum-size message required by this case.','The upload feature works, but its oversized-image behavior differs from this test case. TC-RID-042 separately allows compression and passes.')
 19=@('Blocked','I opened the Add Rider page, but I could not reliably reproduce two simultaneous taps using this non-interactive test session.','Repeat this case manually in a real browser or on a phone while watching the rider list for duplicate records.')
 20=@('Fail','After a logistics administrator creates a rider, the website does not send the rider to a Set Password activation page. The administrator is asked to enter the password on the Add Rider form instead.','The expected rider activation and password-setup journey is not available.')
 21=@('Fail','There is no rider Set Password activation screen where weak-password combinations can be tested.','The activation password rules cannot work as described because the activation flow is missing.')
 22=@('Fail','There is no rider activation password form, so Password and Confirm Password cannot be compared during activation.','Password confirmation during rider activation is missing.')
 23=@('Fail','No rider activation link is issued by the Add Rider journey, so there is no link to test after 23 hours.','The 24-hour activation-link behavior is missing.')
 24=@('Fail','No rider activation link is issued by the Add Rider journey, so an expired-link message could not be reached.','Expired activation-link handling is missing.')
 25=@('Fail','The website does not provide a resend option for a rider activation link.','Activation-link resend and old-link cancellation are missing.')
 26=@('Pass','I tried to sign in with a rider whose account was still pending. The website returned to Login and did not open the Rider dashboard.','Access was denied as expected. The website calls the status pending rather than Pending Activation.')
 27=@('Pass','After the temporary rider was approved, I signed in with the same email and password. The Rider dashboard opened successfully.','The login result matched the expected behavior. The website calls the usable status approved rather than Active.')
 28=@('Pass','I added a rider under one logistics company and checked the account list from another company. The rider was visible only to the company that owned the account.','Company separation worked as expected on the Accounts page.')
 29=@('Fail','While signed out, I opened /register/rider and the public Courier Registration page loaded successfully.','Riders can self-register, which conflicts with the requirement that only an approved logistics account can create them.')
 30=@('Pass','Buyer and seller accounts were redirected away from the logistics account page and could not submit the Add Rider request.','Role-based access worked as expected.')
 31=@('Pass','A logistics account that was not approved received an access-denied response and could not add a rider.','Approval-based access worked as expected.')
 32=@('Pass','An approved rider could open the Rider dashboard but was redirected away from logistics, seller, and administrator pages.','The website kept the rider within the Rider area. Mobile menu visibility still needs a phone check.')
 33=@('Blocked','The website form loaded, but this session could not display or inspect the phone’s on-screen keyboard.','Run on a physical phone to verify email, number, date, and password keyboards and field visibility.')
 34=@('Blocked','The website session could not simulate locking the phone or sending the browser to the background.','Run on a physical phone to confirm that typed values remain after locking and reopening.')
 35=@('Blocked','The website session could not rotate a physical device while the form was partly completed.','Run on a phone or tablet to verify rotation and retained values.')
 36=@('Blocked','The website session could not trigger an incoming call while the form was open.','Run on a phone with a real or simulated call interruption.')
 37=@('Blocked','I could not safely disconnect the active device from the network during submission in this session.','Run manually with Airplane mode to verify the offline message, retained values, and absence of a new rider.')
 38=@('Blocked','I could not reproduce a connection drop immediately after tapping Save and then retry from the same browser page.','Run with controllable network interruption and verify that retry creates only one rider.')
 39=@('Blocked','The Add Rider page opened, but this session could not press the physical Back gesture and respond to a leave-page prompt.','Run in a real mobile browser to verify the unsaved-changes prompt and retained form values.')
 40=@('Blocked','The hidden Rider steps contain license, OR, and CR file controls and a camera control. I could reveal and use the file inputs in Chrome, but this computer session could not verify the phone’s Camera, Files, and Gallery chooser as three separate sources.','Repeat on a physical phone. The controls exist; the remaining check is the mobile operating-system chooser and preview behavior.')
 41=@('Fail','I denied camera access in the Rider registration page. The camera stayed closed, but the page showed no permission explanation and no link to the phone or browser Settings.','The upload fields exist, but the required permission-denial guidance is missing.')
 42=@('Pass','I selected an image measuring 5,347,738 bytes. The Rider page compressed it to 8,388 bytes, kept it as the selected license image, and showed no size error.','Oversized-image compression worked and the resulting file stayed below 5 MB.')
 43=@('Blocked','The website loaded, but this session could not change a phone’s system font and display size to the largest setting.','Run on a small physical phone with maximum font and display scaling.')
 44=@('Fail','No activation email or Rider Set Password link was produced. The website requires the logistics administrator to set the rider’s password during creation.','The installed-app activation journey described by the test case is not available.')
 45=@('Fail','No activation link was available to open on a phone without the app.','The mobile web fallback or app-store guidance for rider activation is missing.')
 46=@('Blocked','I signed in as a temporary approved rider and reopened the Rider dashboard; the web session stayed signed in. I could not verify force-closing an installed Rider app or restarting a phone.','The browser-session portion worked, but the original mobile-app restart steps still require a physical device and Rider app.')
 47=@('Pass','I signed in as a temporary approved rider, selected Sign out, and then tried to open the Rider dashboard again. The website returned to Login and did not show rider data.','The website logout and protected-page check worked. A phone Back/recent-app gesture was not available in this session.')
}

try {
    $sheet = Read-Xml 'xl/worksheets/sheet5.xml'
    $counts = @{Pass=0;Fail=0;Blocked=0}
    foreach ($id in 1..47) {
        $row = $id + 1
        $value = $results[$id]
        if (!$value) { throw "Missing result for case $id" }
        $counts[$value[0]]++
        Set-Cell $sheet "K$row" $value[1]
        Set-Cell $sheet "L$row" $value[0]
        Set-Cell $sheet "M$row" $value[2]
    }
    Save-Xml 'xl/worksheets/sheet5.xml' $sheet

    $summary = Read-Xml 'xl/worksheets/sheet1.xml'
    Set-Cell $summary 'A1' 'Rider Account Creation - Website Test Results'
    Set-Cell $summary 'B5' 'PocketFinds locally served website at http://127.0.0.1:8000'
    Set-Cell $summary 'B6' 'Current local application and database'
    Set-Cell $summary 'B7' 'Website session on Windows; no physical phone used'
    Set-Cell $summary 'B8' 'User-style website review assisted by Codex'
    Set-Cell $summary 'B9' '2026-10-24 (Asia/Singapore)'
    Set-Cell $summary 'A20' "All 47 Rider cases were reviewed: $($counts.Pass) Pass, $($counts.Fail) Fail, and $($counts.Blocked) Blocked."
    Set-Cell $summary 'A21' 'Actual Result and Remarks describe what a tester observed on the website. The public internet address was unreachable from the test environment, so the same application was used through its local website.'
    Set-Cell $summary 'A22' 'Blocked cases still require a physical phone, installed Rider app, camera/gallery permissions, phone interruption, font/rotation controls, or controlled network loss. They were not marked Pass or Fail without performing those steps.'
    Set-Cell $summary 'A24' 'Temporary test accounts were used for the login, company-isolation, session, and logout checks and removed after testing.'
    foreach ($rowNum in @(16,17)) {
        foreach ($pair in @(@('D',$counts.Pass),@('E',$counts.Fail),@('F',$counts.Blocked),@('G',0),@('H',($counts.Pass/47.0)))) {
            $cell = $summary.SelectSingleNode("//*[local-name()='c' and @r='$($pair[0])$rowNum']")
            if ($cell -and $cell.v) { $cell.v = ([string]$pair[1]).Replace(',','.') }
        }
    }
    Save-Xml 'xl/worksheets/sheet1.xml' $summary
} finally {
    $zip.Dispose()
}

$check = [IO.Compression.ZipFile]::OpenRead($output)
try {
    foreach ($entry in $check.Entries | Where-Object { $_.FullName -match '\.xml$|\.rels$' }) {
        $reader = [IO.StreamReader]::new($entry.Open())
        try { [xml]$validated = $reader.ReadToEnd() } finally { $reader.Dispose() }
    }
    Write-Output "Updated workbook validated: $output"
    Write-Output ("Pass={0}; Fail={1}; Blocked={2}" -f $counts.Pass,$counts.Fail,$counts.Blocked)
} finally {
    $check.Dispose()
}
