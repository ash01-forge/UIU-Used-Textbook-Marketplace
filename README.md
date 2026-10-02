# UIU Used Textbook Marketplace

**BookBridge** is a campus marketplace for United International University students to buy and sell used textbooks, course notes, and lab manuals.

The application combines a responsive frontend, PHP APIs, and a MySQL database, with separate workflows for guests, buyers, sellers, and administrators.

## Features

### Admin

- Review and approve seller listings.
- Reject listings or request changes with feedback.
- Manage department and subject categories.
- View dashboard counts and completed-sales reports.
- View reports for weekly, monthly, and yearly periods.

### Seller

- Create listings with book information, department, subject, course code, condition, price, description, and photos.
- Edit owned listings and view moderation feedback.
- Mark eligible listings as sold or available.
- Accept or decline purchase requests.
- Complete meetups through the purchase-request workflow.
- Chat with buyers.
- Track completed sales history.
- Relist books while preserving completed purchase history.

### Buyer

- Register and sign in.
- Browse and search by keyword, course code, subject, or department.
- View listing details.
- Save and remove wishlist items.
- Submit purchase requests using Cash on Meet.
- Track requests and cancel eligible requests.
- Chat with sellers.
- Rate sellers after eligible completed purchases.

### Guest

- Browse public listings without signing in.
- Search and filter available listings.
- View public listing information without private contact details.

## Team and Contributions

### Members and Module Ownership

