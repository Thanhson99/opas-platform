$ComposeFile = "docker-compose.yml"
$TargetServices = @("n8n", "ollama")

Write-Host "============================================================"
Write-Host "UPDATE DOCKER IMAGES AND RESTART CONTAINERS"
Write-Host "Compose file : $ComposeFile"
Write-Host "Services     : $($TargetServices -join ', ')"
Write-Host "============================================================"

Write-Host ""
Write-Host "[1/4] Pulling latest images..."
docker compose -f $ComposeFile pull $TargetServices

Write-Host ""
Write-Host "[2/4] Recreating containers to apply new images..."
docker compose -f $ComposeFile up -d --force-recreate $TargetServices

Write-Host ""
Write-Host "[3/4] Container status..."
docker compose -f $ComposeFile ps

Write-Host ""
Write-Host "[4/4] Checking versions..."
Write-Host "- n8n version:"
docker exec -it n8n n8n --version
Write-Host "- ollama version:"
docker exec -it ollama ollama --version

Write-Host ""
Write-Host "✅ Update completed successfully."
