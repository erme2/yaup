# Agent Notes

This directory stores durable notes for AI-assisted work across `yaup` and its related repositories.

Use it for:

- Cross-repo task notes and links.
- Decisions that should survive a single chat session.
- Commands, checks, or repo-specific context that are likely to be reused.

Do not store:

- Secrets, tokens, credentials, or private keys.
- Dependency folders or generated build output.
- Throwaway drafts, PR or review bodies, patches, logs, rendered artifacts, or
  other scratch files that belong in `/private/tmp`.

Structure:

```text
.agents/
  README.md
  memory/
    tasks/
    repos/
```
