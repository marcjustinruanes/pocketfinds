param([string]$Reference = '', [string]$Revision = 'f4158032a1f568bd238e1390ddd93dd440f1c086')
$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
if (!$Reference) { $Reference = Join-Path $workspace '.tmp/website-reference' }
if (!(Test-Path (Join-Path $Reference '.git'))) {
    git clone --filter=blob:none https://github.com/marcjustinruanes/pocketfinds.git $Reference
    if ($LASTEXITCODE) { throw 'Unable to clone the website source.' }
}
$destination = Join-Path $workspace 'api/storage/buyer-website'
New-Item -ItemType Directory -Force $destination | Out-Null
$archive = Join-Path $workspace '.tmp/buyer-latest.tar'
New-Item -ItemType Directory -Force (Split-Path $archive -Parent) | Out-Null
git -C $Reference archive --format=tar --output=$archive $Revision app bootstrap config routes resources public database/migrations composer.json
if ($LASTEXITCODE) { throw 'Unable to export the pinned website revision. Fetch the repository into .tmp/website-reference first.' }
tar -xf $archive -C $destination
if ($LASTEXITCODE) { throw 'Unable to extract the buyer website.' }
# Keep the mobile buyer age policy when refreshing the pinned runtime.
$registrationController = Join-Path $destination 'app/Http/Controllers/RegisterController.php'
$registrationSource = [System.IO.File]::ReadAllText($registrationController)
$oldAgeRule = 'in_array($request->input(''account_type''), [''buyer'', ''rider''], true) ? 18 : 16'
$newAgeRule = '$request->input(''account_type'') === ''rider'' ? 18 : 16'
if (!$registrationSource.Contains($oldAgeRule) -and !$registrationSource.Contains($newAgeRule)) {
    throw 'Website registration age rules changed; review the mobile buyer minimum age before installing.'
}
[System.IO.File]::WriteAllText($registrationController, $registrationSource.Replace($oldAgeRule, $newAgeRule), [System.Text.UTF8Encoding]::new($false))
New-Item -ItemType Directory -Force (Join-Path $destination 'bootstrap/cache') | Out-Null
Set-Content -LiteralPath (Join-Path $destination 'revision.txt') -Value $Revision -Encoding ascii
Write-Output "Installed buyer website $Revision"
