#!/bin/bash
# Installs the Claude agents from this repo into ~/.<name> and loads their launchd jobs.
# Usage: agents/install.sh [efesto|zeus|ermes|all]
#
# Reads the settings from ~/.config/yaup/agents.env (or AGENTS_CONFIG). Keep that file out of the repo.
# Set AGENTS_DRY_RUN=1 to render the files into AGENTS_OUT (default: a temp folder) without installing.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
CONFIG="${AGENTS_CONFIG:-$HOME/.config/yaup/agents.env}"
[ -f "$CONFIG" ] || { echo "No config at $CONFIG. Copy agents/config.example.env there and fill it in." >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONFIG"

DRY="${AGENTS_DRY_RUN:-0}"
OUT="${AGENTS_OUT:-$(mktemp -d)}"
AGENTS="${1:-all}"
[ "$AGENTS" = "all" ] && AGENTS="efesto zeus ermes"

render() { # render <source> <destination>
  sed -e "s#__HOME__#$HOME#g" \
      -e "s#__WORKSPACE__#$WORKSPACE#g" \
      -e "s#__SLACK_OWNER_DM__#$SLACK_OWNER_DM#g" \
      -e "s#__SLACK_CHANNEL__#$SLACK_CHANNEL#g" "$1" > "$2"
}

for name in $AGENTS; do
  src="$ROOT/$name"
  [ -d "$src" ] || { echo "unknown agent: $name" >&2; exit 1; }
  home="$HOME/.$name"
  target_home="$home"; plist_dir="$HOME/Library/LaunchAgents"
  if [ "$DRY" = "1" ]; then target_home="$OUT/$name"; plist_dir="$OUT/$name"; fi
  mkdir -p "$target_home/logs" "$plist_dir"
  render "$src/prompt.md" "$target_home/prompt.md"
  render "$src/run.sh" "$target_home/run.sh"
  chmod +x "$target_home/run.sh"
  plist="$plist_dir/local.yaup.$name.plist"
  render "$src/launchd.plist.template" "$plist"
  if [ "$DRY" = "1" ]; then echo "rendered $name to $target_home"; continue; fi
  plutil -lint "$plist" >/dev/null
  launchctl bootout "gui/$(id -u)/local.yaup.$name" 2>/dev/null || true
  launchctl bootstrap "gui/$(id -u)" "$plist"
  echo "installed $name (next run follows its schedule in $plist)"
done
