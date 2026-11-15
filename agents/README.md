# Yaup agents

The Claude agents that run unattended on the owner's Mac, kept here so they can be reviewed, versioned and reinstalled (for example on a Raspberry Pi).

| Agent | Role | Schedule (local time) | Folder |
|---|---|---|---|
| Efesto | Backend developer: REST API, database, data structures. Takes the next backend ticket in Next in line (pane, sweegly-data, deploy) and runs `just do` | every hour at :00 | `agents/efesto` |
| Dioniso | Frontend and design developer: UI, components, `.figcraft` design files. Takes the next frontend ticket in Next in line (Latte, sweegly, sweegly-home) and runs `just do` | every hour at :30 | `agents/dioniso` |
| Zeus | Deploys new tags of pane, latte and sweegly to dev | every hour at :10 | `agents/zeus` |
| Ermes | Unblocks, re-prioritises and refills Next in line for both developers | every hour at :50 | `agents/ermes` |
| Crono | Morning digest: sends the w-next snapshot and the blockers that need the owner, with a prompt for each | every day at 07:00 | `agents/crono` |

Apollo (PR merge and fix pass) lives in `bin/apollo` and `skills/apollo`. Athena (Codex review agent) is set up separately.

Efesto and Dioniso both use the `just do` skill. They share the checkouts under `repos/`, so their runners and Apollo hold one lock, `~/.yaup/checkouts.lock`: only one of them switches branches at a time. A run that finds the lock held is skipped, so a long ticket delays the next developer's run rather than colliding with it. A ticket belongs to a developer by its `role:` label (`role: backend` or `role: frontend`), or else by its repository.

## Layout

Each agent folder has:
- `prompt.md`: the instructions it runs with;
- `run.sh`: the wrapper cron starts. It takes a lock, runs `claude -p` with a fixed tool allowlist, and writes logs under `~/.<name>/logs`;
- `crontab.line`: the schedule, one crontab line.

Files contain placeholders (`__WORKSPACE__`, `__SLACK_OWNER_DM__`, `__SLACK_CHANNEL__`, `__HOME__`). `install.sh` fills them in from a local config.

The agents run from the user's crontab, so the same setup works on the Mac and on a Raspberry Pi. Each agent has its own marked block in the crontab (`# >>> yaup agent: <name>`), and `install.sh` replaces only that block.

Mac notes: cron does not run jobs missed while the Mac sleeps (launchd did), and the first run needs Full Disk Access for `cron` (System Settings → Privacy & Security) if the workspace is on an external volume such as `/Volumes/code`. `run.sh` sets its own PATH and logs to `~/.<name>/logs/cron.log` as well as its own log file.

## Install

1. Copy `agents/config.example.env` to `~/.config/yaup/agents.env` and fill it in. Keep that file out of the repo.
2. Preview the rendered files without installing:
   `AGENTS_DRY_RUN=1 AGENTS_OUT=/tmp/agents-preview agents/install.sh all`
3. Install one agent or all of them:
   `agents/install.sh efesto` or `agents/install.sh all`

The install copies the rendered prompt and runner into `~/.<name>` and replaces that agent's crontab block. A first install also retires the agent's old launchd job.

## Changing an agent

Edit the file in its folder, open a PR, and run `install.sh` on each machine after the merge. Live runs read `~/.<name>/prompt.md` when they start, so a change applies from the next run.

## Notes

- Nothing here holds a secret. The agents use the owner's `gh` login and the Slack connector.
- The repo is public, so keep personal IDs and paths out of these files. Put them in the local config.
- A run that finds the previous run still holding its lock exits without posting. The wrapper logs the skip in `logs/skipped.log`.
