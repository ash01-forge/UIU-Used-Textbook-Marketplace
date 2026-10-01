# Database Migrations Guide

This folder contains numbered SQL migration scripts for the BookBridge marketplace.

> [!CAUTION]
> **Do not run migration 001 after a fresh install.** It is only for upgrading the legacy backup schema.
> Migration 002 is additive and is needed on fresh installs as well as upgraded installs
> to provide optional department/subject relationship columns used by category management.
> See [TEAM_GUIDE.md](../TEAM_GUIDE.md) §4 for the correct setup path.

---

## Conventions

1. All files follow the pattern `XXX_short_description.sql` (e.g., `001_align_to_target_schema.sql`).
2. Every statement uses `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE IF NOT EXISTS` or non-destructive DDL.
3. **NEVER** use `DROP DATABASE` or `DROP TABLE` in migrations — protects student marketplace data.
4. Migrations must **not** contain a hardcoded `USE <database>;` statement — the caller selects the target.
5. Execute migrations in ascending numerical order.
6. Each migration is idempotent (safe to inspect; running twice should not corrupt data).

---

## Current Migrations

| File                                    | Description                                                                                                                                                                                           | Status                                      |
| :-------------------------------------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------ |
| `001_align_to_target_schema.sql`        | Aligns the Oct-2026 phpMyAdmin backup schema to the agreed team target schema. Renames columns, adds new columns, inserts parent Department categories, creates the `wishlists` table.                | ✅ Applied to `bookbridge_db` on 2026-10-01 |
| `002_category_relationship_columns.sql` | Adds nullable category department/subject compatibility fields and the optional user department field; preserves populated values and only infers unambiguous subject parents from existing listings. | Added; not executed in this task            |

---

## Execution via phpMyAdmin (if needed for Path B upgrade only)

1. Open `http://localhost/phpmyadmin`.
2. Select `bookbridge_db` in the left panel.
3. Click the **Import** tab.
4. For a legacy backup, apply `001_align_to_target_schema.sql` first if it has not already been applied. Then apply `002_category_relationship_columns.sql` for category CRUD support.
5. For a fresh `bookbridge.sql` install, do not apply migration 001; apply only `002_category_relationship_columns.sql` when category relationship management is needed.

## Execution via Command Line (XAMPP MySQL)

```bash
# Legacy schema upgrade only. Never run 001 after importing bookbridge.sql.
mysql -u root bookbridge_db < "database/migrations/001_align_to_target_schema.sql"
# Fresh or upgraded schema: optional relationship support for category CRUD.
mysql -u root bookbridge_db < "database/migrations/002_category_relationship_columns.sql"
```
