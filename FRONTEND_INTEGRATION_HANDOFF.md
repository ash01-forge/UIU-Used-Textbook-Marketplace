# PR #9 feedback fixes — prepared locally

These fixes were applied to the XAMPP project on 2 October 2026 after write access became available. All seven original file hashes were verified before applying; original files are backed up at C:/Users/adeeb/Documents/Codex/2026-10-01/ei/feedback-fixes/backup-2026-10-02T11-15-47-497Z. JavaScript syntax checks, PHP lint, git diff --check, and the offline behavioral checks passed. Nothing has been committed, pushed, merged, or migrated.

Changes:
- Remove obsolete signed-out notice and auth query after a valid session is established.
- Query the public marketplace API with search, department, item type, condition, and sort; retain pagination and discard stale responses. Show loading and API errors, without demo listings.
- Keep failed action notices across reload for the same signed-in account; clear them on dismissal, success, or an account change.
- Return the existing course_code column from the admin sales report query.
- Display unavailable moderation rating/review fields honestly and explain when an edit is resubmitted for approval.
- Update frontend cache versions. Preserve existing layouts, APIs, and local integrations.

The report course comes from the existing listing metadata, not a historical snapshot. Revenue remains unavailable because no realized transaction amount is stored. No schema change is needed for these fixes.

Verification: JavaScript syntax checks and PHP lint passed. Offline behavioral checks passed for server search/filter parameters, pagination, available-only visibility, API failure without fallback, cancellation of stale responses, failed-action error storage, and invalid upload rejection. These checks use mocked responses and do not write to the database. The newly prepared UI changes have not been tested in a live browser.

Adeeb's earlier manual screenshots verify purchase request 31, seller acceptance, blocked manual mark-sold, bidirectional chat, meetup completion, the 4-star review, relisting with retained sales history, edit persistence, and admin reporting/category flows. Those were user actions before this package; they are not new tests performed by this script.

The package application script verifies original hashes before making changes. The fixes have already been applied; do not run it again. The prior handoff below is retained as historical context and is superseded by this status and verification record.


---
Prior handoff (historical):

# Original frontend integration — manual handoff

## পরবর্তী browser যাচাই ও detail fixes

Guest browse, search empty state/reset, BBA/CSE filter, listing details/reload এবং unauthenticated Seller-route guard browser-এ যাচাই হয়েছে। এই Guest checks-এ JavaScript error পাওয়া যায়নি। Buyer/Seller/Admin authenticated runtime checks এখনও বাকি; নিচের প্রথম handoff-এর runtime সীমা এখন এই scoped Guest verification দিয়ে আপডেট হয়েছে।

Listing detail এখন existing public seller-reviews API থেকে actual review count ও average rating নেয়। Zero reviews হলে `Not rated yet (0 reviews)`; API ব্যর্থ হলে `Reviews unavailable` এবং error notice দেখায়। Safety tip এখন `Cash on Meet only — pay after inspecting the book`। Reload করে দুটি সংশোধন দৃশ্যমান হয়েছে। Database/business records পরিবর্তন করা হয়নি।

## কাজের অবস্থা

`backend/adeeb` branch-এ local পরিবর্তন করা হয়েছে। Commit, push, merge, deployment, database mutation, migration, fixtures অথবা test suite চালানো হয়নি। PR #9-এ এই নতুন local পরিবর্তন এখনও নেই।

Original reference: `D:\Web Programming\FrontEnd Codes`। সেখানে `index.html`, `assets/app.js`, `assets/styles.css` ও `README.txt` আছে; editable JSX/TSX source বা build project পাওয়া যায়নি। Reference folder পরিবর্তন করা হয়নি। Existing compiled React layouts রাখা হয়েছে। নতুন readable integration logic `frontend-integration.js`-এ আছে; compiled component-এর handler wiring `app.js`-এ করা হয়েছে। নতুন framework বা build process যোগ করা হয়নি।

## কী সংশোধন হয়েছে

- Guest: real available listings, categories, department/type/condition filtering, search, detail loading ও unavailable public metrics।
- Buyer: actual identity, dashboard statistics/recommendations/recent requests, wishlist, Cash on Meet purchase request, cancellation এবং completed request ID দিয়ে eligible review।
- Seller: public list-এর বদলে authenticated own listings; create/edit, real file upload, delete, mark sold/relist ও sales history। Accepted request থাকলে manual mark sold backend-এর বিদ্যমান নিয়মেই blocked থাকে। Request panel থেকে accept, accepted request decline এবং Complete meetup shared request-action endpoint ব্যবহার করে।
- Messages: actual conversation partners, thread history, unread counts এবং server-confirmed message submission। Thread খুললে existing backend messages read হিসেবে mark করে; এটি verification-এর সময় চালানো হয়নি।
- Buyer/Seller profile: existing allowlisted profile APIs; email, role বা password পরিবর্তনের ব্যবস্থা যোগ করা হয়নি।
- Admin: actual pending records/statistics, approve/reject/request changes, feedback, categories ও parent department selection, date-filtered completed-sales report।
- Fake timer success, fake order ID, ৳২০ platform fee এবং demo conversation/dashboard data সরানো হয়েছে। Request পাঠানোকে completed purchase বলা হয় না। Unsupported realized revenue/transaction prices `Unavailable`; current listing prices দিয়ে revenue বানানো হয় না। Buyer savings existing backend-এর estimate, historical realized savings নয়।
- Writes shared cookie session/CSRF helper ব্যবহার করে। Failed submission-এ input থাকে এবং error notice দেখা যায়। Duplicate submissions আটকানো হয়েছে। Uploaded file API-তে FormData হিসেবে যায়; blob URL শুধু local preview।
- Legacy public/private HTML links original SPA-তে যায়। Role guards, session expiry, logout, selected listing/review/edit reload এবং cache version update করা হয়েছে।

