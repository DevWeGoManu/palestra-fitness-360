param(
    [string] $BaseUrl = "http://localhost:8000/api"
)

$ErrorActionPreference = "Stop"

function Invoke-Json {
    param(
        [string] $Method,
        [string] $Path,
        [object] $Body = $null,
        [Microsoft.PowerShell.Commands.WebRequestSession] $Session = $null,
        [string] $CsrfToken = ""
    )
    $params = @{ Method = $Method; Uri = "$BaseUrl$Path"; ContentType = "application/json" }
    if ($Session) { $params.WebSession = $Session }
    if ($CsrfToken) { $params.Headers = @{ "X-CSRF-Token" = $CsrfToken } }
    if ($null -ne $Body) { $params.Body = ($Body | ConvertTo-Json -Depth 20) }
    Invoke-RestMethod @params
}

function Assert-True {
    param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}

function Latest-TokenForEmail {
    param([string] $Email)
    $lines = @(Get-Content backend\storage\logs\mail.log -ErrorAction Stop | Where-Object { $_ -like "*$Email*" })
    $line = $lines[-1]
    if ($line -notmatch "token=([a-f0-9]+)") {
        throw "Token non trovato per $Email"
    }
    $matches[1]
}

function Sha256-Hex {
    param([string] $Value)
    $sha = [System.Security.Cryptography.SHA256]::Create()
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($Value)
    (($sha.ComputeHash($bytes) | ForEach-Object { $_.ToString("x2") }) -join "")
}

$stamp = "$(Get-Date -Format 'yyyyMMddHHmmss')-$([guid]::NewGuid().ToString('N').Substring(0, 8))"
$email = "self.$stamp@palestra.local"
$password = "Password123!"
$newPassword = "Password456!"

Write-Host "Registrazione autonoma"
Invoke-Json -Method POST -Path "/auth/register.php" -Body @{
    first_name = "Self"
    last_name = "Register"
    email = $email
    password = $password
    password_confirm = $password
    accepted_terms = $true
    website = ""
} | Out-Null

Write-Host "Login bloccato prima della verifica email"
$blockedUnverified = $false
try {
    Invoke-Json -Method POST -Path "/auth/login.php" -Body @{ email = $email; password = $password } | Out-Null
} catch {
    $blockedUnverified = ($_.Exception.Response.StatusCode.value__ -eq 403)
}
Assert-True $blockedUnverified "Login non bloccato per email non verificata"

Write-Host "Verifica email"
$verifyToken = Latest-TokenForEmail -Email $email
Invoke-Json -Method GET -Path "/auth/verify-email.php?token=$verifyToken" | Out-Null

Write-Host "Login bloccato pending"
$blockedPending = $false
try {
    Invoke-Json -Method POST -Path "/auth/login.php" -Body @{ email = $email; password = $password } | Out-Null
} catch {
    $blockedPending = ($_.Exception.Response.StatusCode.value__ -eq 403)
}
Assert-True $blockedPending "Login non bloccato per account pending"

Write-Host "Approvazione admin"
$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminLogin = Invoke-Json -Method POST -Path "/auth/login.php" -Session $adminSession -Body @{ email = "admin@palestra.local"; password = "password" }
$adminCsrf = $adminLogin.csrf_token
$users = Invoke-Json -Method GET -Path "/users/index.php" -Session $adminSession
$registered = @($users.users | Where-Object { $_.email -eq $email })[0]
Assert-True ($registered.role -eq "atleta" -and $registered.status -eq "pending") "Utente registrato non pending atleta"
Invoke-Json -Method PUT -Path "/users/show.php?id=$($registered.id)" -Session $adminSession -CsrfToken $adminCsrf -Body @{
    full_name = $registered.full_name
    email = $registered.email
    role = "atleta"
    status = "active"
    password = ""
} | Out-Null

Write-Host "Login dopo approvazione"
$userLogin = Invoke-Json -Method POST -Path "/auth/login.php" -Body @{ email = $email; password = $password }
Assert-True ($userLogin.user.role -eq "atleta") "Login atleta registrato fallito"

Write-Host "Richiesta reset password generica"
$unknown = Invoke-Json -Method POST -Path "/auth/request-password-reset.php" -Body @{ email = "missing.$stamp@example.com" }
Assert-True ($unknown.message -like "Se l email esiste*") "Risposta reset non generica"

Write-Host "Reset password"
Invoke-Json -Method POST -Path "/auth/request-password-reset.php" -Body @{ email = $email } | Out-Null
$resetToken = Latest-TokenForEmail -Email $email
Invoke-Json -Method POST -Path "/auth/reset-password.php" -Body @{ token = $resetToken; password = $newPassword; password_confirm = $newPassword } | Out-Null
$newLogin = Invoke-Json -Method POST -Path "/auth/login.php" -Body @{ email = $email; password = $newPassword }
Assert-True ($newLogin.user.email -eq $email) "Login con nuova password fallito"

Write-Host "Token reset monouso"
$usedBlocked = $false
try {
    Invoke-Json -Method POST -Path "/auth/reset-password.php" -Body @{ token = $resetToken; password = "Password789!"; password_confirm = "Password789!" } | Out-Null
} catch {
    $usedBlocked = ($_.Exception.Response.StatusCode.value__ -eq 422)
}
Assert-True $usedBlocked "Token reset usato non bloccato"

Write-Host "Token reset scaduto"
$expiredToken = [guid]::NewGuid().ToString("N") + [guid]::NewGuid().ToString("N")
$expiredHash = Sha256-Hex -Value $expiredToken
docker compose exec -T mysql mysql -u gym_user -pgym_password gym_app -e "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address) VALUES ($($registered.id), '$expiredHash', DATE_SUB(NOW(), INTERVAL 1 MINUTE), '127.0.0.1');" | Out-Null
$expiredBlocked = $false
try {
    Invoke-Json -Method POST -Path "/auth/reset-password.php" -Body @{ token = $expiredToken; password = "Password999!"; password_confirm = "Password999!" } | Out-Null
} catch {
    $expiredBlocked = ($_.Exception.Response.StatusCode.value__ -eq 422)
}
Assert-True $expiredBlocked "Token reset scaduto non bloccato"

Write-Host "OK: registrazione, verifica email, pending, approvazione, reset password e token scaduti verificati"
