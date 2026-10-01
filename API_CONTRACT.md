# BookBridge API Contract & Specification

This document specifies the RESTful JSON API endpoints, request/response structures, error formats, and authentication conventions for the BookBridge marketplace.

---

## 1. Global Conventions

### Base URL

```
http://localhost/UIU-Used-Textbook-Marketplace/api
```

### Headers

| Header         | Value              | Description                                              |
| :------------- | :----------------- | :------------------------------------------------------- |
| `Content-Type` | `application/json` | Required for all POST/PUT/PATCH requests                 |
| `Accept`       | `application/json` | Client expects JSON responses                            |
| `X-CSRF-Token` | `<token>`          | Required on state-modifying requests (POST, PUT, DELETE) |

### Response Envelopes

#### Success Response (HTTP 200 / 201)

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {}
}
```

#### Error Response (HTTP 400 / 401 / 403 / 404 / 405 / 409 / 422 / 500)

```json
{
  "success": false,
  "message": "User-friendly description of error",
  "errors": {
    "field_name": "Specific validation error"
  }
}
```

---

## 2. Shared Foundation Endpoints

### 2.1 System Health

- **URL:** `GET /api/health.php`
- **Access:** Public
- **Response (200 OK):**

```json
{
  "status": "ok",
  "timestamp": "2026-10-01T14:00:00+06:00",
  "environment": "development",
  "php_version": "8.2.12",
  "services": {
    "database": "connected"
  }
}
```

---

## 3. Module API Specifications (For Future Milestones)

Below is the agreed endpoint contract to guide individual module development:

### 3.1 Authentication & Session (`api/auth/` - Owner: Adeeb)

| Method | Endpoint                 | Access        | Description                                                                                                          |
| :----- | :----------------------- | :------------ | :------------------------------------------------------------------------------------------------------------------- |
| `POST` | `/api/auth/login.php`    | Public        | Authenticates credentials, regenerates session ID, sets cookie, returns user & CSRF token                            |
| `POST` | `/api/auth/register.php` | Public        | Registers a new student account (`buyer` or `seller` only; `admin` blocked), sets session, returns user & CSRF token |
| `GET`  | `/api/auth/me.php`       | Authenticated | Returns current logged-in user profile & fresh CSRF token (401 if unauthenticated)                                   |
| `POST` | `/api/auth/logout.php`   | Authenticated | Validates CSRF token, destroys session, clears cookie                                                                |
| `GET`  | `/api/auth/csrf.php`     | Public        | Generates or retrieves current session CSRF token                                                                    |

#### Registration Request Payload (`POST /api/auth/register.php`):

```json
{
  "full_name": "Rahim Ahmed",
  "email": "rahim@uiu.ac.bd",
  "password": "StrongPassword123",
  "role": "buyer",
  "student_id": "011211099",
  "department": "CSE"
}
```

- `role` must be strictly `"buyer"` or `"seller"`. Supplying `"admin"` yields HTTP 422.
- `password` must be at least 8 characters.
- `email` must be unique in `users` table.

#### Registration Success Response (201 Created):

```json
{
  "success": true,
  "message": "Registration successful! Welcome to BookBridge.",
  "data": {
    "user": {
      "id": 4,
      "full_name": "Rahim Ahmed",
      "email": "rahim@uiu.ac.bd",
      "role": "buyer",
      "student_id": "011211099"
    },
    "csrf_token": "a1b2c3d4e5f6..."
  }
}
```

#### Login Request Payload (`POST /api/auth/login.php`):

```json
{
  "email": "seller@uiu.ac.bd",
  "password": "password123"
}
```

#### Login Success Response (200 OK):

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": {
      "id": 2,
      "full_name": "Rafiul Islam",
      "email": "seller@uiu.ac.bd",
      "role": "seller",
      "student_id": "011211054"
    },
    "csrf_token": "a1b2c3d4e5f6..."
  }
}
```

#### Current User Profile Response (`GET /api/auth/me.php`):

- Header: Requires active session cookie `bookbridge_session`.

