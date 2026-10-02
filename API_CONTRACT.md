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
- `email` must be unique in `users` table and belong to `uiu.ac.bd` or one of its subdomains (for example, `bscse.uiu.ac.bd`). Unrelated domains and suffix lookalikes are rejected with HTTP 422.

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

#### Marketplace implementation details (Tashin)

All three marketplace endpoints accept GET without authentication. Other methods return 405 with `Allow: GET`. Only `status=available` listings are public (this status represents admin approval); pending, changes-requested, rejected and sold records are excluded from browse and details. Missing and inaccessible details both return 404. Invalid parameters return 422; unexpected database errors return a generic 500.

`listings.php` retains `data.total` and `data.listings`, adding `data.pagination` with integer `page`, `per_page`, `total`, and `total_pages`. Empty results use `listings: []` and zero total pages. Beyond-last-page requests return an empty array with the actual total. Defaults: page 1, per_page 20, sort created_at, direction desc.

| Parameter            | Rules                                                                                                                     |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| search               | Up to 100 UTF-8 characters; literal substring in title, author, course_code, subject; SQL wildcard characters are literal |
| department, subject  | Up to 100 characters; exact labels using database collation; omitted/empty means no filter                                |
| type                 | Textbook, Notes, Lab Manual; omitted/empty means all                                                                      |
| condition            | New, Like New, Good, Fair, Poor; omitted/empty means all                                                                  |
| category_id          | Positive integer through 2147483647; matches stored listings.category_id directly                                         |
| min_price, max_price | Inclusive nonnegative decimal through 99999999.99, at most two fractional digits; minimum must not exceed maximum         |
| page                 | Integer 1..1000000                                                                                                        |
| per_page             | Integer 1..100                                                                                                            |
| sort                 | created_at, price, title                                                                                                  |
| direction            | asc, desc; ties resolved by listing ID in the same direction                                                              |

Filters combine with AND. Send omitted/empty type or department instead of the demo UI's `all` sentinel. Unknown query keys are ignored. Malformed arrays are rejected for supported parameters.

Browse rows explicitly expose id, category_id, title, author, edition, course_code, department, subject, item_type, condition_type, price, image_url, created_at, seller_name and seller_rating. IDs are integers, price/rating numbers, and missing category/rating is null. Rating is the average of stored reviews, not a fabricated default. Timestamps are database DATETIME strings.

`listing-details.php?id=1` requires a positive integer ID and returns `data.listing` with the browse fields plus description, seller_id and seller_avatar_url. No email, phone, student ID, password hash, admin feedback or internal moderation fields are returned. Consumers must render text safely and validate image URLs before use.

`categories.php` optionally accepts department (up to 100 characters). It returns `data.categories` (id/name/type/department), `departments` (id/name), `subjects` (id/name/departments array), `item_types`, `conditions`, `sort_fields`, and `sort_directions`. These are taxonomy/filter options and may have no current matching listings.

Migration `database/migrations/002_category_relationship_columns.sql` adds nullable category relationship fields required by category CRUD. Before it is applied on a fresh schema, this public endpoint still works by inferring parents only from exact subject names on available listings. Unmapped or ambiguous subjects remain unassigned; no Cartesian department-subject mapping is invented. The optional department filter excludes unrelated/unmapped subjects. Fresh seed listing category IDs often point to a Department, so `category_id` and subject filters are intentionally distinct. This endpoint does not invent category IDs for listing subjects missing from the taxonomy.

Example requests:

```http
GET /api/marketplace/listings.php?department=CSE&type=Textbook&sort=price&direction=asc&page=1&per_page=10
GET /api/marketplace/listing-details.php?id=1
GET /api/marketplace/categories.php?department=CSE
```

Validation: `C:\xampp\php\php.exe tests\marketplace_test.php`. This CLI-only runner creates tagged disposable fixtures and cleans only its IDs. Do not run it concurrently with real database edits; it compares original table contents before and after. Auto-increment counters can advance. It never resets tables, reseeds demo records, or runs migrations.

