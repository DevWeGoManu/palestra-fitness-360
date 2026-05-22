param(
    [string] $BaseUrl = "https://staging.tuodominio.it"
)

$ErrorActionPreference = "Stop"

$checks = @(
    "$BaseUrl/",
    "$BaseUrl/manifest.json",
    "$BaseUrl/service-worker.js",
    "$BaseUrl/api/auth/me.php"
)

foreach ($url in $checks) {
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing
    if ($response.StatusCode -ne 200) {
        throw "$url ha risposto $($response.StatusCode)"
    }
    Write-Host "OK $url"
}

if (-not $BaseUrl.StartsWith("https://")) {
    throw "Staging deve usare HTTPS"
}

Write-Host "OK: staging base raggiungibile"
