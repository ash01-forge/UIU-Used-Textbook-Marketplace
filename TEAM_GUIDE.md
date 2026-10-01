# BookBridge Team Guide & Architecture Guidelines

Welcome team! This document defines the engineering conventions, module ownership, database lifecycle, and business rules for the BookBridge marketplace backend.

---

## 1. Team Ownership & Responsibilities

| Member  | Module / Directory Responsibility                                   | Scope Description |
| :------ | :----------------------------------------------------------------- | :---------------- |
| **Adeeb**  | `config/`, `includes/`, `database/`, `api/auth/`, `api/admin/` | Shared foundation, session & RBAC helpers, CSRF protection, database schema & migrations, admin APIs, and frontend integration. **Only Adeeb edits the compiled `app.js` bundle.** |
| **Tashin** | `api/marketplace/`                                                 | Guest & Buyer public browse, search by keyword/course code, department/subject/type filters, public listing details. |
| **Labib**  | `api/seller/`                                                      | Seller dashboard metrics, submitting listings, editing listings, marking sold, and seller sales history. |
| **Tanvir** | `api/buyer/`, `api/messages/`, `api/reviews/`                      | Buyer dashboard, wishlists, purchase request creation/cancellation, campus messaging chat, and 1-5 star reviews. |

> [!IMPORTANT]
> **Frontend Preservation Rule:**
> To prevent merge conflicts and preserve the UI, team members must build backend endpoints using PHP returning JSON. **Do not replace HTML files with PHP files** and **do not edit `app.js`**. Only Adeeb coordinates integration with `app.js`.

---

## 2. Core Architecture & Coding Standards

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

## 3. Marketplace Business Rules & State Transitions

### A. Listing Lifecycle & Admin Approval
A listing can exist in one of five explicit states:
1. `pending_approval`: Newly created by a seller. Hidden from the public marketplace.
2. `available`: Reviewed and approved by Admin. Live and visible to guests and buyers.
3. `changes_requested`: Admin reviewed the listing and requested edits (e.g. better photos, price correction). The `admin_feedback` column contains the required changes.
4. `rejected`: Rejected by Admin due to policy violations or inappropriate content. `admin_feedback` stores the reason.
5. `sold`: Item has been transacted on campus and handed over. Hidden from active search results.

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
- No online gateway or advance payments (such as bKash/Nagad) are processed in the backend.
- The buyer and seller agree on a meeting place inside UIU (e.g., UIU Library, Main Gate, Cafeteria) and preferred date.
- Money is physically handed over upon physical inspection of the textbook.

### D. Purchase Requests & Sold Status Transitions
1. Buyer submits request (`status: 'pending'`).
2. Seller reviews the request:
   - **Accepts:** `status: 'accepted'`. Buyer and seller proceed to meet.
   - **Declines:** `status: 'declined'`.
3. **Transaction Completion (Mark Sold):**
   - When the physical meeting occurs and cash is exchanged, the seller marks the listing as **Sold**.
   - This updates:
     - `listings.status` = `'sold'`
     - `purchase_requests.status` = `'completed'`, `completed_at` = `NOW()`
   - Once a listing is `'sold'`, it is no longer purchasable by others.

### E. Message & Review Eligibility Rules
- **Messaging Eligibility:**
  - Any logged-in buyer can message a seller regarding an active listing.
  - A seller can reply to any buyer who contacted them.
- **Review Eligibility:**
  - Only a buyer who completed a transaction (`purchase_requests.status = 'completed'`) is eligible to submit a 1-to-5 star rating and comment for that seller.
  - A buyer cannot review their own listing or review a seller without a completed purchase.

---

## 4. Database Coordination
- All database modifications must be accompanied by a numbered script in `database/migrations/`.
- Adeeb reviews and approves any modifications to shared tables.
