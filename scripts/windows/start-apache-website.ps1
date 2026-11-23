param([int]$Port = 8000, [switch]$EnsureWorkersOnly)
$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$env:POCKETFINDS_ROOT = $projectDirectory.Replace('\', '/')
$env:POCKETFINDS_PORT = "$Port"
$env:APP_URL = 'https://pocketfindsph.shop'
$env:APP_DEBUG = 'false'
if (-not $env:PATH.StartsWith('C:\php;')) { $env:PATH = 'C:\php;' + $env:PATH }
if (-not $EnsureWorkersOnly) {
    & 'C:/xampp/apache/bin/httpd.exe' -f (Join-Path $PSScriptRoot 'website-apache.conf') -t
    if ($LASTEXITCODE -ne 0) { throw 'Apache configuration validation failed.' }
}
$router = Join-Path $projectDirectory 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
$runtime = Join-Path $projectDirectory 'storage/app/private/cloudflare'
foreach ($workerPort in 8011..8014) {
    $listener = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners() |
        Where-Object { $_.Port -eq $workerPort }
    if (-not $listener) {
        Start-Process 'C:/php/php.exe' -ArgumentList @(
            '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=40M',
            '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-S', "127.0.0.1:$workerPort", ('"' + $router + '"')
        ) -WorkingDirectory (Join-Path $projectDirectory 'public') -WindowStyle Hidden `
          -RedirectStandardOutput (Join-Path $runtime "worker-$workerPort.stdout.log") `
          -RedirectStandardError (Join-Path $runtime "worker-$workerPort.stderr.log")
    }
}
if ($EnsureWorkersOnly) { return }
& 'C:/xampp/apache/bin/httpd.exe' -f (Join-Path $PSScriptRoot 'website-apache.conf')
