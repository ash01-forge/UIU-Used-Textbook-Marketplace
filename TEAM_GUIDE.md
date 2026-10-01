# BookBridge Team Guide & Architecture Guidelines

Welcome team! This document defines the engineering conventions, module ownership, database lifecycle, member branches, and business rules for the BookBridge marketplace backend.

---

## 1. Team Ownership & Responsibilities

| Member  | Module / Directory Responsibility                                    | Scope Description |
| :------ | :------------------------------------------------------------------- | :---------------- |
| **Adeeb**  | `config/`, `includes/`, `database/`, `api/auth/`, `api/admin/`  | Shared foundation, session & RBAC helpers, CSRF protection, database schema & migrations, admin APIs, and frontend integration. **Only Adeeb edits the compiled `app.js` bundle.** |
| **Tashin** | `api/marketplace/`                                                  | Guest & Buyer public browse, search by keyword/course code, department/subject/type filters, public listing details. |
| **Labib**  | `api/seller/`                                                       | Seller dashboard metrics, submitting listings, editing listings, marking sold, and seller sales history. |
| **Tanvir** | `api/buyer/`, `api/messages/`, `api/reviews/`                       | Buyer dashboard, wishlists, purchase request creation/cancellation, campus messaging chat, and 1-5 star reviews. |

> [!IMPORTANT]
> **Frontend Preservation Rule:**
> To prevent merge conflicts and preserve the UI, team members must build backend endpoints using PHP returning JSON. **Do not replace HTML files with PHP files** and **do not edit `app.js`**. Only Adeeb coordinates integration with `app.js`.

---

## 2. Git Branch & Pull Request Workflow

Each team member works in their own dedicated branch. All changes are merged into `backend-development` via Pull Request — **never directly to `main`**.

| Member  | Branch Name          | Targets              |
| :------ | :------------------- | :------------------- |
| Adeeb   | `backend/adeeb`      | `backend-development` |
| Tashin  | `backend/tashin`     | `backend-development` |
| Labib   | `backend/labib`      | `backend-development` |
| Tanvir  | `backend/tanvir`     | `backend-development` |

### Branch workflow (step-by-step):
```bash
# 1. Always start from a fresh copy of backend-development
git checkout backend-development
git pull origin backend-development

# 2. Create or switch to your branch
git checkout -b backend/tashin        # first time
git checkout backend/tashin           # after already created

# 3. Write your PHP endpoints, commit often
git add api/marketplace/listings.php
git commit -m "Add listings filter endpoint"

# 4. Push your branch and open a Pull Request targeting backend-development
git push origin backend/tashin
```

> [!NOTE]
> Pull Requests must be reviewed by Adeeb before merging to ensure shared helper compatibility.

---

## 3. Core Architecture & Coding Standards

1. **Procedural Plain PHP:**
   - Keep scripts simple and readable for all team members.
   - Use functions from `includes/response.php`, `includes/auth.php`, and `includes/csrf.php`.

2. **PDO with Prepared Statements (Mandatory):**
   - **Never** concatenate user input into SQL queries.
   - Always use prepared statements with placeholders (`?` or `:name`).
   ```php
   // Correct PDO prepared statement example:
   $db = getDbConnection();
   $stmt = $db->prepare('SELECT * FROM listings WHERE id = ? AND status = ?');
   $stmt->execute([$listingId, 'available']);
   $listing = $stmt->fetch();
   ```

3. **Standardized JSON Responses:**
   - Always use the response helpers:
   ```php
   require_once __DIR__ . '/../../includes/response.php';

   // For successful requests:
   sendSuccessResponse('Listing created successfully', ['id' => $newId], 201);

   // For errors:
   sendErrorResponse('Invalid price entered', 422, ['price' => 'Must be a positive number']);
   ```

4. **Authentication & Role Guards:**
   ```php
   require_once __DIR__ . '/../../includes/auth.php';

   // Require login:
   $currentUser = requireLogin();

   // Require specific role:
   $seller = requireRole('seller'); // Halts with 403 if not a seller
   $admin  = requireRole('admin');  // Halts with 403 if not an admin
   ```

5. **CSRF Validation on State Modifications:**
   ```php
   require_once __DIR__ . '/../../includes/csrf.php';

   // Enforce CSRF on POST, PUT, DELETE:
   requireCsrfToken();
   ```

---

## 4. Database Setup for New Team Members

> [!IMPORTANT]
> **Read this section carefully.** There are two paths depending on whether you are setting up from scratch or upgrading an existing database.

