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
2. `bin/yaup instructions:sync` creates or refreshes Yaup-managed `AGENTS.md` bridge files in registered projects.
3. `bin/yaup rules:resolve repos/example` shows the effective structured rules and protected native instruction files.
4. `bin/yaup agent codex repos/example "task"` launches mechanically read-only planning.
5. A human reviews, approves, and commits the saved plan.
6. `bin/yaup agent codex repos/example "execute approved plan" --execute --plan repos/example/plans/task.yaml` verifies Git-backed approval before enabling writes.
7. `bin/yaup validate repos/example` runs every explicitly configured validation category.

For ticket work that spans more than one checkout, `bin/yaup ticket:status <ticket>` shows matching branches, matching plan approval state, and dirty/clean worktree status across Yaup and registered repositories. Pass project names after the ticket to limit the table, for example `bin/yaup ticket:status 20 pane latte`.

### Worked example

The exact transcript depends on the selected agent CLI and the target project, but a complete Yaup pass should look like this:

```sh
$ bin/yaup discover
+---------+---------------------------------+---------------------+
| Project | Remote                          | Cross-repository CI |
+---------+---------------------------------+---------------------+
| example | git@github.com:erme2/example.git | registered          |
+---------+---------------------------------+---------------------+

 [OK] Discovered 1 projects; added 1 registry entries.
```

Planning mode launches the adapter with Yaup's resolved rules and native instruction file list, then requires the agent to leave the checkout unchanged:

```sh
$ bin/yaup agent codex repos/example "Add a health-check endpoint"
[codex output, truncated]
Read Yaup rules and native instructions.
Proposed plan: add GET /health plus feature coverage.
Saved plan to plans/health-check-endpoint.yaml.
```

A saved plan is a normal project file. The human reviews it, changes `status` and `approval`, then commits the approved version:

```yaml
status: approved
summary: Add a GET /health endpoint that returns service status.
changes:
  - path: routes/web.php
    action: add GET /health route
  - path: tests/Feature/HealthCheckTest.php
    action: cover the JSON response and status code
validation:
  - composer test
approval:
  approved: true
  approver: erme2
  approved_at: '2026-08-29T10:30:00+01:00'
```

```sh
$ git -C repos/example add plans/health-check-endpoint.yaml
$ git -C repos/example commit -m "Approve health-check endpoint plan"
```

Execution mode verifies that the approved plan is committed and unchanged before enabling writes:

```sh
$ bin/yaup agent codex repos/example "Execute the approved health-check plan" \
    --execute --plan repos/example/plans/health-check-endpoint.yaml
[codex output, truncated]
Implemented GET /health and feature coverage.
```

Validation runs every mandatory category configured by the project. Missing commands or expired exemptions fail closed:

```sh
$ bin/yaup validate repos/example
+-------------------------+--------+-----------------------------+
| Category                | Status | Detail                      |
+-------------------------+--------+-----------------------------+
| focused-tests           | passed | PHPUnit 1 test passed       |
| full-tests              | passed | PHPUnit 42 tests passed     |
| lint                    | passed | no syntax errors detected   |
| format                  | passed | style check clean           |
| static-analysis         | passed | no errors                   |
| production-build        | exempt | backend-only package        |
| browser-ui-verification | exempt | no browser UI               |
| bug-regression-test     | exempt | feature-only change         |
| feature-tests           | passed | health-check coverage added |
| security-audit          | passed | no advisories found         |
+-------------------------+--------+-----------------------------+
```

The agent must stop on ambiguity, report nearby defects without fixing them, and distinguish correct behavior from tests that merely encode current behavior.

Project implementation work must happen in the registered checkout under `repos/`, for example `repos/burro` for Burro. Agents must not implement from clones, temporary worktrees, or hidden checkouts in `/tmp`, `/private/tmp`, or another location where the human cannot see and review the working-tree changes. Agents may use `/private/tmp` for scratch files, command bodies, patches, logs, rendered artifacts, and isolated review worktrees; these are temporary aids, not durable project records. Git operations remain human-driven unless a specific branch, commit, push, or PR action is explicitly delegated.

Ticket titles must start with `bugfix:`, `hotfix:`, or `feature:`. Use `hotfix:` only for urgent production-impacting corrections, `bugfix:` for normal defects, and `feature:` for new behavior, hardening, chores, and planned improvements.

Ticket execution shorthand is documented in [Ticket work](playbooks/ticket-work.md), with delegation boundaries in [Agent workflow autonomy](docs/agent-workflow-autonomy.md). `jump on ISSUE_LINK` means move the ticket to in progress, branch from latest `main`, implement, and stop before commit. `R4R` means commit, push, and open pull requests for the current ticket across every involved project.

## Rule sources and precedence

Canonical prose lives in `policies/core.md`; enforceable rules live in `policies/rules.yaml`. A project may use structured overrides in `.yaup.yaml`, but mandatory rules cannot be disabled. Recognized native instruction files are discovered read-only, including nested files. Unstructured contradictions require human resolution. `.yaup.local.yaml` provides ignored local preferences and cannot weaken shared rules.

Yaup can create managed `AGENTS.md` bridge files in registered project checkouts with `bin/yaup instructions:sync`. The command refreshes files carrying the Yaup bridge marker and preserves project-owned `AGENTS.md` files without that marker.

Architecture Decision Records under [docs/adr/](docs/adr/) explain the rationale and tradeoffs behind core policy decisions. [Agent adapters](docs/adapters.md) documents the supported CLI adapter contract and permission-mode mapping. ADRs do not replace `policies/rules.yaml`; the rules file remains the enforceable policy source.

## Plans

Plans are committed project records. Approval requires `status: approved`, populated approver and ISO 8601 timestamp fields, and an unchanged committed version. Material scope changes require a new approval commit. Completed plans retain changes, validation results, deviations, risks, and next steps; transcripts are not retained by default.

Yaup mechanically verifies that the approved plan file is committed and unchanged before execution. It does not currently diff implementation scope against the approved plan or decide materiality on its own. The human reviewer is responsible for deciding whether a plan change is material before approving and committing it.

A material scope change is a change that would reasonably alter what the approver thought they were authorizing: adding or removing affected features, files, repositories, data migrations, external services, permissions, security behavior, production behavior, or validation obligations. Rewording a description, fixing spelling, clarifying an already-approved step, or adding non-normative detail is not material when it does not alter intended work, risk, or validation.

When in doubt, treat the change as material, return to planning, and require a fresh approval commit.

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

## License

Yaup is licensed under GPL-3.0-only.
