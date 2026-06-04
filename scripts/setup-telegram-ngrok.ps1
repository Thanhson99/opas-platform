$ErrorActionPreference = "Stop"

# -----------------------------------------------------------------------------
# Path and runtime configuration
# - AppDir/LaravelEnvFile: Laravel app and env source of truth.
# - Log/PID files: ngrok + worker lifecycle state for start/status/stop flows.
# -----------------------------------------------------------------------------
$RootDir = Split-Path -Parent $PSScriptRoot
$AppDir = Join-Path $RootDir "apps/laravel"
$LaravelEnvFile = Join-Path $AppDir ".env"
$WebhookScript = Join-Path $PSScriptRoot "setup-telegram-webhook.ps1"
$LogDir = Join-Path $RootDir ".codex-tmp/telegram-ngrok"
$PidFile = Join-Path $LogDir "ngrok.pid"
$LogFile = Join-Path $LogDir "ngrok.log"
$WorkerPidFile = Join-Path $LogDir "auto-coding-worker.pid"
$WorkerLogFile = Join-Path $LogDir "auto-coding-worker.log"
$ApiUrl = "http://127.0.0.1:4040/api/tunnels"
$TunnelUrlFile = Join-Path $LogDir "public-url.txt"

$Mode = "start"
$LaravelPort = if ($env:LARAVEL_PORT) { $env:LARAVEL_PORT } else { "8881" }
$NgrokBin = if ($env:NGROK_BIN) { $env:NGROK_BIN } else { "ngrok" }
$TelegramLocale = if ($env:TELEGRAM_LOCALE) { $env:TELEGRAM_LOCALE } else { "en" }
$DropPendingUpdates = $false
$StepIndex = 0
$StepTotal = 9

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# Show CLI usage for all supported operational modes.
function Show-Usage {
    Write-Host "Usage:"
    Write-Host "  .\scripts\setup-telegram-ngrok.ps1 start [--port=8881] [--lang=en|vi] [--drop-pending-updates]"
    Write-Host "  .\scripts\setup-telegram-ngrok.ps1 status"
    Write-Host "  .\scripts\setup-telegram-ngrok.ps1 stop"
}

# Interactive fallback for operators running the script without flags.
function Show-InteractiveMenu {
    Write-Host "Telegram ngrok setup"
    Write-Host "1. Start"
    Write-Host "2. Status"
    Write-Host "3. Stop"
    Write-Host "4. Help"

    $selection = Read-Host "Choose [1-4] (1)"

    if ([string]::IsNullOrWhiteSpace($selection)) {
        $selection = "1"
    }

    switch ($selection) {
        "1" {
            $customPort = Read-Host "Laravel port [$LaravelPort]"

            if (-not [string]::IsNullOrWhiteSpace($customPort)) {
                $script:LaravelPort = $customPort
            }

            $language = Read-Host "Language [EN/VI] ($TelegramLocale)"

            if (-not [string]::IsNullOrWhiteSpace($language)) {
                $script:TelegramLocale = $language
            }

            $dropPending = Read-Host "Drop pending Telegram updates? [Y/N]"

            if ($dropPending -match '^[Yy]$') {
                $script:DropPendingUpdates = $true
            }
        }
        "2" {
            $script:Mode = "status"
        }
        "3" {
            $script:Mode = "stop"
        }
        "4" {
            Show-Usage
            exit 0
        }
        default {
            throw "[OPAS] Invalid selection."
        }
    }
}

# Check whether a command exists in PATH.
function Command-Exists {
    param([string]$CommandName)

    return $null -ne (Get-Command $CommandName -ErrorAction SilentlyContinue)
}

# Read one key from .env without loading shell env exports.
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

    $value = $line.Substring($Key.Length + 1)

    return $value.Trim('"')
}

# Infer docker-first execution based on DB host conventions.
function Should-UseDocker {
    param([string]$DbHost)

    return $DbHost -in @("postgres", "pgsql", "mysql", "mariadb", "laravel", "laravel-app")
}

# Run Laravel artisan in the runtime that can reach the configured database.
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

    Push-Location $AppDir

    try {
        & php artisan @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "[OPAS] Laravel artisan command failed: $($Arguments -join ' ')"
        }
    } finally {
        Pop-Location
    }
}

