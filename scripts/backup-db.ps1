param(
    [string] $OutDir = "backups"
)

$ErrorActionPreference = "Stop"
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$stamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$file = Join-Path $OutDir "gym_app_$stamp.sql"

docker compose exec -T mysql mysqldump --no-tablespaces -u gym_user -pgym_password gym_app | Set-Content -Encoding UTF8 $file
Write-Host "Backup creato: $file"
