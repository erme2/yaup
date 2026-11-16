#!/bin/bash
# Zeus, the hourly deploy agent: checks pane, latte and sweegly for tags newer than dev and deploys them to dev.
# Started by its crontab line at :10 past each hour. Logs in ~/.zeus/logs.
set -u
HERE="$HOME/.zeus"
WORKSPACE="__WORKSPACE__"
LOGS="$HERE/logs"
LOCK="$HERE/run.lock"
export PATH="$HOME/.local/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
# Claude login under cron: the long-lived token from setup-token, in a 600 file outside the repo.
if [ -r "$HOME/.config/yaup/claude-token" ]; then export CLAUDE_CODE_OAUTH_TOKEN="$(cat "$HOME/.config/yaup/claude-token")"; fi
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

STARTED=$(date +%s)
RUN_NOTE="Run started at epoch $STARTED. End the last Slack message you send in this run with ' (took Xm Ys)', where the time is the output of date +%s minus $STARTED."
claude -p "$(cat "$HERE/prompt.md")

$RUN_NOTE" \
  --model claude-opus-5-5 \
  --allowedTools "${ALLOWED[@]}" \
  --output-format json > "$log.json" 2> "$log"
status=$?

report=$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1])).get("result",""))' "$log.json" 2>/dev/null)
printf '%s\n' "$report" >> "$log"
echo "$(date) run finished (exit $status)" >> "$log"
