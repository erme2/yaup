# Yaup

Yaup applies one versioned policy and mechanically enforced planning gate to Codex, Claude Code, Cursor, GitHub Copilot, and Gemini CLI work across repositories.

The name is intentionally literal: **Yaup** means "yet another unfinished project", because unfinished side projects have a way of multiplying unless something boring and strict keeps them moving toward done.

## Requirements

PHP 8.2+, Composer, Node.js 22+, and Git 2.39+ on Linux or macOS.

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

Common conversational shortcuts such as `jump on <ticket>`, `r4r`, and `IR <pull-request>` are defined in `policies/core.md` so implementation, publish, and review handoffs stay consistent across repositories.

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

Real agent adapters fail closed when their executable is unavailable. CI exercises adapter construction without live AI credentials; authenticated smoke tests remain an explicit release check.
