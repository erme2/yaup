---
name: ir
description: Perform an independent review of a Yaup pull request when the user says IR with a PR or ticket link. Inspect the PR and post review findings without fixing them.
---

# IR

Use this skill when the user says `IR <pull-request>`, `IR <ticket>`, or asks for the Yaup independent-review flow.

`IR` is a direct command. The user has authorized normal GitHub PR inspection commands such as `gh pr view`, `gh pr diff`, `gh pr checks`, and posting the review result as a PR comment.

## Workflow

1. Identify the PR or, when given a ticket, find related open PRs across Yaup repos.
2. Prefer an isolated review worktree so local implementation work is not disturbed.
3. Inspect:
   - PR title, body, linked issue, branch, and base,
   - diff and relevant surrounding code,
   - tests and documentation touched by the change,
   - CI/check status,
   - prior review comments and whether they remain valid.
4. Run focused validation when it materially improves confidence, and broader validation when risk or scope justifies it.
5. Post the review result on the pull request.
6. If findings exist, lead with actionable issues and include enough file/line context for the implementer to fix them.
7. If there are no blockers, say that clearly and list PR links the human can manually merge.

## Boundaries

Do not implement fixes during an `IR` pass. Do not merge, close issues, force-push, or mark project items done.

If GitHub does not allow a formal review action, post the same result as a PR comment and report that limitation.
