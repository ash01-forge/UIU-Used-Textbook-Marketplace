# BookBridge API Contract & Specification

This document specifies the RESTful JSON API endpoints, request/response structures, error formats, and authentication conventions for the BookBridge marketplace.

---

## 1. Global Conventions

### Base URL
```
http://localhost/UIU-Used-Textbook-Marketplace/api
```

### Headers
| Header | Value | Description |
| :----- | :---- | :---------- |
| `Content-Type` | `application/json` | Required for all POST/PUT/PATCH requests |
| `Accept` | `application/json` | Client expects JSON responses |
| `X-CSRF-Token` | `<token>` | Required on state-modifying requests (POST, PUT, DELETE) |

### Response Envelopes

#### Success Response (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {}
}
```

#### Error Response (HTTP 400 / 401 / 403 / 404 / 422 / 500)
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
* **URL:** `GET /api/health.php`
* **Access:** Public
* **Response (200 OK):**
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

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `POST` | `/api/auth/login.php` | Public | Authenticates credentials, regenerates session ID, sets cookie, returns user & CSRF token |
| `POST` | `/api/auth/register.php` | Public | Registers a new student account (`buyer` or `seller` only; `admin` blocked), sets session, returns user & CSRF token |
| `GET`  | `/api/auth/me.php` | Authenticated | Returns current logged-in user profile & fresh CSRF token (401 if unauthenticated) |
| `POST` | `/api/auth/logout.php` | Authenticated | Validates CSRF token, destroys session, clears cookie |
| `GET`  | `/api/auth/csrf.php` | Public | Generates or retrieves current session CSRF token |

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
* `role` must be strictly `"buyer"` or `"seller"`. Supplying `"admin"` yields HTTP 422.
* `password` must be at least 8 characters.
* `email` must be unique in `users` table.

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
* Header: Requires active session cookie `bookbridge_session`.
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
* Header: Requires active session cookie AND `X-CSRF-Token: <token>` (or `csrf_token` in body).
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
* **HTTP 401**: Unauthenticated / invalid credentials.
* **HTTP 403**: CSRF validation failed / unauthorized role access.
* **HTTP 405**: Method not allowed (e.g. GET on register.php).
* **HTTP 422**: Validation errors / duplicate entries / prohibited privileges.

---

### 3.2 Marketplace & Browse (`api/marketplace/` - Owner: Tashin)

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `GET`  | `/api/marketplace/listings.php` | Public | Returns available listings with optional filters (`search`, `department`, `type`, `page`) |
| `GET`  | `/api/marketplace/listing-details.php?id={id}` | Public | Returns details of a specific live listing |
| `GET`  | `/api/marketplace/categories.php` | Public | Returns available departments and subjects |

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
        "price": 450.00,
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

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `GET`  | `/api/seller/dashboard.php` | Seller | Seller metrics (total listings, active, sold, revenue) |
| `GET`  | `/api/seller/listings.php` | Seller | Listings owned by logged-in seller |
| `POST` | `/api/seller/add-listing.php` | Seller | Creates a new listing with status `pending_approval` |
| `PUT`  | `/api/seller/edit-listing.php` | Seller | Updates listing owned by seller |
| `POST` | `/api/seller/mark-sold.php` | Seller | Marks listing as `sold` and concludes transaction |
| `GET`  | `/api/seller/sales-history.php`| Seller | History of completed sales |

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

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `GET`  | `/api/buyer/dashboard.php` | Buyer | Buyer stats (wishlist count, active requests, completed purchases, money saved), recommendations, recent requests |
| `GET`  | `/api/buyer/profile.php` | Buyer | Retrieves current buyer profile details |
| `POST` / `PUT` | `/api/buyer/profile.php` | Buyer | Updates permitted profile fields (`full_name`, `phone`, `student_id`, `avatar_url`) (CSRF required) |
| `GET`  | `/api/buyer/wishlist.php` | Buyer | Listings saved by buyer |
| `POST` | `/api/buyer/wishlist.php` | Buyer | Add, remove, or toggle listing in wishlist (CSRF required) |
| `POST` | `/api/buyer/purchase-request.php` | Buyer | Submit Cash on Meet purchase request (CSRF required) |
| `GET`  | `/api/buyer/my-requests.php` | Buyer | List of buyer's purchase requests with optional `status` filter |
| `GET`  | `/api/buyer/request-detail.php?id={id}` | Buyer / Seller | Full purchase request details, listing info, meetup data, review status (Participant isolated) |
| `POST` | `/api/buyer/cancel-request.php` | Buyer | Cancel buyer's own pending/accepted request (CSRF required) |
| `GET`  | `/api/buyer/seller-requests.php` | Seller | List purchase requests received for seller's listings |
| `POST` | `/api/buyer/seller-request-action.php` | Seller | Seller accepts, declines, or completes request (concurrency protected, CSRF required) |

#### Purchase Request Payload (Strict Cash on Meet only):
`POST /api/buyer/purchase-request.php` (Header: `X-CSRF-Token: <token>`)
```json
{
  "listing_id": 1,
  "meeting_location": "UIU Library 3rd Floor",
  "preferred_date": "2026-10-05",
  "note": "Can meet during lunch break."
}
```
* Rules: Listing must be `status = 'available'`; requester cannot buy own listing; duplicate active requests rejected (422).

#### Purchase Request Status Transitions:
```
           [Buyer Submits: pending]
                     │
         ┌───────────┴───────────┐
         ▼ (Seller Accepts)      ▼ (Seller Declines / Buyer Cancels)
    [accepted]              [declined / cancelled]
         │
         ▼ (Seller Marks Completed / Cash on Meet Concluded)
    [completed]  ──>  [listings.status = 'sold']
