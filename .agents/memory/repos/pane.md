# Pane

Repository: https://github.com/erme2/Pane

Recent context:

- PR #30 adds WorkOS auth.
- Review feedback addressed in commit `03a9500`.
- Follow-up issue for PR test automation: https://github.com/erme2/Pane/issues/31
- 2026-11-07: PR #118 (code-comment rules) and PR #123 (issue #120, multiple trusted
  frontends via `PANE_EXTRA_FRONTEND_ORIGINS`) merged; `2b62023` deployed to Hostinger.
- Known flake: the "Cleanup stale branches" workflow races when two merges land close
  together; the second run fails with 422 "Reference does not exist" on branch delete.
  Harmless, but the delete call should tolerate 422.
