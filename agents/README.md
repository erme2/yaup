# Yaup agents

The Claude agents that run unattended on the owner's Mac, kept here so they can be reviewed, versioned and reinstalled (for example on a Raspberry Pi).

| Agent | Role | Schedule (local time) | Folder |
|---|---|---|---|
| Efesto | Takes the next Next in line ticket and runs `just do` | every hour at :00 | `agents/efesto` |
| Zeus | Deploys new tags of pane, latte and sweegly to dev | every hour at :10 | `agents/zeus` |
| Ermes | Unblocks, re-prioritises and refills Next in line | every hour at :50 | `agents/ermes` |

Apollo (PR merge and fix pass) lives in `bin/apollo` and `skills/apollo`. Athena (Codex review agent) is set up separately.

## Layout

Each agent folder has:
- `prompt.md`: the instructions it runs with;
- `run.sh`: the wrapper launchd starts. It takes a lock, runs `claude -p` with a fixed tool allowlist, and writes logs under `~/.<name>/logs`;
- `launchd.plist.template`: the schedule.

Files contain placeholders (`__WORKSPACE__`, `__SLACK_OWNER_DM__`, `__SLACK_CHANNEL__`, `__HOME__`). `install.sh` fills them in from a local config.

## Install

1. Copy `agents/config.example.env` to `~/.config/yaup/agents.env` and fill it in. Keep that file out of the repo.
2. Preview the rendered files without installing:
   `AGENTS_DRY_RUN=1 AGENTS_OUT=/tmp/agents-preview agents/install.sh all`
3. Install one agent or all of them:
   `agents/install.sh efesto` or `agents/install.sh all`

The install copies the rendered prompt and runner into `~/.<name>` and reloads the launchd job.

## Changing an agent

Edit the file in its folder, open a PR, and run `install.sh` on each machine after the merge. Live runs read `~/.<name>/prompt.md` when they start, so a change applies from the next run.

## Notes

- Nothing here holds a secret. The agents use the owner's `gh` login and the Slack connector.
- The repo is public, so keep personal IDs and paths out of these files. Put them in the local config.
- A run that finds the previous run still holding its lock exits without posting. The wrapper logs the skip in `logs/skipped.log`.
