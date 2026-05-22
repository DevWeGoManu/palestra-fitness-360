param(
    [string] $BaseUrl = "http://localhost:8000/api",
    [int] $Attempts = 10
)

$ErrorActionPreference = "Stop"
$blocked = 0
for ($i = 1; $i -le $Attempts; $i++) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    try {
        Invoke-RestMethod -Method POST -Uri "$BaseUrl/auth/login.php" -WebSession $session -ContentType "application/json" -Body (@{
            email = "admin@palestra.local"
            password = "wrong-$i"
        } | ConvertTo-Json) | Out-Null
    } catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 429) {
            $blocked++
        }
    }
}

if ($blocked -lt 1) {
    throw "Rate limit login non attivato"
}

Write-Host "OK: rate limit login attivato ($blocked blocchi su $Attempts tentativi)"
