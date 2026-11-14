#!/bin/bash
# Efesto, the hourly Sweegly board agent: runs headless Claude Code in the Yaup workspace on one ticket.
# Started by ~/Library/LaunchAgents/local.yaup.efesto.plist. Logs in ~/.efesto/logs.
set -u
HERE="$HOME/.efesto"
WORKSPACE="__WORKSPACE__"
LOGS="$HERE/logs"
LOCK="$HERE/run.lock"
export PATH="$HOME/.local/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"
mkdir -p "$LOGS"
stamp=$(date +%Y-%m-%d_%H%M)
log="$LOGS/$stamp.log"

notify() { /usr/bin/osascript -e "display notification \"${2//\"/\'}\" with title \"Efesto\" subtitle \"${1//\"/\'}\"" >/dev/null 2>&1 || true; }

# One run at a time: a long ticket can take more than an hour.
if ! mkdir "$LOCK" 2>/dev/null; then
  echo "$(date) previous run still going, skipping" >> "$LOGS/skipped.log"
  exit 0
fi
trap 'rmdir "$LOCK"' EXIT

if [ ! -d "$WORKSPACE/repos" ]; then
  echo "$(date) $WORKSPACE not mounted, skipping" >> "$LOGS/skipped.log"
  exit 0
fi
cd "$WORKSPACE" || exit 1

ALLOWED=(
  Read Edit Write Glob Grep
  "Bash(git:*)" "Bash(gh:*)" "Bash(php:*)" "Bash(composer:*)" "Bash(vendor/bin/*)" "Bash(./bash/*)"
  "Bash(npm:*)" "Bash(npx:*)" "Bash(node:*)" "Bash(python3:*)" "Bash(docker:*)" "Bash(bin/yaup:*)"
  "Bash(ls:*)" "Bash(cat:*)" "Bash(head:*)" "Bash(tail:*)" "Bash(grep:*)" "Bash(find:*)" "Bash(sed:*)"
  "Bash(wc:*)" "Bash(sort:*)" "Bash(diff:*)" "Bash(jq:*)" "Bash(mkdir:*)" "Bash(cp:*)" "Bash(mv:*)"
  "Bash(touch:*)" "Bash(echo:*)" "Bash(printf:*)" "Bash(test:*)" "Bash(cd:*)" "Bash(pwd)" "Bash(date:*)"
  "Bash(export:*)" "Bash(sleep:*)"
  "mcp__claude_ai_Slack__slack_send_message"
)

claude -p "$(cat "$HERE/prompt.md")" \
  --model claude-opus-5-5 \
  --allowedTools "${ALLOWED[@]}" \
  --output-format json > "$log.json" 2> "$log"
status=$?

report=$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1])).get("result",""))' "$log.json" 2>/dev/null)
printf '%s\n' "$report" >> "$log"
first=$(printf '%s\n' "$report" | head -1 | cut -c1-200)

if [ $status -ne 0 ] || [ -z "$first" ]; then
  notify "Run failed" "Exit $status. See $log"
else
  case "$first" in
    PR:*) notify "PR opened" "$first" ;;
    NEEDS\ INPUT:*) notify "Needs your answer" "$first" ;;
    STOPPED:*) notify "Stopped" "$first" ;;
    *) notify "Run finished" "$first" ;;
  esac
fi
