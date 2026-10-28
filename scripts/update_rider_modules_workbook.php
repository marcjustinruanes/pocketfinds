<?php
$source = $argv[1];
$target = $argv[2];
copy($source, $target);

$pass = ['RID-PROF-007','RID-JOB-007','RID-DLV-002','RID-DLV-007','RID-FAIL-002','RID-EARN-006'];
$results = [
'RID-PROF-001'=>['The profile shows the rider, company, status, vehicle, plate, license number and expiry. The uploaded license and vehicle documents cannot be opened from this screen.','Add links or preview buttons for each submitted document.'],
'RID-PROF-002'=>['The account screen has no controls for changing the plate number or replacing OR/CR files.','Vehicle changes and document re-verification cannot be completed.'],
'RID-PROF-003'=>['There is no plate-number field on the account form, so duplicate plate values cannot be submitted or checked.','Add plate editing with a case-insensitive duplicate check.'],
'RID-PROF-004'=>['The license expiry is shown as read-only information and cannot be changed.','The expiry boundary cannot be checked through the Rider account.'],
'RID-PROF-005'=>['There is no driver-license upload control in the Rider account.','File type and 5 MB limits are not available in this flow.'],
'RID-PROF-006'=>['A suspended Rider cannot enter the Rider area, but there is no Online control or suspension message in the expected flow.','Access is restricted, but the full expected behavior is missing.'],
'RID-PROF-007'=>['Changing the requested record does not reveal another Rider profile; Rider access remains limited to the signed-in account.','Another Rider’s personal details were not exposed.'],
'RID-PROF-008'=>['The Rider account does not offer camera capture or file replacement for license and OR/CR documents.','Document replacement and re-verification are unavailable.'],

'RID-AVAIL-001'=>['The dashboard has no Online toggle and does not report a live availability state to Logistics. Jobs are shown as open requests.','Online-based automatic offers are not implemented.'],
'RID-AVAIL-002'=>['There is no Offline toggle or availability-based exclusion from assignment.','The expected Offline assignment rule is unavailable.'],
'RID-AVAIL-003'=>['The Rider cannot switch availability while keeping an active job because no availability control is provided.','Active-job behavior cannot match the expected flow.'],
'RID-AVAIL-004'=>['Logging out ends access, but no Rider availability state is changed or displayed to Logistics.','The expected automatic Offline state is missing.'],
'RID-AVAIL-005'=>['Going Online does not request location because the Rider area has no Online control or live-location permission flow.','No permission message or Settings link is shown.'],
'RID-AVAIL-006'=>['Turning location off has no effect on assignment because live Rider location and availability are not used.','Location-loss warnings and offer blocking are missing.'],
'RID-AVAIL-007'=>['Closing the Rider screen does not create an Offline state after five minutes.','Presence tracking and automatic timeout are unavailable.'],

'RID-JOB-001'=>['A Rider can open an available request and accept it. The request does not behave as a timed offer and does not show the expected COD and fixed pickup pay details.','Acceptance works, but the full offer details and assignment flow do not match.'],
'RID-JOB-002'=>['Available requests have Accept only; there is no Decline action that forwards the offer to another Rider.','Decline and next-Rider routing are missing.'],
'RID-JOB-003'=>['Requests have no 60-second countdown and do not expire after the expected time.','The offer timeout boundary is not enforced.'],
'RID-JOB-004'=>['There is no decline counter or automatic move to an Unassigned queue after three declines.','The expected reassignment sequence is unavailable.'],
'RID-JOB-005'=>['First acceptance is protected from a second acceptance, but there is no timed offer versus manual-assignment conflict flow with the expected message.','Only the first-come claim behavior is present.'],
'RID-JOB-006'=>['The Rider can accept requests without a three-active-job limit being shown or enforced in this flow.','A fourth-job boundary is not available.'],
'RID-JOB-007'=>['A Rider cannot accept a request belonging to another logistics company; the request is not made available and the parcel remains unchanged.','Company access separation worked as expected.'],
'RID-JOB-008'=>['No locked-screen push offer or continuing 60-second countdown is provided.','Timed push offers are not implemented.'],
'RID-JOB-009'=>['The pickup address is displayed as text, but there is no Navigate action to open a maps app.','Map handoff is unavailable.'],

'RID-PICK-001'=>['The accepted pickup shows tracking number, seller, address, contact and amount. It does not show the expected parcel count in the pickup details.','The displayed details are incomplete.'],
'RID-PICK-002'=>['Pickup is confirmed with a button and requires the seller’s confirmation; there is no waybill scan or fixed pickup-pay entry.','The expected scan flow and ₱30 credit are missing.'],
'RID-PICK-003'=>['There is no field for typing a tracking number to confirm pickup.','Manual tracking-number confirmation is unavailable.'],
'RID-PICK-004'=>['Another or random tracking number cannot be entered because the tracking input is absent.','The required mismatch validation message is not provided.'],
'RID-PICK-005'=>['A second receipt confirmation is stopped after the first, but there is no scan event or pickup-pay record to verify.','Duplicate confirmation is limited, but the full expected result is not met.'],
'RID-PICK-006'=>['The pickup screen has no Scan action, camera permission prompt, Settings link or tracking-number input.','The denied-camera fallback is unavailable.'],
'RID-PICK-007'=>['Pickup confirmation has no offline queue or visible retry state. It requires a normal connected submission.','The no-connection behavior does not meet the expected result.'],

'RID-DLV-001'=>['The Rider can move a parcel to Out for Delivery and then Delivered, but no proof photo, delivery OTP or COD amount is requested.','The delivery can finish without the required proof and payment checks.'],
'RID-DLV-002'=>['Delivered is not offered before Out for Delivery, and a direct attempt cannot skip the required status sequence.','The status remained unchanged when the sequence was bypassed.'],
'RID-DLV-003'=>['A delivery can be completed without attaching any photo, and there is no upload-size check on the action.','Proof of delivery is not required.'],
'RID-DLV-004'=>['The delivery form has no OTP field, wrong-code counter or 15-minute lock.','OTP verification is missing.'],
'RID-DLV-005'=>['The delivery form does not ask for the collected COD amount.','Underpayment and overpayment cannot be checked.'],
'RID-DLV-006'=>['No COD amount is requested, but the job also does not show a Paid or ₱0.00 label or a COD balance.','The prepaid indication and balance check are missing.'],
'RID-DLV-007'=>['A Rider cannot open or update a delivery assigned to another Rider, and that delivery remains unchanged.','Delivery ownership protection worked as expected.'],
'RID-DLV-008'=>['There is no proof-photo action, so neither gallery refusal nor live camera capture can be completed.','Live proof capture is unavailable.'],
'RID-DLV-009'=>['There is no proof photo, OTP, COD entry, offline save or Retry flow for completing delivery.','The expected offline delivery protection is missing.'],
'RID-DLV-010'=>['Message Buyer opens the Rider conversation, but there is no Call action or dialer handoff.','Messaging is present; calling is missing.'],
'RID-DLV-011'=>['Completing a delivery records the time, but no GPS position is captured or shown to Logistics.','Delivery location evidence is missing.'],

'RID-FAIL-001'=>['A reason can be entered and the job becomes Failed Delivery, with a buyer notification. The attempt number and fixed zero-pay result are not shown.','Failure recording is only partially implemented.'],
'RID-FAIL-002'=>['Submitting the failed-delivery form without a reason is blocked and the delivery status stays unchanged.','Required-reason validation worked as expected.'],
'RID-FAIL-003'=>['Choosing or typing Refused records a normal Failed Delivery instead of moving directly to Return to Sender.','The special Refused transition is missing.'],
'RID-FAIL-004'=>['There is no attempt counter or Rider reschedule flow for a second delivery attempt.','The expected attempt 2 sequence is unavailable.'],
'RID-FAIL-005'=>['Failed deliveries do not track three attempts or automatically move to Return to Sender after the third failure.','The third-attempt boundary is not enforced.'],

'RID-EARN-001'=>['History lists finished jobs once with tracking number, parties, amount, date and status. It has no date filter and does not show pay per job.','History is visible, but filtering and pay details are missing.'],
'RID-EARN-002'=>['The earnings screen totals shipping fees from delivered orders instead of ₱30 per pickup and ₱50 per successful delivery. It has no Today or This Week view.','The total does not follow the expected Rider pay rule.'],
'RID-EARN-003'=>['There is no COD balance screen or list of collected COD orders.','The expected ₱1,800 balance cannot be reviewed.'],
'RID-EARN-004'=>['There is no Rider remittance form, pending state or Logistics confirmation flow.','COD remittance is unavailable.'],
'RID-EARN-005'=>['A short remittance cannot be submitted because the remittance feature is absent.','Shortfall detection and outstanding balance are unavailable.'],
'RID-EARN-006'=>['A Rider cannot use Logistics-only actions to confirm a remittance; access is rejected.','Role protection worked as expected.'],
];

