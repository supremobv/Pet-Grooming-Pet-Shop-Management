param([string]$AdminEmail = 'owner@example.com', [switch]$Demo)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
function Invoke-Checked { param([scriptblock]$Action) & $Action; if ($LASTEXITCODE -ne 0) { throw 'Setup stopped. Resolve the error above and run setup again.' } }
if (!(Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    Write-Host 'Created .env. Set your MySQL connection and create its database, then run setup again.'
    exit 0
}
Invoke-Checked { composer install --prefer-dist --no-interaction }
Invoke-Checked { php artisan config:clear }
$keyLine = Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^APP_KEY=.+$' }
if (!$keyLine) { Invoke-Checked { php artisan key:generate } }
Invoke-Checked { php artisan migrate --force }
Invoke-Checked { php artisan app:admin $AdminEmail }
if ($Demo) { Invoke-Checked { php artisan db:seed --class=DemoSeeder } }
Write-Host 'Setup complete. Start the app with: .\scripts\start.ps1'
