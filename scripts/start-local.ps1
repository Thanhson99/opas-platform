$ErrorActionPreference = "Stop"

$ForceRebuild = $false

foreach ($arg in $args) {
    switch ($arg) {
        "--fresh" { $ForceRebuild = $true }
        "--build" { $ForceRebuild = $true }
        default {
            throw "Unknown argument: $arg. Usage: .\scripts\start-local.ps1 [--fresh]"
        }
    }
}

$RootDir = Split-Path -Parent $PSScriptRoot
Set-Location $RootDir

$StepIndex = 0
$StepTotal = 8

function Copy-IfMissing {
    param(
        [string]$Source,
        [string]$Target
    )

    if ((Test-Path $Source) -and -not (Test-Path $Target)) {
        Copy-Item $Source $Target
    }
}

function Set-EnvValue {
    param(
        [string]$Path,
        [string]$Key,
        [string]$Value
    )

    if (-not (Test-Path $Path) -or [string]::IsNullOrWhiteSpace($Value)) {
        return
    }

    $content = Get-Content -Path $Path
    $updated = $false

    for ($i = 0; $i -lt $content.Length; $i++) {
        if ($content[$i] -match "^$([regex]::Escape($Key))=") {
            $content[$i] = "{0}={1}" -f $Key, $Value
            $updated = $true
            break
        }
    }

    if (-not $updated) {
        $content += "{0}={1}" -f $Key, $Value
    }

    Set-Content -Path $Path -Value $content
}

function Invoke-Step {
    param(
        [string]$Message,
        [scriptblock]$Action
    )

    $script:StepIndex++
    Write-Progress -Activity "Starting local stack" -Status $Message -PercentComplete (($script:StepIndex - 1) / $script:StepTotal * 100)
    & $Action | Out-Null
    Write-Host ("[{0}/{1}] {2} done" -f $script:StepIndex, $script:StepTotal, $Message)
}

function Get-SharedNetworkName {
    $defaultName = "shared_net"
    $envFile = Join-Path $RootDir ".env"

    if (-not (Test-Path $envFile)) {
        return $defaultName
    }

    $line = Select-String -Path $envFile -Pattern '^SHARED_NET_NAME=' | Select-Object -Last 1
    if ($null -eq $line) {
        return $defaultName
    }

    $value = $line.Line.Substring("SHARED_NET_NAME=".Length).Trim()
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $defaultName
    }

    return $value
}

function Wait-Url {
    param(
        [string]$Url,
        [int]$MaxAttempts = 60
    )

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri $Url -Method Get -TimeoutSec 5
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
                return
            }
        } catch {
            if ($_.Exception.Response -and $_.Exception.Response.StatusCode.value__ -ge 500) {
                throw "Local web returned HTTP $($_.Exception.Response.StatusCode.value__) while waiting for readiness. Check: docker compose logs laravel --tail=100"
            }
            Start-Sleep -Seconds 2
        }
    }

    throw "Timed out waiting for $Url"
}

Invoke-Step "Prepare env files" {
    Copy-IfMissing (Join-Path $RootDir ".env.example") (Join-Path $RootDir ".env")
    $laravelEnv = Join-Path $RootDir "apps/laravel/.env"
    Copy-IfMissing (Join-Path $RootDir "apps/laravel/.env.example") $laravelEnv
    Copy-IfMissing (Join-Path $RootDir "services/n8n/.env.example") (Join-Path $RootDir "services/n8n/.env")
    Copy-IfMissing (Join-Path $RootDir "services/python/.env.example") (Join-Path $RootDir "services/python/.env")

    $rootEnv = Join-Path $RootDir ".env"
    $rootConfig = @{}
    foreach ($line in Get-Content -Path $rootEnv) {
        if ($line -match '^(?<key>[A-Z0-9_]+)=(?<value>.*)$') {
            $rootConfig[$matches.key] = $matches.value
        }
    }

    Set-EnvValue -Path $laravelEnv -Key "DB_HOST" -Value $rootConfig["LARAVEL_DB_HOST"]
    Set-EnvValue -Path $laravelEnv -Key "DB_DATABASE" -Value $rootConfig["LARAVEL_DB_DATABASE"]
    Set-EnvValue -Path $laravelEnv -Key "DB_USERNAME" -Value $rootConfig["LARAVEL_DB_USERNAME"]
    Set-EnvValue -Path $laravelEnv -Key "DB_PASSWORD" -Value $rootConfig["LARAVEL_DB_PASSWORD"]
}

Invoke-Step "Create shared docker network" {
    $networkName = Get-SharedNetworkName
    docker network inspect $networkName *> $null
    if ($LASTEXITCODE -ne 0) {
        docker network create $networkName | Out-Null
    }
}

if ($ForceRebuild) {
    Invoke-Step "Build and start containers" {
        docker compose up -d --build | Out-Null
    }
} else {
    Invoke-Step "Start containers" {
        docker compose up -d | Out-Null
    }
}

if ($ForceRebuild -or -not (Test-Path (Join-Path $RootDir "apps/laravel/vendor/autoload.php"))) {
    Invoke-Step "Install Laravel PHP dependencies" {
        docker compose exec -T laravel composer install --no-interaction --prefer-dist --no-progress | Out-Null
    }
} else {
    $script:StepIndex++
    Write-Host ("[{0}/{1}] Laravel PHP dependencies already available, skipping" -f $script:StepIndex, $script:StepTotal)
}

if ($ForceRebuild -or -not (Test-Path (Join-Path $RootDir "apps/laravel/public/build/manifest.json"))) {
    Invoke-Step "Build Laravel frontend assets" {
        docker run --rm -v "${RootDir}/apps/laravel:/app" -w /app node:20-alpine sh -lc "npm ci --silent && npm run build -- --logLevel error" | Out-Null
    }
} else {
    $script:StepIndex++
    Write-Host ("[{0}/{1}] Laravel frontend assets already built, skipping" -f $script:StepIndex, $script:StepTotal)
}

$LaravelEnvFile = Join-Path $RootDir "apps/laravel/.env"
$AppKeyLine = ""
if (Test-Path $LaravelEnvFile) {
    $AppKeyLine = (Select-String -Path $LaravelEnvFile -Pattern '^APP_KEY=' | Select-Object -Last 1 | ForEach-Object { $_.Line })
}

if ($ForceRebuild -or [string]::IsNullOrWhiteSpace($AppKeyLine) -or $AppKeyLine -eq "APP_KEY=") {
    Invoke-Step "Generate Laravel app key" {
        docker compose exec -T laravel php artisan key:generate --force --ansi | Out-Null
    }
} else {
    $script:StepIndex++
    Write-Host ("[{0}/{1}] Laravel app key already set, skipping" -f $script:StepIndex, $script:StepTotal)
}

Invoke-Step "Run Laravel migrations" {
    docker compose exec -T laravel php artisan migrate --force --graceful --ansi | Out-Null
}

Invoke-Step "Wait for local web" {
    Wait-Url -Url "http://localhost:8881"
}

Write-Progress -Activity "Starting local stack" -Completed
Start-Process "http://localhost:8881"

Write-Host ""
Write-Host "Local stack is ready."
Write-Host ""
Write-Host "- Laravel App:      http://localhost:8881"
Write-Host "- n8n:              http://localhost:5678"
Write-Host "- LibreTranslate:   http://localhost:8884"
Write-Host ""
