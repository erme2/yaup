---
name: jump-on
description: Start implementation for a Yaup-tracked issue when the user says "jump on" with a ticket link or repo ticket reference. Move the ticket to active work, implement locally, validate, and stop before commit/push/PR.
---

# Jump On

Use this skill when the user says `jump on <ticket>`, `jump on ISSUE_LINK`, or gives an equivalent Yaup ticket-start command.

## Workflow

1. Inspect the ticket, linked PRs, comments, labels, project status, and relevant repository context.
2. Assign the ticket to the human caller when the hosting system supports assignment.
3. Move the ticket to `In progress` in the project board when project access is available.
4. Work only in the registered checkout under the Yaup `repos/` directory for the target project.
5. Update the local checkout to the latest `main` before starting, unless doing so would overwrite unrelated local work.
6. Create a ticket branch from `main`.
7. Name the branch from the ticket number and a short description, without an `issue-` segment, for example `feature/37-secure-session-cookie-defaults`.
8. Use `/private/tmp` for temporary notes, issue-body drafts, patches, logs, and other scratch artifacts that should not become project records.
9. Ask architecture or product questions only when the answer materially affects the solution.
10. Implement the scoped change with appropriate tests and documentation.
11. Run validation proportional to the change.
12. Stop when the work is ready for human review.

## Boundaries

`jump on` does not authorize committing, pushing, opening pull requests, merging, closing issues, force-pushing, or destructive Git operations. Those require an explicit later command such as `R4R`.

Scratch files in `/private/tmp` are temporary aids. They do not authorize doing implementation work in hidden clones or bypassing the registered checkout.

Do not create, switch to, or implement from a worktree during `jump on` unless the human explicitly approves that specific deviation first. If the main registered checkout is unavailable or unsafe because of unrelated local work, stop and ask how to proceed.

If the ticket spans multiple repos, handle each involved repo explicitly and report the status of each worktree at the end.
