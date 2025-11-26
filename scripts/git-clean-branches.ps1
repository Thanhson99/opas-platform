#!/usr/bin/env pwsh
<#
----------------------------------------
 Git Branch Cleanup Script (Windows / PowerShell)
----------------------------------------
This script automatically:
1. Detects the default branch (main/master/other) based on the remote HEAD
2. Deletes local branches that are already merged into the default branch
3. Deletes local branches whose corresponding remote no longer exists (stale)
4. Can be used in any Git repository without modification

Usage:
  pwsh .\git-clean-branches.ps1              # Uses default remote 'origin'
  pwsh .\git-clean-branches.ps1 upstream     # Uses remote 'upstream'

Notes:
- The default branch itself will NEVER be deleted.
- Run this script from the root of a Git repository.
----------------------------------------
#>

param(
    [string]$Remote = "origin"
)

# Ensure we are inside a Git repository
git rev-parse --is-inside-work-tree 2>$null 1>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: This is not a Git repository." -ForegroundColor Red
    exit 1
}

# Ensure the remote exists
git remote get-url $Remote 2>$null 1>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Remote '$Remote' does not exist." -ForegroundColor Red
    exit 1
}

Write-Host "Detecting default branch for remote '$Remote'..."

# Method 1: Try to read the remote HEAD (e.g. refs/remotes/origin/HEAD)
$defaultBranch = git symbolic-ref --quiet --short "refs/remotes/$Remote/HEAD" 2>$null

if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($defaultBranch)) {
    # If we got something like 'origin/main', strip the remote prefix
    if ($defaultBranch.StartsWith("$Remote/")) {
        $defaultBranch = $defaultBranch.Substring($Remote.Length + 1)
    }
} else {
    # Method 2: Fallback to 'git remote show'
    $remoteInfo = git remote show $Remote 2>$null
    $headLine = $remoteInfo | Where-Object { $_ -match "HEAD branch" }
    if ($headLine) {
        $defaultBranch = ($headLine -replace '.*:\s*', '').Trim()
    }
}

if ([string]::IsNullOrWhiteSpace($defaultBranch)) {
    Write-Host "Error: Could not determine default branch for remote '$Remote'." -ForegroundColor Red
    exit 1
}

Write-Host "Default branch detected: $defaultBranch" -ForegroundColor Green
Write-Host ""

Write-Host "Fetching and pruning from '$Remote'..."
git fetch --prune $Remote
Write-Host ""

# ----------------------------------------
# Delete local branches merged into default branch
# ----------------------------------------
Write-Host "Deleting local branches merged into '$defaultBranch'..."

$mergedBranchesRaw = git branch --merged $defaultBranch 2>$null
$mergedBranches = @()

foreach ($line in $mergedBranchesRaw) {
    $name = $line.Replace("*","").Trim()
    if (-not [string]::IsNullOrWhiteSpace($name) -and $name -ne $defaultBranch) {
        $mergedBranches += $name
    }
}

if ($mergedBranches.Count -gt 0) {
    foreach ($br in $mergedBranches) {
        Write-Host "Deleting merged branch: $br"
        git branch -d $br 2>$null
    }
} else {
    Write-Host "No merged branches to delete."
}
Write-Host ""

# ----------------------------------------
# Delete stale local branches (no matching remote)
# ----------------------------------------
Write-Host "Deleting local branches missing on remote '$Remote'..."

# Get all local branches (refs/heads)
$localBranches = git for-each-ref --format='%(refname:short)' refs/heads/ 2>$null

foreach ($br in $localBranches) {
    $branchName = $br.Trim()

    if ([string]::IsNullOrWhiteSpace($branchName)) {
        continue
    }

    # Do not touch the default branch
    if ($branchName -eq $defaultBranch) {
        continue
    }

    # Check if the remote tracking branch exists
    git show-ref --verify --quiet "refs/remotes/$Remote/$branchName"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Deleting stale local branch: $branchName"
        git branch -D $branchName 2>$null
    }
}

Write-Host ""
Write-Host "Done! ✅" -ForegroundColor Green
