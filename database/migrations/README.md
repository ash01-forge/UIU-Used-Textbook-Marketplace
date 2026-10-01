# Database Migrations Guide

This folder contains numbered SQL migration scripts for the BookBridge marketplace.

> [!CAUTION]
> **New team members: do NOT run migration scripts after a fresh install.**
> Migrations are only for upgrading an old database that predates the current schema.
> If you just imported `database/bookbridge.sql`, your schema is already up to date.
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

| File | Description | Status |
| :--- | :--- | :--- |
| `001_align_to_target_schema.sql` | Aligns the Oct-2026 phpMyAdmin backup schema to the agreed team target schema. Renames columns, adds new columns, inserts parent Department categories, creates the `wishlists` table. | ✅ Applied to `bookbridge_db` on 2026-10-01 |

---

## Execution via phpMyAdmin (if needed for Path B upgrade only)
1. Open `http://localhost/phpmyadmin`.
2. Select `bookbridge_db` in the left panel.
3. Click the **Import** tab.
4. Browse to the specific migration file and click **Go**.

## Execution via Command Line (XAMPP MySQL)
```bash
# Select the database explicitly — the migration file has no USE statement
mysql -u root bookbridge_db < "database/migrations/001_align_to_target_schema.sql"
```
