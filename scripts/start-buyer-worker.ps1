param([int]$IntervalSeconds = 20)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Push-Location $projectRoot
try {
    while ($true) {
        & php api/buyer-worker.php --run
        Start-Sleep -Seconds ([Math]::Max(5, $IntervalSeconds))
    }
} finally { Pop-Location }
