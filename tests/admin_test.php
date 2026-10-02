<?php
/**
 * BookBridge admin API integration tests. CLI only:
 *   C:\xampp\php\php.exe tests\admin_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "403 Forbidden\nThis test runner is CLI-only.\n";
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$base = 'http://localhost/UIU-Used-Textbook-Marketplace/api';
$pass = 0;
$fail = 0;
$jars = [];
$userIds = [];
$listingIds = [];
$purchaseIds = [];
$categoryIds = [];
$db = getDbConnection();
$tag = strtoupper(bin2hex(random_bytes(4)));
$password = 'AdminFixture-' . bin2hex(random_bytes(8));
$adminEmail = 'admin_test_' . strtolower($tag) . '@uiu.ac.bd';
$buyerEmail = 'buyer_test_' . strtolower($tag) . '@uiu.ac.bd';
$sellerEmail = 'seller_test_' . strtolower($tag) . '@uiu.ac.bd';
$department = 'QA-' . $tag;
$subject = 'Subject-' . $tag;
$renamedDepartment = 'QA-RENAMED-' . $tag;
$renamedSubject = 'Subject-RENAMED-' . $tag;

function adminCheck(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "[PASS] {$label}\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}\n";
    }
}

function adminRequest(string $url, string $method = 'GET', ?array $body = null, array $headers = [], string $jar = ''): array
{
    $curl = curl_init($url);
    $requestHeaders = array_merge(['Accept: application/json'], $headers);
    if ($body !== null) {
        $requestHeaders[] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $requestHeaders,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    }
    if ($jar !== '') {
        curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    }
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    return [
        'status' => $status,
        'json' => json_decode($raw === false ? '' : $raw, true) ?? [],
        'curl_error' => $error,
    ];
}

function adminUrl(string $path, array $query = []): string
{
    global $base;
    return $base . '/admin/' . $path . ($query ? '?' . http_build_query($query) : '');
}

function adminLogin(string $email, string $jar): array
{
    global $base, $password;
    return adminRequest($base . '/auth/login.php', 'POST', [
        'email' => $email,
        'password' => $password,
    ], [], $jar);
}

function adminHeader(string $csrf): array
{
    return ['X-CSRF-Token: ' . $csrf];
}

function adminConcurrentModeration(string $url, array $bodies, string $csrf, string $jar): array
{
    $multi = curl_multi_init();
    $handles = [];
    foreach ($bodies as $index => $body) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-CSRF-Token: ' . $csrf,
            ],
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_multi_add_handle($multi, $curl);
        $handles[$index] = $curl;
    }

    do {
        $result = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 && $result === CURLM_OK);

    $responses = [];
    foreach ($handles as $curl) {
        $raw = curl_multi_getcontent($curl);
        $responses[] = [
            'status' => (int) curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'json' => json_decode($raw, true) ?? [],
        ];
        curl_multi_remove_handle($multi, $curl);
        curl_close($curl);
    }
    curl_multi_close($multi);

    return $responses;
}

function createAdminFixtureUser(string $name, string $email, string $role, ?string $department = null): int
{
    global $db, $password, $userIds;
    $insert = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, department, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department]);
    $id = (int) $db->lastInsertId();
    $userIds[] = $id;
    return $id;
}

function createAdminFixtureListing(int $sellerId, ?int $categoryId, string $department, string $subject, string $title, string $type, string $status): int
{
    global $db, $listingIds;
    $insert = $db->prepare(
        'INSERT INTO listings (
            seller_id, category_id, title, author, edition, department, course_code,
            subject, item_type, condition_type, price, description, status, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $insert->execute([
        $sellerId,
        $categoryId,
        $title,
        'QA Author',
        '1st Edition',
        $department,
        'QA-' . substr($title, -6),
        $subject,
        $type,
        'Good',
        123.45,
        'Disposable admin API test fixture.',
        $status,
    ]);
    $id = (int) $db->lastInsertId();
    $listingIds[] = $id;
    return $id;
}

function fixtureResponseId(array $response): int
{
    return (int) ($response['json']['data']['category']['id'] ?? 0);
}

try {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for admin integration tests.');
    }

    echo "BookBridge Admin API tests (CLI)\n";

    $guestDashboard = adminRequest(adminUrl('dashboard.php'));
    $guestQueue = adminRequest(adminUrl('pending-listings.php'));
    $guestCategories = adminRequest(adminUrl('categories.php'));
    $guestReport = adminRequest(adminUrl('sales-report.php'));
    $guestReview = adminRequest(adminUrl('review-listing.php'), 'POST', []);
    adminCheck($guestDashboard['status'] === 401, 'Guest dashboard denied with 401');
    adminCheck($guestQueue['status'] === 401, 'Guest moderation queue denied with 401');
    adminCheck($guestCategories['status'] === 401, 'Guest categories denied with 401');
    adminCheck($guestReport['status'] === 401, 'Guest sales report denied with 401');
    adminCheck($guestReview['status'] === 401, 'Guest moderation action denied with 401');

    $adminId = createAdminFixtureUser('Admin API Fixture', $adminEmail, 'admin');
    $buyerId = createAdminFixtureUser('Buyer API Fixture', $buyerEmail, 'buyer');
    $sellerId = createAdminFixtureUser('Seller API Fixture', $sellerEmail, 'seller');

    $adminJar = tempnam(sys_get_temp_dir(), 'bb_admin_test_');
    $buyerJar = tempnam(sys_get_temp_dir(), 'bb_admin_test_');
    $sellerJar = tempnam(sys_get_temp_dir(), 'bb_admin_test_');
    $jars = [$adminJar, $buyerJar, $sellerJar];
    $adminLogin = adminLogin($adminEmail, $adminJar);
    $buyerLogin = adminLogin($buyerEmail, $buyerJar);
    $sellerLogin = adminLogin($sellerEmail, $sellerJar);
    adminCheck($adminLogin['status'] === 200, 'Disposable admin login succeeds');
    adminCheck($buyerLogin['status'] === 200, 'Disposable buyer login succeeds');
    adminCheck($sellerLogin['status'] === 200, 'Disposable seller login succeeds');

    $buyerDenied = [];
    $sellerDenied = [];
    foreach (['dashboard.php', 'pending-listings.php', 'categories.php', 'sales-report.php'] as $endpoint) {
        $buyerDenied[] = adminRequest(adminUrl($endpoint), 'GET', null, [], $buyerJar)['status'] === 403;
        $sellerDenied[] = adminRequest(adminUrl($endpoint), 'GET', null, [], $sellerJar)['status'] === 403;
    }
    $buyerDenied[] = adminRequest(
        adminUrl('review-listing.php'), 'POST', ['listing_id' => 1, 'action' => 'approve'], [], $buyerJar
    )['status'] === 403;
    $sellerDenied[] = adminRequest(
        adminUrl('review-listing.php'), 'POST', ['listing_id' => 1, 'action' => 'approve'], [], $sellerJar
    )['status'] === 403;
    adminCheck(!in_array(false, $buyerDenied, true), 'Buyer is denied from every admin endpoint with 403');
    adminCheck(!in_array(false, $sellerDenied, true), 'Seller is denied from every admin endpoint with 403');

    $csrf = $adminLogin['json']['data']['csrf_token'] ?? '';
    adminCheck(is_string($csrf) && strlen($csrf) === 64, 'Admin login returns CSRF token');
    $badCsrfReview = adminRequest(
        adminUrl('review-listing.php'),
        'POST',
        ['listing_id' => 1, 'action' => 'approve'],
        adminHeader('tampered-token'),
        $adminJar
    );
    $missingCsrfReview = adminRequest(
        adminUrl('review-listing.php'),
        'POST',
        ['listing_id' => 1, 'action' => 'approve'],
        [],
        $adminJar
    );
    $missingCsrfCategory = adminRequest(
        adminUrl('categories.php'),
        'POST',
        ['type' => 'Department', 'name' => 'Must Not Create'],
        [],
        $adminJar
    );
    $badCsrfCategory = adminRequest(
        adminUrl('categories.php'),
        'DELETE',
        ['id' => 1],
        adminHeader('tampered-token'),
        $adminJar
    );
    adminCheck($badCsrfReview['status'] === 403, 'Invalid CSRF rejected on review');
    adminCheck($missingCsrfReview['status'] === 403, 'Missing CSRF rejected on review');
    adminCheck($missingCsrfCategory['status'] === 403, 'Missing CSRF rejected on category create');
    adminCheck($badCsrfCategory['status'] === 403, 'Invalid CSRF rejected on category delete');

    $createDepartment = adminRequest(
        adminUrl('categories.php'),
        'POST',
        ['type' => 'Department', 'name' => $department],
        adminHeader($csrf),
        $adminJar
    );
    $departmentId = fixtureResponseId($createDepartment);
    if ($departmentId > 0) {
        $categoryIds[] = $departmentId;
    }
    adminCheck($createDepartment['status'] === 201 && $departmentId > 0, 'Admin creates a department category');

    $createSubject = adminRequest(
        adminUrl('categories.php'),
        'POST',
        ['type' => 'Subject', 'name' => $subject, 'department' => $department],
        adminHeader($csrf),
        $adminJar
    );
    $subjectId = fixtureResponseId($createSubject);
    if ($subjectId > 0) {
        $categoryIds[] = $subjectId;
    }
    adminCheck($createSubject['status'] === 201 && $subjectId > 0, 'Admin creates a subject linked to its department');

    $duplicateCategory = adminRequest(
        adminUrl('categories.php'),
        'POST',
        ['type' => 'Department', 'name' => $department],
        adminHeader($csrf),
        $adminJar
    );
    $subjectWithoutParent = adminRequest(
        adminUrl('categories.php'),
        'POST',
        ['type' => 'Subject', 'name' => 'Orphan-' . $tag],
        adminHeader($csrf),
        $adminJar
    );
    $renameDuplicateCategory = adminRequest(
        adminUrl('categories.php'),
        'PUT',
        ['id' => $departmentId, 'name' => 'CSE'],
        adminHeader($csrf),
        $adminJar
    );
    adminCheck($duplicateCategory['status'] === 409, 'Duplicate category rejected');
    adminCheck($subjectWithoutParent['status'] === 422, 'Subject requires an existing department');
    adminCheck($renameDuplicateCategory['status'] === 409, 'Duplicate category rename rejected');

    $sellerDepartment = $db->prepare('UPDATE users SET department = ? WHERE id = ?');
    $sellerDepartment->execute([$department, $sellerId]);

    $listingApprove = createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture A ' . $tag, 'Textbook', 'pending_approval');
    $listingReject = createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture B ' . $tag, 'Notes', 'pending_approval');
    $listingChanges = createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture C ' . $tag, 'Textbook', 'pending_approval');
    $listingRepeat = createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture D ' . $tag, 'Textbook', 'pending_approval');
    $listingChangedState = createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture E ' . $tag, 'Textbook', 'changes_requested');
    $listingSold = createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture F ' . $tag, 'Textbook', 'sold');
    createAdminFixtureListing($sellerId, $subjectId, $department, $subject, 'Moderation Fixture G ' . $tag, 'Textbook', 'available');

    $queue = adminRequest(adminUrl('pending-listings.php', [
        'department' => $department,
        'page' => 1,
        'per_page' => 2,
        'sort' => 'created_at',
        'direction' => 'asc',
    ]), 'GET', null, [], $adminJar);
    $queueData = $queue['json']['data'] ?? [];
    adminCheck($queue['status'] === 200 && ($queueData['pagination']['total'] ?? 0) === 4, 'Queue filters pending status and department');
    adminCheck(($queueData['pagination']['per_page'] ?? 0) === 2 && ($queueData['pagination']['total_pages'] ?? 0) === 2, 'Queue pagination metadata is correct');
    adminCheck(count($queueData['listings'] ?? []) === 2, 'Queue returns requested page size');
    $firstListing = $queueData['listings'][0] ?? [];
    adminCheck(isset($firstListing['description'], $firstListing['seller_name'], $firstListing['course_code']), 'Queue includes moderation details and seller identity');
    adminCheck(!isset($firstListing['password_hash']), 'Queue does not expose password hashes');

    $searchQueue = adminRequest(adminUrl('pending-listings.php', ['search' => 'Fixture A ' . $tag]), 'GET', null, [], $adminJar);
    $typeQueue = adminRequest(adminUrl('pending-listings.php', ['department' => $department, 'type' => 'Notes']), 'GET', null, [], $adminJar);
    $badPage = adminRequest(adminUrl('pending-listings.php', ['page' => 0]), 'GET', null, [], $adminJar);
    $badSort = adminRequest(adminUrl('pending-listings.php', ['sort' => 'title; DELETE']), 'GET', null, [], $adminJar);
    adminCheck($searchQueue['status'] === 200 && ($searchQueue['json']['data']['pagination']['total'] ?? 0) === 1, 'Queue search filter works');
    adminCheck($typeQueue['status'] === 200 && ($typeQueue['json']['data']['pagination']['total'] ?? 0) === 1, 'Queue item type filter works');
    adminCheck($badPage['status'] === 422, 'Invalid page rejected');
    adminCheck($badSort['status'] === 422, 'Sort field is allowlisted');

    $queueChanged = adminRequest(adminUrl('pending-listings.php', ['department' => $department]), 'GET', null, [], $adminJar);
    $queueIds = array_column($queueChanged['json']['data']['listings'] ?? [], 'id');
    adminCheck(!in_array($listingChangedState, $queueIds, true) && !in_array($listingSold, $queueIds, true), 'Changed and sold listings are not in pending queue');

    $missingFeedback = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingReject, 'action' => 'reject'],
        adminHeader($csrf), $adminJar
    );
    $badId = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => 'not-an-id', 'action' => 'approve'],
        adminHeader($csrf), $adminJar
    );
    $badAction = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingReject, 'action' => 'sold'],
        adminHeader($csrf), $adminJar
    );
    $longFeedback = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingReject, 'action' => 'reject', 'admin_feedback' => str_repeat('x', 2001)],
        adminHeader($csrf), $adminJar
    );
    adminCheck($missingFeedback['status'] === 422, 'Reject requires feedback');
    adminCheck($badId['status'] === 422, 'Invalid listing ID rejected');
    adminCheck($badAction['status'] === 422, 'Unknown moderation action rejected');
    adminCheck($longFeedback['status'] === 422, 'Overlong moderation feedback rejected');

    $approve = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingApprove, 'action' => 'approve'],
        adminHeader($csrf), $adminJar
    );
    $approvedRow = $db->prepare('SELECT status, admin_feedback, reviewed_by, reviewed_at FROM listings WHERE id = ?');
    $approvedRow->execute([$listingApprove]);
    $approved = $approvedRow->fetch();
    adminCheck($approve['status'] === 200 && ($approved['status'] ?? '') === 'available', 'Pending listing can be approved');
    adminCheck((int) ($approved['reviewed_by'] ?? 0) === $adminId && !empty($approved['reviewed_at']), 'Approval records session admin and timestamp');
    adminCheck($approved['admin_feedback'] === null, 'Approval clears old feedback');
    adminCheck(!isset($approve['json']['data']['listing']['password_hash']), 'Review response contains no password hash');

    $approveAgain = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingApprove, 'action' => 'approve'],
        adminHeader($csrf), $adminJar
    );
    $rejectSold = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingSold, 'action' => 'reject', 'admin_feedback' => 'Must not modify sold fixture.'],
        adminHeader($csrf), $adminJar
    );
    $rejectMissing = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => 4294967294, 'action' => 'approve'],
        adminHeader($csrf), $adminJar
    );
    adminCheck($approveAgain['status'] === 409, 'Repeated moderation request conflicts');
    adminCheck($rejectSold['status'] === 409, 'Sold listing cannot be reopened or rejected');
    adminCheck($rejectMissing['status'] === 404, 'Unknown listing returns 404');

    $concurrentReviews = adminConcurrentModeration(
        adminUrl('review-listing.php'),
        [
            ['listing_id' => $listingRepeat, 'action' => 'approve'],
            ['listing_id' => $listingRepeat, 'action' => 'reject', 'admin_feedback' => 'Concurrent review fixture.'],
        ],
        $csrf,
        $adminJar
    );
    $concurrentStatuses = array_column($concurrentReviews, 'status');
    sort($concurrentStatuses);
    $concurrentRow = $db->prepare('SELECT status, reviewed_by, reviewed_at FROM listings WHERE id = ?');
    $concurrentRow->execute([$listingRepeat]);
    $concurrentListing = $concurrentRow->fetch();
    adminCheck($concurrentStatuses === [200, 409], 'Concurrent moderation allows one winner and conflicts the other');
    adminCheck(in_array($concurrentListing['status'] ?? '', ['available', 'rejected'], true)
        && (int) ($concurrentListing['reviewed_by'] ?? 0) === $adminId
        && !empty($concurrentListing['reviewed_at']), 'Concurrent winner persists a single valid review');

    $feedback = 'Please correct the cover photo.';
    $reject = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingReject, 'action' => 'reject', 'admin_feedback' => $feedback],
        adminHeader($csrf), $adminJar
    );
    $rejectRow = $db->prepare('SELECT status, admin_feedback, reviewed_by, reviewed_at FROM listings WHERE id = ?');
    $rejectRow->execute([$listingReject]);
    $rejected = $rejectRow->fetch();
    adminCheck($reject['status'] === 200 && ($rejected['status'] ?? '') === 'rejected', 'Pending listing can be rejected');
    adminCheck(($rejected['admin_feedback'] ?? '') === $feedback, 'Rejection feedback is persisted');
    adminCheck((int) ($rejected['reviewed_by'] ?? 0) === $adminId && !empty($rejected['reviewed_at']), 'Rejection reviewer and timestamp are persisted');

    $requestChanges = adminRequest(
        adminUrl('review-listing.php'), 'POST',
        ['listing_id' => $listingChanges, 'action' => 'changes_requested', 'admin_feedback' => 'Add the edition number.'],
        adminHeader($csrf), $adminJar
    );
    adminCheck($requestChanges['status'] === 200 && ($requestChanges['json']['data']['listing']['status'] ?? '') === 'changes_requested', 'Changes-requested transition is supported');

    $dashboard = adminRequest(adminUrl('dashboard.php'), 'GET', null, [], $adminJar);
    $dashboardData = $dashboard['json']['data'] ?? [];
    adminCheck($dashboard['status'] === 200 && is_int($dashboardData['pending_review_count'] ?? null), 'Admin dashboard returns pending count');
    adminCheck(is_array($dashboardData['listing_counts'] ?? null) && is_array($dashboardData['user_counts'] ?? null), 'Admin dashboard returns listing and role counts');
    adminCheck(is_int($dashboardData['completed_sales_count'] ?? null), 'Admin dashboard returns completed sales count');
    adminCheck(array_key_exists('revenue', $dashboardData) && is_numeric($dashboardData['revenue']), 'Dashboard returns recorded revenue');
    adminCheck(!isset($dashboardData['password_hash']), 'Dashboard does not expose password hashes');

    $purchase = $db->prepare(
        "INSERT INTO purchase_requests (
            listing_id, buyer_id, seller_id, meeting_location, preferred_date,
            payment_method, status, completed_at, created_at, updated_at
         ) VALUES (?, ?, ?, 'QA Campus', CURDATE(), 'cash_on_meet', 'completed', NOW(), NOW(), NOW())"
    );
    $purchase->execute([$listingSold, $buyerId, $sellerId]);
    $purchaseIds[] = (int) $db->lastInsertId();

    $today = date('Y-m-d');
    $report = adminRequest(adminUrl('sales-report.php', ['from' => $today, 'to' => $today, 'page' => 1, 'per_page' => 1]), 'GET', null, [], $adminJar);
    $reportData = $report['json']['data'] ?? [];
    adminCheck($report['status'] === 200 && ($reportData['summary']['completed_sales_count'] ?? 0) >= 1, 'Sales report counts completed transactions within date filter');
    adminCheck(count($reportData['transactions'] ?? []) === 1 && ($reportData['pagination']['per_page'] ?? 0) === 1, 'Sales report rows are paginated');
    adminCheck(array_key_exists('revenue', $reportData) && is_numeric($reportData['revenue']) && ($reportData['summary']['unpriced_sales_count'] ?? 0) >= 1, 'Sales report returns recorded revenue and identifies legacy unpriced sales');
    adminCheck(!isset($reportData['transactions'][0]['password_hash']), 'Sales report excludes password hashes');
    $badDate = adminRequest(adminUrl('sales-report.php', ['from' => '2026-99-40']), 'GET', null, [], $adminJar);
    $badRange = adminRequest(adminUrl('sales-report.php', ['from' => '2026-10-02', 'to' => '2026-10-01']), 'GET', null, [], $adminJar);
    adminCheck($badDate['status'] === 422 && $badRange['status'] === 422, 'Sales report rejects invalid date filters');

    $categories = adminRequest(adminUrl('categories.php', ['type' => 'Subject', 'department' => $department]), 'GET', null, [], $adminJar);
    $categoryNames = array_column($categories['json']['data']['categories'] ?? [], 'name');
    adminCheck($categories['status'] === 200 && in_array($subject, $categoryNames, true), 'Admin category filter preserves subject-parent relationship');

    $renameDepartment = adminRequest(
        adminUrl('categories.php'), 'PUT',
        ['id' => $departmentId, 'name' => $renamedDepartment],
        adminHeader($csrf), $adminJar
    );
    $renamedSubjects = adminRequest(adminUrl('categories.php', ['type' => 'Subject', 'department' => $renamedDepartment]), 'GET', null, [], $adminJar);
    $renamedListing = $db->prepare('SELECT department FROM listings WHERE id = ?');
    $renamedListing->execute([$listingApprove]);
    $renamedSeller = $db->prepare('SELECT department FROM users WHERE id = ?');
    $renamedSeller->execute([$sellerId]);
    adminCheck($renameDepartment['status'] === 200, 'Department rename succeeds');
    adminCheck(($renamedSubjects['json']['data']['categories'][0]['department'] ?? '') === $renamedDepartment, 'Department rename preserves child subject links');
    adminCheck($renamedListing->fetchColumn() === $renamedDepartment && $renamedSeller->fetchColumn() === $renamedDepartment, 'Department rename preserves listing and user department labels');

    $renameSubject = adminRequest(
        adminUrl('categories.php'), 'PUT',
        ['id' => $subjectId, 'name' => $renamedSubject],
        adminHeader($csrf), $adminJar
    );
    $renamedListingSubject = $db->prepare('SELECT subject FROM listings WHERE id = ?');
    $renamedListingSubject->execute([$listingApprove]);
    adminCheck($renameSubject['status'] === 200 && $renamedListingSubject->fetchColumn() === $renamedSubject, 'Subject rename preserves linked listing labels');

    $legacySubjectName = 'Legacy-Subject-' . $tag;
    $legacyCategoryInsert = $db->prepare(
        "INSERT INTO categories (name, type, department, subject) VALUES (?, 'Subject', NULL, ?)"
    );
    $legacyCategoryInsert->execute([$legacySubjectName, $legacySubjectName]);
    $legacyCategoryId = (int) $db->lastInsertId();
    $categoryIds[] = $legacyCategoryId;
    $legacyListingId = createAdminFixtureListing(
        $sellerId,
        null,
        'CSE',
        $legacySubjectName,
        'Legacy subject fixture ' . $tag,
        'Textbook',
        'available'
    );
    $renamedLegacySubject = 'Renamed-Legacy-Subject-' . $tag;
    $renameLegacySubject = adminRequest(
        adminUrl('categories.php'), 'PUT',
        ['id' => $legacyCategoryId, 'name' => $renamedLegacySubject],
        adminHeader($csrf), $adminJar
    );
    $legacyListingQuery = $db->prepare('SELECT subject FROM listings WHERE id = ?');
    $legacyListingQuery->execute([$legacyListingId]);
    adminCheck($renameLegacySubject['status'] === 200 && $legacyListingQuery->fetchColumn() === $renamedLegacySubject, 'Legacy subject without parent preserves listing label on rename');
    $deleteLegacySubject = adminRequest(
        adminUrl('categories.php'), 'DELETE', ['id' => $legacyCategoryId], adminHeader($csrf), $adminJar
    );
    adminCheck($deleteLegacySubject['status'] === 409, 'Legacy subject cannot be deleted while a listing uses its label');

    $deleteReferencedSubject = adminRequest(
        adminUrl('categories.php'), 'DELETE', ['id' => $subjectId], adminHeader($csrf), $adminJar
    );
    $deleteReferencedDepartment = adminRequest(
        adminUrl('categories.php'), 'DELETE', ['id' => $departmentId], adminHeader($csrf), $adminJar
    );
    adminCheck($deleteReferencedSubject['status'] === 409, 'Referenced subject category cannot be deleted');
    adminCheck($deleteReferencedDepartment['status'] === 409, 'Referenced department category cannot be deleted');

    $unusedDepartmentName = 'UNUSED-' . $tag;
    $unusedDepartmentResponse = adminRequest(
        adminUrl('categories.php'), 'POST', ['type' => 'Department', 'name' => $unusedDepartmentName],
        adminHeader($csrf), $adminJar
    );
    $unusedDepartmentId = fixtureResponseId($unusedDepartmentResponse);
    if ($unusedDepartmentId > 0) {
        $categoryIds[] = $unusedDepartmentId;
    }
    $unusedSubjectResponse = adminRequest(
        adminUrl('categories.php'), 'POST', ['type' => 'Subject', 'name' => 'UNUSED-SUBJECT-' . $tag, 'department' => $unusedDepartmentName],
        adminHeader($csrf), $adminJar
    );
    $unusedSubjectId = fixtureResponseId($unusedSubjectResponse);
    if ($unusedSubjectId > 0) {
        $categoryIds[] = $unusedSubjectId;
    }
    $deleteUnusedSubject = adminRequest(
        adminUrl('categories.php'), 'DELETE', ['id' => $unusedSubjectId], adminHeader($csrf), $adminJar
    );
    $deleteUnusedDepartment = adminRequest(
        adminUrl('categories.php'), 'DELETE', ['id' => $unusedDepartmentId], adminHeader($csrf), $adminJar
    );
    adminCheck($deleteUnusedSubject['status'] === 200 && $deleteUnusedDepartment['status'] === 200, 'Unreferenced category records can be deleted');

    $wrongMethod = adminRequest(adminUrl('dashboard.php'), 'POST', [], [], $adminJar);
    adminCheck($wrongMethod['status'] === 405, 'Dashboard rejects unsupported HTTP method');
} catch (Throwable $e) {
    $fail++;
    echo '[FAIL] Test runner error: ' . $e->getMessage() . "\n";
} finally {
    if (isset($db) && $db instanceof PDO) {
        try {
            foreach ($purchaseIds as $id) {
                $db->prepare('DELETE FROM purchase_requests WHERE id = ?')->execute([$id]);
            }
            foreach ($listingIds as $id) {
                $db->prepare('DELETE FROM listings WHERE id = ?')->execute([$id]);
            }
            foreach ($categoryIds as $id) {
                $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            }
            foreach ($userIds as $id) {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            }
            echo "Cleanup: removed only fixture records tracked by this test run.\n";
        } catch (Throwable $cleanupError) {
            $fail++;
            echo '[FAIL] Fixture cleanup: ' . $cleanupError->getMessage() . "\n";
        }
    }
    foreach ($jars as $jar) {
        if (is_file($jar)) {
            unlink($jar);
        }
    }
}

echo "Results: {$pass} passed, {$fail} failed.\n";
exit($fail === 0 ? 0 : 1);