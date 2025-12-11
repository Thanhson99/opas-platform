$ErrorActionPreference = "Stop"

# Move to repo root (script can run from anywhere)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location (Resolve-Path "$ScriptDir\..")

$OllamaContainer = $env:OLLAMA_CONTAINER
if ([string]::IsNullOrWhiteSpace($OllamaContainer)) { $OllamaContainer = "ollama" }

# Default models if none provided
$Models = $args
if ($Models.Count -eq 0) {
  $Models = @("qwen2.5:7b", "mistral:7b")
}

Write-Host "==> Starting ollama container (if not running)..."
docker compose up -d ollama | Out-Host

Write-Host "==> Waiting for Ollama to be ready..."
$Ready = $false
for ($i=0; $i -lt 60; $i++) {
  try {
    docker exec $OllamaContainer ollama list *> $null
    $Ready = $true
    break
  } catch {
    Start-Sleep -Seconds 1
  }
}

if (-not $Ready) {
  Write-Host "❌ Ollama did not become ready in time."
  Write-Host "Try: docker compose logs -f ollama"
  exit 1
}

Write-Host "==> Pulling models..."
foreach ($m in $Models) {
  Write-Host "---- Pull: $m"
  try {
    docker exec -it $OllamaContainer ollama pull $m | Out-Host
  } catch {
    Write-Host "❌ Failed to pull model: $m"
    Write-Host "Installed models:"
    try { docker exec -it $OllamaContainer ollama list | Out-Host } catch {}
    exit 1
  }
}

Write-Host "==> Installed models:"
docker exec -it $OllamaContainer ollama list | Out-Host

Write-Host "✅ Done. n8n can call: http://ollama:11434/api/chat"
