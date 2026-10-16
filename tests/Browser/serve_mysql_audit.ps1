$ErrorActionPreference = 'Stop'
$auditRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$auditMetadata = Get-Content -LiteralPath (Join-Path $auditRoot 'docs/teacher-test-audit/mysql-audit-database.json') -Raw | ConvertFrom-Json
if ($auditMetadata.database -notmatch '^pocketfinds_teacher_audit_[0-9_]+$') { throw 'Invalid audit database name.' }
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = $auditMetadata.database
$env:DB_URL = ''
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'file'
$env:MAIL_MAILER = 'log'
$env:APP_DEBUG = 'false'
Set-Location (Join-Path $auditRoot 'public')
php -d upload_max_filesize=64M -d post_max_size=160M -d max_file_uploads=50 -S 127.0.0.1:8020 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
