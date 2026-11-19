Opened by Efesto, the backend developer agent.

Closes #173

## Summary
- `DELETE /api/v1/users/{user_id}` (user's `uuid`): a user may anonymise themselves, an active administrator may anonymise anyone. `204` on success; self-deletion ends the session.
- `UserAnonymisationService`: deletes the WorkOS user first (404 counts as deleted, so retries are safe), then in one transaction blanks the user (`anon-<random>` name and email, no WorkOS id/org, details reduced to `anonymised_at`, inactive), revokes and redacts the user's invitations, redacts the email in audit `metadata`, and records `user.anonymise` with only the user id.
- WorkOS failure: nothing changes in Pane, `503 dependency_unavailable`, the provider message is never returned.
- Idempotent; the last active administrator can't be anonymised (`409`).
- New migration: the audit append-only update trigger now lets `metadata` change (and nothing else); deletes stay blocked. Needed because audit metadata held invitation emails.
- Docs in `docs/api-v1.md`.

## Validation
- `php artisan test --env=testing`: 360 passed, 1 skipped (fresh seeded test DB)
- `composer analyse`: no errors
- `vendor/bin/pint --dirty`: passed
- MySQL/MariaDB integration run: not run locally; the new trigger has MySQL/MariaDB and SQLite branches and only SQLite ran here, so CI's DB jobs are the check for the MySQL branch.

## Risks and follow-ups
- Audit redaction scans audit rows with metadata in PHP (no JSON search that works on all drivers). Fine for the MVP; an indexed lookup is a follow-up if the table grows.
- Audit `resource_ids`/`client_metadata` are immutable and assumed not to hold emails (true today; only `metadata` does).
- Anonymising the WorkOS side needs the API key to be configured; without it the call fails with 500 as other WorkOS calls do.
- The sweegly screens and web deletion page stay in erme2/sweegly#9.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
