$ErrorActionPreference = "Stop"

# -----------------------------------------------------------------------------
# Path and execution context
# - AppDir/LaravelEnvFile: Laravel runtime location and env source of truth.
# - LogDir: per-step logs for inspect/register/delete/sync operations.
# -----------------------------------------------------------------------------
$RootDir = Split-Path -Parent $PSScriptRoot
$AppDir = Join-Path $RootDir "apps/laravel"
$LaravelEnvFile = Join-Path $AppDir ".env"
$LogDir = Join-Path $RootDir ".codex-tmp/telegram-webhook"

$Mode = "register"
$PublicBaseUrl = ""
$DropPendingUpdates = $false
$SyncCommands = $true
$ClearConfig = $true
$StepIndex = 0
$StepTotal = 4

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# Show supported modes and quick usage examples.
function Show-Usage {
    Write-Host "Usage:"
    Write-Host "  .\scripts\setup-telegram-webhook.ps1 <public-base-url> [--no-sync] [--no-config-clear] [--drop-pending-updates]"
    Write-Host "  .\scripts\setup-telegram-webhook.ps1 --inspect"
    Write-Host "  .\scripts\setup-telegram-webhook.ps1 --delete [--drop-pending-updates]"
    Write-Host "  .\scripts\setup-telegram-webhook.ps1 --sync-commands"
}

# Interactive fallback when the script is executed without flags.
function Show-InteractiveMenu {
    Write-Host "Telegram webhook setup"
    Write-Host "1. Register webhook"
    Write-Host "2. Inspect webhook"
    Write-Host "3. Delete webhook"
    Write-Host "4. Sync bot commands"
    Write-Host "5. Help"

    $selection = Read-Host "Choose an option [1-5]"

    switch ($selection) {
        "1" {
            $script:Mode = "register"
            $baseUrl = Read-Host "Public base URL (example: https://abc123.ngrok-free.app)"

            if ([string]::IsNullOrWhiteSpace($baseUrl)) {
                throw "[OPAS] Public base URL is required."
            }

            $script:PublicBaseUrl = $baseUrl
            $dropPending = Read-Host "Drop pending Telegram updates? [Y/N]"

            if ($dropPending -match '^[Yy]$') {
                $script:DropPendingUpdates = $true
            }
        }
        "2" { $script:Mode = "inspect" }
        "3" {
            $script:Mode = "delete"
            $dropPending = Read-Host "Drop pending Telegram updates while deleting? [y/N]"

            if ($dropPending -match '^[Yy]$') {
                $script:DropPendingUpdates = $true
            }
        }
        "4" { $script:Mode = "sync-commands" }
        "5" {
            Show-Usage
            exit 0
        }
        default {
            throw "[OPAS] Invalid selection."
        }
    }
}

# Read a single key=value from .env.
function Read-EnvValue {
    param(
        [string]$FilePath,
        [string]$Key
    )

    if (-not (Test-Path $FilePath)) {
        return $null
    }

    $line = Get-Content -Path $FilePath | Where-Object { $_ -match "^$Key=" } | Select-Object -Last 1

    if ([string]::IsNullOrWhiteSpace($line)) {
        return $null
    }

    return $line.Substring($Key.Length + 1).Trim('"')
}

# Infer docker-first execution based on DB host conventions.
function Should-UseDocker {
    param([string]$DbHost)

    return $DbHost -in @("postgres", "pgsql", "mysql", "mariadb", "laravel", "laravel-app")
}

# Validate binary dependency availability.
function Require-Command {
    param([string]$CommandName)

    if ($null -eq (Get-Command $CommandName -ErrorAction SilentlyContinue)) {
        throw "[OPAS] Missing required command: $CommandName"
    }
}

# Validate expected files before command execution.
function Require-File {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        throw "[OPAS] Required file not found: $Path"
    }
}

