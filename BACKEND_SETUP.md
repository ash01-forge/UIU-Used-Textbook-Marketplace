# BookBridge Backend Setup Guide (XAMPP Localhost)

Welcome to the BookBridge (UIU Used Textbook Marketplace) backend development environment! This guide walks you through setting up your local environment using XAMPP on Windows.

---

## 1. Prerequisites

- **XAMPP** (with Apache and MySQL / MariaDB) installed.
- **PHP 8.0+** (included in modern XAMPP).
- Web browser (Chrome, Edge, Firefox).

---

## 2. Project Directory

Ensure the repository is placed in your XAMPP web root:

```
C:\xampp\htdocs\UIU-Used-Textbook-Marketplace
```

The application will be accessible at:

```
http://localhost/UIU-Used-Textbook-Marketplace/
```

---

## 3. Database Setup (MySQL/MariaDB)

### Option A: Using phpMyAdmin (Recommended for Beginners)

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open your browser and navigate to: `http://localhost/phpmyadmin/`.
3. If setting up a fresh install:
   - Click on the **Import** tab at the top.
   - Click **Choose File** and select:
     ```
     C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\bookbridge.sql
     ```
   - Click **Go** at the bottom.
   - The `bookbridge_db` database will be created with all 7 tables and initial demo seed data.
4. If migrating an existing database:
   - Ensure `bookbridge_db` is selected, then import:
     ```
     C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\migrations\001_align_to_target_schema.sql
     ```
5. The current fresh-install `bookbridge.sql` includes the user department and category relationship columns. Older installations missing those columns need `database/migrations/002_category_relationship_columns.sql` before registration or category management. This additive migration preserves existing values. Do not rerun migration 001 on a fresh install.
6. Older installations where `listings.subject` is still `NOT NULL` need `database/migrations/003_nullable_listing_subject.sql`. It aligns the column with the API's optional subject and the fresh-install schema, preserving existing listing values. It can safely be applied again. Fresh installs already allow a missing subject.

### Option B: Using MySQL Command Line

Run the following in PowerShell / Command Prompt:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < "C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\bookbridge.sql"
```

---

## 4. Configuration

1. In the `config/` folder, check if `config.php` exists. If not, copy `config.example.php`:
   - Copy `config/config.example.php` -> `config/config.php`
2. Open `config/config.php` and verify your local settings:
   ```php
   'db' => [
       'host'     => '127.0.0.1',
       'port'     => 3306,
       'dbname'   => 'bookbridge_db',
       'username' => 'root',
       'password' => '', // Default XAMPP password is empty
       'charset'  => 'utf8mb4',
   ],
   ```
3. `config/config.php` is ignored by Git, so your local passwords won't accidentally be committed.

---

## 5. Verify Setup (Health Endpoint)

Open your browser or run a test in Postman/browser to visit:

```
http://localhost/UIU-Used-Textbook-Marketplace/api/health.php
```

You should receive an HTTP 200 JSON response:

```json
{
  "status": "ok",
  "timestamp": "2026-10-01T14:00:00+06:00",
  "environment": "development",
  "php_version": "8.2.x",
  "services": {
    "database": "connected"
  }
}
```

If the database shows `"disconnected"`:

- Ensure MySQL is running in the XAMPP Control Panel (green light on port 3306).
- Check that the `bookbridge` database was imported in phpMyAdmin.

---

## 6. Seed Accounts for Testing

The seed file `database/bookbridge.sql` includes ready-to-use demo accounts:

| Role   | Full Name    | UIU Email          | Password      | Student ID  |
| :----- | :----------- | :----------------- | :------------ | :---------- |
| Admin  | Admin User   | `admin@uiu.ac.bd`  | `password123` | `011200001` |
| Seller | Rafiul Islam | `seller@uiu.ac.bd` | `password123` | `011211054` |
| Buyer  | Zahir Raihan | `buyer@uiu.ac.bd`  | `password123` | `011211088` |
| Seller | Nusrat Jahan | `nusrat@uiu.ac.bd` | `password123` | `011212030` |
| Buyer  | Tanvir Ahmed | `tanvir@uiu.ac.bd` | `password123` | `011213012` |

---

## 7. Frontend Authentication

Run Apache and MySQL, then open the project through `http://localhost/UIU-Used-Textbook-Marketplace/`. Do not use Live Server for authentication: PHP endpoints and the session cookie require the XAMPP origin.

