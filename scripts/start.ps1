param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
if (!(Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Run composer install first. See docs/SETUP.md.' }
if (!(Test-Path -LiteralPath '.env')) { throw 'Configure .env first. See docs/SETUP.md.' }
php artisan serve --host=127.0.0.1 --port=$Port --no-reload
