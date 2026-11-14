#!/bin/bash
# Zeus, the hourly deploy agent: checks pane, latte and sweegly for tags newer than dev and deploys them to dev.
# Started by ~/Library/LaunchAgents/local.yaup.zeus.plist at :10 past each hour. Logs in ~/.zeus/logs.
set -u
HERE="$HOME/.zeus"
WORKSPACE="__WORKSPACE__"
LOGS="$HERE/logs"
LOCK="$HERE/run.lock"
export PATH="$HOME/.local/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
mkdir -p "$LOGS"
stamp=$(date +%Y-%m-%d_%H%M)
log="$LOGS/$stamp.log"

# One run at a time: a deploy can take a while, so a run may still be going when the next one starts.
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

ALLOWED=(
  Read Glob Grep
  "Bash(gh:*)" "Bash(curl:*)" "Bash(jq:*)" "Bash(sort:*)" "Bash(grep:*)" "Bash(sed:*)"
  "Bash(sleep:*)" "Bash(date:*)" "Bash(echo:*)" "Bash(printf:*)" "Bash(test:*)" "Bash(head:*)" "Bash(tail:*)"
  "mcp__claude_ai_Slack__slack_send_message"
)

claude -p "$(cat "$HERE/prompt.md")" \
  --model claude-opus-5-5 \
  --allowedTools "${ALLOWED[@]}" \
  --output-format json > "$log.json" 2> "$log"
status=$?

report=$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1])).get("result",""))' "$log.json" 2>/dev/null)
printf '%s\n' "$report" >> "$log"
echo "$(date) run finished (exit $status)" >> "$log"