$zip = new ZipArchive();
if ($zip->open($target) !== true) throw new RuntimeException('Could not open output workbook.');
$sharedXml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
$sharedXml->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$shared=[];
foreach($sharedXml->xpath('//x:si') as $si){$si->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$p=[];foreach($si->xpath('.//x:t') as $t)$p[]=(string)$t;$shared[]=implode('',$p);}

function setInline(DOMDocument $dom, DOMXPath $xp, DOMElement $row, string $ref, string $text): void {
    $cells=$xp->query('./x:c[@r="'.$ref.'"]',$row);
    if($cells->length){$cell=$cells->item(0);while($cell->firstChild)$cell->removeChild($cell->firstChild);}else{$cell=$dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main','c');$cell->setAttribute('r',$ref);$row->appendChild($cell);}
    $cell->setAttribute('t','inlineStr');
    $is=$dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main','is');
    $t=$dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main','t');$t->appendChild($dom->createTextNode($text));$is->appendChild($t);$cell->appendChild($is);
}

for($sheet=5;$sheet<=11;$sheet++){
    $name="xl/worksheets/sheet{$sheet}.xml";$raw=$zip->getFromName($name);
    $dom=new DOMDocument();$dom->preserveWhiteSpace=false;$dom->formatOutput=false;$dom->loadXML($raw);
    $xp=new DOMXPath($dom);$xp->registerNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    foreach($xp->query('//x:sheetData/x:row') as $row){
        $a=$xp->query('./x:c[@r="A'.$row->getAttribute('r').'"]/x:v',$row);
        if(!$a->length)continue;$idx=(int)$a->item(0)->nodeValue;$id=$shared[$idx]??'';
        if(!isset($results[$id]))continue;$n=$row->getAttribute('r');
        setInline($dom,$xp,$row,'K'.$n,$results[$id][0]);
        setInline($dom,$xp,$row,'L'.$n,in_array($id,$pass,true)?'Pass':'Fail');
        setInline($dom,$xp,$row,'M'.$n,$results[$id][1]);
    }
    $zip->addFromString($name,$dom->saveXML());
}
$workbook=$zip->getFromName('xl/workbook.xml');
$workbook=str_replace('<calcPr ', '<calcPr calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1" ', $workbook);
$zip->addFromString('xl/workbook.xml',$workbook);
$zip->close();
echo "Updated ".count($results)." cases: ".count($pass)." Pass, ".(count($results)-count($pass))." Fail, 0 Blocked\n";