```json
{
  "success": true,
  "message": "Session is active.",
  "data": {
    "user": {
      "id": 2,
      "full_name": "Rafiul Islam",
      "email": "seller@uiu.ac.bd",
      "role": "seller",
      "student_id": "011211054",
      "phone": null,
      "avatar_url": null
    },
    "csrf_token": "a1b2c3d4e5f6..."
  }
}
```

#### Logout Request (`POST /api/auth/logout.php`):

- Header: Requires active session cookie AND `X-CSRF-Token: <token>` (or `csrf_token` in body).

```json
{
  "success": true,
  "message": "You have been logged out successfully."
}
```

#### CSRF Token Request (`GET /api/auth/csrf.php`):

```json
{
  "success": true,
  "message": "CSRF token issued.",
  "data": {
    "csrf_token": "a1b2c3d4e5f6..."
  }
}
```

#### Standard Error Response Format:

```json
{
  "success": false,
  "message": "Registration failed. Please fix the errors below.",
  "errors": {
    "email": "An account with this email address already exists.",
    "password": "Password must be at least 8 characters long."
  }
}
```

- **HTTP 401**: Unauthenticated / invalid credentials.
- **HTTP 403**: CSRF validation failed / unauthorized role access.
- **HTTP 405**: Method not allowed (e.g. GET on register.php).
- **HTTP 422**: Validation errors / duplicate entries / prohibited privileges.

---

### 3.2 Marketplace & Browse (`api/marketplace/` - Owner: Tashin)

| Method | Endpoint                                       | Access | Description                                                                               |
| :----- | :--------------------------------------------- | :----- | :---------------------------------------------------------------------------------------- |
| `GET`  | `/api/marketplace/listings.php`                | Public | Returns available listings with optional filters (`search`, `department`, `type`, `page`) |
| `GET`  | `/api/marketplace/listing-details.php?id={id}` | Public | Returns details of a specific live listing                                                |
| `GET`  | `/api/marketplace/categories.php`              | Public | Returns available departments and subjects                                                |

#### Listings Filter Query Parameters:

- `search`: Keyword matching title, author, course code, or subject.
- `department`: Filter by department (e.g. `CSE`, `EEE`, `BBA`).
- `type`: Filter by type (`Textbook`, `Notes`, `Lab Manual`).

#### Listings Response (200 OK):

```json
{
  "success": true,
  "message": "Listings retrieved",
  "data": {
    "total": 1,
    "listings": [
      {
        "id": 1,
        "title": "Data Structures and Algorithms",
        "course_code": "CSE-2101",
        "department": "CSE",
        "item_type": "Textbook",
        "condition_type": "Like New",
        "price": 450.0,
        "image_url": "https://images.unsplash.com/...",
        "seller_name": "Rafiul Islam",
        "seller_rating": 4.8
      }
    ]
  }
}
```

---

### 3.3 Seller Management (`api/seller/` - Owner: Labib)

| Method | Endpoint                        | Access | Description                                            |
| :----- | :------------------------------ | :----- | :----------------------------------------------------- |
| `GET`  | `/api/seller/dashboard.php`     | Seller | Seller metrics (total listings, active, sold, revenue) |
| `GET`  | `/api/seller/listings.php`      | Seller | Listings owned by logged-in seller                     |
| `POST` | `/api/seller/add-listing.php`   | Seller | Creates a new listing with status `pending_approval`   |
| `PUT`  | `/api/seller/edit-listing.php`  | Seller | Updates listing owned by seller                        |
| `POST` | `/api/seller/mark-sold.php`     | Seller | Marks listing as `sold` and concludes transaction      |
| `GET`  | `/api/seller/sales-history.php` | Seller | History of completed sales                             |

#### Add Listing Request Payload:

```json
{
  "title": "Artificial Intelligence: A Modern Approach",
  "author": "Stuart Russell",
  "edition": "4th Edition",
  "course_code": "CSE-4101",
  "department": "CSE",
  "subject": "Artificial Intelligence",
  "item_type": "Textbook",
  "condition_type": "Good",
  "price": 500,
  "description": "Clean copy with minimal pencil markings."
}
```

