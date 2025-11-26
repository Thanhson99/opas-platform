#!/usr/bin/env bash
set -euo pipefail

# ----------------------------------------
#  Git Branch Cleanup Script (macOS / Linux)
# ----------------------------------------
# This script automatically:
# 1. Detects the default branch (main/master/other) based on the remote HEAD
# 2. Deletes local branches that are already merged into the default branch
# 3. Deletes local branches whose corresponding remote no longer exists (stale)
# 4. Can be used in any Git repository without modification
#
# Usage:
#   ./git-clean-branches.sh             # Uses default remote 'origin'
#   ./git-clean-branches.sh upstream    # Uses remote 'upstream'
#
# Notes:
# - The default branch itself will NEVER be deleted.
# - Use at the root of a Git repository.
# ----------------------------------------

REMOTE="${1:-origin}"

# Ensure we are inside a Git repository
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: This is not a Git repository."
  exit 1
fi

# Ensure the remote exists
if ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
  echo "Error: Remote '$REMOTE' does not exist."
  exit 1
fi

echo "Detecting default branch for remote '$REMOTE'..."

# Method 1: Try to read the remote HEAD (e.g. refs/remotes/origin/HEAD)
DEFAULT_BRANCH="$(git symbolic-ref --quiet --short "refs/remotes/$REMOTE/HEAD" 2>/dev/null || true)"

# If we got something like 'origin/main', strip the remote prefix
if [[ -n "$DEFAULT_BRANCH" && "$DEFAULT_BRANCH" == "$REMOTE/"* ]]; then
  DEFAULT_BRANCH="${DEFAULT_BRANCH#"$REMOTE/"}"
fi

# Method 2: Fallback to 'git remote show'
if [[ -z "$DEFAULT_BRANCH" ]]; then
  DEFAULT_BRANCH="$(git remote show "$REMOTE" | sed -n '/HEAD branch/s/.*: //p')"
fi

if [[ -z "$DEFAULT_BRANCH" ]]; then
  echo "Error: Could not determine default branch for remote '$REMOTE'."
  exit 1
fi

echo "Default branch detected: $DEFAULT_BRANCH"
echo

echo "Fetching and pruning from '$REMOTE'..."
git fetch --prune "$REMOTE"
echo

echo "Deleting local branches merged into '$DEFAULT_BRANCH'..."
# Get list of branches already merged into default branch
MERGED_BRANCHES=$(git branch --merged "$DEFAULT_BRANCH" | sed 's/* //g' | grep -v "^$DEFAULT_BRANCH$" || true)

if [[ -n "$MERGED_BRANCHES" ]]; then
  echo "$MERGED_BRANCHES" | while read -r BR; do
    if [[ -n "$BR" ]]; then
      echo "Deleting merged branch: $BR"
      git branch -d "$BR" || true
    fi
  done
else
  echo "No merged branches to delete."
fi
echo

echo "Deleting local branches missing on remote '$REMOTE'..."
# Iterate over all local branches
for BR in $(git for-each-ref --format='%(refname:short)' refs/heads/); do
  # Skip the default branch
  if [[ "$BR" == "$DEFAULT_BRANCH" ]]; then
    continue
  fi

  # If there is no corresponding remote branch, this is a stale local branch
  if ! git show-ref --verify --quiet "refs/remotes/$REMOTE/$BR"; then
    echo "Deleting stale local branch: $BR"
    git branch -D "$BR" || true
  fi
done

echo
echo "Done! ✅"
