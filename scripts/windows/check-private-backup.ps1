$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.Security
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$backup=Get-ChildItem -LiteralPath (Join-Path $root 'storage/app/private/database-backups') -Filter 'private-files-*.dpapi' -File|Sort-Object LastWriteTime -Descending|Select-Object -First 1
if(!$backup){throw 'No protected private-file backup exists'}
$manifest=Get-Content -LiteralPath ($backup.FullName+'.json') -Raw|ConvertFrom-Json
if((Get-FileHash -LiteralPath $backup.FullName).Hash -ne $manifest.Sha256){throw 'Backup checksum mismatch'}
$plain=[Security.Cryptography.ProtectedData]::Unprotect([IO.File]::ReadAllBytes($backup.FullName),$null,[Security.Cryptography.DataProtectionScope]::CurrentUser)
$memory=[IO.MemoryStream]::new($plain,$false)
$archive=[IO.Compression.ZipArchive]::new($memory,[IO.Compression.ZipArchiveMode]::Read,$false)
try{
    $entry=$archive.GetEntry('.env');if(!$entry){throw 'Application key backup missing'}
    $reader=[IO.StreamReader]::new($entry.Open())
    try{$environment=$reader.ReadToEnd()}finally{$reader.Dispose()}
    $live=[IO.File]::ReadAllText((Join-Path $root '.env'))
    $backupKey=[regex]::Match($environment,'(?m)^APP_KEY=[^\r\n]*').Value
    $liveKey=[regex]::Match($live,'(?m)^APP_KEY=[^\r\n]*').Value
    if(!$backupKey -or $backupKey -ne $liveKey){throw 'Backed-up application key differs'}
    $result=[pscustomobject]@{CheckedAt=(Get-Date).ToUniversalTime().ToString('o');ChecksumMatched=$true;Decrypted=$true;Files=$archive.Entries.Count;AppKeyMatches=$true;OriginalRestoreCount=$manifest.VerifiedRestoredFiles;OffHostVerified=$false;PortableRecoveryVerified=$false;RecoveryScope='Existing Windows user/profile DPAPI only'}
    $result|ConvertTo-Json
}finally{$archive.Dispose();$memory.Dispose();[Array]::Clear($plain,0,$plain.Length)}
