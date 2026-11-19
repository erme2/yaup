#!/bin/bash
# Dioniso, the hourly frontend agent for the Sweegly board: runs headless Claude Code in the Yaup workspace on one ticket.
# Started by its crontab line (see crontab.line, installed by agents/install.sh). Logs in ~/.dioniso/logs.
# Holds its own lock (~/.dioniso/run.lock), so a long run skips its own next slot. Efesto has its own lock: the prompts keep the two out of each other's repositories.
set -u
HERE="$HOME/.dioniso"
WORKSPACE="__WORKSPACE__"
LOGS="$HERE/logs"
LOCK="$HERE/run.lock"
mkdir -p "$HERE"
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

notify() { /usr/bin/osascript -e "display notification \"${2//\"/\'}\" with title \"Dioniso\" subtitle \"${1//\"/\'}\"" >/dev/null 2>&1 || true; }

# One Dioniso run at a time: a long ticket can take more than an hour. Apollo also takes this lock while it switches branches.
if ! mkdir "$LOCK" 2>/dev/null; then
  echo "$(date) previous run (or Apollo) still holds the lock, skipping" >> "$LOGS/skipped.log"
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
  "Skill"
  "mcp__claude_ai_Slack__slack_send_message"
  "mcp__claude_ai_Figma__whoami" "mcp__claude_ai_Figma__get_figma_skill" "mcp__claude_ai_Figma__create_session" "mcp__claude_ai_Figma__search_design_system" "mcp__claude_ai_Figma__get_libraries" "mcp__claude_ai_Figma__get_design_context" "mcp__claude_ai_Figma__get_screenshot" "mcp__claude_ai_Figma__get_metadata" "mcp__claude_ai_Figma__get_variable_defs" "mcp__claude_ai_Figma__get_code_connect_map" "mcp__claude_ai_Figma__use_figma" "mcp__claude_ai_Figma__get_comments"
)

# Never switch, reset or stash in the shared checkouts under repos/: the owner works there.
DENIED=(
  "Bash(git checkout:*)" "Bash(git switch:*)" "Bash(git stash:*)" "Bash(git reset:*)" "Bash(git clean:*)"
  "Bash(git restore:*)" "Bash(git pull:*)" "Bash(git rebase:*)" "Bash(git branch -D:*)" "Bash(git push --force:*)"
  "Bash(git -C * checkout:*)" "Bash(git -C * switch:*)" "Bash(git -C * stash:*)" "Bash(git -C * reset:*)"
  "Bash(git -C * clean:*)" "Bash(git -C * restore:*)" "Bash(git -C * pull:*)" "Bash(git -C * rebase:*)"
  "Bash(git -C * push --force:*)"
)
STARTED=$(date +%s)
RUN_NOTE="Run started at epoch $STARTED. End the last Slack message you send in this run with ' (took Xm Ys)', where the time is the output of date +%s minus $STARTED."
claude -p "$(cat "$HERE/prompt.md")

$RUN_NOTE" \
  --model claude-sonnet-5-5 --effort medium \
  --allowedTools "${ALLOWED[@]}" \
  --disallowedTools "${DENIED[@]}" \
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
