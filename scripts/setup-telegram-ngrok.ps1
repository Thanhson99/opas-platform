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

$Mode = "start"
$LaravelPort = if ($env:LARAVEL_PORT) { $env:LARAVEL_PORT } else { "8881" }
$NgrokBin = if ($env:NGROK_BIN) { $env:NGROK_BIN } else { "ngrok" }
$DropPendingUpdates = $false
$StepIndex = 0
$StepTotal = 6

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

# Show CLI usage for all supported operational modes.
function Show-Usage {
    Write-Host "Usage:"
    Write-Host "  .\scripts\setup-telegram-ngrok.ps1 start [--port=8881] [--drop-pending-updates]"
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
    Set-Content -Path (Join-Path $LogDir "public-url.txt") -Value $url
}

function Start-AutoCodingWorker {
    $dbHost = Read-EnvValue -FilePath $LaravelEnvFile -Key "DB_HOST"

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
    $publicBaseUrl = (Get-Content -Path (Join-Path $LogDir "public-url.txt") -Raw).Trim()
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
Invoke-Step "Check or install ngrok" "install-ngrok.log" { Install-NgrokIfMissing }
Invoke-Step "Verify Docker and Laravel target" "verify-runtime.log" { Ensure-LocalRuntimeReady }
Invoke-Step "Start Docker auto-coding worker" "start-auto-coding-worker.log" { Start-AutoCodingWorker }
Invoke-Step "Start ngrok tunnel" "start-ngrok.log" { Start-Ngrok }

$ngrokPid = Read-Pid

if ([string]::IsNullOrWhiteSpace($ngrokPid)) {
    throw "[OPAS] ngrok process id was not recorded."
}

Invoke-Step "Wait for public HTTPS tunnel" "wait-tunnel.log" { Wait-HttpsTunnelToFile $using:ngrokPid }
Invoke-Step "Register Telegram webhook" "register-webhook.log" { Register-TelegramWebhookFromTunnel }

Write-Host ""
Write-Host "[OPAS] Telegram tunnel, Docker worker, and webhook are ready."
Write-Host "[OPAS] In Telegram: send /start to enable Codex chat, /stop to disable it."
Write-Host "[OPAS] Helper commands: /queue, /changes latest, /clear, /clear_all."
Write-Host "[OPAS] Keep this ngrok process running while testing Telegram."
Write-Host "[OPAS] Stop it later with: scripts\setup-telegram-ngrok.ps1 stop"
