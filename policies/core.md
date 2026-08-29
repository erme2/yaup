# Yaup contributor policy

Yaup gives every supported coding agent the same minimum operating contract.

## Work gate

Inspect in read-only mode. Record the interpretation, scope, intended changes, risks, and validation plan. A human must approve that saved plan in Git before implementation. Material scope changes invalidate approval and require a fresh human approval commit; Yaup verifies the approved plan file is committed and unchanged, but materiality is currently a human review decision.

## Engineering judgment

Follow established project conventions. Write code a human maintainer can understand, debug, and change without reverse-engineering cleverness: prefer clear names, small coherent units, explicit control flow, local patterns, and necessary tests over terse or magical solutions. Stop when conventions conflict or are unclear. Passing tests do not establish correct behavior: compare implementation, requirements, and tests. If a function and its test encode the same defect, explain both and propose a coordinated correction before editing.

## Scope and reporting

Only perform approved work. Project implementation work must happen in the registered checkout under `repos/` for the target project unless the human explicitly approves a specific visible worktree deviation first; never implement from temporary clones, hidden worktrees, or other hidden locations. Agents may use `/private/tmp` for scratch artifacts and isolated review worktrees, but scratch files are not durable project records and do not authorize repository writes, Git metadata/index writes, or implementation outside the registered checkout. Report nearby defects and wait. Report every change, validation result, limitation, risk, and practical next step.

## Tickets

Every ticket title must start with one of `bugfix:`, `hotfix:`, or `feature:` according to the work type. Use `hotfix:` only for urgent production-impacting corrections, `bugfix:` for normal defect corrections, and `feature:` for new behavior, hardening, chores, or planned improvements that are not defects.

## Conversational shortcuts

When a human uses `jump on <ticket>`, take the ticket into active implementation in the repository's main checkout folder. Do not use a separate worktree unless the human explicitly approves that specific deviation first. Assign the ticket to the human who called the command when the hosting system supports assignment, move its project status to in progress when possible, implement locally, run appropriate validation, and stop before commit, push, or pull request creation so the human can review the code.

Follow the shortcut rules as written. If a better option appears necessary, challenge the rule, explain the trade-off, and ask the human before deviating.

When a human uses `r4r`, treat it as "ready for review": the human has reviewed the local code. Re-check the worktree, commit only the intended changes, push the branch, create or update the pull request as draft/ready according to the current workflow, and report the PR and validation status.

When a human uses `IR <pull-request>`, perform an independent review of the current pull request head. Prefer an isolated review worktree under `/private/tmp` so local implementation work is not disturbed. Inspect the diff, relevant surrounding code, tests, CI, and prior review comments, then post the review result on the pull request. If findings exist, lead with actionable issues and do not implement them during the IR pass.

Shortcut autonomy is documented in `docs/agent-workflow-autonomy.md`. The default for unspecified GitHub, Git, filesystem, production, secret, or settings operations is to ask first.

## Security and Git

Secrets may be used locally but never repeated in output. Confirm external disclosure. Production access is prohibited. Humans review working-tree changes before Git operations. Humans perform Git operations unless they explicitly delegate a specific operation.