# Normalize Telegram locale to supported values.
function Normalize-Locale {
    param([string]$Value)

    $normalized = $Value.ToLowerInvariant().Trim()

    if ($normalized -eq "vi") {
        return "vi"
    }

    return "en"
}

# Import .env Telegram bootstrap settings into the database default bot.
function Bootstrap-TelegramBotFromEnv {
    Invoke-Artisan @("opas:auto-coding:telegram:default-bot:import-env") | Out-Null
}

# Persist the selected Telegram locale on the database default bot.
function Persist-TelegramLocale {
    $script:TelegramLocale = Normalize-Locale $script:TelegramLocale
    Invoke-Artisan @("opas:auto-coding:telegram:default-bot:update", "--locale=$script:TelegramLocale") | Out-Null
}

# Validate the database-backed Telegram runtime before opening webhook traffic.
function Assert-ReadyTelegramRuntime {
    $runtimeJson = Invoke-Artisan @("opas:auto-coding:telegram:runtime")
    $payload = $runtimeJson | ConvertFrom-Json

    if ($null -eq $payload) {
        throw "[OPAS] Unable to decode Telegram runtime payload."
    }

    $missing = @()

    if ($payload.enabled -ne $true) {
        $source = if ($payload.source) { $payload.source } else { "runtime" }
        $missing += "bot runtime is disabled (source: $source)"
    }

    if ([string]::IsNullOrWhiteSpace($payload.bot_token)) {
        $missing += "bot token is missing"
    }

    if ([string]::IsNullOrWhiteSpace($payload.webhook_secret)) {
        $missing += "webhook secret is missing"
    }

    $chatIds = @($payload.allowed_chat_ids)
    $userIds = @($payload.allowed_user_ids)

    if ($chatIds.Count -eq 0 -and $userIds.Count -eq 0) {
        $missing += "at least one allowed chat id or user id is required"
    }

    if ($missing.Count -gt 0) {
        throw "[OPAS] Telegram bot config is not ready: $($missing -join '; '). Configure /admin/auto-coding/telegram-bots or bootstrap AUTO_CODING_TELEGRAM_* env keys."
    }
}

# Resolve provider name from .env for choosing Docker vs host Codex worker.
function Get-AutoCodingProvider {
    $provider = Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_PROVIDER"

    if ([string]::IsNullOrWhiteSpace($provider)) {
        return "null"
    }

    return $provider.ToLowerInvariant().Trim()
}

# Resolve Codex CLI from configured path or PATH.
function Resolve-CodexExecutable {
    param([string]$ConfiguredExecutable)

    if (-not [string]::IsNullOrWhiteSpace($ConfiguredExecutable) -and (Test-Path $ConfiguredExecutable)) {
        return $ConfiguredExecutable
    }

    $command = Get-Command $ConfiguredExecutable -ErrorAction SilentlyContinue

    if ($null -ne $command) {
        return $command.Source
    }

    throw "[OPAS] AUTO_CODING_PROVIDER=codex but Codex CLI is not available. Set AUTO_CODING_CODEX_EXECUTABLE to codex.exe or its full path."
}

# Verify docker compose service "laravel" is running.
function Test-DockerLaravelRunning {
    & docker compose ps --status running laravel *> $null
    return $LASTEXITCODE -eq 0
}

# Verify docker laravel can access the mounted repository git root.
function Test-DockerRepositoryReady {
    & docker compose exec -T laravel sh -lc "git -C /workspace/repo rev-parse --show-toplevel >/dev/null 2>&1" *> $null
    return $LASTEXITCODE -eq 0
}

# Confirm the local Laravel HTTP target is reachable before opening ngrok.
function Test-LocalLaravelTargetReady {
    try {
        $response = Invoke-WebRequest -Uri ("http://127.0.0.1:{0}/" -f $LaravelPort) -Method Get -TimeoutSec 5 -UseBasicParsing
        return $response.StatusCode -ge 200 -and $response.StatusCode -lt 400
    } catch {
        return $false
    }
}