```

#### Seller Request Action Payload:
`POST /api/buyer/seller-request-action.php` (Header: `X-CSRF-Token: <token>`)
```json
{
  "request_id": 3,
  "action": "accept" // Options: "accept", "decline", "complete"
}
```
* Rules:
  - `accept`: Only pending requests on available listings. Only 1 accepted request allowed per listing.
  - `decline`: Allowed on pending or accepted requests.
  - `complete`: Concludes transaction. Marks listing as `sold`, request as `completed`, and auto-declines other pending requests.

---

### 3.5 Messaging & Reviews (Owner: Tanvir)

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `GET`  | `/api/messages/conversations.php` | Authenticated | List conversation threads with latest message and unread counts |
| `GET`  | `/api/messages/thread.php?with_user_id={id}` | Authenticated | Full chat history between users; automatically marks incoming messages read |
| `POST` | `/api/messages/send.php` | Authenticated | Send a campus coordination message (CSRF required) |
| `POST` | `/api/reviews/create.php` | Buyer | Submit a 1-5 star review for an eligible completed purchase (CSRF required) |
| `GET`  | `/api/reviews/seller-reviews.php?seller_id={id}` | Public | Reviews and aggregate rating statistics for a seller |
| `GET`  | `/api/reviews/my-reviews.php` | Buyer | List of reviews submitted by the logged-in buyer |

#### Send Message Payload:
`POST /api/messages/send.php` (Header: `X-CSRF-Token: <token>`)
```json
{
  "receiver_id": 2,
  "listing_id": 1,
  "message_text": "Hello! Is this textbook still available?"
}
```

#### Submit Review Payload:
`POST /api/reviews/create.php` (Header: `X-CSRF-Token: <token>`)
```json
{
  "purchase_request_id": 2,
  "rating": 5,
  "comment": "Punctual seller, textbook in excellent condition!"
}
```
* Rules: Request must have `status = 'completed'`; reviewer must be the buyer; duplicate reviews for the same purchase request are rejected (422); rating must be between 1 and 5.

### 3.6 Admin Management (`api/admin/` - Owner: Adeeb)

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `GET`  | `/api/admin/dashboard.php` | Admin | Overview KPIs (pending review count, sales, revenue) |
| `GET`  | `/api/admin/pending-listings.php`| Admin | All listings awaiting approval |
| `POST` | `/api/admin/review-listing.php` | Admin | Approve, Reject, or Request Changes on a listing |
| `GET`  | `/api/admin/categories.php` | Admin | Manage departments and subjects |
| `POST` | `/api/admin/categories.php` | Admin | Add a new department or subject |
| `PUT`  | `/api/admin/categories.php` | Admin | Update category name |
| `DELETE`| `/api/admin/categories.php` | Admin | Delete category |
| `GET`  | `/api/admin/sales-report.php`| Admin | Sales volume & revenue analytics |

#### Review Listing Request Payload:
```json
{
  "listing_id": 7,
  "action": "approve" // Options: "approve", "reject", "changes_requested"
}
```
If `action` is `"reject"` or `"changes_requested"`, `admin_feedback` is required:
```json
{
  "listing_id": 7,
  "action": "changes_requested",
  "admin_feedback": "Please upload a clearer photo of the book cover."
}
```
