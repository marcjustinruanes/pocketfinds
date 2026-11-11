param([Parameter(Mandatory=$true)][string]$PhpExecutable)
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$directory=Join-Path $root 'storage/app/private/commission-scheduler'
[void][IO.Directory]::CreateDirectory($directory)
$statusFile=Join-Path $directory 'installation-status.json'
try {
    $identity=[Security.Principal.WindowsIdentity]::GetCurrent()
    $principal=New-Object Security.Principal.WindowsPrincipal($identity)
    if(!$principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){throw 'Run this installer as Administrator to register the unattended task.'}
    $taskName='PocketFinds-Commission-Reconcile'
    $existing=Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    & (Join-Path $PSScriptRoot 'register-commission-verification.ps1') -Register -UpdateExisting:([bool]$existing) -Mode CommissionOnly -PhpExecutable $PhpExecutable | Out-Null
    $requested=Get-Date
    Start-ScheduledTask -TaskName $taskName
    $deadline=$requested.AddSeconds(90)
    do {
        Start-Sleep -Milliseconds 500
        $task=Get-ScheduledTask -TaskName $taskName
        $info=Get-ScheduledTaskInfo -TaskName $taskName
        $ran=$info.LastRunTime -ge $requested.AddSeconds(-1)
        if($ran -and $task.State -ne 'Running'){break}
    } while((Get-Date) -lt $deadline)
    if(!$ran -or $task.State -eq 'Running'){throw 'Task registered, but its first run did not finish within 90 seconds.'}
    if($info.LastTaskResult -ne 0){throw ('Task registered, but its first run returned '+$info.LastTaskResult+'. Inspect private commission runner logs.')}
    $result=[pscustomobject]@{Installed=$true;Verified=$true;TaskName=$taskName;State=[string]$task.State;IntervalMinutes=5;LogonType=[string]$task.Principal.LogonType;LastTaskResult=$info.LastTaskResult;LastRunTime=$info.LastRunTime.ToString('o');NextRunTime=$info.NextRunTime.ToString('o')}
    $result|ConvertTo-Json|Set-Content -LiteralPath $statusFile -Encoding UTF8
    $result|ConvertTo-Json
} catch {
    [pscustomobject]@{Installed=$false;Verified=$false;Error=$_.Exception.Message;At=[DateTimeOffset]::Now.ToString('o')}|ConvertTo-Json|Set-Content -LiteralPath $statusFile -Encoding UTF8
    exit 1
}