# Execute one setup step with log capture and concise progress output.
function Invoke-Step {
    param(
        [string]$Message,
        [string]$LogName,
        [scriptblock]$Action
    )

    $script:StepIndex++
    $logPath = Join-Path $LogDir $LogName
    Write-Progress -Activity "Telegram setup" -Status $Message -PercentComplete (($script:StepIndex - 1) / $script:StepTotal * 100)

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

# Install ngrok automatically when missing from the current machine.
function Install-NgrokIfMissing {
    if (Command-Exists $NgrokBin) {
        return
    }

    Write-Host "[OPAS] ngrok is not installed. Attempting automatic installation."

    if (Command-Exists "winget") {
        winget install --id Ngrok.Ngrok --exact --accept-package-agreements --accept-source-agreements
    } elseif (Command-Exists "choco") {
        choco install ngrok -y
    } else {
        throw "[OPAS] Could not install ngrok automatically. Install winget or choco first."
    }

    if (-not (Command-Exists $NgrokBin)) {
        $machinePath = [Environment]::GetEnvironmentVariable("Path", "Machine")
        $userPath = [Environment]::GetEnvironmentVariable("Path", "User")
        $env:Path = "$machinePath;$userPath;$env:Path"
    }

    if (-not (Command-Exists $NgrokBin)) {
        throw "[OPAS] ngrok installation completed but the command is still unavailable in PATH."
    }
}

# Read one PID from a persisted pid file.
function Read-Pid {
    param([string]$FilePath = $PidFile)

    if (Test-Path $FilePath) {
        return (Get-Content -Path $FilePath -Raw).Trim()
    }

    return $null
}

# Check whether the given PID currently exists.
function Test-NgrokRunning {
    param([string]$Pid)

    if ([string]::IsNullOrWhiteSpace($Pid)) {
        return $false
    }

    $process = Get-Process -Id $Pid -ErrorAction SilentlyContinue

    return $null -ne $process
}

# Discover ngrok process IDs matching the configured Laravel port.
function Find-NgrokPids {
    $processes = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object {
        $_.Name -match '^ngrok(\.exe)?$' -and $_.CommandLine -match "http\s+$LaravelPort(\s|$)"
    }

    return @($processes | ForEach-Object { [string] $_.ProcessId })
}

# Resolve the current ngrok PID from pid file or process discovery.
function Resolve-NgrokPid {
    $recordedPid = Read-Pid

    if (Test-NgrokRunning $recordedPid) {
        return $recordedPid
    }

    $discoveredPid = Find-NgrokPids | Select-Object -First 1

    if (-not [string]::IsNullOrWhiteSpace($discoveredPid)) {
        Set-Content -Path $PidFile -Value $discoveredPid
        return $discoveredPid
    }

    Remove-Item -Force -ErrorAction SilentlyContinue $PidFile

    return $null
}

# Stop existing ngrok processes and cleanup stale PID files.
function Stop-ExistingNgrok {
    $pidList = @()
    $recordedPid = Read-Pid

    if (-not [string]::IsNullOrWhiteSpace($recordedPid)) {
        $pidList += $recordedPid
    }

    $pidList += Find-NgrokPids
    $pidList = @($pidList | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique)

    if ($pidList.Count -eq 0) {
        Remove-Item -Force -ErrorAction SilentlyContinue $PidFile
        return
    }

    foreach ($pid in $pidList) {
        if (Test-NgrokRunning $pid) {
            Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
            Start-Sleep -Milliseconds 300
        }
    }

    foreach ($pid in (Find-NgrokPids | Select-Object -Unique)) {
        if (Test-NgrokRunning $pid) {
            Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
            Start-Sleep -Milliseconds 150
        }
    }

    Remove-Item -Force -ErrorAction SilentlyContinue $PidFile
}

# Stop existing background worker process and cleanup stale PID file.
function Stop-ExistingWorker {
    $pid = Read-Pid $WorkerPidFile

    if ([string]::IsNullOrWhiteSpace($pid)) {
        Remove-Item -Force -ErrorAction SilentlyContinue $WorkerPidFile
        return
    }

    if (Test-NgrokRunning $pid) {
        Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
        Start-Sleep -Milliseconds 400
    }

    Remove-Item -Force -ErrorAction SilentlyContinue $WorkerPidFile
}

# Start ngrok in background and persist process id.
function Start-Ngrok {
    $arguments = @("http", $LaravelPort, "--log=stdout", "--host-header=rewrite")

    $process = Start-Process -FilePath $NgrokBin -ArgumentList $arguments -RedirectStandardOutput $LogFile -RedirectStandardError $LogFile -PassThru -WindowStyle Hidden
    Set-Content -Path $PidFile -Value $process.Id
}

# Read HTTPS public tunnel URL from local ngrok API.
function Get-HttpsTunnelUrl {
    try {
        $response = Invoke-RestMethod -Uri $ApiUrl -Method Get -TimeoutSec 2
    } catch {
        return $null
    }

    if ($null -eq $response.tunnels) {
        return $null
    }

    foreach ($tunnel in $response.tunnels) {
        if ($null -ne $tunnel.public_url -and $tunnel.public_url.StartsWith("https://")) {
            return $tunnel.public_url
        }
    }

    return $null
}

# Wait for ngrok HTTPS tunnel readiness or fail with diagnostic tail logs.
function Wait-HttpsTunnelUrl {
    param([string]$Pid)

    for ($attempt = 1; $attempt -le 40; $attempt++) {
        if (-not (Test-NgrokRunning $Pid)) {
            Write-Host "[OPAS] ngrok exited before a tunnel became available." -ForegroundColor Red
            if (Test-Path $LogFile) {
                Get-Content -Path $LogFile -Tail 40
            }
            exit 1
        }

        $httpsUrl = Get-HttpsTunnelUrl

        if (-not [string]::IsNullOrWhiteSpace($httpsUrl)) {
            return $httpsUrl
        }

        Start-Sleep -Milliseconds 500
    }

    Write-Host "[OPAS] Timed out waiting for ngrok HTTPS tunnel." -ForegroundColor Red
    if (Test-Path $LogFile) {
        Get-Content -Path $LogFile -Tail 40
    }
    exit 1
}

function Show-Status {
    $pid = Resolve-NgrokPid
    $httpsUrl = Get-HttpsTunnelUrl
    $workerPid = Read-Pid $WorkerPidFile

    if (Test-NgrokRunning $pid) {
        Write-Host "[OPAS] ngrok status: running"
    } elseif (-not [string]::IsNullOrWhiteSpace($httpsUrl)) {
        Write-Host "[OPAS] ngrok status: running"
    } else {
        Write-Host "[OPAS] ngrok status: stopped"
    }

    if (-not [string]::IsNullOrWhiteSpace($httpsUrl)) {
        Write-Host "[OPAS] Public HTTPS URL: available"
    } else {
        Write-Host "[OPAS] Public HTTPS URL: unavailable"
    }

    if (Test-NgrokRunning $workerPid) {
        Write-Host "[OPAS] Auto-coding worker: running"
    } else {
        Write-Host "[OPAS] Auto-coding worker: stopped"
    }
}

function Wait-HttpsTunnelToFile {
    param([string]$Pid)

    $url = Wait-HttpsTunnelUrl $Pid
    Set-Content -Path $TunnelUrlFile -Value $url
}

function Start-AutoCodingWorker {
    $dbHost = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_HOST"
    $provider = Get-AutoCodingProvider

    if ($provider -eq "codex") {
        Start-LocalCodexWorker $dbHost
        return
    }

    if (-not (Should-UseDocker $dbHost)) {
        throw "[OPAS] Docker worker auto-start is only supported when DB_HOST points to the Docker stack."
    }

    if (-not (Command-Exists "docker")) {
        throw "[OPAS] Docker is required to run the Laravel auto-coding worker."
    }

    if (-not (Test-DockerLaravelRunning)) {
        throw "[OPAS] Docker Laravel service is not running. Start it first with: scripts/start-local.sh"
    }

    $arguments = @("compose", "exec", "-T", "laravel", "php", "artisan", "opas:auto-coding:work", "--execute", "--interval=5", "--max-iterations=0")
    $process = Start-Process -FilePath "docker" -ArgumentList $arguments -RedirectStandardOutput $WorkerLogFile -RedirectStandardError $WorkerLogFile -PassThru -WindowStyle Hidden
    Set-Content -Path $WorkerPidFile -Value $process.Id

    Start-Sleep -Seconds 1

    if ($process.HasExited) {
        throw "[OPAS] Docker auto-coding worker exited immediately."
    }
}

function Start-LocalCodexWorker {
    param([string]$DbHost)

    if (-not (Command-Exists "php")) {
        throw "[OPAS] Host PHP is required for AUTO_CODING_PROVIDER=codex because Codex runs outside Docker on this machine."
    }

    if ((Should-UseDocker $DbHost) -and -not (Test-DockerLaravelRunning)) {
        throw "[OPAS] Docker Laravel service is not running. Start it first with: scripts/start-local.ps1"
    }

    $configuredCodex = Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_CODEX_EXECUTABLE"
    $configuredCodex = if ([string]::IsNullOrWhiteSpace($configuredCodex)) { "codex" } else { $configuredCodex }
    $codexExecutable = Resolve-CodexExecutable $configuredCodex
    $hostDbHost = Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_HOST_DB_HOST"
    $hostDbHost = if ([string]::IsNullOrWhiteSpace($hostDbHost)) { "127.0.0.1" } else { $hostDbHost }
    $dbPort = Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_HOST_DB_PORT"
    $dbPort = if ([string]::IsNullOrWhiteSpace($dbPort)) { Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_PORT" } else { $dbPort }
    $dbPort = if ([string]::IsNullOrWhiteSpace($dbPort)) { "5432" } else { $dbPort }
    $dbDatabase = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_DATABASE"
    $dbUsername = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_USERNAME"
    $dbPassword = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_PASSWORD"
    $repositoryPath = Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_LOCAL_WORKER_REPOSITORY_PATH"
    $repositoryPath = if ([string]::IsNullOrWhiteSpace($repositoryPath)) { Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_DEFAULT_REPOSITORY_PATH" } else { $repositoryPath }
    $repositoryPath = if ([string]::IsNullOrWhiteSpace($repositoryPath)) { $RootDir } else { $repositoryPath }
    $promptPath = Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_LOCAL_WORKER_PROMPT_PATH"
    $promptPath = if ([string]::IsNullOrWhiteSpace($promptPath)) { Read-EnvValue -FilePath $LaravelEnvFile -Key "AUTO_CODING_PROMPT_PATH" } else { $promptPath }
    $promptPath = if ([string]::IsNullOrWhiteSpace($promptPath)) { Join-Path $repositoryPath "ai-local/agents/laravel-n8n-orchestrator.md" } else { $promptPath }

    $environment = @{
        DB_HOST = $hostDbHost
        DB_PORT = $dbPort
        DB_DATABASE = if ([string]::IsNullOrWhiteSpace($dbDatabase)) { "laravel" } else { $dbDatabase }
        DB_USERNAME = if ([string]::IsNullOrWhiteSpace($dbUsername)) { "root" } else { $dbUsername }
        DB_PASSWORD = if ($null -eq $dbPassword) { "" } else { $dbPassword }
        AUTO_CODING_DEFAULT_REPOSITORY_PATH = $repositoryPath
        AUTO_CODING_PROMPT_PATH = $promptPath
        AUTO_CODING_CODEX_EXECUTABLE = $codexExecutable
    }

    $previousEnvironment = @{}

    foreach ($key in $environment.Keys) {
        $previousEnvironment[$key] = [Environment]::GetEnvironmentVariable($key, "Process")
        [Environment]::SetEnvironmentVariable($key, [string]$environment[$key], "Process")
    }

    try {
        $process = Start-Process -FilePath "php" -ArgumentList @("artisan", "opas:auto-coding:work", "--execute", "--interval=5", "--max-iterations=0") -WorkingDirectory $AppDir -RedirectStandardOutput $WorkerLogFile -RedirectStandardError $WorkerLogFile -PassThru -WindowStyle Hidden
    } finally {
        foreach ($key in $previousEnvironment.Keys) {
            [Environment]::SetEnvironmentVariable($key, $previousEnvironment[$key], "Process")
        }
    }

    Set-Content -Path $WorkerPidFile -Value $process.Id

    Start-Sleep -Seconds 1

    if ($process.HasExited) {
        throw "[OPAS] Local Codex auto-coding worker exited immediately."
    }
}

function Ensure-LocalRuntimeReady {
    $dbHost = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_HOST"

    if (Should-UseDocker $dbHost) {
        if (-not (Command-Exists "docker")) {
            throw "[OPAS] Docker is required to run the Laravel auto-coding worker."
        }

        if (-not (Test-DockerLaravelRunning)) {
            throw "[OPAS] Docker Laravel service is not running. Start it first with: scripts/start-local.sh"
        }

        if (-not (Test-DockerRepositoryReady)) {
            throw "[OPAS] Docker worker cannot access the repository git root. Recreate the laravel container with: docker compose up -d --force-recreate laravel"
        }
    }

    if (-not (Test-LocalLaravelTargetReady)) {
        throw ("[OPAS] Laravel app is not reachable on http://127.0.0.1:{0}. Start the local app before opening ngrok with: scripts/start-local.sh" -f $LaravelPort)
    }
}

function Register-TelegramWebhookFromTunnel {
    $publicBaseUrl = (Get-Content -Path $TunnelUrlFile -Raw).Trim()
    $command = @($publicBaseUrl)

    if ($DropPendingUpdates) {
        $command += "--drop-pending-updates"
    }

    & $WebhookScript @command
}

foreach ($arg in $args) {
    switch -Regex ($arg) {
        '^start$' { $Mode = "start" }
        '^status$' { $Mode = "status" }
        '^stop$' { $Mode = "stop" }
        '^--port=(.+)$' { $LaravelPort = $Matches[1] }
        '^--lang=(.+)$' { $TelegramLocale = $Matches[1] }
        '^--drop-pending-updates$' { $DropPendingUpdates = $true }
        '^--status$' { $Mode = "status" }
        '^--stop$' { $Mode = "stop" }
        '^--help$|^-h$' {
            Show-Usage
            exit 0
        }
        default {
            throw "Unknown option: $arg"
        }
    }
}

if ($args.Count -eq 0) {
    Show-InteractiveMenu
}

if (-not (Test-Path $WebhookScript)) {
    throw "[OPAS] Missing helper script: $WebhookScript"
}

switch ($Mode) {
    "status" {
        Show-Status
        exit 0
    }
    "stop" {
        Stop-ExistingNgrok
        Stop-ExistingWorker
        Write-Host "[OPAS] Telegram tunnel and auto-coding worker stopped."
        exit 0
    }
}

Stop-ExistingNgrok
Stop-ExistingWorker
Invoke-Step "Verify Docker and Laravel target" "verify-runtime.log" { Ensure-LocalRuntimeReady }
Invoke-Step "Bootstrap Telegram bot config" "bootstrap-telegram-bot.log" { Bootstrap-TelegramBotFromEnv }
Invoke-Step "Persist Telegram locale" "persist-telegram-locale.log" { Persist-TelegramLocale }
Invoke-Step "Validate Telegram bot config" "validate-telegram-bot.log" { Assert-ReadyTelegramRuntime }
Invoke-Step "Check or install ngrok" "install-ngrok.log" { Install-NgrokIfMissing }
Invoke-Step "Start auto-coding worker" "start-auto-coding-worker.log" { Start-AutoCodingWorker }
Invoke-Step "Start ngrok tunnel" "start-ngrok.log" { Start-Ngrok }

$ngrokPid = Read-Pid

if ([string]::IsNullOrWhiteSpace($ngrokPid)) {
    throw "[OPAS] ngrok process id was not recorded."
}

Invoke-Step "Wait for public HTTPS tunnel" "wait-tunnel.log" { Wait-HttpsTunnelToFile $ngrokPid }
Invoke-Step "Register Telegram webhook" "register-webhook.log" { Register-TelegramWebhookFromTunnel }

Write-Host ""
Write-Host "[OPAS] Telegram tunnel, Docker worker, and webhook are ready."
Write-Host "[OPAS] In Telegram: send /start to enable Codex chat, /stop to disable it."
Write-Host "[OPAS] Helper commands: /queue, /changes latest, /clear, /clear_all."
Write-Host "[OPAS] Keep this ngrok process running while testing Telegram."
Write-Host "[OPAS] Stop it later with: scripts\setup-telegram-ngrok.ps1 stop"