# Normalize URL by trimming a trailing slash.
function Normalize-BaseUrl {
    param([string]$RawUrl)

    return $RawUrl.TrimEnd('/')
}

# Build the exact webhook URL used by Telegram registration.
function Build-WebhookUrl {
    param([string]$BaseUrl)

    return "$BaseUrl/api/telegram/auto-coding/webhook"
}

# Guard against non-HTTPS webhook URLs.
function Assert-HttpsUrl {
    param([string]$Url)

    if (-not $Url.StartsWith("https://")) {
        throw "[OPAS] Telegram webhook URLs must use HTTPS: $Url"
    }
}

# Run artisan from the runtime that can reach the configured database.
function Invoke-Artisan {
    param([string[]]$Arguments)

    $dbHost = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_HOST"

    if (Should-UseDocker $dbHost) {
        Push-Location $RootDir

        try {
            & docker compose exec -T laravel php artisan @Arguments
            if ($LASTEXITCODE -ne 0) {
                throw "[OPAS] Laravel artisan command failed: $($Arguments -join ' ')"
            }
        } finally {
            Pop-Location
        }

        return
    }

    try {
        Push-Location $AppDir
        & php artisan @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "[OPAS] Laravel artisan command failed: $($Arguments -join ' ')"
        }
    } finally {
        Pop-Location
    }
}

# Read the active Telegram bot runtime payload from Laravel.
function Read-TelegramRuntimeJson {
    Push-Location $AppDir

    try {
        & php artisan opas:auto-coding:telegram:runtime
    } finally {
        Pop-Location
    }
}

# Validate the database-backed default Telegram bot before mutating webhook state.
function Assert-RequiredTelegramRuntime {
    $runtimeJson = Read-TelegramRuntimeJson
    $payload = $runtimeJson | ConvertFrom-Json

    if ($null -eq $payload) {
        throw "[OPAS] Unable to decode Telegram runtime payload."
    }

    if ($payload.enabled -ne $true) {
        throw "[OPAS] The default Telegram bot is disabled in the admin settings."
    }

    if ([string]::IsNullOrWhiteSpace($payload.bot_token)) {
        throw "[OPAS] The default Telegram bot is missing a bot token in admin settings."
    }

    if ([string]::IsNullOrWhiteSpace($payload.webhook_secret)) {
        throw "[OPAS] The default Telegram bot is missing a webhook secret in admin settings."
    }

    $chatIds = @($payload.allowed_chat_ids)
    $userIds = @($payload.allowed_user_ids)

    if ($chatIds.Count -eq 0 -and $userIds.Count -eq 0) {
        throw "[OPAS] The default Telegram bot needs at least one allowed chat or user id in admin settings."
    }
}

# Execute one operational step with local log capture.
function Invoke-Step {
    param(
        [string]$Message,
        [string]$LogName,
        [scriptblock]$Action
    )

    $script:StepIndex++
    $logPath = Join-Path $LogDir $LogName
    Write-Progress -Activity "Telegram webhook setup" -Status $Message -PercentComplete (($script:StepIndex - 1) / $script:StepTotal * 100)

    try {
        & $Action *>&1 | Out-File -FilePath $logPath -Encoding utf8
        Write-Host ("[{0}/{1}] {2} done" -f $script:StepIndex, $script:StepTotal, $Message)
    } catch {
        Write-Host ("[{0}/{1}] {2} failed" -f $script:StepIndex, $script:StepTotal, $Message) -ForegroundColor Red

        if (Test-Path $logPath) {
            Write-Host "[OPAS] Recent log output:" -ForegroundColor Yellow
            Get-Content -Path $logPath -Tail 40
        }

        throw
    }
}

