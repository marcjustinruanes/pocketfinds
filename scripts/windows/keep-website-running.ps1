param([switch]$Once)

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runtimeDirectory = Join-Path $projectDirectory 'storage/app/private/cloudflare'
$logPath = Join-Path $runtimeDirectory 'website-watchdog.log'
$websiteProcess = $null
$tunnelProcess = $null
$imageCacheProcess = $null
$nextImageCacheRefresh = Get-Date
$mutex = New-Object System.Threading.Mutex($false, 'Local\PocketFindsWebsiteWatchdog')
if (-not $mutex.WaitOne(0)) { return }

function Write-Status([string]$Message) {
    Add-Content -LiteralPath $logPath -Value "$(Get-Date -Format o) $Message"
}

function Test-LocalHealth([string]$Url) {
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 8
        return $response.StatusCode -eq 200
    } catch { return $false }
}

function Test-PortInUse([int]$Port) {
    $listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
    return @($listeners | Where-Object { $_.Port -eq $Port }).Count -gt 0
}

try {
    Write-Status 'Watchdog started.'
    do {
        if ((Get-Date) -ge $nextImageCacheRefresh -and ($null -eq $imageCacheProcess -or $imageCacheProcess.HasExited)) {
            $imageCacheScript = Join-Path $PSScriptRoot 'generate-app-image-cache.php'
            $imageCacheProcess = Start-Process 'C:/php/php.exe' -ArgumentList @(('"' + $imageCacheScript + '"')) -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDirectory 'image-cache.stdout.log') -RedirectStandardError (Join-Path $runtimeDirectory 'image-cache.stderr.log')
            $nextImageCacheRefresh = (Get-Date).AddMinutes(5)
        }
        if (@(8011..8014 | Where-Object { -not (Test-PortInUse $_) }).Count -gt 0) {
            & (Join-Path $PSScriptRoot 'start-apache-website.ps1') -EnsureWorkersOnly
        }
        if (-not (Test-LocalHealth 'http://127.0.0.1:8000/up')) {
            if (-not (Test-PortInUse 8000)) {
                $websiteScript = Join-Path $PSScriptRoot 'start-website.ps1'
                $websiteProcess = Start-Process powershell.exe -ArgumentList @('-NoProfile', '-File', "`"$websiteScript`"") -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDirectory 'website.stdout.log') -RedirectStandardError (Join-Path $runtimeDirectory 'website.stderr.log')
                Write-Status "Started website launcher PID $($websiteProcess.Id)."
            } else { Write-Status 'Website health failed, but port 8000 is occupied; existing process preserved.' }
        }
        if (-not (Test-LocalHealth 'http://127.0.0.1:20245/ready')) {
            if (-not (Test-PortInUse 20245)) {
                $tunnelScript = Join-Path $runtimeDirectory 'start-connector.ps1'
                if ((Test-Path -LiteralPath $tunnelScript) -and (Test-Path -LiteralPath (Join-Path $runtimeDirectory 'tunnel-token.txt'))) {
                    $tunnelProcess = Start-Process powershell.exe -ArgumentList @('-NoProfile', '-File', "`"$tunnelScript`"") -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDirectory 'watchdog-tunnel.stdout.log') -RedirectStandardError (Join-Path $runtimeDirectory 'watchdog-tunnel.stderr.log')
                    Write-Status "Started tunnel launcher PID $($tunnelProcess.Id)."
                } else { Write-Status 'Tunnel launcher or token file missing.' }
            } else { Write-Status 'Tunnel is reconnecting; existing connector preserved.' }
        }
        if (-not $Once) { Start-Sleep -Seconds 15 }
    } while (-not $Once)
} finally {
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}