---

### 3.4 Buyer & Transactions (`api/buyer/` - Owner: Tanvir)

| Method | Endpoint                          | Access | Description                                                |
| :----- | :-------------------------------- | :----- | :--------------------------------------------------------- |
| `GET`  | `/api/buyer/dashboard.php`        | Buyer  | Buyer stats (wishlist count, active requests, money saved) |
| `GET`  | `/api/buyer/wishlist.php`         | Buyer  | Listings saved by buyer                                    |
| `POST` | `/api/buyer/wishlist.php`         | Buyer  | Add or remove listing from wishlist                        |
| `POST` | `/api/buyer/purchase-request.php` | Buyer  | Submit Cash on Meet purchase request                       |
| `GET`  | `/api/buyer/my-requests.php`      | Buyer  | List of buyer's purchase requests                          |

#### Purchase Request Payload (Cash on Meet only):

```json
{
  "listing_id": 1,
  "meeting_location": "UIU Library",
  "preferred_date": "2026-10-05",
  "note": "Can meet during lunch break."
}
```

---

### 3.5 Messaging & Reviews (Owner: Tanvir)

| Method | Endpoint                                     | Access        | Description                                       |
| :----- | :------------------------------------------- | :------------ | :------------------------------------------------ |
| `GET`  | `/api/messages/conversations.php`            | Authenticated | List conversations and unread counts              |
| `GET`  | `/api/messages/thread.php?with_user_id={id}` | Authenticated | Chat history with specific user                   |
| `POST` | `/api/messages/send.php`                     | Authenticated | Send a campus coordination message                |
| `POST` | `/api/reviews/create.php`                    | Buyer         | Submit a 1-5 star review for a completed purchase |

---

### 3.6 Admin Management (`api/admin/` - Owner: Adeeb)

| Method   | Endpoint                          | Access | Description                                                                  |
| :------- | :-------------------------------- | :----- | :--------------------------------------------------------------------------- |
| `GET`    | `/api/admin/dashboard.php`        | Admin  | Counts by listing status and user role, pending reviews, and completed sales |
| `GET`    | `/api/admin/pending-listings.php` | Admin  | Filtered, paginated pending queue with moderation details                    |
| `POST`   | `/api/admin/review-listing.php`   | Admin  | Approve, reject, or request changes for a pending listing                    |
| `GET`    | `/api/admin/categories.php`       | Admin  | List departments and subjects with their relationships                       |
| `POST`   | `/api/admin/categories.php`       | Admin  | Create a department or a subject under an existing department                |
| `PUT`    | `/api/admin/categories.php`       | Admin  | Rename a category and preserve linked relationships                          |
| `DELETE` | `/api/admin/categories.php`       | Admin  | Delete only an unreferenced category                                         |
| `GET`    | `/api/admin/sales-report.php`     | Admin  | Filtered, paginated completed transaction counts and rows                    |

