param([switch]$Register,[switch]$UpdateExisting,[ValidateSet('VerificationOnly','CommissionOnly')][string]$Mode='VerificationOnly',[string]$PhpExecutable='')
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$name=if($Mode -eq 'VerificationOnly'){'PocketFinds-Commission-Reconcile-Verification'}else{'PocketFinds-Commission-Reconcile'}
$identity=[Security.Principal.WindowsIdentity]::GetCurrent()
if(!$PhpExecutable){$phpCommand=Get-Command php -CommandType Application -ErrorAction SilentlyContinue;if($phpCommand){$PhpExecutable=$phpCommand.Source}}
if(!$PhpExecutable -or !(Test-Path -LiteralPath $PhpExecutable -PathType Leaf)){throw 'Provide -PhpExecutable with the installed PHP executable path'}
$PhpExecutable=(Resolve-Path -LiteralPath $PhpExecutable).Path
if(!$Register){[pscustomobject]@{TaskName=$name;Account=$identity.Name;IntervalMinutes=5;Mode=$Mode;Logon='S4U: runs whether the user is logged on or not; local resources only';ChangesApplied=$false}|ConvertTo-Json;exit}
$runner=Join-Path $PSScriptRoot 'run-commission-verification.ps1'
$action=New-ScheduledTaskAction -Execute "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe" -Argument ('-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File "'+$runner+'" -'+$Mode+' -Runs 1 -PhpExecutable "'+$PhpExecutable+'"') -WorkingDirectory $root
$trigger=New-ScheduledTaskTrigger -Daily -At '00:00'
$trigger.Repetition=New-CimInstance -Namespace 'Root/Microsoft/Windows/TaskScheduler' -ClassName MSFT_TaskRepetitionPattern -ClientOnly -Property @{Interval='PT5M';Duration='P1D';StopAtDurationEnd=$false}
$principal=New-ScheduledTaskPrincipal -UserId $identity.User.Value -LogonType S4U -RunLevel Limited
$settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 2) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
# Explicit replacement only, and preserve the previous task definition for recovery.
$existing=Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
if($existing){
    if(!$UpdateExisting){throw 'Task exists. Use -UpdateExisting to back up and replace this commission task.'}
    $expected='-File "'+$runner+'" -'+$Mode+' -Runs 1'
    $expectedWithPhp=$expected+' -PhpExecutable "'+$PhpExecutable+'"'
    if($existing.Actions.Count -ne 1 -or !($existing.Actions[0].Arguments.EndsWith($expected) -or $existing.Actions[0].Arguments.EndsWith($expectedWithPhp))){throw 'Existing task action differs; refusing to replace it'}
    $backup=Join-Path $root ('storage/app/private/commission-scheduler/task-before-'+[DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss-fffffff')+'.xml')
    [void][IO.Directory]::CreateDirectory((Split-Path $backup))
    Export-ScheduledTask -TaskName $name | Set-Content -LiteralPath $backup -Encoding Unicode
}
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description ('Commission-only '+$Mode+'. Unattended S4U, every five minutes. Never invokes the full Laravel scheduler or transfers money.') -Force:$UpdateExisting|Select-Object TaskName,State
