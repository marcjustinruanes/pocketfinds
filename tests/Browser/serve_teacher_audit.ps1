$ErrorActionPreference = 'Stop'
$auditRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = Join-Path $auditRoot 'storage/app/private/teacher-audit/browser.sqlite'
$env:DB_URL = ''
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'file'
$env:MAIL_MAILER = 'log'
$env:APP_DEBUG = 'false'
Set-Location (Join-Path $auditRoot 'public')
php -S 127.0.0.1:8019 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
