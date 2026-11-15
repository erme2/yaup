# Installing and running the agents

The Claude agents that work the Sweegly board unattended live in `agents/`. This page explains how to install them on the Mac, how to check and stop them, and where their logs are. The source of truth is the repository; the installed copies live in your home folder.

## The agents

| Agent | Job | Schedule | Folder |
|---|---|---|---|
| Efesto | Backend developer: REST API, database, data structures. Takes the next backend ticket and runs `just do` | every hour at :00 | `agents/efesto` |
| Dioniso | Frontend and design developer: UI, components, `.figcraft` files. Takes the next frontend ticket and runs `just do` | every hour at :30 | `agents/dioniso` |
| Zeus | Deploys new tags of pane, latte and sweegly to dev | every hour at :10 | `agents/zeus` |
| Ermes | Unblocks, re-prioritises and refills Next in line for both developers | every hour at :50 | `agents/ermes` |
| Crono | Morning digest: the w-next snapshot and the blockers that need the owner | every day at 07:00 | `agents/crono` |

Apollo (the PR merge and fix pass) is separate: `bin/apollo` and `skills/apollo`.

Each agent folder holds:
- `prompt.md`: the instructions the agent runs with;
- `run.sh`: the wrapper cron starts. It takes a lock, runs `claude -p` with a fixed tool allowlist, and writes logs;
- `crontab.line`: the schedule as one crontab line.

Files contain placeholders (`__WORKSPACE__`, `__HOME__`, `__SLACK_OWNER_DM__`, `__SLACK_CHANNEL__`). The installer fills them in.

## Requirements

On the machine that runs the agents:
- `claude` (Claude Code CLI), logged in as the owner;
- `gh`, logged in as `erme2`, with access to the repositories;
- `git`, `node`, `npm`, `php`, `composer`, `python3`, and `docker` if Pane's integration tests should run;
- the Slack connector available to Claude;
- this repository checked out at the workspace path (`/Volumes/code/yaup` on the Mac), with the checkouts under `repos/`.

## Install

1. Copy the example config and fill it in. Keep the filled-in file out of the repository:

   ```sh
   mkdir -p ~/.config/yaup
   cp agents/config.example.env ~/.config/yaup/agents.env
   ```

   The file needs three values:
   - `WORKSPACE`: the absolute path of this repository;
   - `SLACK_OWNER_DM`: the owner's own Slack DM channel ID (the agents send blockers and digests there);
   - `SLACK_CHANNEL`: the #sweegly channel ID (the run starts and summaries go there).

2. Preview the rendered files without installing anything:

   ```sh
   AGENTS_DRY_RUN=1 AGENTS_OUT=/tmp/agents-preview agents/install.sh all
   ```

   Check the printed crontab lines and that no `__…__` placeholders are left in the rendered prompts.

3. Install one agent or all of them:

   ```sh
   agents/install.sh efesto      # one agent
   agents/install.sh all         # every agent
   ```

   The installer:
   - copies the rendered `prompt.md` and `run.sh` into `~/.<name>/` and creates `~/.<name>/logs/`;
   - writes the agent's schedule into your crontab, inside a marked block (`# >>> yaup agent: <name>` … `# <<< yaup agent: <name>`). It replaces only that block and leaves other crontab lines alone;
   - removes the agent's old launchd job, if it had one from an earlier setup.

Re-running the installer is safe. It replaces each agent's block and copies the current files. Changes to a prompt take effect from the next run, because each run reads `~/.<name>/prompt.md` when it starts.

## Check the schedule

```sh
crontab -l                       # the yaup blocks are in here
tail -n 50 ~/.efesto/logs/cron.log
ls -t ~/.efesto/logs | head      # one log per run, newest first
```

Use the same commands with `dioniso`, `zeus`, `ermes` or `crono`.

## Run one agent now

To test an agent without waiting for its slot, run its wrapper directly:

```sh
/bin/bash ~/.ermes/run.sh
```

It takes the same lock as its scheduled runs, so it's skipped if another run of that agent (or, for Efesto and Dioniso, of either developer) is in progress.

## Stop or remove an agent

Pause one agent by deleting its block from the crontab. Remove everything from the crontab for that agent, then save:

```sh
crontab -l | awk '/^# >>> yaup agent: ermes$/{skip=1;next} /^# <<< yaup agent: ermes$/{skip=0;next} !skip' | crontab -
```

To run it again, reinstall it with `agents/install.sh ermes`.

If a run is stuck and holds its lock, the next runs of that agent are skipped until it ends. Only remove a lock if you're sure no run is active:

```sh
ps aux | grep -E "claude|run.sh" | grep -v grep   # check nothing is running
rmdir ~/.yaup/checkouts.lock                      # Efesto, Dioniso and Apollo
rmdir ~/.ermes/run.lock                           # likewise ~/.<name>/run.lock for Zeus, Ermes and Crono
```

## Logs

Each run writes:
- `~/.<name>/logs/<date>_<time>.log`: the run's own log, with the final report at the end;
- `~/.<name>/logs/<date>_<time>.log.json`: the raw JSON output of `claude -p`;
- `~/.<name>/logs/cron.log`: anything cron itself printed;
- `~/.<name>/logs/skipped.log`: runs that were skipped because the lock was held or the workspace was not mounted.

## Shared lock

Efesto, Dioniso and Apollo all switch branches in `repos/`, so they share one lock, `~/.yaup/checkouts.lock`. Only one of them runs at a time. A run that finds the lock held is skipped, so a long ticket delays the next developer's run rather than colliding with it. Zeus, Ermes and Crono each have their own lock, `~/.<name>/run.lock`, so they only skip their own overlapping runs. Ermes and Crono work on the board, not the checkouts. I haven't checked whether Zeus's deploy steps touch `repos/`; if they do, it should move to the shared lock.

## Mac notes

- **Sleep.** cron doesn't run jobs that were missed while the Mac was asleep. Launchd did run them on wake. If the Mac is often asleep at :00, the hourly runs will be missed.
- **Full Disk Access.** If the workspace is on an external volume such as `/Volumes/code`, grant Full Disk Access to `cron` in System Settings → Privacy & Security, or the runs can't read the repositories.
- **Keychain.** The Claude CLI keeps its login in the macOS keychain. Check that a scheduled run can use it: add a temporary crontab line for `claude -p "say ok"` a minute ahead, then read `~/.<name>/logs/cron.log`. If it fails with an authentication error, the agents need launchd or another login arrangement.
- **Notifications.** `run.sh` posts a macOS notification at the end of each run. These can be silent under cron; the Slack messages are the reliable record.

## Raspberry Pi (later)

The same installer works on a Raspberry Pi running Raspberry Pi OS Lite (64-bit), with cron and the same tools installed. Clone the repository, create the config, and run `agents/install.sh all`. The Keychain and notification notes don't apply there. A container image would make the setup portable, but it isn't built yet.

## Changing an agent

1. Edit the file in `agents/<name>/` and, if the change is meaningful, open a PR.
2. Preview with `AGENTS_DRY_RUN=1 … agents/install.sh <name>`.
3. Install on each machine with `agents/install.sh <name>` after the merge.

Nothing in `agents/` holds a secret. The agents use the owner's `gh` login and the Slack connector. Keep personal IDs and paths in `~/.config/yaup/agents.env`, not in the files.
