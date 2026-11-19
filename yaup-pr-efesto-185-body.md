Opened by Efesto, the backend developer agent.

Closes #185

## Summary
- New nullable `access` JSON column on `maps_tables` (new migration), validated by `MapRules`, versioned by `MapStore`. Rule: `{"members": {"table", "key", "members_key", "members_user"}}`.
- `/crud/{subject}` reads non-system tables from the new maps (`maps_tables` / `maps_fields`); system subjects keep the legacy `map_*` tables (owner answer 1).
- Members rule on the route: a signed-in user reads, updates and deletes only rows whose key has a members row with their `uuid`. Lists are filtered; a key they don't belong to (or that doesn't exist) answers 403. Administrators keep everything; system tables stay admin-only.
- POST on a parent table (key is its own primary key) adds the creator's members row in the same transaction, when the members table has a rule. POST/PUT on other rule tables needs membership of the key in the body, so nobody can add themselves to someone else's list.
- Fail closed: a rule with an unmapped members table or unknown columns answers 500; `app:install` refuses it before writing.
- Docs: `docs/crud-authentication.md`, `docs/architecture.md`.

## Validation
- `./bash/refresh.sh -c yes -t yes -s yes -v no -o no -f testing`, then `php artisan test --env=testing`: 372 passed, 1 skipped (before the last install test; the AppInstall, crud and Maps suites re-run after: 72 passed, 1 skipped).
- `composer analyse`: no errors. `vendor/bin/pint --dirty`: passed.
- New `tests/Feature/crud/MembersAccessTest.php` covers member reads/writes, non-member, self-add to someone else's list, moving an item into a foreign list, creator row, map validation, admin, system subjects, fail-closed and unknown key field.
- MySQL/MariaDB integration tests were not run locally (SQLite suite only).

## Risks and follow-ups
- Fields whose `sql_name` differs from `name` are refused by the CRUD route (500) for now; no current map uses it.
- The members table's other columns need defaults/nullable for the creator row.
- Maps admin API (#157) should reuse the access validation when it edits a rule.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
