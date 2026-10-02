# Final local validation — 3 October 2026

Validated the checkout based on merged PR #16 (`50fc7a1`) and the fixes on `fix/final-validation`.

## Repairs

- Added migration 003 to align older `listings.subject` columns with the nullable fresh-install schema and optional API field. Applied it locally and verified that every original listing row was unchanged. Release regressions verify value preservation and repeatability.
- Authentication and buyer test preservation checks now compare the actual pre-run records, including passwords and roles, instead of assuming untouched seed credentials and fixed seed row counts. Existing users and listings are not reset to make tests pass.
- Marketplace tests accept an isolated `bookbridge_review_` database instead of one date-specific database. The separate review checkout restriction and before/after table snapshots remain enforced.
- Corrected one React JSX static-child flag on the sales report's price cell. The compiled bundle's original formatting and behavior are preserved; only the invalid array flag changed. Updated the asset version to prevent cached code.

## Final test results

| Suite | Result |
|---|---|
| Authentication | 75 passed, 0 failed |
| Admin API | 68 passed, 0 failed |
| Seller API | 71 passed, 0 failed |
| Buyer, messaging and reviews | 87 passed, 0 failed |
| Marketplace API, separate fresh review database | 45 passed, 0 failed |
| Release readiness | Passed, including migration and concurrent review regressions |
| Seller frontend integration | Passed |
| Marketplace frontend regression | Passed |
| Standalone guest regression | Passed |
| PHP and root JavaScript syntax | No failures |

Authentication check totals depend on the number of original accounts being preserved.

## Browser checks

- Guest department filtering, empty results and filter reset.
- Supplied buyer login, dashboard and logout.
- Disposable seller login, dashboard, management page, active EditListing form, save without a replacement cover, and persistence after reload.
- Supplied admin login, dashboard, categories and sales report. No new React console error after the report fix.
- Server tests cover cover upload/MIME checks, ownership/role/CSRF guards, accepted-request decline, meetup completion, wishlist, messages, reviews, moderation, categories and reports. Frontend regressions cover upload retry caching, preview cleanup, deep links and duplicate submission locking.

## Scope and data handling

Browser checks are smoke tests, not an exhaustive device/browser matrix. The later sale-price reporting change below supersedes the original revenue limitation; unknown historical amounts remain explicitly unrecorded.

Labib confirmed the supplied seller account was created on his own PC. It is not present in this localhost database. No existing account was recreated or had its password changed; disposable seller fixtures were used instead.

Temporary test records and browser fixtures were removed. Existing uploaded book covers were excluded from the commit. Migration 003 should be applied to other older installations where `listings.subject` is still NOT NULL. Fresh installations already allow NULL.

## Sale amount reporting — 3 October 2026

Migration 004 adds nullable `purchase_requests.sale_price`. Completion saves the locked listing price atomically. Dashboard/report totals and seller revenue use these immutable amounts, including valid zero-price sales. Date filters apply to the full summary, independent of report pagination. Existing historical prices remain unknown and are explicitly excluded with a coverage note; no fabricated backfill was performed. These are recorded listing prices at completion, not verification of external cash payments.

Validation rerun after this change:
- Sale-price backend regression: 12 checks plus isolated database cleanup passed (repeatable migration, ownership, completion, immutable history, duplicate rejection, zero, averages, pagination, date range and seller scope).
- Admin API: 68 passed; seller API: 71 passed; buyer/messages/reviews: 87 passed.
- Release readiness passed.
- Sale-price UI, seller integration, marketplace UI and guest marketplace suites passed.
- PHP API/helper and JavaScript syntax checks passed.
- Browser dashboard and sales report showed a disposable QA transaction of BDT 245.75, correct total/average, daily revenue and an unknown historical amount. The QA transaction, listing and buyer were removed. After the markup correction the browser produced no new console errors.

The local database migration is applied and original request fields are preserved. Other installations must apply migration 004 before using the new APIs. Existing app.js formatting and user uploads were excluded from this commit.

## Final audit of merged main

On 3 October 2026, all eleven suites were rerun on merged main `6e0504f`: authentication, admin, seller, buyer/messages/reviews, sale-price backend, release readiness, isolated marketplace API, sale-price UI, seller integration, marketplace UI and guest marketplace. All exited successfully. All API/helper PHP files and root JavaScript files passed syntax checks. The local health endpoint reported database connected. The marketplace review database and checkout were removed after validation.

Faculty-required admin, seller, buyer and guest workflows are covered by the existing integration tests and browser checks described above. Purchase requests satisfy the request-to-buy requirement; donation is optional. This is local validation, not a guarantee against every possible browser or deployment issue.

Presentation checklist: start Apache/MySQL; use the localhost URL; demonstrate seller submission → admin approval → buyer request → seller acceptance/completion → buyer review and admin report. Keep a database and uploads backup outside Git. Accounts created on a teammate's laptop are not automatically transferred by Git. Personal viva preparation, report and slides remain the presenters' responsibility.

## Seller Sales History summary correction

The compiled Sales History layout still displayed hardcoded Unavailable totals. The `sales-history` route now uses an editable API-connected component in `frontend-integration.js`; `app.js` is unchanged by this repair. Revenue and average use recorded amounts across all pages loaded by the existing seller API adapter. Zero-price sales are included, unknown historical/manual amounts are excluded with a note, and loading/errors have explicit states with Retry rather than misleading zero totals.

After this repair, sale-price UI regressions, seller frontend integration, marketplace UI regressions and all 12 sale-price backend checks passed; frontend JavaScript syntax and changed-file whitespace checks passed. Browser verification with disposable seller data showed total sales 3, recorded revenue BDT 245.75, average BDT 122.88, zero amount BDT 0.00 and one unknown amount. No browser error/warning was captured. The test session was signed out and all fixture records removed. Existing seller credentials were not reset.