Every endpoint requires an authenticated `admin` session. Guests receive HTTP 401; authenticated buyers/sellers receive HTTP 403. POST, PUT, and DELETE require `X-CSRF-Token` (or the shared helper's accepted body token). Responses use the global JSON envelope and do not expose password hashes or unnecessary account fields.

#### Dashboard

`GET /api/admin/dashboard.php` returns live counts:

```json
{
  "success": true,
  "message": "Admin dashboard counts retrieved.",
  "data": {
    "pending_review_count": 1,
    "listing_counts": {
      "pending_approval": 1,
      "available": 6,
      "changes_requested": 0,
      "rejected": 0,
      "sold": 0
    },
    "user_counts": { "buyer": 2, "seller": 2, "admin": 1 },
    "completed_sales_count": 0,
    "revenue": null,
    "revenue_note": "Unavailable: completed purchases do not store transaction-time prices."
  }
}
```

Revenue is unavailable because `purchase_requests` has no transaction-time price snapshot. Current listing prices are not realized sale amounts and are not summed as revenue.

#### Pending Listing Queue

`GET /api/admin/pending-listings.php` returns only listings currently in `pending_approval`. Supported query parameters:

| Parameter    | Default      | Rules                                                                  |
| :----------- | :----------- | :--------------------------------------------------------------------- |
| `page`       | `1`          | Integer from 1 to 1,000,000                                            |
| `per_page`   | `25`         | Integer from 1 to 100                                                  |
| `search`     | empty        | Up to 100 characters; searches title, author, course code, and subject |
| `department` | empty        | Exact department match                                                 |
| `type`       | empty        | `Textbook`, `Notes`, or `Lab Manual`                                   |
| `sort`       | `created_at` | Allowlisted: `created_at` or `title`                                   |
| `direction`  | `asc`        | `asc` or `desc`                                                        |

Each row includes listing moderation fields, seller display name, and linked category name/type. Seller email and password data are excluded. The response contains `listings`, normalized `filters`, and `pagination` (`page`, `per_page`, `total`, `total_pages`). Queue rows include the necessary details; there is no separate listing-detail endpoint.

Example:

```http
GET /api/admin/pending-listings.php?search=Data&page=1&per_page=10&department=CSE&type=Textbook&sort=created_at&direction=asc
```

#### Listing Moderation

`POST /api/admin/review-listing.php` accepts:

```json
{ "listing_id": 7, "action": "approve" }
```

Allowed actions are `approve`, `reject`, and `changes_requested`. Reject and change requests require non-empty `admin_feedback` of at most 2,000 characters:

```json
{
  "listing_id": 7,
  "action": "reject",
  "admin_feedback": "Please correct the cover photo."
}
```

Only `pending_approval` can transition to `available`, `rejected`, or `changes_requested`. Conditional database updates prevent stale/repeated actions from reopening sold or otherwise changed listings. Conflicts return HTTP 409; unknown IDs return 404; invalid IDs/actions/feedback return 422. `reviewed_by` comes from the authenticated admin session and `reviewed_at` is set by the database. Approval clears old feedback; reject/change requests persist it.

#### Categories

`GET /api/admin/categories.php` returns `categories` with `id`, `name`, `type`, `department`, `subject`, and `created_at`. Optional filters: `type=Department|Subject`, exact `department`, and `search` (up to 100 characters).

Create a department:

```http
POST /api/admin/categories.php
X-CSRF-Token: <session token>
Content-Type: application/json

{"type":"Department","name":"CSE"}
```

Create a subject under an existing department:

```json
{ "type": "Subject", "name": "Operating Systems", "department": "CSE" }
```

`PUT /api/admin/categories.php` renames a category with `{"id":12,"name":"New name"}`. Department rename updates child subject links and matching listing/user department labels; subject rename updates matching listing subject labels. Category IDs remain stable. `DELETE /api/admin/categories.php` accepts `{"id":12}` and returns HTTP 409 while listings, users, child subjects, or category-ID references depend on it.

No user-listing, role-change, account-deletion, password-reset, or bulk-user operation is defined by the contract, so this milestone adds no user-management endpoint.

#### Completed Sales Report

`GET /api/admin/sales-report.php` returns completed purchase-request counts and minimal transaction rows (`purchase_request_id`, `listing_id`, listing title, buyer/seller display names, `completed_at`). Optional `from` and `to` filters are inclusive valid `YYYY-MM-DD` dates. `page` and `per_page` use the queue bounds. Invalid dates or reversed ranges return HTTP 422. The response contains `summary.completed_sales_count`, `transactions`, `filters`, and `pagination`. Revenue is `null` with an explanatory note because the schema stores no transaction-time price.

#### Admin Error Codes

- HTTP 401: No authenticated session.
- HTTP 403: Authenticated user is not an admin, or CSRF validation failed.
- HTTP 404: Requested listing/category does not exist.
- HTTP 405: Unsupported method (`Allow` header is returned).
- HTTP 409: Stale moderation transition, duplicate category, or referenced-category deletion.
- HTTP 422: Invalid ID, filter, pagination, category data, action, or feedback.
