$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.Security
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$directories=@('storage/app/public','storage/app/private/gcash-proofs','storage/app/private/commission-proofs','storage/app/private/logistics-finance-proofs','storage/app/private/logistics-shipping-proofs','storage/app/private/sensitive-documents','storage/app/private/messages','storage/app/private/message_attachments')
$files=@(Get-Item -LiteralPath (Join-Path $root '.env') -Force)
foreach($relative in $directories){$path=Join-Path $root $relative;if(Test-Path -LiteralPath $path){$files+=@(Get-ChildItem -LiteralPath $path -File -Force -Recurse)}}
$memory=[IO.MemoryStream]::new()
$archive=[IO.Compression.ZipArchive]::new($memory,[IO.Compression.ZipArchiveMode]::Create,$true)
$hash=[Security.Cryptography.SHA256]::Create();$expected=@{}
try{
 foreach($file in $files){
  if($file.LinkType){throw 'Linked source requires a separate backup review'}
  if(!$file.FullName.StartsWith($root+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Unexpected backup source'}
  $relative=$file.FullName.Substring($root.Length+1).Replace('\','/')
  $bytes=[IO.File]::ReadAllBytes($file.FullName)
  $expected[$relative]=[BitConverter]::ToString($hash.ComputeHash($bytes)).Replace('-','')
  $entry=$archive.CreateEntry($relative,[IO.Compression.CompressionLevel]::Optimal);$stream=$entry.Open()
  try{$stream.Write($bytes,0,$bytes.Length)}finally{$stream.Dispose()}
 }
}finally{$archive.Dispose()}
$plain=$memory.ToArray();$memory.Dispose()
$cipher=[Security.Cryptography.ProtectedData]::Protect($plain,$null,[Security.Cryptography.DataProtectionScope]::CurrentUser)
$restored=[Security.Cryptography.ProtectedData]::Unprotect($cipher,$null,[Security.Cryptography.DataProtectionScope]::CurrentUser)
$restoreStream=[IO.MemoryStream]::new($restored,$false)
$restoreArchive=[IO.Compression.ZipArchive]::new($restoreStream,[IO.Compression.ZipArchiveMode]::Read,$false)
$verified=0
try{
 foreach($entry in $restoreArchive.Entries){
  $stream=$entry.Open()
  try{$actual=[BitConverter]::ToString($hash.ComputeHash($stream)).Replace('-','')}finally{$stream.Dispose()}
  if($expected[$entry.FullName] -ne $actual){throw 'Restored backup data differs'}
  $verified++
 }
 if($verified -ne $files.Count -or !$expected.ContainsKey('.env')){throw 'Incomplete backup restore'}
}finally{$restoreArchive.Dispose();$restoreStream.Dispose()}
$destination=Join-Path $root ('storage/app/private/database-backups/private-files-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'.dpapi')
[IO.File]::WriteAllBytes($destination,$cipher)
$report=[pscustomobject]@{CreatedAt=(Get-Date).ToUniversalTime().ToString('o');Files=$files.Count;VerifiedRestoredFiles=$verified;EnvironmentAndAppKeyRestored=$true;Encrypted=$true;Scope='Windows DPAPI CurrentUser';OffHostVerified=$false;RecoveryLimit='Requires this Windows user profile and DPAPI recovery material; not a portable disaster-recovery backup';Sha256=(Get-FileHash -LiteralPath $destination).Hash;Destination=$destination}
$report|ConvertTo-Json|Set-Content -LiteralPath ($destination+'.json') -Encoding UTF8
$report|ConvertTo-Json
[Array]::Clear($plain,0,$plain.Length);[Array]::Clear($restored,0,$restored.Length)
