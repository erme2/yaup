# 0005: Validation Categories

## Context

AI-assisted changes can look correct while missing lint, static analysis, browser behavior, security checks, or regression coverage. A single test command is not enough to describe project confidence.

## Decision

Yaup defines mandatory validation categories and requires each managed project to provide a command or an unexpired exemption for every category. `ValidationRunner` reports missing categories as failures.

## Consequences and tradeoffs

Projects must be explicit about validation coverage, including categories that do not apply yet. This creates setup overhead, but it prevents silent gaps and makes skipped checks visible in review.

## Alternatives considered

- Let each project define arbitrary validation only. Rejected because missing categories would be indistinguishable from intentional omissions.
- Require one universal command for every project. Rejected because supported projects use different stacks.
- Treat passing tests as proof of correctness. Rejected because Yaup requires comparing behavior, requirements, and tests.

## Related files or rules

- `config/yaup.yaml`
- `policies/core.md`
- `policies/rules.yaml`, rules `quality.validate` and `quality.behavior-over-tests`
- `src/Validation/ValidationRunner.php`
- `src/Command/ValidateCommand.php`
