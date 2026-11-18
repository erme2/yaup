#!/bin/bash
# Athena, the independent PR reviewer: runs headless Codex (codex exec) in the Yaup workspace every 30 minutes.
# Started by its crontab line. Logs in ~/.athena/logs. Reviews open pull requests and posts findings; never fixes.
set -u
HERE="$HOME/.athena"
WORKSPACE="__WORKSPACE__"
LOGS="$HERE/logs"
LOCK="$HERE/run.lock"
export PATH="$HOME/.local/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
# Node from nvm's default version when nvm is installed: Homebrew's node breaks when Homebrew upgrades a library it links.
if [ -s "$HOME/.nvm/nvm.sh" ]; then
  nvm_node=$(bash -c '. "$HOME/.nvm/nvm.sh" --no-use && nvm which default' 2>/dev/null)
  [ -x "$nvm_node" ] && export PATH="$(dirname "$nvm_node"):$PATH"
fi
mkdir -p "$LOGS"
stamp=$(date +%Y-%m-%d_%H%M)
log="$LOGS/$stamp.log"

# One run at a time.
if ! mkdir "$LOCK" 2>/dev/null; then
  echo "$(date) previous run still going, skipping" >> "$LOGS/skipped.log"
  exit 0
fi
trap 'rmdir "$LOCK"' EXIT

if [ ! -d "$WORKSPACE" ]; then
  echo "$(date) $WORKSPACE not mounted, skipping" >> "$LOGS/skipped.log"
  exit 0
fi
cd "$WORKSPACE" || exit 1

# GitHub's connected MCP integration cannot access every private repository.
# Give gh the owner's sweegly token from Keychain so private PRs are discoverable.
if ! GH_TOKEN="$(/usr/bin/security find-generic-password -a sweegly -s codex-ermes-gh-token -w 2>/dev/null)" || [ -z "$GH_TOKEN" ]; then
  echo "$(date) sweegly GitHub token unavailable from Keychain" >> "$LOGS/skipped.log"
  exit 1
fi
export GH_TOKEN

# Workspace-write so the review worktrees and the review comment can be written; network so gh can reach GitHub.
codex exec \
  -C "$WORKSPACE" \
  -s workspace-write \
  -c 'sandbox_workspace_write.network_access=true' \
  --skip-git-repo-check \
  -o "$log.last" \
  "$(cat "$HERE/prompt.md")" > "$log" 2>&1
status=$?

echo "$(date) run finished (exit $status)" >> "$log"
exit "$status"