foreach ($arg in $args) {
    switch -Regex ($arg) {
        '^--inspect$' { $Mode = "inspect" }
        '^--delete$' { $Mode = "delete" }
        '^--sync-commands$' { $Mode = "sync-commands" }
        '^--drop-pending-updates$' { $DropPendingUpdates = $true }
        '^--no-sync$' { $SyncCommands = $false }
        '^--no-config-clear$' { $ClearConfig = $false }
        '^--help$|^-h$' {
            Show-Usage
            exit 0
        }
        '^--.+$' {
            throw "Unknown option: $arg"
        }
        default {
            if (-not [string]::IsNullOrWhiteSpace($PublicBaseUrl)) {
                throw "Only one public base URL can be provided."
            }

            $PublicBaseUrl = $arg
        }
    }
}

if ($args.Count -eq 0) {
    Show-InteractiveMenu
}

Require-File (Join-Path $AppDir "artisan")
Require-File $LaravelEnvFile

if (Should-UseDocker (Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_HOST")) {
    Require-Command "docker"
} else {
    Require-Command "php"
}

switch ($Mode) {
    "inspect" {
        $StepTotal = if ($ClearConfig) { 2 } else { 1 }

        if ($ClearConfig) {
            Invoke-Step "Clear Laravel config cache" "inspect-config-clear.log" { Invoke-Artisan @("config:clear") }
        }

        Invoke-Step "Inspect Telegram webhook state" "inspect-webhook.log" { Invoke-Artisan @("opas:auto-coding:telegram:webhook") }
    }
    "delete" {
        Assert-RequiredTelegramRuntime
        $StepTotal = if ($ClearConfig) { 2 } else { 1 }

        if ($ClearConfig) {
            Invoke-Step "Clear Laravel config cache" "delete-config-clear.log" { Invoke-Artisan @("config:clear") }
        }

        if ($DropPendingUpdates) {
            Invoke-Step "Delete Telegram webhook" "delete-webhook.log" { Invoke-Artisan @("opas:auto-coding:telegram:webhook-delete", "--drop-pending-updates") }
        } else {
            Invoke-Step "Delete Telegram webhook" "delete-webhook.log" { Invoke-Artisan @("opas:auto-coding:telegram:webhook-delete") }
        }
    }
    "sync-commands" {
        Assert-RequiredTelegramRuntime
        $StepTotal = if ($ClearConfig) { 2 } else { 1 }

        if ($ClearConfig) {
            Invoke-Step "Clear Laravel config cache" "sync-config-clear.log" { Invoke-Artisan @("config:clear") }
        }

        Invoke-Step "Sync Telegram bot commands" "sync-commands.log" { Invoke-Artisan @("opas:auto-coding:telegram:commands-sync") }
    }
    "register" {
        if ([string]::IsNullOrWhiteSpace($PublicBaseUrl)) {
            throw "[OPAS] A public base URL is required for webhook registration."
        }

        Assert-RequiredTelegramRuntime

        $normalizedBaseUrl = Normalize-BaseUrl $PublicBaseUrl
        $webhookUrl = Build-WebhookUrl $normalizedBaseUrl
        Assert-HttpsUrl $webhookUrl
        $StepTotal = 2 + [int]$ClearConfig + [int]$SyncCommands

        if ($ClearConfig) {
            Invoke-Step "Clear Laravel config cache" "register-config-clear.log" { Invoke-Artisan @("config:clear") }
        }

        if ($DropPendingUpdates) {
            Invoke-Step "Register Telegram webhook" "register-webhook.log" { Invoke-Artisan @("opas:auto-coding:telegram:webhook", $webhookUrl, "--drop-pending-updates") }
        } else {
            Invoke-Step "Register Telegram webhook" "register-webhook.log" { Invoke-Artisan @("opas:auto-coding:telegram:webhook", $webhookUrl) }
        }

        if ($SyncCommands) {
            Invoke-Step "Sync Telegram bot commands" "register-commands.log" { Invoke-Artisan @("opas:auto-coding:telegram:commands-sync") }
        }

        Invoke-Step "Verify Telegram webhook state" "register-verify.log" { Invoke-Artisan @("opas:auto-coding:telegram:webhook") }
    }
}
