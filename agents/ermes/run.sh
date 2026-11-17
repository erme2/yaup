#!/bin/bash
# Ermes, the hourly board agent: unblocks tickets, re-prioritises and refills Next in line on the Sweegly board.
# Started by its crontab line at :50 past each hour. Logs in ~/.ermes/logs.
set -u
HERE="$HOME/.ermes"
WORKSPACE="__WORKSPACE__"
LOGS="$HERE/logs"
LOCK="$HERE/run.lock"
export PATH="$HOME/.local/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
# Node from nvm's default version when nvm is installed: Homebrew's node breaks when Homebrew upgrades a library it links.
if [ -s "$HOME/.nvm/nvm.sh" ]; then
  nvm_node=$(bash -c '. "$HOME/.nvm/nvm.sh" --no-use && nvm which default' 2>/dev/null)
  [ -x "$nvm_node" ] && export PATH="$(dirname "$nvm_node"):$PATH"
fi
# Claude login under cron: the long-lived token from setup-token, in a 600 file outside the repo.
if [ -r "$HOME/.config/yaup/claude-token" ]; then export CLAUDE_CODE_OAUTH_TOKEN="$(cat "$HOME/.config/yaup/claude-token")"; fi
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

ALLOWED=(
  Read Glob Grep
  "Bash(gh:*)" "Bash(jq:*)" "Bash(python3:*)" "Bash(sort:*)" "Bash(grep:*)" "Bash(sed:*)"
  "Bash(date:*)" "Bash(echo:*)" "Bash(printf:*)" "Bash(test:*)" "Bash(head:*)" "Bash(tail:*)"
  "mcp__claude_ai_Slack__slack_send_message" "mcp__claude_ai_Slack__slack_read_channel"
)

STARTED=$(date +%s)
RUN_NOTE="Run started at epoch $STARTED. End the last Slack message you send in this run with ' (took Xm Ys)', where the time is the output of date +%s minus $STARTED."
PROMPT="$(cat "$HERE/prompt.md")

$RUN_NOTE"
# ERMES_MESSAGE_ONLY=1 sends just the summary message to #sweegly: no board changes, no DMs, no starting line.
if [ "${ERMES_MESSAGE_ONLY:-0}" = "1" ]; then
  PROMPT="$PROMPT

## This run: message only

Skip steps 2a, 2, 3 and 4, the starting line, every DM and every board change. Read the board once, work out the summary message of Slack item 3 from the current state (the Ermes bullet says \`no changes (message-only run)\`), send only that one message to #sweegly, and report in one line."
fi

claude -p "$PROMPT" \
  --model claude-opus-5-5 \
  --allowedTools "${ALLOWED[@]}" \
  --output-format json > "$log.json" 2> "$log"
status=$?

report=$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1])).get("result",""))' "$log.json" 2>/dev/null)
printf '%s\n' "$report" >> "$log"
echo "$(date) run finished (exit $status)" >> "$log"
