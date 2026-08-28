---
name: l8p
description: Use after R4R on a pull request to loop independent review, fix findings, validate, commit, and push until no actionable review findings remain. Do not use for first implementation, ordinary IR-only review, or merging.
---

# L8P

L8P means "loop to pass": after an R4R-created pull request exists, repeatedly run an IR-style review, fix actionable findings, validate, commit, and push updates until the review is clean or a stopping condition is reached.

Follow `docs/agent-workflow-autonomy.md` for the allowed autonomous actions and ask-first boundaries.

## When To Use

Use this skill only when the user explicitly invokes `L8P` or asks for this loop-review workflow on an existing pull request.

Required inputs:

- A pull request URL or enough local context to identify the current PR.
- Permission implied by `L8P` to push fixup commits to that PR branch.

If no PR can be identified, ask for the PR URL. If the local checkout is not on the PR branch or cannot be safely aligned with the PR head, stop and report the mismatch.

## Workflow

1. Confirm the PR, repository, head branch, base branch, and current project/issue status.
2. Ensure the issue or PR is assigned to the human owner when possible, and keep the project item in an active review/fix status when available.
3. Start each review cycle with an IR-style independent review:
   - Prefer an isolated worktree under `/private/tmp` for review so implementation state does not bias or disturb the review.
   - Inspect the PR diff, surrounding code, tests, CI/check status, and prior review comments.
   - Do not approve the PR or merge it.
4. If the review finds no actionable issues, stop the loop and report the clean result.
5. If actionable findings exist:
   - Implement only fixes for those findings on the PR branch.
   - Avoid unrelated refactors and unrelated issue work.
   - Run focused validation for the changed behavior, then broader validation proportional to the risk.
   - Commit the intended fixes with a clear message and push the PR branch.
   - Post or report a short summary mapping fixes to findings when useful.
6. Repeat the independent review cycle after the push.

## Stopping Conditions

Stop and report instead of continuing when:

- A finding requires product direction, secret rotation, production access, destructive history rewriting, or another decision the user must make.
- The next fix would expand materially beyond the PR's issue/scope.
- The same class of finding remains after three fix cycles.
- Validation cannot run or keeps failing for reasons unrelated to the PR changes.
- The PR branch receives unexpected external changes that make the local state unsafe to continue.

## Git And GitHub Boundaries

`L8P` authorizes normal fixup commits and pushes to the existing PR branch. It does not authorize:

- Merging the PR.
- Approving the PR.
- Force-pushing or rebasing published history unless the user explicitly asks.
- Deleting branches.
- Closing issues or marking project items done.
- Accessing production systems.
- Printing or copying secrets.

Use `/private/tmp` for review worktrees, review notes, patch files, logs, and other scratch artifacts. Scratch files are temporary aids, not durable project records, and they do not expand the PR's approved implementation scope.

At completion, report:

- PR URL and current review result.
- Commits pushed during L8P.
- Validation commands and outcomes.
- Remaining risks, blockers, or user decisions.
