# Installing and running the agents

The Claude agents that work the Sweegly board unattended live in `agents/`. This page explains how to install them on the Mac, how to check and stop them, and where their logs are. The source of truth is the repository; the installed copies live in your home folder.

## The agents

| Agent | Job | Schedule | Folder |
|---|---|---|---|
| Efesto | Backend developer: REST API, database, data structures. Takes the next backend ticket and runs `just do` | every hour at :00 | `agents/efesto` |
| Dioniso | Frontend and design developer: UI, components, `.figcraft` files. Takes the next frontend ticket and runs `just do` | every hour at :30 | `agents/dioniso` |
| Zeus | Deploys new tags of pane, latte and sweegly to dev | every hour at :10 | `agents/zeus` |
| Ermes | Unblocks, re-prioritises and refills Next in line for both developers | scheduled by the owner's own crontab line, not by the installer | `agents/ermes` |
| Crono | Morning digest: the w-next snapshot and the blockers that need the owner | every day at 07:00 | `agents/crono` |

Apollo (the PR merge and fix pass) is separate: `bin/apollo` and `skills/apollo`. Athena (the review agent) is installed by the same installer but scheduled by Codex: her `crontab.line` is a comment, so no launchd job is written for her.

Each agent folder holds:
- `prompt.md`: the instructions the agent runs with;
- `run.sh`: the wrapper launchd starts. It takes a lock, runs `claude -p` with a fixed tool allowlist, and writes logs. It tells the agent when the run started, and the agent ends its last Slack message with `(took Xm Ys)`;
- `crontab.line`: the schedule, in crontab notation (minute, hour, then `* * *`). The installer turns it into a launchd job.

Files contain placeholders (`__WORKSPACE__`, `__HOME__`, `__SLACK_OWNER_DM__`, `__SLACK_CHANNEL__`). The installer fills them in.

## Requirements

On the machine that runs the agents:
- `claude` (Claude Code CLI), logged in as the owner;
- `gh`, logged in as `erme2`, with access to the repositories;
- `git`, `node`, `npm`, `php`, `composer`, `python3`, and `docker` if Pane's integration tests should run;
- the Slack connector available to Claude, or the Slack bot token below (the fallback for runners that have no connector, such as Codex);
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

   **Slack bot token** (needed only for the fallback `agents/lib/slack_post.py`):
   1. In https://api.slack.com/apps open (or create) the Yaup agents app and give its bot token the scopes `chat:write`, `im:write` (to open the owner's DM) and `channels:read`; reinstall the app to the workspace if Slack asks.
   2. In App Home, turn on the Messages tab so the bot can DM you.
   3. Invite the bot to the #sweegly channel (`/invite @yaup_agents`).
   4. Save the bot token (starts with `xoxb-`) outside the repository:

      ```sh
      printf '%s' 'xoxb-...' > ~/.config/yaup/slack-token && chmod 600 ~/.config/yaup/slack-token
      ```
   5. Check it: `echo "Yaup Slack check" | python3 agents/lib/slack_post.py dm`. It prints `sent to dm`. If the token file or `agents.env` is missing or empty, it stops with a message naming the file; Slack errors are printed as `slack <method> failed: <error>`.

2. Preview the rendered files without installing anything:

   ```sh
   AGENTS_DRY_RUN=1 AGENTS_OUT=/tmp/agents-preview agents/install.sh all
   ```

   Check the rendered files, including each `local.yaup.<name>.plist`, and that no `__…__` placeholders are left in the rendered prompts.

3. Install one agent or all of them:

   ```sh
   agents/install.sh efesto      # one agent
   agents/install.sh all         # every agent
   ```

   The installer:
   - copies the rendered `prompt.md` and `run.sh` into `~/.<name>/` and creates `~/.<name>/logs/`;
   - writes the agent's launchd job, `~/Library/LaunchAgents/local.yaup.<name>.plist`, from its `crontab.line`, and loads it in your logged-in session;
   - removes the agent's block from your crontab, if it has one from the cron setup.

Re-running the installer is safe. It replaces each agent's job and copies the current files. Changes to a prompt take effect from the next run, because each run reads `~/.<name>/prompt.md` when it starts.

## Check the schedule

```sh
launchctl list | grep local.yaup   # the loaded jobs
tail -n 50 ~/.efesto/logs/launchd.log
ls -t ~/.efesto/logs | head        # one log per run, newest first
```

Use the same commands with `dioniso`, `zeus`, `ermes` or `crono`.

## Run one agent now

To test an agent without waiting for its slot, run its wrapper directly:

```sh
/bin/bash ~/.ermes/run.sh
```

It takes the same lock as its scheduled runs, so it's skipped if another run of that agent (or, for Efesto and Dioniso, of either developer) is in progress.

## Stop or remove an agent

Pause one agent by unloading its launchd job. To remove it, also delete the plist and its folder:

```sh
launchctl bootout gui/$(id -u)/local.yaup.ermes
rm ~/Library/LaunchAgents/local.yaup.ermes.plist   # to remove it for good
rm -rf ~/.ermes                                    # its installed files and logs
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
- `~/.<name>/logs/launchd.log`: anything launchd itself captured from the runner;
- `~/.<name>/logs/skipped.log`: runs that were skipped because the lock was held or the workspace was not mounted.

## Shared lock

Efesto, Dioniso and Apollo all switch branches in `repos/`, so they share one lock, `~/.yaup/checkouts.lock`. Only one of them runs at a time. A run that finds the lock held is skipped, so a long ticket delays the next developer's run rather than colliding with it. Zeus, Ermes and Crono each have their own lock, `~/.<name>/run.lock`, so they only skip their own overlapping runs. Ermes and Crono work on the board, not the checkouts. I haven't checked whether Zeus's deploy steps touch `repos/`; if they do, it should move to the shared lock.

## Mac notes

- **Why launchd, not cron.** The agents ran on cron on 15 Nov and, from about 22:00, every run failed: `gh`'s token read as invalid and the Slack connector was missing. cron runs outside your login session and can't rely on the keychain logins. launchd runs inside it, so the jobs use the same logins as your terminal. This is the likely cause, not a proven one; confirm with the first scheduled run after an install.
- **Sleep.** launchd runs a job that was missed during sleep when the Mac wakes. A Mac that is logged out or powered off runs nothing.
- **Full Disk Access.** If the workspace is on an external volume such as `/Volumes/code`, the runs need access to it. If a run can't read the repositories, grant `/bin/bash` Full Disk Access in System Settings → Privacy & Security.
- **Notifications.** `run.sh` posts a macOS notification at the end of each run. These can be silent under cron; the Slack messages are the reliable record.

## Raspberry Pi (later)

The installer now writes launchd jobs, so it is macOS-only. A Raspberry Pi would need a cron or systemd variant of the installer, which isn't written yet. The `crontab.line` files already hold the schedules it would use.

## Changing an agent

1. Edit the file in `agents/<name>/` and, if the change is meaningful, open a PR.
2. Preview with `AGENTS_DRY_RUN=1 … agents/install.sh <name>`.
3. Install on each machine with `agents/install.sh <name>` after the merge.

Nothing in `agents/` holds a secret. The agents use the owner's `gh` login and the Slack connector. Keep personal IDs and paths in `~/.config/yaup/agents.env`, not in the files.
