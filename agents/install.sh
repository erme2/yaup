#!/bin/bash
# Installs the Claude agents from this repo into ~/.<name> and loads their launchd jobs.
# Usage: agents/install.sh [efesto|dioniso|zeus|ermes|crono|athena|all]
#
# Reads the settings from ~/.config/yaup/agents.env (or AGENTS_CONFIG). Keep that file out of the repo.
# Set AGENTS_DRY_RUN=1 to render the files into AGENTS_OUT (default: a temp folder) without installing.
#
# Each agent folder keeps its schedule in crontab.line (minute, hour, then * * *; `*/30` and plain
# numbers are supported). The installer turns it into a launchd job, local.yaup.<name>. launchd runs
# inside your logged-in session, so the jobs can use the keychain logins (gh, Claude, Slack) that cron
# can't reach. Installing replaces that agent's job and removes its old crontab block, if any.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
CONFIG="${AGENTS_CONFIG:-$HOME/.config/yaup/agents.env}"
[ -f "$CONFIG" ] || { echo "No config at $CONFIG. Copy agents/config.example.env there and fill it in." >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONFIG"

DRY="${AGENTS_DRY_RUN:-0}"
OUT="${AGENTS_OUT:-$(mktemp -d)}"
AGENTS="${1:-all}"
[ "$AGENTS" = "all" ] && AGENTS="efesto dioniso zeus ermes crono athena"

render() { # render <source> <destination>
  sed -e "s#__HOME__#$HOME#g" \
      -e "s#__WORKSPACE__#$WORKSPACE#g" \
      -e "s#__SLACK_OWNER_DM__#$SLACK_OWNER_DM#g" \
      -e "s#__SLACK_CHANNEL__#$SLACK_CHANNEL#g" "$1" > "$2"
}

# make_plist <name> <crontab.line> <target_home> <plist path>
make_plist() {
  python3 -I - "$1" "$2" "$3" "$4" <<'PY'
import sys, itertools
name, line_file, home, out = sys.argv[1:5]
fields = open(line_file).read().split()[:5]
minute, hour = fields[0], fields[1]
if fields[2:5] != ['*', '*', '*']:
    sys.exit(f"{name}: only 'minute hour * * *' schedules are supported")

def expand(field, top):
    if field == '*':
        return [None]
    if field.startswith('*/'):
        return list(range(0, top, int(field[2:])))
    return [int(x) for x in field.split(',')]

minutes, hours = expand(minute, 60), expand(hour, 24)
entries = ''
for h, m in itertools.product(hours, minutes):
    parts = ''
    if h is not None:
        parts += f'\t\t\t<key>Hour</key>\n\t\t\t<integer>{h}</integer>\n'
    if m is not None:
        parts += f'\t\t\t<key>Minute</key>\n\t\t\t<integer>{m}</integer>\n'
    entries += f'\t\t<dict>\n{parts}\t\t</dict>\n'

import os
user_home = os.path.expanduser('~')
plist = f'''<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
\t<key>Label</key>
\t<string>local.yaup.{name}</string>
\t<key>ProgramArguments</key>
\t<array>
\t\t<string>/bin/bash</string>
\t\t<string>{home}/run.sh</string>
\t</array>
\t<key>EnvironmentVariables</key>
\t<dict>
\t\t<key>PATH</key>
\t\t<string>{user_home}/.local/bin:/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin</string>
\t</dict>
\t<key>RunAtLoad</key>
\t<false/>
\t<key>StartCalendarInterval</key>
\t<array>
{entries}\t</array>
\t<key>StandardOutPath</key>
\t<string>{home}/logs/launchd.log</string>
\t<key>StandardErrorPath</key>
\t<string>{home}/logs/launchd.log</string>
</dict>
</plist>
'''
open(out, 'w').write(plist)
PY
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
  # A crontab.line that starts with # means the agent is scheduled elsewhere (Athena, by Codex): copy the files only.
  if grep -q '^#' "$src/crontab.line"; then
    echo "installed $name files in $target_home (scheduled elsewhere, no launchd job)"
    continue
  fi
  plist="$target_home/local.yaup.$name.plist"
  make_plist "$name" "$target_home/crontab.line" "$target_home" "$plist"
  if [ "$DRY" = "1" ]; then echo "rendered $name to $target_home ($(head -c 40 "$src/crontab.line" | cut -d' ' -f1-2))"; continue; fi

  # Drop this agent's crontab block from the cron setup, if it has one.
  begin="# >>> yaup agent: $name"
  end="# <<< yaup agent: $name"
  current="$(crontab -l 2>/dev/null || true)"
  if printf '%s\n' "$current" | grep -qxF "$begin"; then
    printf '%s\n' "$current" | awk -v b="$begin" -v e="$end" '
        $0 == b { skip = 1; next }
        $0 == e { skip = 0; next }
        !skip' | crontab -
  fi

  # Replace the launchd job and load it in the user's GUI session.
  agent_plist="$HOME/Library/LaunchAgents/local.yaup.$name.plist"
  mkdir -p "$HOME/Library/LaunchAgents"
  launchctl bootout "gui/$(id -u)/local.yaup.$name" 2>/dev/null || true
  cp "$plist" "$agent_plist"
  launchctl bootstrap "gui/$(id -u)" "$agent_plist"
  echo "installed $name as launchd job local.yaup.$name ($(cut -d' ' -f1-5 "$src/crontab.line"))"
done
