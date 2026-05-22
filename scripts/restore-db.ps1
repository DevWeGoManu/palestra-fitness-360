param(
    [Parameter(Mandatory = $true)]
    [string] $File
)

$ErrorActionPreference = "Stop"
if (-not (Test-Path $File)) {
    throw "File backup non trovato: $File"
}

Get-Content $File | docker compose exec -T mysql mysql -u gym_user -pgym_password gym_app
Write-Host "Restore completato da: $File"