- Sign in from the main marketplace or `seller-login.html`; the server session and returned role determine the destination.
- Use the root app's **Admin Portal** route to sign in as an administrator. The server-returned role sends authorized admins to `admin-dashboard.html`; direct visits to that page require a valid admin session.
- Create buyer or seller accounts at `register.html` with an email at `uiu.ac.bd` or a subdomain such as `bscse.uiu.ac.bd`. Other domains and admin self-registration are rejected.
- Buyer, seller, and admin pages restore the session from `api/auth/me.php`, display the server's account name, and provide a server-backed sign-out action. Guests are sent to sign-in; a buyer or seller cannot open the admin page.
- Admin dashboard counts, listing moderation, category management, and completed-sales reports use the admin APIs. Revenue is unavailable because completed purchases do not store transaction-time prices.
- Buyer dashboard metrics and recommendations, saved listings, purchase requests and cancellations, eligible reviews, messages, seller listings and moderation feedback, buyer request actions, seller reviews, and sales-history rows are loaded from their existing APIs. Revenue is not calculated from current listing prices when no sale-time snapshot exists.
- Guest browse, search, department/subject/category/type/condition/price filters, sorting, pagination and public listing details use the read-only APIs under `api/marketplace/`. Buyer purchase actions remain session- and role-protected.
- Fresh `bookbridge.sql` installs include the category `department`/`subject` fields and `users.department`; migration 002 is only needed for older installations missing them and is not run automatically.
- If PHP or an API is unavailable, connected screens show an error state and do not substitute fake API success or counts.

Buyer savings are labeled estimated because the dashboard derives them from current listing prices. Seller sales history omits a sale-price column because transaction-time prices are not stored. Admin user-management actions are not part of the current API contract.

## 8. Running Backend Tests (CLI Only)

For security, test runners are placed in the `tests/` directory outside the public `api/` directory, and web execution is blocked (HTTP 403 Forbidden). Tests must be executed directly via command line.

### Prerequisites:

1. XAMPP **Apache** and **MySQL** must be running.
2. The `bookbridge_db` database must be initialized.

### Test Execution Command:

Open PowerShell or Command Prompt in the project root and run:

```powershell
C:\xampp\php\php.exe tests\auth_test.php
```

Run the admin API integration suite separately:

```powershell
C:\xampp\php\php.exe tests\admin_test.php
```

The admin suite is CLI-only. It creates uniquely tagged temporary admin/buyer/seller users, category/listing fixtures, and a completed purchase request; it tracks and deletes only those fixture IDs at shutdown. It does not run migrations, reset tables, or alter demo users/categories/listings.

### Safety Features:

- **CLI Only**: The test runner rejects browser/HTTP invocations before performing any operations.
- **Disposable Accounts**: Uses dynamically generated temporary emails (`test_buyer_<uniq>@uiu.ac.bd`) and automatically cleans them up after completion.
- **Data Preservation**: Baseline demo users (IDs 1, 2, 3) and existing database listings/categories are strictly preserved and never modified or deleted.

The admin endpoint behavior and schema limitations are documented in `API_CONTRACT.md` under **Admin Management**. No user-management endpoint is included because no user operations are defined by the current contract.

Run the release-readiness regression suite with `C:\xampp\php\php.exe tests\release_readiness_test.php`. It requires permission to create/drop a temporary database. It verifies fresh schema compatibility, seed passwords, UIU email validation, and concurrent submissions through the actual review endpoint code. Its uniquely named test database is removed afterwards; it does not change application accounts, run application migrations, or reset application tables.

### Recorded sale amounts

Existing installations must run `database/migrations/004_sale_price_snapshot.sql` before deploying the updated APIs. It is repeatable and leaves earlier completed sales unpriced. Fresh installations include the column. A completed meetup records the locked listing price; later listing edits/relisting do not change historical amounts. Reports show recorded revenue and explicitly exclude unpriced older transactions.

## Active-user tracking upgrade

Back up the existing database, select it in phpMyAdmin and import `database/migrations/005_user_activity_tracking.sql`. This additive migration preserves existing marketplace records and matches legacy signed/unsigned user IDs. Repeating it does not reset the tracking-start timestamp. The current fresh schema includes these tables; do not reimport it into an existing database.

Active Users means unique authenticated accounts in the selected report period, including all roles, excluding guests and failed logins. Login and protected API guards record at most one row per account/database-calendar day. No IP addresses or tokens are stored. Prior activity is unknown and partial coverage is disclosed. It is not a currently-online indicator.
