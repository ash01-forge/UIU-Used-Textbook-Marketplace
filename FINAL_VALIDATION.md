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

Browser checks are smoke tests, not an exhaustive device/browser matrix. Revenue remains unavailable because transaction-time prices are not stored; it is not fabricated from current listing prices.

Labib confirmed the supplied seller account was created on his own PC. It is not present in this localhost database. No existing account was recreated or had its password changed; disposable seller fixtures were used instead.

Temporary test records and browser fixtures were removed. Existing uploaded book covers were excluded from the commit. Migration 003 should be applied to other older installations where `listings.subject` is still NOT NULL. Fresh installations already allow NULL.
