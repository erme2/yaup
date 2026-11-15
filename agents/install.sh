#!/bin/bash
# Installs the Claude agents from this repo into ~/.<name> and schedules them in the user's crontab.
# Usage: agents/install.sh [efesto|dioniso|zeus|ermes|crono|all]
#
# Reads the settings from ~/.config/yaup/agents.env (or AGENTS_CONFIG). Keep that file out of the repo.
# Set AGENTS_DRY_RUN=1 to render the files into AGENTS_OUT (default: a temp folder) without installing.
# Each agent owns one marked block in the crontab; installing replaces that block and leaves the others alone.
# The first install also retires the agent's old launchd job, if it had one.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
CONFIG="${AGENTS_CONFIG:-$HOME/.config/yaup/agents.env}"
[ -f "$CONFIG" ] || { echo "No config at $CONFIG. Copy agents/config.example.env there and fill it in." >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONFIG"

DRY="${AGENTS_DRY_RUN:-0}"
OUT="${AGENTS_OUT:-$(mktemp -d)}"
AGENTS="${1:-all}"
[ "$AGENTS" = "all" ] && AGENTS="efesto dioniso zeus ermes crono"

render() { # render <source> <destination>
  sed -e "s#__HOME__#$HOME#g" \
      -e "s#__WORKSPACE__#$WORKSPACE#g" \
      -e "s#__SLACK_OWNER_DM__#$SLACK_OWNER_DM#g" \
      -e "s#__SLACK_CHANNEL__#$SLACK_CHANNEL#g" "$1" > "$2"
}

for name in $AGENTS; do
  src="$ROOT/$name"
  [ -d "$src" ] || { echo "unknown agent: $name" >&2; exit 1; }
  target_home="$HOME/.$name"
  if [ "$DRY" = "1" ]; then target_home="$OUT/$name"; fi
  mkdir -p "$target_home/logs"
  render "$src/prompt.md" "$target_home/prompt.md"
  render "$src/run.sh" "$target_home/run.sh"
  chmod +x "$target_home/run.sh"
  render "$src/crontab.line" "$target_home/crontab.line"
  line="$(cat "$target_home/crontab.line")"
  if [ "$DRY" = "1" ]; then echo "rendered $name to $target_home"; echo "  $line"; continue; fi

  # Retire the launchd job from the earlier setup, if this Mac has one.
  launchctl bootout "gui/$(id -u)/local.yaup.$name" 2>/dev/null || true
  rm -f "$HOME/Library/LaunchAgents/local.yaup.$name.plist"

  # Replace this agent's block in the crontab, keeping every other line.
  begin="# >>> yaup agent: $name"
  end="# <<< yaup agent: $name"
  current="$(crontab -l 2>/dev/null || true)"
  {
    if [ -n "$current" ]; then printf '%s\n' "$current"; fi
  } | awk -v b="$begin" -v e="$end" '
      $0 == b { skip = 1; next }
      $0 == e { skip = 0; next }
      !skip' > "$target_home/crontab.new"
  { cat "$target_home/crontab.new"; printf '%s\n%s\n%s\n' "$begin" "$line" "$end"; } | crontab -
  echo "installed $name in crontab: $line"
done