The guest browse and public listing detail pages use these read-only endpoints. Cash on Meet only; no transaction writes are part of this module.

### 3.3 Seller Management (`api/seller/` - Owner: Labib)

Every seller endpoint requires an authenticated session with `role === 'seller'`. Guests receive HTTP 401; users with role `'buyer'` or `'admin'` receive HTTP 403. Every state-changing request (`POST`, `PUT`, `DELETE`) requires `X-CSRF-Token` (or a `csrf_token` body field). Sellers cannot view, modify, mark sold, or delete listings belonging to other sellers (strict cross-seller ownership enforcement yields HTTP 403). Responses use the standardized JSON envelope.

| Method | Endpoint                           | Access | Description                                                             |
| :----- | :--------------------------------- | :----- | :---------------------------------------------------------------------- |
| `GET`  | `/api/seller/dashboard.php`        | Seller | Real-time seller metrics (listings count by status, revenue, rating)    |
| `GET`  | `/api/seller/listings.php`         | Seller | Filtered, paginated list of seller's own listings                       |
| `GET`  | `/api/seller/listings.php?id={id}` | Seller | Full details of a specific listing owned by seller                      |
| `POST` | `/api/seller/add-listing.php`      | Seller | Submit a new textbook/notes listing (forced status: `pending_approval`) |
| `PUT`  | `/api/seller/edit-listing.php`     | Seller | Update an existing listing owned by seller                              |
| `POST` | `/api/seller/mark-sold.php`        | Seller | Mark an available listing as `sold` (does not mutate purchase requests) |
| `POST` | `/api/seller/mark-unsold.php`      | Seller | Revert a sold listing back to `available`                               |
| `POST` | `/api/seller/delete-listing.php`   | Seller | Delete an unreferenced listing owned by seller                          |
| `GET`  | `/api/seller/sales-history.php`    | Seller | View completed campus sales history with buyer info                     |
| `GET`  | `/api/seller/profile.php`          | Seller | View seller profile, student ID, and summary stats                      |
| `PUT`  | `/api/seller/profile.php`          | Seller | Update seller name, phone, or avatar (allowlisted fields only)          |
| `POST` | `/api/seller/upload-image.php`     | Seller | Upload a textbook cover photo (`multipart/form-data`)                   |

#### 3.3.1 Seller Dashboard

`GET /api/seller/dashboard.php` returns live counts and revenue status for the authenticated seller:

```json
{
  "success": true,
  "message": "Seller dashboard metrics retrieved.",
  "data": {
    "stats": {
      "total_listings": 3,
      "active_listings": 1,
      "pending_approval": 1,
      "changes_requested": 0,
      "rejected": 0,
      "sold_listings": 1,
      "revenue": 0,
      "revenue_note": "Earlier sales without recorded amounts are excluded from revenue.",
      "seller_rating": 4.8,
      "review_count": 1,
      "active_requests_count": 0
    }
  }
}
```

#### 3.3.2 Seller Listings Queue & Details

- **Listings Filter:** `GET /api/seller/listings.php?status=available&department=CSE&type=Textbook&search=Algorithms&page=1&per_page=20`
  - `status`: optional filter (`pending_approval`, `available`, `changes_requested`, `rejected`, `sold`).
  - `department`, `type`, `search`, `page`, `per_page` supported.
- **Single Listing Detail:** `GET /api/seller/listings.php?id=1`
  - Returns `listing` object owned by the seller. Accessing another seller's listing ID yields HTTP 403 Forbidden.

#### 3.3.3 Add Listing

