param([string]$BindAddress = '127.0.0.1', [int]$Port = 8081)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Push-Location $projectRoot
try {
    Write-Host "PocketFinds API: http://${BindAddress}:$Port (MySQL pocketfinds)"
    & php -d display_errors=0 -d log_errors=1 -d post_max_size=28M -d upload_max_filesize=20M -S "${BindAddress}:$Port" -t api api/router.php
} finally { Pop-Location }