| Member | GitHub | Primary Contribution | Backend Modules |
| --- | --- | --- | --- |
| **Adeeb** | [@ash01-forge](https://github.com/ash01-forge) | Shared backend foundation, authentication, sessions, role-based access, CSRF protection, database schema and migrations, admin workflows, frontend API integration, and integration coordination | `config/`, `includes/`, `database/`, `api/auth/`, `api/admin/` |
| **Tashin** | [@TaSHin0302](https://github.com/TaSHin0302) | Public marketplace browsing, keyword and course-code search, listing filters, and public listing details | `api/marketplace/` |
| **Labib** | [@Farhan-Labib2003](https://github.com/Farhan-Labib2003) | Seller dashboard, listing submission and editing, image uploads, sold/relist actions, and sales history | `api/seller/` |
| **Tanvir** | [@tanrif21](https://github.com/tanrif21) | Buyer dashboard, wishlists, purchase-request lifecycle, messaging, and purchase reviews | `api/buyer/`, `api/messages/`, `api/reviews/` |

### Database Responsibilities

| Member | Database-Related Contribution | Related Tables |
| --- | --- | --- |
| **Adeeb** — [@ash01-forge](https://github.com/ash01-forge) | Shared database schema, relationships, migrations, PDO connection setup, authentication queries, listing moderation updates, category management, and admin report queries | Shared schema for all seven tables; admin and authentication work primarily uses `users`, `categories`, `listings`, and `purchase_requests` |
| **Tashin** — [@TaSHin0302](https://github.com/TaSHin0302) | Public listing queries, search and filtering, pagination, listing-detail retrieval, and public visibility rules | `listings`, `categories`, and `users` |
| **Labib** — [@Farhan-Labib2003](https://github.com/Farhan-Labib2003) | Seller-owned listing creation and updates, uploaded-image references, sold/available state changes, and completed-sales history queries | Primarily `listings` and `purchase_requests`, with related `users` and `categories` data |
| **Tanvir** — [@tanrif21](https://github.com/tanrif21) | Wishlist queries, purchase-request creation and state transitions, meetup completion, messaging, and eligible review submission | `wishlists`, `purchase_requests`, `messages`, and `reviews`, with related `listings` and `users` data |

The database responsibilities describe each member's module queries and workflows. The shared schema and migrations are coordinated by Adeeb; related tables may be used by multiple modules.

Adeeb also coordinates changes to the compiled frontend bundle and connects the shared interface to the team's APIs. Integration fixes and reviews may involve multiple members.

See [TEAM_GUIDE.md](TEAM_GUIDE.md) for ownership conventions and the collaboration workflow.

## Technology Stack

| Layer | Technology |
| --- | --- |
| Frontend | HTML, CSS, JavaScript, compiled React interface |
| Backend | PHP 8.0 or later, JSON APIs |
| Database | MySQL or MariaDB |
| Database Access | PDO prepared statements |
| Authentication | Server-side PHP sessions |
| Local Environment | XAMPP with Apache and MySQL |
| Version Control | Git and GitHub |

## Application Architecture

```text
User action
    ↓
Frontend event handler
    ↓
API request
    ↓
PHP authentication, validation, and business rules
    ↓
PDO database query
    ↓
MySQL database
    ↓
JSON response
    ↓
Frontend update
```

The main application starts at `index.html` and loads:

| File | Purpose |
| --- | --- |
| `app.js` | Compiled React interface |
| `auth.js` | Frontend authentication and session handling |
| `api-integration.js` | API endpoint mappings |
| `frontend-integration.js` | API-connected page behavior |
| `styles.css` and `integration.css` | Interface styling |

The repository also contains standalone HTML pages and supporting scripts. The original editable React JSX/TSX source project is not included in this checkout; `app.js` is a compiled bundle.

## Repository Structure

```text
UIU-Used-Textbook-Marketplace/
├── api/
│   ├── auth/                 Authentication and account profile
│   ├── admin/                Moderation, categories, and reports
│   ├── marketplace/          Public browsing and listing details
│   ├── seller/               Seller listings, uploads, and sales
│   ├── buyer/                Buyer and purchase-request workflows
│   ├── messages/             Conversations and messages
│   └── reviews/              Purchase reviews
├── config/                   Application and database configuration
├── includes/                 Shared authentication, CSRF, and response helpers
├── database/                 Fresh-install schema and upgrade migrations
├── tests/                    PHP API and JavaScript integration suites
├── uploads/                  Uploaded listing images
├── index.html                Main application entry point
├── app.js                    Compiled React interface
├── auth.js                   Authentication and session handling
├── api-integration.js        API endpoint adapter
├── frontend-integration.js   Connected frontend behavior
├── styles.css
├── integration.css
├── API_CONTRACT.md
├── BACKEND_SETUP.md
├── FRONTEND_INTEGRATION_HANDOFF.md
└── TEAM_GUIDE.md
```

## Local Setup

### 1. Clone the Repository

```bash
git clone https://github.com/ash01-forge/UIU-Used-Textbook-Marketplace.git
```

Place the project inside the XAMPP web directory:

```text
C:\xampp\htdocs\UIU-Used-Textbook-Marketplace
```

### 2. Start XAMPP Services

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Set Up the Database

For a **fresh installation**:

1. Open [phpMyAdmin](http://localhost/phpmyadmin/).
2. Import `database/bookbridge.sql`.
3. The script creates `bookbridge_db`, application tables, and seed accounts.

For an **existing installation**:

- Preserve the existing database and back it up before upgrades.
- Read [database/migrations/README.md](database/migrations/README.md).
- Apply only the migrations required by that installation.
- Do not reimport the fresh-install schema into an existing demo database.

The current fresh-install schema already includes user department and category relationship columns.

### 4. Configure the Database Connection

If `config/config.php` does not exist, copy:

```text
config/config.example.php
```

to:

```text
config/config.php
```

Set the database host, port, database name, username, and password for your local environment.

`config/config.php` is ignored by Git. Do not commit private credentials.

### 5. Open the Application

Visit:

[http://localhost/UIU-Used-Textbook-Marketplace/](http://localhost/UIU-Used-Textbook-Marketplace/)

Use the Apache URL. Opening HTML directly or using Live Server does not execute the PHP backend.

Check backend connectivity using the [health endpoint](http://localhost/UIU-Used-Textbook-Marketplace/api/health.php).

See [BACKEND_SETUP.md](BACKEND_SETUP.md) for detailed instructions.

## Fresh-Install Demo Accounts

These accounts are supplied by the fresh-install seed file. Existing local accounts may have different credentials.

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@uiu.ac.bd` | `password123` |
| Seller | `seller@uiu.ac.bd` | `password123` |
| Buyer | `buyer@uiu.ac.bd` | `password123` |

Seed credentials are intended for local demonstrations.

Registration accepts UIU email addresses at `uiu.ac.bd` and its subdomains. Users can register as buyers or sellers; administrator self-registration is not supported.

## Database Design

The application uses seven main tables.

| Table | Purpose |
| --- | --- |
| `users` | Accounts, password hashes, roles, and profiles |
| `categories` | Department and subject categories |
| `listings` | Book information, ownership, price, and moderation state |
| `purchase_requests` | Purchase requests, meetup details, and completion history |
| `wishlists` | Saved buyer listings |
| `messages` | Buyer and seller conversations |
| `reviews` | Ratings and comments for eligible completed purchases |

### Relationships

- Listings belong to seller accounts and can reference categories.
- Purchase requests connect buyers, sellers, and listings.
- Wishlist entries connect buyers to saved listings.
- Messages connect sender and receiver accounts.
- Reviews reference completed purchase requests and their participants.

Foreign keys maintain relationships between records. Unique constraints protect values such as account emails and wishlist pairs.

The fresh-install schema is stored in `database/bookbridge.sql`. Upgrade scripts and instructions are maintained in `database/migrations/`.

## Business Rules

### Listing Moderation

- New listings require administrator review before public availability.
- Listing states include `pending_approval`, `available`, `changes_requested`, `rejected`, and `sold`.
- Rejected listings and change requests include administrator feedback.
- Sellers can modify only listings they own.
- Backend permission checks remain authoritative.

### Category Management

- Categories support Department and Subject types.
- Subject categories must reference an existing department when created.
- Category renaming updates related records where applicable.
- Deletion is blocked when a category remains referenced by related records.

### Purchase Requests

- Request states include `pending`, `accepted`, `declined`, `completed`, and `cancelled`.
- Allowed actions depend on the current state and authenticated user.
- Sellers can decline accepted requests when allowed by the backend workflow.
- Meetup completion uses `api/buyer/seller-request-action.php`.
- Manual mark-sold is a separate listing action and does not complete a purchase request.
- Relisting preserves completed purchase history.

### Reviews

- Reviews require an eligible completed purchase.
- Review submissions use the actual purchase-request ID.
- Duplicate reviews for the same purchase are rejected.

### Payments

- The application supports **Cash on Meet**.
- No bKash, Nagad, advance-payment fee, or online payment gateway is integrated.
- There is no separate dummy-payment flow; buyers submit purchase requests and arrange an exchange.

### Reports

- Reports show completed-sales counts and transaction records.
- Completed meetups store a listing-price snapshot for revenue, average order value and transaction amounts. Later listing edits do not change past revenue. Older transactions without a snapshot are labelled Not recorded and excluded from monetary totals.
- Buyer savings are estimates.

## Security and Validation

- Passwords are hashed with bcrypt and checked with `password_verify`.
- PHP sessions establish authenticated identity.
- Protected APIs enforce login, role, and ownership checks.
- State-changing requests validate CSRF tokens.
- PDO prepared statements separate query parameters from SQL.
- Backend validation checks inputs independently of browser validation.
- Transactions and row locks protect related updates where required.
- Private configuration is excluded from Git.
- CLI test suites are blocked from web execution.

## API Integration Behavior

- Connected pages use real API responses.
- Listing details load through the listing-details endpoint.
- Failed API actions display errors.
- Success states are shown only after successful API responses.
- Action buttons prevent duplicate submissions while requests are pending.
- Counts and statuses refresh after successful actions.
- Request loading and refresh failures remain visible to the user.

## Testing

Run tests from the project directory using PHP CLI. Read each suite's prerequisites and fixture behavior before execution.

```powershell
C:\xampp\php\php.exe tests\auth_test.php
C:\xampp\php\php.exe tests\admin_test.php
C:\xampp\php\php.exe tests\buyer_workflow_test.php
C:\xampp\php\php.exe tests\seller_test.php
C:\xampp\php\php.exe tests\release_readiness_test.php
C:\xampp\php\php.exe tests\sale_price_test.php
```

JavaScript regressions (Node.js required):

```powershell
node tests/seller_integration_test.js
node tests/marketplace_ui_test.js
node tests/guest_marketplace_test.js
node tests/sale_price_ui_test.js
```

`marketplace_test.php` requires an isolated database whose name starts with `bookbridge_review_` and a separate Apache checkout at `http://localhost/UIU-Used-Textbook-Marketplace-main-review`. Configure that checkout to use the isolated database, import the fresh schema there, and run the suite from that checkout. Never point this suite at the demonstration database. Remove only the isolated database and checkout afterward.

The release-readiness suite checks fresh-schema compatibility, seed password verification, UIU email validation, and concurrent review submissions.

It requires permission to create and drop a uniquely named temporary test database, which is removed afterward.

Test coverage does not replace checking browser workflows against the database used for a demonstration.

## Git Collaboration

| Branch | Purpose |
| --- | --- |
| `main` | Integrated project baseline |
| `backend-development` | Team integration branch |
| `backend/adeeb` | Adeeb's development branch |
| `backend/tashin` | Tashin's development branch |
| `backend/labib` | Labib's development branch |
| `backend/tanvir` | Tanvir's development branch |

### Workflow

1. Develop changes on the appropriate member branch.
2. Submit a pull request to `backend-development`.
3. Review and verify the integrated changes.
4. Promote the integration branch to `main` through a release pull request.

Preserve teammates' uncommitted work. Keep unrelated uploads and private configuration out of code commits.

## Documentation

| Document | Purpose |
| --- | --- |
| [Backend Setup](BACKEND_SETUP.md) | Local environment and backend setup |
| [API Contract](API_CONTRACT.md) | Endpoint contracts and API behavior |
| [Team Guide](TEAM_GUIDE.md) | Module ownership and collaboration workflow |
| [Frontend Integration Handoff](FRONTEND_INTEGRATION_HANDOFF.md) | Frontend integration notes |
| [Database Migrations](database/migrations/README.md) | Database upgrade instructions |

## Current Scope

The repository includes integrated authentication, public marketplace, buyer, seller, messaging, review, and admin workflows.

- The optional book-donation feature is not implemented.
- Administrator account-management and password-reset APIs are not included in the current API contract.
- Hosting is a separate step from merging source code.
- GitHub Pages can serve static frontend files, but the PHP APIs and MySQL database require a compatible backend host.

## Academic Purpose

Developed as a United International University academic project demonstrating frontend, backend, database integration, role-based workflows, and collaborative development.
