---
name: just-do
description: Run a Yaup ticket end to end when the user says "just do" with an issue link. Do the jump-on steps, implement and validate, then do R4R (commit, push, open the PR, move the ticket to In review) without stopping for review. Use only when the user wants the change published without a review gate; use jump on to stop before commit, and R4R when the human has already reviewed the work.
---

# Just Do

Use this skill when the user says `just do <ticket>` or `just do ISSUE_LINK`.

`just do` is `jump on` followed by `R4R` in one run. Unlike `jump on`, it does not stop for the human to review the code: it publishes the work for review itself. The user has accepted that by using this command.

Follow `docs/agent-workflow-autonomy.md` for the allowed autonomous actions and ask-first boundaries. Its `jump on` and `R4R` sections apply in full, and `just do` adds nothing beyond them.

## Workflow

Part 1, `jump on`:

1. Inspect the issue, linked PRs, comments, labels, project status, and the relevant repository context.
2. Assign the issue to the human caller when the hosting system supports assignment.
3. Move the project item to `In progress` when project access is available.
4. Work only in the registered checkout under the Yaup `repos/` directory for the target project.
5. Update the checkout to the latest `main` unless that would overwrite unrelated local work.
6. Create a ticket branch from `main`, named from the issue number and a short description (for example `feature/56-admin-only-login-redirect`).
7. Implement the scoped change with the tests and docs it needs.
8. Run validation proportional to the change, and read the results.

Part 2, `R4R`:

9. Re-check the working tree. Stage only the files that belong to this ticket, and leave unrelated user changes unstaged.
10. Commit with the repository's commit title convention (`feature:` or `bugfix:`), and end the message with the attribution lines required for commits.
11. Push the ticket branch.
12. Open the pull request against `main` and link the issue (`Closes #<n>`). The body has the summary, validation results, risks and follow-ups, and ends with the attribution line required for pull requests.
13. Move the project item to `In review`.
14. Check the CI status for the PR and report it.

## Stop Instead Of Publishing

Do not commit, push or open a PR when any of these hold. Report what is blocking and wait for the human:

- Validation fails, or a check cannot run and the change depends on it.
- The change needs a product or architecture decision the ticket does not settle.
- The change needs more than the ticket's scope, or touches files outside it.
- The working tree has unrelated changes that cannot be separated from the ticket's files.
- The ticket is blocked (for example by an open dependency or a `status: blocked` label).

When you stop, say which part you finished (for example "implemented and validated, not published") and what is needed.

## Boundaries

`just do` authorizes the `jump on` and `R4R` actions above and nothing more. It does not authorize:

- Approving or merging the PR, or enabling auto-merge.
- Resolving review threads.
- Closing issues or moving project items to `Done`.
- Force pushing, rebasing published history, or deleting branches.
- Changing repository or environment settings, secrets, branch protection, or WorkOS and hosting configuration.
- Production access or deployments.
- Creating new tickets unless the repository rules require them.

The PR stays open for the human to review and merge.

## Output

Report back with:

- The issue, branch, commit SHA and PR link.
- Project status after the run.
- Validation commands and outcomes, and CI status.
- Anything left for the human: review, open decisions, or follow-up tickets.
