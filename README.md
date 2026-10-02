# UIU Used Textbook Marketplace

**BookBridge** is a campus marketplace for United International University students to buy and sell used textbooks, course notes, and lab manuals.

The project includes a responsive frontend, PHP APIs, and a MySQL database, with separate workflows for guests, buyers, sellers, and administrators.

## Features

### Admin
- Review and approve seller listings.
- Reject listings or request changes with feedback.
- Manage department and subject categories.
- View dashboard counts and completed-sales reports.
- Filter sales reports by date.

### Seller
- Submit books, notes, and lab manuals with course information, condition, price, and photos.
- Edit and manage owned listings.
- Mark eligible listings as sold or available.
- Accept or decline purchase requests and complete meetups.
- Chat with buyers.
- Track completed sales history.

### Buyer
- Browse and search listings by course, subject, and department.
- Save listings to a wishlist.
- Submit and track purchase requests.
- Cancel eligible purchase requests.
- Chat with sellers.
- Rate sellers after an eligible completed purchase.

### Guest
- Browse public listings without signing in.
- Search and filter available books.
- View public listing information without seller email or phone details.

## Team Contributions

| Member | Responsibility | Primary Backend Modules |
| --- | --- | --- |
| **Adeeb** | Shared backend foundation, authentication, PHP sessions, role-based access, CSRF protection, database setup, admin features, and frontend API integration | `config/`, `includes/`, `database/`, `api/auth/`, `api/admin/` |
| **Tashin** | Public marketplace browsing, search, filters, and listing details | `api/marketplace/` |
| **Labib** | Seller dashboard, listing submission and management, image uploads, sold/relist actions, and sales history | `api/seller/` |
| **Tanvir** | Buyer dashboard, wishlists, purchase request workflows, messaging, and reviews | `api/buyer/`, `api/messages/`, `api/reviews/` |

Adeeb coordinates integration with the compiled frontend. Members also collaborate on reviews and integration fixes.

## Technology Stack

| Layer | Technology |
| --- | --- |
| Frontend | HTML, CSS, JavaScript, compiled React interface |
| Backend | PHP 8.0 or later |
| Database | MySQL or MariaDB |
| Database Access | PDO prepared statements |
| Authentication | Server-side PHP sessions |
| Local Server | XAMPP with Apache and MySQL |
| Version Control | Git and GitHub |

## Architecture

```text
User action
    → JavaScript handler
    → PHP API request
    → Authentication and validation
    → PDO database query
    → MySQL
    → JSON response
    → Frontend update
```

The main application starts from `index.html`.

- `app.js` contains the compiled React interface.
- `auth.js` handles frontend authentication and session restoration.
- `api-integration.js` maps actions to API endpoints.
- `frontend-integration.js` connects page behavior to server data.

The original editable React JSX/TSX source project is not included in this checkout. The repository also contains standalone HTML pages and supporting scripts.

## Project Structure

```text
UIU-Used-Textbook-Marketplace/
├── api/
│   ├── auth/
│   ├── admin/
│   ├── marketplace/
│   ├── seller/
│   ├── buyer/
│   ├── messages/
│   └── reviews/
├── config/
├── includes/
├── database/
│   └── migrations/
├── tests/
├── uploads/
├── index.html
├── app.js
├── auth.js
├── api-integration.js
├── frontend-integration.js
├── styles.css
├── integration.css
├── API_CONTRACT.md
├── BACKEND_SETUP.md
└── TEAM_GUIDE.md
```

## Local Setup

### 1. Place the project in XAMPP

Clone or copy the repository into:

```text
C:\xampp\htdocs\UIU-Used-Textbook-Marketplace
```

Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 2. Set up the database

For a **fresh installation**:

1. Open `http://localhost/phpmyadmin/`.
2. Import `database/bookbridge.sql`.
3. The schema creates `bookbridge_db`, application tables, and seed data.

For an existing installation, back up the database and consult `database/migrations/README.md` before upgrading. Do not reimport the fresh-install schema into an existing populated database.

### 3. Configure the connection

If needed, copy:

```text
config/config.example.php → config/config.php
```

Update the database host, port, name, username, and password for your local environment.

`config/config.php` is ignored by Git. Do not commit private credentials.

### 4. Open the application

```text
http://localhost/UIU-Used-Textbook-Marketplace/
```