### Path A — Fresh Installation (Recommended for all new members)

Use this if you do **not** yet have a `bookbridge_db` database on your machine.

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/`.
3. Click the **Import** tab at the top.
4. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\bookbridge.sql
   ```
5. Click **Go**.
6. The `bookbridge_db` database is created with all 7 tables and demo seed data — ready to use.

> [!CAUTION]
> **Do NOT run the migration script (`database/migrations/001_align_to_target_schema.sql`) after a fresh install.** The migration is only for upgrading an old pre-migration database. Running it on a fresh install will fail or produce duplicate data.

### Path B — Upgrading an Existing Old Database

Use this **only** if you already had a `bookbridge_db` created before the backend foundation was set up (i.e., it has `item_condition` instead of `condition_type`, or `admin_note` instead of `admin_feedback`).

1. **Create a backup first** (via phpMyAdmin → Export).
2. Open `http://localhost/phpmyadmin/`, select `bookbridge_db`.
3. Click the **Import** tab and import:
   ```
   C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\migrations\001_align_to_target_schema.sql
   ```
4. Click **Go**.

> [!NOTE]
> If you are unsure which path applies to you, check your `bookbridge_db` → `users` table. If it has a column named `full_name`, you already have the migrated/fresh schema. Use **Path A** for a clean reinstall if anything looks wrong.

---

## 5. Configuration

1. In the `config/` folder, check if `config.php` exists. If not, copy `config.example.php`:
   ```
   config/config.example.php  →  config/config.php
   ```
2. Open `config/config.php` and verify:
   ```php
   'db' => [
       'host'     => '127.0.0.1',
       'port'     => 3306,
       'dbname'   => 'bookbridge_db',   // ← must be bookbridge_db
       'username' => 'root',
       'password' => '',
       'charset'  => 'utf8mb4',
   ],
   ```
3. `config/config.php` is in `.gitignore` — it will never be committed.

---

## 6. Marketplace Business Rules & State Transitions

### A. Listing Lifecycle & Admin Approval
A listing can exist in one of five explicit states:
1. `pending_approval`: Newly created by a seller. Hidden from the public marketplace.
2. `available`: Reviewed and approved by Admin. Live and visible to guests and buyers.
3. `changes_requested`: Admin requested changes. The `admin_feedback` column contains the required changes.
4. `rejected`: Rejected by Admin. `admin_feedback` stores the reason.
5. `sold`: Transacted on campus. Hidden from active search results.

```
       [Seller Submits]
              │
              ▼
      ┌────────────────┐
      │pending_approval│
      └───────┬────────┘
              │ (Admin Reviews)
       ┌──────┴──────────────────────┐
       ▼                             ▼
┌───────────────┐           ┌─────────────────┐
│   available   │           │changes_requested│
└──────┬────────┘           └────────┬────────┘
       │                             │ (Seller Edits)
       │ (Cash on Meet)              ▼
       ▼                    ┌────────────────┐
┌───────────────┐           │pending_approval│
│     sold      │           └────────────────┘
└───────────────┘
```

### B. Seller Ownership Rule
- A seller can **only** edit or update listings where `seller_id = $_SESSION['user']['id']`.
- Never trust an `id` or `seller_id` passed in the request body without verifying ownership in the database.

### C. Cash on Meet Only Policy
- BookBridge operates strictly on **Cash on Meet**.
- No online gateway or advance payments (bKash/Nagad) are processed in the backend.
- The `purchase_requests.payment_method` column is an `ENUM('cash_on_meet')` — the database itself enforces the policy.

### D. Purchase Requests & Sold Status Transitions
1. Buyer submits request (`status: 'pending'`).
2. Seller reviews the request:
   - **Accepts:** `status: 'accepted'`. Buyer and seller proceed to meet.
   - **Declines:** `status: 'declined'`.
3. **Transaction Completion:**
   - Seller marks the listing as **Sold**.
   - Updates: `listings.status = 'sold'`, `purchase_requests.status = 'completed'`, `purchase_requests.completed_at = NOW()`.

### E. Message & Review Eligibility Rules
- **Messaging:** Any logged-in user can message regarding an active listing.
- **Review:** Only a buyer with `purchase_requests.status = 'completed'` may submit a 1-5 star review.

---

## 7. Database Coordination
- All database modifications must be accompanied by a numbered script in `database/migrations/`.
- Adeeb reviews and approves any modifications to shared tables.
- **Never** run `DROP TABLE` or `TRUNCATE` in migrations.
