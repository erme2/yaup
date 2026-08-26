# Yaup Agent Context

Before doing substantive work in this repository, read the local Yaup context:

- `README.md` for the project workflow, planning gate, registered checkout rules, and validation expectations.
- `config/yaup.yaml` for configured paths, native instruction filenames, and mandatory validation categories.
- `.agents/README.md` for durable memory conventions.
- Relevant files under `.agents/memory/` when the task touches a remembered repository or topic.
- Any invoked skill under `skills/*/SKILL.md` before following that workflow.

Project implementation work for managed repositories must happen in the registered checkout under `repos/`, not in temporary clones or hidden working directories. Treat existing worktree changes as user-owned unless the user explicitly asks to modify or revert them.

Command shorthand:

- `jump on <ticket>`: start ticket work locally, branch from latest `main`, implement and validate, then stop before commit, push, or PR.
- `R4R`: publish current ticket work for review by committing intended changes, pushing, opening or updating PRs, and moving the ticket to review when possible.
- `IR <pr-or-ticket>`: perform independent review only; post findings and do not implement fixes.
- `L8P <pr>`: after R4R, loop review, fix actionable findings, validate, commit, and push until clean or blocked.
