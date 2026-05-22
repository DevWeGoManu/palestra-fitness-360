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

    $params = @{
        Method = $Method
        Uri = "$BaseUrl$Path"
        ContentType = "application/json"
    }

    if ($Session) {
        $params.WebSession = $Session
    }
    if ($CsrfToken) {
        $params.Headers = @{ "X-CSRF-Token" = $CsrfToken }
    }
    if ($null -ne $Body) {
        $params.Body = ($Body | ConvertTo-Json -Depth 20)
    }

    Invoke-RestMethod @params
}

function Assert-True {
    param([bool] $Condition, [string] $Message)
    if (-not $Condition) {
        throw $Message
    }
}

$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$athleteSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession

Write-Host "Login admin"
$login = Invoke-Json -Method POST -Path "/auth/login.php" -Session $adminSession -Body @{
    email = "admin@palestra.local"
    password = "password"
}
Assert-True ($login.user.role -eq "admin") "Login admin fallito"
$adminCsrf = $login.csrf_token

Write-Host "Sessione admin"
$me = Invoke-Json -Method GET -Path "/auth/me.php" -Session $adminSession
Assert-True ($me.user.email -eq "admin@palestra.local") "Sessione admin non persistente"

Write-Host "Permessi istruttore"
$coachSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$coachLogin = Invoke-Json -Method POST -Path "/auth/login.php" -Session $coachSession -Body @{
    email = "coach@palestra.local"
    password = "password"
}
$coachCsrf = $coachLogin.csrf_token
$coachBlocked = $false
try {
    Invoke-Json -Method PUT -Path "/users/show.php?id=1" -Session $coachSession -Body @{
        full_name = "Admin Palestra"
        email = "admin@palestra.local"
        password = ""
        role = "admin"
    } -CsrfToken $coachCsrf | Out-Null
} catch {
    $coachBlocked = ($_.Exception.Response.StatusCode.value__ -eq 403)
}
Assert-True $coachBlocked "Istruttore non bloccato su modifica admin"

Write-Host "CRUD utenti"
$stamp = "$(Get-Date -Format 'yyyyMMddHHmmss')-$([guid]::NewGuid().ToString('N').Substring(0, 8))"
$newUser = Invoke-Json -Method POST -Path "/users/index.php" -Session $adminSession -CsrfToken $adminCsrf -Body @{
    full_name = "Atleta Test $stamp"
    email = "atleta.test.$stamp@palestra.local"
    password = "Password123!"
    role = "atleta"
}
Assert-True ($newUser.id -gt 0) "Creazione utente fallita"
$users = Invoke-Json -Method GET -Path "/users/index.php" -Session $adminSession
$createdUsers = @($users.users | Where-Object { [int]$_.id -eq [int]$newUser.id })
Assert-True ($createdUsers.Count -eq 1) "Lista utenti non contiene il nuovo utente"

Invoke-Json -Method PUT -Path "/users/show.php?id=$($newUser.id)" -Session $adminSession -Body @{
    full_name = "Atleta Test Aggiornato $stamp"
    email = "atleta.test.updated.$stamp@palestra.local"
    password = ""
    role = "atleta"
} -CsrfToken $adminCsrf | Out-Null
$updatedUser = Invoke-Json -Method GET -Path "/users/show.php?id=$($newUser.id)" -Session $adminSession
Assert-True ($updatedUser.user.email -like "atleta.test.updated.*") "Modifica utente fallita"

Write-Host "Libreria esercizi"
$library = Invoke-Json -Method GET -Path "/exercises/index.php?muscle_group=petto&q=panca" -Session $adminSession
Assert-True ($library.exercises.Count -ge 1) "Libreria esercizi non restituisce risultati"

Write-Host "CRUD programmi"
$plan = Invoke-Json -Method POST -Path "/workouts/index.php" -Session $adminSession -CsrfToken $adminCsrf -Body @{
    name = "Programma Test $stamp"
    assigned_user_id = $newUser.id
}
Assert-True ($plan.id -gt 0) "Creazione programma fallita"

$loadedPlan = Invoke-Json -Method GET -Path "/workouts/show.php?id=$($plan.id)" -Session $adminSession
Assert-True ($loadedPlan.plan.days.Count -eq 7) "Il programma non ha 7 day"

$loadedPlan.plan.days[0].exercises = @(
    @{
        name = "Squat"
        sets = "4"
        reps = "8"
        weight = "80 kg"
        rest = "120s"
        notes = "Tecnica controllata"
        order_index = 1
    }
)

$savedPlan = Invoke-Json -Method PUT -Path "/workouts/show.php?id=$($plan.id)" -Session $adminSession -CsrfToken $adminCsrf -Body $loadedPlan.plan
Assert-True ($savedPlan.plan.days[0].exercises.Count -eq 1) "Salvataggio esercizio fallito"

$completed = Invoke-Json -Method POST -Path "/sessions/index.php" -Session $adminSession -CsrfToken $adminCsrf -Body @{
    workout_plan_id = $plan.id
}
Assert-True ($completed.id -gt 0) "Completamento allenamento fallito"

$history = Invoke-Json -Method GET -Path "/sessions/index.php?user_id=$($newUser.id)" -Session $adminSession
$createdSessions = @($history.sessions | Where-Object { [int]$_.id -eq [int]$completed.id })
Assert-True ($createdSessions.Count -eq 1) "Storico allenamenti non contiene la sessione"

Write-Host "Login atleta demo e permessi"
$athleteLogin = Invoke-Json -Method POST -Path "/auth/login.php" -Session $athleteSession -Body @{
    email = "atleta@palestra.local"
    password = "password"
}
Assert-True ($athleteLogin.user.role -eq "atleta") "Login atleta fallito"

$athletePlans = Invoke-Json -Method GET -Path "/workouts/index.php" -Session $athleteSession
Assert-True ($null -ne $athletePlans.plans) "Lista programmi atleta fallita"

$forbidden = $false
try {
    Invoke-Json -Method GET -Path "/users/index.php" -Session $athleteSession | Out-Null
} catch {
    $forbidden = ($_.Exception.Response.StatusCode.value__ -eq 403)
}
Assert-True $forbidden "Atleta non bloccato su /users"

Write-Host "Eliminazione programma"
$deleted = Invoke-Json -Method DELETE -Path "/workouts/show.php?id=$($plan.id)" -Session $adminSession -CsrfToken $adminCsrf
Assert-True ($deleted.ok -eq $true) "Eliminazione programma fallita"

Write-Host "Eliminazione utente"
$deletedUser = Invoke-Json -Method DELETE -Path "/users/show.php?id=$($newUser.id)" -Session $adminSession -CsrfToken $adminCsrf
Assert-True ($deletedUser.ok -eq $true) "Eliminazione utente fallita"

Write-Host "OK: API, auth, ruoli, CRUD utenti, libreria esercizi, CRUD programmi e storico verificati"
