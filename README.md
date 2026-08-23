# Yaup

Yaup applies one versioned policy and mechanically enforced planning gate to Codex, Claude Code, Cursor, GitHub Copilot, and Gemini CLI work across repositories.

The name is a joke at my own expense: **Yaup** means "yet another unfinished project", because pretty much all of my personal projects are unfinished. Yaup itself is no exception — it won't ever really be "done", both because how AI gets used in programming keeps changing out from under it, and because it exists to be a container for the rest of my projects, most of which are proofs of concept at best. The strictness is the point: something boring and rule-bound to keep those unfinished projects moving forward, run by a tool that's honest about being unfinished too.

## Requirements

Running yaup itself needs PHP 8.2+, Composer, and Git 2.39+, on Linux or macOS. CI exercises PHP 8.2 through 8.5 on Linux; macOS has only been verified locally so far.

Node.js 22+ isn't required by yaup, but most supported agent CLIs (Codex, Claude Code, Cursor, Copilot, Gemini) are distributed via npm, so you'll need it to install and run whichever of those you use.

## Install

```sh
composer install
bin/yaup list
```

## Workflow

1. `bin/yaup discover` discovers direct children of `repos/` and registers projects with an origin remote.
2. `bin/yaup rules:resolve repos/example` shows the effective structured rules and protected native instruction files.
3. `bin/yaup agent codex repos/example "task"` launches mechanically read-only planning.
4. A human reviews, approves, and commits the saved plan.
5. `bin/yaup agent codex repos/example "execute approved plan" --execute --plan repos/example/plans/task.yaml` verifies Git-backed approval before enabling writes.
6. `bin/yaup validate repos/example` runs every explicitly configured validation category.

The agent must stop on ambiguity, report nearby defects without fixing them, and distinguish correct behavior from tests that merely encode current behavior.

Project work must happen in the registered checkout under `repos/`, for example `repos/burro` for Burro. Agents must not clone or edit project repositories in `/tmp`, `/private/tmp`, or another location where the human cannot see and review the working-tree changes. Git operations remain human-driven unless a specific branch, commit, push, or PR action is explicitly delegated.

Ticket titles must start with `bugfix:`, `hotfix:`, or `feature:`. Use `hotfix:` only for urgent production-impacting corrections, `bugfix:` for normal defects, and `feature:` for new behavior, hardening, chores, and planned improvements.

Ticket execution shorthand is documented in [Ticket work](playbooks/ticket-work.md). `jump on ISSUE_LINK` means move the ticket to in progress, branch from latest `main`, implement, and stop before commit. `R4R` means commit, push, and open pull requests for the current ticket across every involved project.

## Rule sources and precedence

Canonical prose lives in `policies/core.md`; enforceable rules live in `policies/rules.yaml`. A project may use structured overrides in `.yaup.yaml`, but mandatory rules cannot be disabled. Recognized native instruction files are discovered read-only, including nested files. Unstructured contradictions require human resolution. `.yaup.local.yaml` provides ignored local preferences and cannot weaken shared rules.

## Plans

Plans are committed project records. Approval requires `status: approved`, populated approver and ISO 8601 timestamp fields, and an unchanged committed version. Material scope changes require a new approval commit. Completed plans retain changes, validation results, deviations, risks, and next steps; transcripts are not retained by default.

## Private repositories

Project workflows pin an exact Yaup release and use a read-only GitHub App token to download private artifacts. Configure `YAUP_APP_ID` and `YAUP_APP_PRIVATE_KEY` as repository or organization secrets. Repository identity is configured once in `config/yaup.yaml` so a rename does not require source changes.

## Development

```sh
composer test
composer analyse
composer format:check
composer validate:composer
composer audit
```

`composer check` runs `validate:composer`, `test`, and `analyse`, and `format:check` together as one shortcut.

Real agent adapters fail closed when their executable is unavailable. CI exercises adapter construction without live AI credentials; authenticated smoke tests remain an explicit release check.
