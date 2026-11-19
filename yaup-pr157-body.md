Opened by Efesto, the backend developer agent.

Closes #157

## Summary
Maps admin API under `/api/v1/maps`, admin role only (`user` gets `403`), following the owner's decisions on the ticket (2026-11-16 and 2026-11-19: no organization segment).

- `PUT /maps/{source}/{table}`: create (`If-None-Match: *`, `201`) or replace (`If-Match` with the table map `ETag`) the whole map. The table and every non-computed field must exist (`422` with a per-path `fields` object). No route runs DDL.
- `GET /maps/{source}/{table}/versions` and `POST .../versions/{version}/restore` (`If-Match`).
- `GET` and `PUT /maps/menu` (whole menu, versioned as subject `menu`), `GET /maps/menu/versions`, `POST /maps/menu/versions/{version}/restore`.
- `PATCH /maps/functions/{key}` with `{enabled}` and `If-Match` `"<key>-on|off"`; audited, not versioned.
- System maps: `409 system_map_protected`, even for admins.
- Every edit, restore and switch writes an `audit_events` row (actor, subject, from/to versions); the diff stays in `maps_versions`.
- `MapStore::tableVersion()` extracted; `MapReader::etag()` shared by the read and edit routes. Docs: `docs/api-v1.md`, `docs/architecture.md`.

## Validation
- `php artisan test --env=testing`: 373 passed, 1 skipped, 1 failed. The failure is `DeleteActionTest > with valid key`, which is flaky: it fails the same way on `main` without this change and passes alone.
- New `tests/Feature/Maps/MapAdminApiTest.php` (13 tests) passes.
- `composer analyse`: no errors. `vendor/bin/pint --dirty`: pass.
- MySQL integration tests not run locally (CI runs them).

## Risks and follow-ups
- `contracts/pane-v1.json` still describes the removed organization routes (including the maps read routes), so the new routes are documented in `docs/api-v1.md` only. A contract refresh needs its own ticket.
- A source named `menu` or `functions` would be shadowed by the fixed routes on a few paths; the fixed routes are registered first.
- `DeleteActionTest` flakiness is pre-existing.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
