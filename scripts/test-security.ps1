param(
    [string] $BaseUrl = "http://localhost:8000/api"
)

$ErrorActionPreference = "Stop"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Invoke-RestMethod -Method POST -Uri "$BaseUrl/auth/login.php" -WebSession $session -ContentType "application/json" -Body (@{
    email = "admin@palestra.local"
    password = "password"
} | ConvertTo-Json) | Out-Null

$csrfBlocked = $false
try {
    Invoke-RestMethod -Method POST -Uri "$BaseUrl/users/index.php" -WebSession $session -ContentType "application/json" -Body (@{
        full_name = "No Csrf"
        email = "nocsrf@example.com"
        password = "Password123!"
        role = "atleta"
    } | ConvertTo-Json) | Out-Null
} catch {
    $csrfBlocked = ($_.Exception.Response.StatusCode.value__ -eq 419)
}

if (-not $csrfBlocked) {
    throw "CSRF non bloccato"
}

$headers = Invoke-WebRequest -Uri "$BaseUrl/auth/me.php" -WebSession $session -UseBasicParsing
foreach ($header in @("X-Frame-Options", "X-Content-Type-Options", "Referrer-Policy", "Content-Security-Policy")) {
    if (-not $headers.Headers[$header]) {
        throw "Header mancante: $header"
    }
}

Write-Host "OK: CSRF e secure headers verificati"