Use the XAMPP Apache URL. Live Server and directly opening HTML files do not execute the PHP backend.

Check connectivity through:

```text
http://localhost/UIU-Used-Textbook-Marketplace/api/health.php
```

See [BACKEND_SETUP.md](BACKEND_SETUP.md) for detailed instructions.

## Fresh Installation Demo Accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@uiu.ac.bd` | `password123` |
| Seller | `seller@uiu.ac.bd` | `password123` |
| Buyer | `buyer@uiu.ac.bd` | `password123` |

These credentials apply to the current fresh-install seed data. Existing local accounts may have different passwords. Use seed accounts only for local demonstrations.

Registration supports buyer and seller accounts using UIU email addresses at `uiu.ac.bd` or its subdomains. Administrator self-registration is not supported.

## Database Tables

| Table | Purpose |
| --- | --- |
| `users` | Accounts, password hashes, roles, and profiles |
| `categories` | Department and subject categories |
| `listings` | Book information, price, ownership, and moderation state |
| `purchase_requests` | Purchase requests, meetup details, and completion history |
| `wishlists` | Saved listings |
| `messages` | Buyer and seller conversations |
| `reviews` | Ratings and comments for eligible completed purchases |

## Main Business Rules

- New listings require admin approval before becoming publicly available.
- Sellers can modify only listings they own.
- Rejecting a listing or requesting changes requires admin feedback.
- Subject categories require an existing parent department.
- Referenced categories cannot be deleted.
- Purchase completion uses `api/buyer/seller-request-action.php`.
- Manual mark-sold is a separate listing action and does not substitute for completing a purchase request.
- Relisting preserves completed purchase history.
- Reviews require an eligible completed purchase and its actual request ID.
- Payments use **Cash on Meet**. No online payment gateway or separate dummy-payment flow is implemented.
- Donation is an optional requirement and is not implemented.

## Sales Reporting

Admin reports show completed-sales counts and transaction records with date filtering.

Completed purchases do not store transaction-time prices. The application therefore does not treat the current editable listing price as historical revenue. Buyer savings are shown as estimates.

## Security

- Bcrypt password hashing and `password_verify`.
- Server-side sessions and role-based API access.
- Backend ownership and input validation.
- CSRF token validation for state-changing requests.
- PDO prepared statements.
- Transactions and row locks for relevant operations.
- Category reference protection.
- Backend eligibility checks for purchase actions and reviews.

## Testing

Run backend tests through PHP CLI from the project directory:

```powershell
C:\xampp\php\php.exe tests\auth_test.php
C:\xampp\php\php.exe tests\admin_test.php
C:\xampp\php\php.exe tests\marketplace_test.php
C:\xampp\php\php.exe tests\buyer_workflow_test.php
C:\xampp\php\php.exe tests\seller_test.php
C:\xampp\php\php.exe tests\release_readiness_test.php
```

Read the setup guide and each test's prerequisites before execution.

The release-readiness suite checks fresh-schema compatibility, seed passwords, UIU email validation, and concurrent review submissions. It requires permission to create and drop a temporary test database.

Test runners are blocked from browser execution.

## Git Workflow

| Branch | Purpose |
| --- | --- |
| `main` | Integrated project baseline |
| `backend-development` | Team integration branch |
| `backend/adeeb` | Adeeb's development branch |
| `backend/tashin` | Tashin's development branch |
| `backend/labib` | Labib's development branch |
| `backend/tanvir` | Tanvir's development branch |

Member changes are submitted through pull requests to `backend-development`. Reviewed integration changes are promoted to `main` through a release pull request.

Keep commits focused, preserve teammates' local work, and exclude private configuration and unrelated uploaded images.

## Documentation

- [Backend Setup](BACKEND_SETUP.md)
- [API Contract](API_CONTRACT.md)
- [Team Guide](TEAM_GUIDE.md)
- [Frontend Integration Handoff](FRONTEND_INTEGRATION_HANDOFF.md)
- [Database Migrations](database/migrations/README.md)

## Project Scope

Developed for the UIU academic project demonstration and viva.

The project includes integrated authentication, marketplace, buyer, seller, messaging, review, and admin workflows. Hosting is separate from source-code integration.

Administrator account deletion, role changes, and password-reset APIs are outside the current API contract.
