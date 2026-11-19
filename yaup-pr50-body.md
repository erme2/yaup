Opened by Efesto, the backend developer agent.

Closes #50

## Summary
- Moves Sweegly's table migrations (9 files, unchanged), `bin/migrate`, its test and `docs/data-model.md` from the local sweegly-data work (`feature/19-initial-migrations`) and `main` into `data/`, as the owner decided on the ticket.
- Script and test only changed in the migrations path (`database/migrations` -> `migrations`).
- Adds `data/README.md` (what the folder is, rules, Pane's artisan command Pane#186), a CI job running `data/tests/migrate_test.sh`, README and CHANGELOG entries.
- No change to `app/`. No reference to sweegly-data remains.

## Validation
- `data/tests/migrate_test.sh` run in a throwaway `mariadb:11` container: all 14 checks passed.
- Not run: a real `dbmate up` against a database (shell scripts could not be run directly in this session); migrations are byte-identical to the originals.

## Risks / follow-ups
- Archiving erme2/sweegly-data is the separate archive ticket.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
