param([switch]$VerificationOnly,[switch]$CommissionOnly,[ValidateRange(1,3)][int]$Runs=1)
$ErrorActionPreference='Stop'
if($VerificationOnly -eq $CommissionOnly){throw 'Choose exactly one explicit mode: VerificationOnly or CommissionOnly'}
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$directory=Join-Path $root 'storage/app/private/commission-scheduler'
[void][IO.Directory]::CreateDirectory($directory)
$start=[DateTime]::UtcNow
try {
for($index=0;$index -lt $Runs;$index++){
    $due=$start.AddSeconds(300*$index)
    while([DateTime]::UtcNow -lt $due){Start-Sleep -Milliseconds 250}
    $stamp=[DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss-fff')+'-'+$PID
    $stdout=Join-Path $directory ($stamp+'.json')
    $stderr=Join-Path $directory ($stamp+'.err')
    $script=Join-Path $root 'scripts/commission-reconcile-verify.php'
    $mode=if($VerificationOnly){'--verify-empty'}else{'--commission-only'}
    $process=Start-Process -FilePath 'C:\php\php.exe' -ArgumentList @(('"'+$script+'"'),$mode) -WorkingDirectory $root -WindowStyle Hidden -RedirectStandardOutput $stdout -RedirectStandardError $stderr -PassThru
    # Retain the handle before waiting: Windows PowerShell can otherwise lose
    # ExitCode when a short-lived Start-Process child exits.
    $processHandle=$process.Handle
    $completed=$process.WaitForExit(60000)
    if(!$completed){$process.Kill();throw 'Commission verification exceeded its 60-second limit'}
    $process.Refresh()
    $exit=$process.ExitCode
    # Require both the process result and a complete structured command result.
    $result=Get-Content -LiteralPath $stdout -Raw|ConvertFrom-Json
    if($null -eq $result -or $null -eq $result.exit_code -or $null -eq $exit -or $exit -ne 0){throw 'Commission child process did not complete successfully'}
    if($result.failed -or $result.exit_code -ne 0 -or ($VerificationOnly -and !$result.read_only)){throw 'Commission reconciliation failed; inspect its private JSON log'}
    if((Get-Item -LiteralPath $stderr).Length -gt 0){throw 'Unexpected PHP diagnostic; inspect private error log'}
    $entry=[pscustomobject]@{Iteration=$index+1;DueUtc=$due.ToString('o');ObservedUtc=$result.started_at;ExitCode=$result.exit_code;ReadOnly=$result.read_only;Obligations=$result.obligations_after;Source=$mode;SessionId=(Get-Process -Id $PID).SessionId;ChildLog=$stdout}
    $entry|ConvertTo-Json -Compress|Add-Content -LiteralPath (Join-Path $directory 'runs.jsonl') -Encoding UTF8
    $entry|ConvertTo-Json -Compress
}
} catch {
    [pscustomobject]@{AtUtc=[DateTime]::UtcNow.ToString('o');Failed=$true;ErrorType=$_.Exception.GetType().FullName;Reason='Verification failed; inspect the matching private child logs and Task Scheduler result'}|ConvertTo-Json -Compress|Add-Content -LiteralPath (Join-Path $directory 'failures.jsonl') -Encoding UTF8
    exit 1
}
