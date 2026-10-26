param([string]$Database = 'paw_polish_test')
$ErrorActionPreference = 'Stop'
if ($Database -notmatch '^[a-zA-Z0-9_]+_test$') { throw 'Use a dedicated test database ending in _test. Tests reset its tables.' }
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$previousConnection = $env:DB_CONNECTION
$previousDatabase = $env:DB_DATABASE
try {
    $env:DB_CONNECTION = 'mysql'
    $env:DB_DATABASE = $Database
    php artisan test
    if ($LASTEXITCODE -ne 0) { throw 'MySQL tests failed.' }
} finally {
    $env:DB_CONNECTION = $previousConnection
    $env:DB_DATABASE = $previousDatabase
}
