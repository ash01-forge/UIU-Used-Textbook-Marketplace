# Database Migrations Guide

This folder contains numbered SQL migration scripts for the BookBridge marketplace.

## Conventions
1. All files follow the pattern `XXX_short_description.sql` (e.g., `001_initial_schema.sql`).
2. Every statement uses `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE`, or non-destructive DDL.
3. **NEVER** use `DROP DATABASE` or `DROP TABLE` in migrations to protect student marketplace data.
4. Execute migrations in ascending numerical order.

## Execution via phpMyAdmin
1. Open `http://localhost/phpmyadmin`.
2. Select or create the `bookbridge` database.
3. Click the **Import** tab.
4. Browse to the specific migration file and click **Go**.

## Execution via Command Line (XAMPP MySQL)
```bash
mysql -u root -p bookbridge < database/migrations/001_initial_schema.sql
```
