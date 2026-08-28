---
name: r4r
description: Publish current Yaup ticket work for review when the user says R4R. Re-check scope, commit intended changes, push branches, open/update PRs, and move the ticket to In review.
---

# R4R

Use this skill when the user says `R4R`, `r4r`, or an equivalent ready-for-review command for current ticket work.

`R4R` means the human has reviewed the local working tree and explicitly delegates the GitHub publishing steps for the current ticket only.

Follow `docs/agent-workflow-autonomy.md` for the allowed autonomous actions and ask-first boundaries. `R4R` authorizes staging intended files, committing, pushing, opening or updating pull requests, linking issues, creating missing labels/project entries/milestones when needed, moving the project item to `In review`, and checking CI.

## Workflow

1. Identify the current ticket and every involved project.
2. Inspect each involved worktree and confirm the branch and diff belong to the current ticket.
3. Run or verify relevant validation before publishing. If validation is stale, rerun it unless clearly unnecessary.
4. Use `/private/tmp` for pull request bodies, patch files, review notes, logs, and other temporary publication artifacts.
5. Commit only intended ticket changes in each involved project that has a relevant diff.
6. Push each ticket branch.
7. Open or update a pull request for each involved project with:
   - a clear title and summary,
   - the issue link,
   - validation notes,
   - remaining risks or follow-up notes.
8. Move the ticket status to `In review` in the project board when project access is available.
9. End the response with a clear list of PR links, one per involved project.

## Boundaries

Do not include unrelated local changes. Report any involved project that has no remaining diff, is already merged, or cannot produce a non-empty PR.

Scratch files in `/private/tmp` are not durable project records and must not be committed unless the user explicitly asks to promote one into the repository.

`R4R` does not authorize PR approvals, force pushes, branch deletion, destructive Git commands, publishing changes outside the ticket scope, merging PRs, or closing tickets outside normal linked-PR merge closure.