`POST /api/seller/add-listing.php` creates a new listing. The seller identity (`seller_id`) is strictly extracted from the active session. Status is always assigned `pending_approval` awaiting Admin moderation; client attempts to self-approve or assign moderation fields (`reviewed_by`, `admin_feedback`) are ignored/blocked.

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
  "description": "Clean copy with minimal pencil markings.",
  "image_url": "uploads/listings/img_a1b2c3d4e5f6.jpg"
}
```

Response (HTTP 201 Created):

```json
{
  "success": true,
  "message": "Listing submitted successfully and is pending admin approval.",
  "data": {
    "listing": {
      "id": 8,
      "seller_id": 2,
      "title": "Artificial Intelligence: A Modern Approach",
      "course_code": "CSE-4101",
      "department": "CSE",
      "price": 500.0,
      "status": "pending_approval",
      "created_at": "2026-10-01 19:00:00"
    }
  }
}
```

#### 3.3.4 Edit Listing

`PUT /api/seller/edit-listing.php` updates an owned listing. Allowlisted editable fields: `title`, `author`, `edition`, `course_code`, `department`, `subject`, `item_type`, `condition_type`, `price`, `description`, `image_url`, `category_id`.

- Editing a listing in `changes_requested` or `rejected` automatically transitions its status back to `pending_approval` for re-moderation.
- Sold listings cannot be edited directly (returns HTTP 409 Conflict).
- Updates constrain the previously read status and timestamp. If either changes before the write, reload the listing after HTTP 409 rather than overwriting a newer moderation/sold transition.
- Uploaded covers must exist under the listing upload directory and belong to the seller's session or own listings; an unchanged existing cover is retained. Remote HTTP/HTTPS demo covers remain supported.

#### 3.3.5 Mark Sold & Mark Unsold

- `POST /api/seller/mark-sold.php`: Accepts `{"id": 1}`. Only approved (`available`) listings can transition to `sold` using concurrency-safe conditional locking. Updates listing status to `sold`. In accordance with team module ownership, purchase-request status mutations belong strictly to Tanvir's buyer/transaction module.
- Manual `mark-sold` returns HTTP 409 while an accepted purchase request exists. Use the existing `api/buyer/seller-request-action.php` with `action: complete` to finish the meetup, or decline the accepted request first. The seller module does not mutate request status.
- The seller UI offers decline for both pending and accepted requests, matching the existing action API, and only shows Complete meetup for accepted requests on available listings. Status/action errors stay visible after refresh.
- `POST /api/seller/mark-unsold.php`: Accepts `{"id": 1}`. Only `sold` listings can transition to `unsold` using concurrency-safe conditional locking. Reverts status back to `available`. Stale concurrent requests return HTTP 409 Conflict.

#### 3.3.6 Delete Listing

`POST /api/seller/delete-listing.php` (or `DELETE`): Accepts `{"id": 1}`.

- Deleting a listing that is `sold` or has completed purchase records is blocked with HTTP 409 Conflict to preserve transaction integrity.
- Deleting a listing with active proposals (`pending` or `accepted`) returns HTTP 409 Conflict.
- Deletion checks are serialized with a listing row lock. The database deletion commits before optional cover cleanup.
- Cover cleanup only targets generated direct image paths inside `uploads/listings/`, and keeps files referenced by another listing. The response includes `image_removed`; failed cleanup does not incorrectly report that the committed listing deletion failed.

#### 3.3.7 Sales History

`GET /api/seller/sales-history.php` returns completed purchase-request records plus currently sold listings with no completed request. Completed records remain visible after relisting; pagination counts joined history rows consistently. Manual sold records have `purchase_request_id: null` and `completed_at: null`, with `sold_at` as a listing-update timestamp rather than a transaction-completion timestamp. The returned `price` is the current listing price, not realized revenue or a historical transaction price. The nullable `sale_price` is the immutable listing-price snapshot at meetup completion; older unknown amounts remain null.

Example completed transaction:

```json
{
  "success": true,
  "message": "Seller sales history retrieved.",
  "data": {
    "total": 1,
    "sales": [
      {
        "listing_id": 1,
        "title": "Data Structures and Algorithms",
        "course_code": "CSE-2101",
        "department": "CSE",
        "price": 450.0,
        "status": "sold",
        "buyer_name": "Zahir Raihan",
        "buyer_email": "buyer@uiu.ac.bd",
        "meeting_location": "UIU Library",
        "completed_at": "2026-10-01 14:00:00"
      }
    ],
    "pagination": { "total": 1, "page": 1, "per_page": 20, "total_pages": 1 }
  }
}
```

#### 3.3.8 Seller Profile

- `GET /api/seller/profile.php`: Returns profile details (`full_name`, `email`, `student_id`, `phone`, `avatar_url`) and seller stats.
- `PUT /api/seller/profile.php`: Accepts updates for `full_name`, `phone`, `avatar_url`. Modifying `id`, `email`, or `role` yields HTTP 422.

#### 3.3.9 Secure Image Upload

`POST /api/seller/upload-image.php`:

- Content-Type: `multipart/form-data`, file field: `image`.
- Header: `X-CSRF-Token: <token>`.
- Allowed MIME types: `image/jpeg` (.jpg), `image/png` (.png), `image/webp` (.webp). Verified using file content inspection (`finfo`), not client extensions.
- Maximum size: 2 MB; empty files, invalid PHP upload metadata and unreadable images are rejected. The actual temporary file size and image content are checked.
- Newly uploaded image URLs are registered to the authenticated seller's session for attachment validation. Uploading and saving remain separate requests; the form retains the uploaded URL for retry if saving fails. After session expiration, unattached images must be uploaded again.
- Security: Filename is securely generated (`img_<hex>.ext`). Executable scripts and PHP execution are strictly disabled via `.htaccess` in `uploads/`.
- Response (HTTP 201 Created):
  ```json
  {
    "success": true,
    "message": "Image uploaded successfully.",
    "data": {
      "image_url": "uploads/listings/img_4f9a12c8b0e5d3fa718290bc98471201.jpg",
      "mime_type": "image/jpeg",
      "size": 142050
    }
  }
  ```

#### 3.3.10 Seller Error Codes

- **HTTP 401**: Unauthenticated session.
- **HTTP 403**: Forbidden (user role is not `seller`, cross-seller ownership violation, or CSRF token mismatch).
- **HTTP 404**: Listing or profile not found.
- **HTTP 405**: Method not allowed (`Allow` header included).
- **HTTP 409**: Conflict (e.g. attempting to edit/delete a sold listing, repeated mark-sold, or active purchase request blocker).
- **HTTP 422**: Validation failed (missing required fields, negative price, invalid enum, non-image upload, or unauthorized privilege assignment).

---

### 3.4 Buyer & Transactions (`api/buyer/` - Owner: Tanvir)

Buyer dashboard `stats.total_spent` and `stats.money_saved` are `null` with an
`amounts_note` explaining availability. The schema has no transaction-time price
or retail comparison price; mutable listing prices must not imply historical
spending or savings. Counts remain numeric. Seller request `can_decline` is true
for both pending and accepted requests, matching the existing action endpoint.

Dashboard recommendations include `seller_id`. Request status filters reject
unsupported/non-string values with 422. Wishlist actions accept only add, remove,
or toggle and serialize writes using the listing row lock; the response retains
the existing `is_wishlisted` and `wishlist_count` fields. Reviews accept integer
ratings from 1 through 5, serialize duplicate submissions on the purchase row,
and seller-review lookup returns 404 for users who are not sellers.

| Method         | Endpoint                                | Access         | Description                                                                                                       |
| :------------- | :-------------------------------------- | :------------- | :---------------------------------------------------------------------------------------------------------------- |
| `GET`          | `/api/buyer/dashboard.php`              | Buyer          | Buyer stats (wishlist count, active requests, completed purchases, money saved), recommendations, recent requests |
| `GET`          | `/api/buyer/profile.php`                | Buyer          | Retrieves current buyer profile details                                                                           |
| `POST` / `PUT` | `/api/buyer/profile.php`                | Buyer          | Updates permitted profile fields (`full_name`, `phone`, `student_id`, `avatar_url`) (CSRF required)               |
| `GET`          | `/api/buyer/wishlist.php`               | Buyer          | Listings saved by buyer                                                                                           |
| `POST`         | `/api/buyer/wishlist.php`               | Buyer          | Add, remove, or toggle listing in wishlist (CSRF required)                                                        |
| `POST`         | `/api/buyer/purchase-request.php`       | Buyer          | Submit Cash on Meet purchase request (CSRF required)                                                              |
| `GET`          | `/api/buyer/my-requests.php`            | Buyer          | List of buyer's purchase requests with optional `status` filter                                                   |
| `GET`          | `/api/buyer/request-detail.php?id={id}` | Buyer / Seller | Full purchase request details, listing info, meetup data, review status (Participant isolated)                    |
| `POST`         | `/api/buyer/cancel-request.php`         | Buyer          | Cancel buyer's own pending/accepted request (CSRF required)                                                       |
| `GET`          | `/api/buyer/seller-requests.php`        | Seller         | List purchase requests received for seller's listings                                                             |
| `POST`         | `/api/buyer/seller-request-action.php`  | Seller         | Seller accepts, declines, or completes request (concurrency protected, CSRF required)                             |

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

- Rules: Listing must be `status = 'available'`; requester cannot buy own listing; duplicate active requests rejected (422).

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

- Rules:
  - `accept`: Only pending requests on available listings. Only 1 accepted request allowed per listing.
  - `decline`: Allowed on pending or accepted requests.
  - `complete`: Concludes transaction. Marks listing as `sold`, request as `completed`, and auto-declines other pending requests.

---

### 3.5 Messaging & Reviews (Owner: Tanvir)

| Method | Endpoint                                         | Access        | Description                                                                 |
| :----- | :----------------------------------------------- | :------------ | :-------------------------------------------------------------------------- |
| `GET`  | `/api/messages/conversations.php`                | Authenticated | List conversation threads with latest message and unread counts             |
| `GET`  | `/api/messages/thread.php?with_user_id={id}`     | Authenticated | Full chat history between users; automatically marks incoming messages read |
| `POST` | `/api/messages/send.php`                         | Authenticated | Send a campus coordination message (CSRF required)                          |
| `POST` | `/api/reviews/create.php`                        | Buyer         | Submit a 1-5 star review for an eligible completed purchase (CSRF required) |
| `GET`  | `/api/reviews/seller-reviews.php?seller_id={id}` | Public        | Reviews and aggregate rating statistics for a seller                        |
| `GET`  | `/api/reviews/my-reviews.php`                    | Buyer         | List of reviews submitted by the logged-in buyer                            |

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

- Rules: Request must have `status = 'completed'`; reviewer must be the buyer; duplicate reviews for the same purchase request are rejected (422); rating must be between 1 and 5.

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
    "revenue": 0,
    "revenue_note": "Earlier sales without recorded amounts are excluded from revenue."
  }
}
```

Revenue sums `purchase_requests.sale_price` for completed transactions. Later listing price changes do not affect it. `unpriced_sales_count` discloses legacy sales excluded from the total; the average uses priced transactions only.

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

### Completed-sale price snapshots

On accepted request completion, `sale_price` stores the current listing price in the same locked transaction as completion. It represents the app listing price at completion, not a verified external payment or negotiated cash amount. Admin report rows and seller history expose nullable `sale_price`. Dashboard/report `revenue` sums recorded completed sale prices only; `unpriced_sales_count` and `revenue_note` disclose older missing prices. Report `average_order_value` uses priced sales only. Report totals use the full date filter, independent of pagination. Manual mark-sold without a purchase request does not create recorded revenue.

### Active-user reporting

Admin sales-report `data.summary` also returns `active_users` (integer; null only for a period wholly before tracking), `activity_tracking_started_at` (database server timestamp), `activity_coverage_complete` (boolean), and `activity_note`. Date filters apply inclusively to daily activity and counts deduplicate each account across the whole period, independent of sales pagination. Tracking starts with migration 005 and records successful session authentication and protected API use; guests and failed logins are excluded. Counts include all roles, and are not live online counts. Existing accounts are not backfilled as active. Analytics failures are logged without preventing authentication.
