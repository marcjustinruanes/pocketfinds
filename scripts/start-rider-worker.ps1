param([int]$IntervalSeconds = 10, [switch]$EnablePush)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Push-Location $projectRoot
try {
    while ($true) {
        if ($EnablePush) { & php api/rider-worker.php --run --push }
        else { & php api/rider-worker.php --run }
        Start-Sleep -Seconds ([Math]::Max(5, $IntervalSeconds))
    }
} finally { Pop-Location }
