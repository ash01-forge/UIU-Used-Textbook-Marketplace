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
| `POST` | `/api/auth/login.php` | Public | Authenticates user credentials, sets session cookie |
| `POST` | `/api/auth/register.php` | Public | Registers a new UIU student account |
| `GET`  | `/api/auth/me.php` | Authenticated | Returns logged-in user profile & CSRF token |
| `POST` | `/api/auth/logout.php` | Authenticated | Destroys session and clears cookie |

#### Login Request Payload:
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
  "message": "Login successful",
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
| `GET`  | `/api/buyer/dashboard.php` | Buyer | Buyer stats (wishlist count, active requests, money saved) |
| `GET`  | `/api/buyer/wishlist.php` | Buyer | Listings saved by buyer |
| `POST` | `/api/buyer/wishlist.php` | Buyer | Add or remove listing from wishlist |
| `POST` | `/api/buyer/purchase-request.php`| Buyer | Submit Cash on Meet purchase request |
| `GET`  | `/api/buyer/my-requests.php` | Buyer | List of buyer's purchase requests |

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

| Method | Endpoint | Access | Description |
| :----- | :------- | :----- | :---------- |
| `GET`  | `/api/messages/conversations.php` | Authenticated | List conversations and unread counts |
| `GET`  | `/api/messages/thread.php?with_user_id={id}` | Authenticated | Chat history with specific user |
| `POST` | `/api/messages/send.php` | Authenticated | Send a campus coordination message |
| `POST` | `/api/reviews/create.php` | Buyer | Submit a 1-5 star review for a completed purchase |

---

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
