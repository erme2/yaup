# Agent Workflow Autonomy

Yaup workflow shortcuts are explicit delegation boundaries for registered Yaup repositories. They let agents perform routine GitHub and Git workflow steps without repeated confirmation while keeping high-risk actions manual.

This policy is tool-neutral. Agents may use the GitHub CLI, GitHub MCP tools, or another available GitHub integration when it provides the same scoped operation. Prefer narrow, auditable capabilities over broad shell or account-wide grants.

Autonomy applies only to repositories registered with this Yaup installation unless the human explicitly extends scope for a specific task.

## Always Allowed After Shortcut Invocation

After a human invokes `jump on`, `R4R`, `IR`, or `L8P` for a Yaup ticket or pull request, agents may inspect related GitHub and Git state without asking again:

- Read issues, pull requests, comments, reviews, labels, milestones, project items, workflow runs, and check results.
- Fetch remote refs and inspect branch, commit, and diff state.
- Use `/private/tmp` for scratch artifacts and isolated review worktrees when the workflow permits review worktrees.
- Add missing labels, project entries, or milestones when doing so is necessary to complete the invoked workflow.

## `jump on`

`jump on` authorizes local implementation in the main registered checkout only:

- Assign the issue to the human owner when possible.
- Move the project item to `In progress`.
- Update the main checkout to latest `main` when that can be done without overwriting unrelated work.
- Create and switch to a ticket branch from `main`.
- Implement the scoped change locally.
- Run appropriate validation.

`jump on` does not authorize commits, pushes, pull requests, PR approvals, merges, issue closure, force pushes, branch deletion, destructive filesystem commands, or worktree use. If the main registered checkout is unavailable or unsafe, ask before using a worktree or any other deviation.

## `R4R`

`R4R` means the human has reviewed local ticket work and authorizes publication for review:

- Stage only files that belong to the current ticket.
- Commit intended changes.
- Push the ticket branch.
- Create or update pull requests.
- Link the issue and include summary, validation, risks, and follow-up notes.
- Move the project item to `In review`.
- Check CI and report status.

`R4R` does not authorize PR approval, merging, force pushing, branch deletion, destructive filesystem commands, production access, secrets disclosure, repository settings changes, branch-protection changes, global dependency installation, or unrelated publication.

## `IR`

`IR` authorizes independent review and posting the result:

- Inspect the pull request, linked issue, diff, surrounding code, tests, CI, prior comments, and prior reviews.
- Use an isolated review worktree under `/private/tmp` when useful.
- Run focused or broader validation when it materially improves confidence.
- Post the review result to the pull request, including a clean "no findings" result.

`IR` does not authorize implementing fixes, PR approvals, merging, force pushing, branch deletion, issue closure, or marking project items done. If GitHub blocks a formal review action, post the same result as a comment.

## `L8P`

`L8P` authorizes the loop after a pull request exists:

- Run an IR-style review pass.
- Implement only fixes for actionable review findings on the existing PR branch.
- Run validation proportional to the fix.
- Commit and push fixup commits.
- Repeat until no actionable findings remain or a stopping condition is reached.

`L8P` does not authorize PR approval, merging, force pushing or rebasing published history, branch deletion, production access, secrets disclosure, or scope expansion.

## After Manual Merge

After a human manually merges or closes a pull request, agents may update linked Yaup project items to `Done` and verify issue closure when the merge or closure already performed that action. Branch deletion remains manual or automation-owned, preferably handled by GitHub Actions.

## Ask First

Ask before any operation not explicitly allowed above. The default for unspecified actions is manual confirmation.

Always ask before:

- Approving a pull request.
- Merging a pull request.
- Force pushing or rewriting history.
- Deleting branches.
- Closing issues outside normal linked-PR merge closure.
- Running destructive filesystem commands.
- Accessing production systems.
- Reading, printing, moving, or disclosing secrets.
- Changing repository settings or branch protection.
- Installing dependencies globally.
