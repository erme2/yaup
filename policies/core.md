# Yaup contributor policy

Yaup gives every supported coding agent the same minimum operating contract.

## Work gate

Inspect in read-only mode. Record the interpretation, scope, intended changes, risks, and validation plan. A human must approve that saved plan in Git before implementation. Material scope changes invalidate approval.

## Engineering judgment

Follow established project conventions. Stop when they conflict or are unclear. Passing tests do not establish correct behavior: compare implementation, requirements, and tests. If a function and its test encode the same defect, explain both and propose a coordinated correction before editing.

## Scope and reporting

Only perform approved work. Report nearby defects and wait. Report every change, validation result, limitation, risk, and practical next step.

## Conversational shortcuts

When a human uses `jump on <ticket>`, take the ticket into active implementation in the repository's main checkout folder. Use a separate worktree only for review tasks or when the main checkout is unavailable or unsafe to use. Assign the ticket to the human who called the command when the hosting system supports assignment, move its project status to in progress when possible, implement locally, run appropriate validation, and stop before commit, push, or pull request creation so the human can review the code.

Follow the shortcut rules as written. If a better option appears necessary, challenge the rule, explain the trade-off, and ask the human before deviating.

When a human uses `r4r`, treat it as "ready for review": the human has reviewed the local code. Re-check the worktree, commit only the intended changes, push the branch, create or update the pull request as draft/ready according to the current workflow, and report the PR and validation status.

When a human uses `IR <pull-request>`, perform an independent review of the current pull request head. Prefer an isolated worktree for review so local implementation work is not disturbed. Inspect the diff, relevant surrounding code, tests, CI, and prior review comments, then post the review result on the pull request. If findings exist, lead with actionable issues and do not implement them during the IR pass.

## Security and Git

Secrets may be used locally but never repeated in output. Confirm external disclosure. Production access is prohibited. Humans perform Git operations unless they explicitly delegate a specific operation.
