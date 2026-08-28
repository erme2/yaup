# Ticket work

Use this playbook when a human delegates work by GitHub issue link or uses a
shared shorthand for ticket execution.

Shortcut autonomy is defined in
[Agent workflow autonomy](../docs/agent-workflow-autonomy.md). Actions not
explicitly allowed there require confirmation before proceeding.

## Scratch workspace

Agents may use `/private/tmp` as a scratch workspace for temporary workflow
artifacts: issue bodies, pull request bodies, review comments, patches, logs,
rendered files, and isolated review worktrees. These files are not durable
project records and should not be treated as source-of-truth documentation.

Scratch writes are different from repository writes. Git index updates,
commits, pushes, pull requests, project-board edits, and other repository or
GitHub mutations still require the workflow authorization that permits them.

Implementation work for managed repositories must still happen in the
registered checkout under `repos/`. Do not use `/private/tmp` clones or
temporary worktrees for implementation. `IR` may use isolated review worktrees
so local implementation state is not disturbed.

## `jump on ISSUE_LINK`

When the human says `jump on ISSUE_LINK`:

1. Inspect the issue and any relevant project context.
2. Move the ticket to `In progress` in the project board when project access is
   available.
3. Update the local checkout to the latest `main` for each project involved in
   the ticket.
4. Create a ticket branch from `main`.
5. Name the branch from the ticket number and a short description, without an
   `issue-` segment, for example `feature/37-secure-session-cookie-defaults`.
6. Use `/private/tmp` for temporary notes, issue-body drafts, patches, or logs
   that do not belong in the project checkout.
7. Ask architecture or product questions only when the answer materially affects
   the solution.
8. Implement the scoped change with appropriate tests and documentation.
9. Stop when the work is ready to commit so the human can review the working
   tree.

## `R4R`

When the human says `R4R`, publish the ticket work for review:

1. Inspect every involved project worktree and confirm the branch/diff belongs
   to the current ticket.
2. Use `/private/tmp` for pull request bodies, patch files, review notes, and
   command output that is useful during publication but should not be committed.
3. Commit the current ticket work in each involved project that has a relevant
   diff.
4. Push each ticket branch.
5. Open a pull request for each involved project with a clear summary, issue
   link, and validation notes.
6. Report any involved project that has no remaining diff, is already merged, or
   cannot produce a non-empty pull request.
7. Move the ticket status to `In review` in the project board when project
   access is available.

`R4R` is an explicit delegation of the required Git operations for the current
ticket only. It does not authorize unrelated commits, force pushes, destructive
Git commands, or publishing changes outside the ticket scope.
