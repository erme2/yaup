# 0003: Human Git Boundary

## Context

Git operations can publish, rewrite, or hide work. Yaup's default workflow keeps the human maintainer in control of branch, commit, push, pull request, and merge decisions unless they explicitly delegate a specific step.

## Decision

Agents leave changes in the visible working tree by default. Commands such as `jump on`, `R4R`, `IR`, and `L8P` define the limited GitHub or Git actions authorized by that shortcut.

## Consequences and tradeoffs

The default is conservative and sometimes requires another human command after implementation. The benefit is a clear authorization boundary: local edits, publishing, review, and merge are distinct actions.

## Alternatives considered

- Let agents commit after every successful validation run. Rejected because validation does not prove the change is intended.
- Let agents push branches automatically. Rejected because publishing repository state is a separate trust decision.
- Encode all Git behavior in agent prompts only. Rejected because Yaup needs stable workflow semantics across supported agents.

## Related files or rules

- `policies/core.md`
- `policies/rules.yaml`, rule `git.human-by-default`
- `skills/jump-on/SKILL.md`
- `skills/r4r/SKILL.md`
- `skills/ir/SKILL.md`
- `skills/l8p/SKILL.md`