## Local entry ও navigation

`http://localhost/UIU-Used-Textbook-Marketplace/`

Login-এর পরে actual server role অনুযায়ী একই original UI-তে `#buyer-dashboard`, `#seller-dashboard` বা `#admin-dashboard` দেখা যায়। Buyer/Seller-এর user menu থেকে Purchase Requests, Messages ও My Profile পাওয়া যায়।

## যাচাইয়ের সীমা

চারটি JavaScript file (`app.js`, `auth.js`, `api-integration.js`, `frontend-integration.js`) Node syntax check-এ pass করেছে। API implementation-এর routes, methods, request fields ও response shapes source inspection করে মিলিয়ে নেওয়া হয়েছে। `git diff --check`-এ whitespace error নেই। মূল stylesheet ও backend PHP files পরিবর্তন করা হয়নি।

Browser/runtime verification হয়নি। Syntax pass দিয়ে actual login, UI rendering, uploads বা database-connected flow-এর success নিশ্চিত করা যায় না। সম্পূর্ণ editable frontend source অনুপস্থিত থাকায় ভবিষ্যতে compiled bundle rebuild করলে integration wiring হারাতে পারে; original source উদ্ধার করে helper wiring source-এ স্থানান্তর করতে হবে।

## Manual verification — প্রথমে শুধু দেখা

1. XAMPP Apache/MySQL start করে entry URL খুলুন; hard refresh করুন। Console/Network-এ JavaScript error, 404 বা failed API response থাকলে সংশ্লিষ্ট error সংগ্রহ করুন।
2. Guest হিসেবে homepage, browse, search/filter ও listing detail দেখুন। Empty database response হলে demo listing যেন ফিরে না আসে।
3. Existing Buyer account-এ login করে আসল নাম/statistics, recommendations, recent request statuses ও wishlist দেখুন।
4. Existing Seller account-এ login করে নিজের pending/available/sold records ও sales history দেখুন। অন্য seller-এর records যেন না থাকে।
5. Existing Admin account-এ pending list, dashboard, categories এবং weekly/monthly/yearly report দেখুন। Unsupported metrics-এর explanation দেখুন।
6. Selected detail/edit/review link reload, logout, expired session ও ভুল role-এর private route দেখুন। Legacy HTML URL-ও original UI-তে পৌঁছায় কি না যাচাই করুন।

## Manual actions — আলাদা disposable data হলে

এগুলো application usage হিসেবে records বদলায়। Existing important records/accounts/uploads ব্যবহার করে delete বা status changes করবেন না। আলাদা অনুমোদিত disposable records/account ছাড়া এই ধাপগুলো বাদ রাখুন।

- Buyer wishlist toggle, request submit/cancel; request response-এর actual ID দেখুন। Pending request-এ review করা যায় না।
- Seller valid JPG/PNG/WebP <=2 MB upload, listing create/edit ও moderation feedback; invalid upload/API failure হলে input/error থাকে কি না দেখুন।
- Buyer request → Seller accept → manual mark sold blocked → accepted request decline অথবা Complete meetup। Relist-এর পর completed sale history থাকে কি না দেখুন।
- Completed request-এর Buyer review; duplicate/ineligible submission-এর backend error দেখুন।
- Messages send/read ও profile update। Failure-এ message/form input থাকছে কি না দেখুন।
- Admin approve/reject/changes requested, department/subject creation/update এবং referenced-category deletion rejection।

## Teammate setup

এই কাজ review করে shared branch-এ অন্তর্ভুক্ত হওয়ার পরে teammates updated code নেবে। Local database credentials ignored local config-এ থাকবে; password/account reset লাগবে না। Git pull database/accounts আপডেট করে না। নিজের configured database-এ required category relationship fields না থাকলে exact schema gap যাচাই করে documented setup অনুসরণ করতে হবে; এই কাজ কোনো migration চালায়নি। Adeeb-এর আগের screenshot-এ migration 002 `bookbridge_db`-তে successful import দেখানো আছে; তাই যাচাই ছাড়া আবার import নয়। Database name `uiu_textbook_marketplace` ধরে নেবেন না।

Suggested commit summary: `Fix original frontend API flows and remove mock success states`

