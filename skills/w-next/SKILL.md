---
name: w-next
description: Show where the work stands when the user says "w-next" (or asks "what's next?"). Prints the recently merged PRs (green), blocked tickets (red), tickets in progress or in review (black) and the next tickets to pick up (blue) from the GitHub project board. Read-only.
---

# W-Next

Use this skill when the user says `w-next`, optionally with a number of next tickets (`w-next 6`) or a board number (`w-next board 14`).

`w-next` is read-only. It reads the GitHub project board and merged pull requests and changes nothing: no labels, statuses, comments or files.

## Workflow

1. Run the snapshot script from the Yaup root:

   ```sh
   skills/w-next/w-next.py --owner erme2 --project 14 --merged 5 --next 4
   ```

   Use the number the user gave for `--next`, and the board they named for `--project`. Board 14 ("Sweegly") holds the Pane, Latte, Sweegly app, sweegly-home and deploy tickets.

2. Show the script's Markdown output to the user as it is. The terminal cannot color text, so the groups are marked with colored circles:
   - 🟢 recently merged PRs, newest first;
   - 🔴 blocked tickets (`status: blocked` label), by priority;
   - ⚫ tickets in progress or in review;
   - 🔵 the next tickets: `Todo`, not blocked, ordered by priority label (blocking, high, medium, low) and then board order.

3. After the list, add at most two short lines:
   - an inconsistency worth fixing, if one is obvious (for example a ticket in review with no open PR, or a blocked ticket whose blockers are closed);
   - when two agents are working, which of the next tickets can run in parallel (different repositories, no dependency between them).

Do not start work on any ticket. Ask before changing the board.
