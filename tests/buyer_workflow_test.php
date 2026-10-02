<?php
/**
 * BookBridge - Comprehensive Buyer, Messages & Reviews Workflow Test Suite
 * =========================================================================
 * CLI-ONLY execution guard ensures this script cannot be triggered via web.
 *
 * Usage:
 *   C:\xampp\php\php.exe tests\buyer_workflow_test.php
 *
 * Safety & Data Preservation:
 *   - Reuses shared configuration and PDO connection helper (config/db.php).
 *   - Creates ONLY uniquely identifiable disposable fixtures (users, listings,
 *     requests, messages, reviews).
 *   - Strictly tracks every fixture ID created.
 *   - Cleans up ONLY test-owned records in foreign-key safe reverse order in a
 *     guaranteed finally block.
 *   - Ensures any cleanup error directly causes a failed test suite result.
 *   - Genuine simultaneous concurrency testing via curl_multi with separate
 *     independent sessions and distinct CSRF tokens.
 *   - Demo accounts (IDs 1-5) and baseline listings (IDs 1-7) are NEVER touched.
 */

// 1. Strict CLI-only guard
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden\nThis test runner can only be executed via the command line.\n";
    exit(1);
}

// 2. Reuse shared database connection and config
require_once __DIR__ . '/../config/db.php';

echo "====================================================================\n";
echo "BOOKBRIDGE - TANVIR'S BACKEND MODULE TEST SUITE (CLI)\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n";

$baseUrl = 'http://localhost/UIU-Used-Textbook-Marketplace/api';

$pass = 0;
$fail = 0;
$total = 0;
$cookieJars = [];

function check(bool $cond, string $label, string $detail = ''): void {
    global $pass, $fail, $total;
    $total++;
    if ($cond) {
        $pass++;
        echo "   [PASS] $label\n";
    } else {
        $fail++;
        echo "   [FAIL] $label" . ($detail !== '' ? " - $detail" : '') . "\n";
    }
}

function section(string $title): void {
    echo "\n── $title ─────────────────────────────────────────\n";
}

function newJar(): string {
    global $cookieJars;
    $jar = tempnam(sys_get_temp_dir(), 'bb_test_jar_');
    $cookieJars[] = $jar;
    return $jar;
}

/**
 * Extracts the bookbridge_session cookie value from a cURL cookie jar file.
 */
function getSessionIdFromJar(string $jarPath): string {
    if (!file_exists($jarPath)) {
        return '';
    }
    $content = file_get_contents($jarPath);
    if (preg_match('/bookbridge_session\s+([^\r\n\s]+)/', $content, $matches)) {
        return $matches[1];
    }
    return '';
}

function apiRequest(string $url, string $method = 'GET', array $data = [], array $headers = [], string $jar = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $allHeaders = array_merge(
        ['Content-Type: application/json', 'Accept: application/json'],
        $headers
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

    if ($jar !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }

    $upperMethod = strtoupper($method);
    if ($upperMethod === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($upperMethod === 'PUT' || $upperMethod === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($raw, true) ?? ['_raw' => $raw];
    return ['code' => $code, 'body' => $body];
}

/**
 * Executes multiple HTTP requests simultaneously in parallel via cURL multi.
 * Used for genuine simultaneous concurrency testing.
 */
function multiApiRequest(array $requests): array {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($requests as $key => $r) {
        $ch = curl_init($r['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($r['data'] ?? []),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $r['headers'] ?? []),
        ]);
        if (!empty($r['jar'])) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $r['jar']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $r['jar']);
        }
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc === CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc === CURLM_OK) {
        if (curl_multi_select($mh) !== -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        }
    }

    $results = [];
    foreach ($handles as $key => $ch) {
        $raw = curl_multi_getcontent($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $results[$key] = ['code' => $code, 'body' => json_decode($raw, true) ?? ['_raw' => $raw]];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

// Check server connectivity
$health = apiRequest("$baseUrl/health.php");
if ($health['code'] !== 200 || ($health['body']['status'] ?? '') !== 'ok') {
    echo "\n[ABORT] Health check failed at $baseUrl/health.php. Is Apache and MySQL running?\n";
    exit(2);
}
echo "Health check: OK (Database connected)\n";

// Connect to database using shared helper (no hardcoded credentials)
$pdo = getDbConnection();

$tag = substr(md5(microtime(true) . mt_rand()), 0, 8);
$testPassword = 'ValidTestPassword123!';
$testPasswordHash = password_hash($testPassword, PASSWORD_BCRYPT);

// Tracking lists for safe cleanup
$fixtureUserIds = [];
$fixtureListingIds = [];
$fixtureRequestIds = [];
$fixtureMessageIds = [];
$fixtureReviewIds = [];

try {
    section("1. Setting Up Disposable Fixtures");
    // Helper to create disposable test user
    function createTestUser($pdo, $name, $role, $email, $hash, &$fixtureUserIds) {
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, student_id, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$name, $email, $hash, $role, '011' . mt_rand(100000, 999999), '017' . mt_rand(10000000, 99999999)]);
        $id = (int) $pdo->lastInsertId();
        $fixtureUserIds[] = $id;
        return $id;
    }

    // Helper to create disposable test listing
    function createTestListing($pdo, $sellerId, $title, $price, $status, &$fixtureListingIds) {
        $stmt = $pdo->prepare("
            INSERT INTO listings (seller_id, category_id, title, author, edition, course_code, department, subject, item_type, condition_type, price, description, status, created_at)
            VALUES (?, 1, ?, 'Test Author', '1st Edition', 'CSE-1101', 'CSE', 'Data Structures', 'Textbook', 'Good', ?, 'Test description', ?, NOW())
        ");
        $stmt->execute([$sellerId, $title, $price, $status]);
        $id = (int) $pdo->lastInsertId();
        $fixtureListingIds[] = $id;
        return $id;
    }

    $b1Email = "test_b1_{$tag}@uiu.ac.bd";
    $b2Email = "test_b2_{$tag}@uiu.ac.bd";
    $s1Email = "test_s1_{$tag}@uiu.ac.bd";
    $s2Email = "test_s2_{$tag}@uiu.ac.bd";

    $buyer1Id  = createTestUser($pdo, "Buyer One $tag", 'buyer', $b1Email, $testPasswordHash, $fixtureUserIds);
    $buyer2Id  = createTestUser($pdo, "Buyer Two $tag", 'buyer', $b2Email, $testPasswordHash, $fixtureUserIds);
    $seller1Id = createTestUser($pdo, "Seller One $tag", 'seller', $s1Email, $testPasswordHash, $fixtureUserIds);
    $seller2Id = createTestUser($pdo, "Seller Two $tag", 'seller', $s2Email, $testPasswordHash, $fixtureUserIds);

    // Create listings
    $listingAvail1 = createTestListing($pdo, $seller1Id, "Algorithms Book $tag", 400.00, 'available', $fixtureListingIds);
    $listingAvail2 = createTestListing($pdo, $seller1Id, "Database Systems $tag", 350.00, 'available', $fixtureListingIds);
    $listingSold   = createTestListing($pdo, $seller1Id, "Sold Book $tag", 200.00, 'sold', $fixtureListingIds);
    $listingUnapproved = createTestListing($pdo, $seller1Id, "Unapproved Book $tag", 300.00, 'pending_approval', $fixtureListingIds);

    echo "   Created 4 test users (Buyers: $buyer1Id, $buyer2Id | Sellers: $seller1Id, $seller2Id)\n";
    echo "   Created test listings (Available: $listingAvail1, $listingAvail2 | Sold: $listingSold | Unapproved: $listingUnapproved)\n";

    // Establish HTTP sessions via real login endpoint
    $jarB1 = newJar();
    $rLoginB1 = apiRequest("$baseUrl/auth/login.php", 'POST', ['email' => $b1Email, 'password' => $testPassword], [], $jarB1);
    check($rLoginB1['code'] === 200, "Buyer 1 login HTTP 200");
    $csrfB1 = $rLoginB1['body']['data']['csrf_token'] ?? '';

    $jarB2 = newJar();
    $rLoginB2 = apiRequest("$baseUrl/auth/login.php", 'POST', ['email' => $b2Email, 'password' => $testPassword], [], $jarB2);
    check($rLoginB2['code'] === 200, "Buyer 2 login HTTP 200");
    $csrfB2 = $rLoginB2['body']['data']['csrf_token'] ?? '';

    // Seller 1 Session A
    $jarS1_A = newJar();
    $rLoginS1_A = apiRequest("$baseUrl/auth/login.php", 'POST', ['email' => $s1Email, 'password' => $testPassword], [], $jarS1_A);
    check($rLoginS1_A['code'] === 200, "Seller 1 (Session A) login HTTP 200");
    $csrfS1_A = $rLoginS1_A['body']['data']['csrf_token'] ?? '';

    // Seller 1 Session B (independent concurrent session)
    $jarS1_B = newJar();
    $rLoginS1_B = apiRequest("$baseUrl/auth/login.php", 'POST', ['email' => $s1Email, 'password' => $testPassword], [], $jarS1_B);
    check($rLoginS1_B['code'] === 200, "Seller 1 (Session B) login HTTP 200");
    $csrfS1_B = $rLoginS1_B['body']['data']['csrf_token'] ?? '';

    // Verify session IDs differ
    $sessS1_A = getSessionIdFromJar($jarS1_A);
    $sessS1_B = getSessionIdFromJar($jarS1_B);
    check(!empty($sessS1_A) && !empty($sessS1_B) && $sessS1_A !== $sessS1_B, "Seller 1 independent sessions have distinct session IDs");
    check(!empty($csrfS1_A) && !empty($csrfS1_B), "Both Seller 1 sessions issued valid CSRF tokens");

    // Default seller jar for sequential checks
    $jarS1 = $jarS1_A;
    $csrfS1 = $csrfS1_A;

    $jarS2 = newJar();
    $rLoginS2 = apiRequest("$baseUrl/auth/login.php", 'POST', ['email' => $s2Email, 'password' => $testPassword], [], $jarS2);
    check($rLoginS2['code'] === 200, "Seller 2 login HTTP 200");
    $csrfS2 = $rLoginS2['body']['data']['csrf_token'] ?? '';

    // ═══════════════════════════════════════════════════════════════════════
    // Section 2: Buyer Profile Endpoints
    // ═══════════════════════════════════════════════════════════════════════
    section("2. Buyer Profile Endpoints (/api/buyer/profile.php)");

    // 2a. Unauthenticated access blocked
    $r = apiRequest("$baseUrl/buyer/profile.php", 'GET');
    check($r['code'] === 401, "Unauthenticated GET profile returns 401");

    // 2b. Seller access blocked (wrong role)
    $r = apiRequest("$baseUrl/buyer/profile.php", 'GET', [], [], $jarS1);
    check($r['code'] === 403, "Seller accessing buyer profile returns 403");

    // 2c. Buyer GET profile
    $r = apiRequest("$baseUrl/buyer/profile.php", 'GET', [], [], $jarB1);
    check($r['code'] === 200, "Buyer GET profile returns 200");
    check(($r['body']['data']['user']['email'] ?? '') === $b1Email, "Buyer profile returns correct user");

    // 2d. Missing CSRF on profile update blocked
    $r = apiRequest("$baseUrl/buyer/profile.php", 'POST', ['full_name' => 'Updated Buyer Name'], [], $jarB1);
    check($r['code'] === 403, "Profile update without CSRF returns 403");

    // 2e. Valid profile update
    $r = apiRequest("$baseUrl/buyer/profile.php", 'POST', [
        'full_name'  => 'Buyer One Updated',
        'phone'      => '01899998888',
        'student_id' => '011223344',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 200, "Buyer profile update with CSRF returns 200");
    check(($r['body']['data']['user']['full_name'] ?? '') === 'Buyer One Updated', "Full name updated successfully");
    check(($r['body']['data']['user']['phone'] ?? '') === '01899998888', "Phone updated successfully");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 3: Buyer Dashboard Endpoints
    // ═══════════════════════════════════════════════════════════════════════
    section("3. Buyer Dashboard Endpoints (/api/buyer/dashboard.php)");

    $r = apiRequest("$baseUrl/buyer/dashboard.php", 'GET');
    check($r['code'] === 401, "Unauthenticated dashboard returns 401");

    $r = apiRequest("$baseUrl/buyer/dashboard.php", 'GET', [], [], $jarS1);
    check($r['code'] === 403, "Seller role on buyer dashboard returns 403");

    $r = apiRequest("$baseUrl/buyer/dashboard.php", 'GET', [], [], $jarB1);
    check($r['code'] === 200, "Buyer GET dashboard returns 200");
    check(isset($r['body']['data']['stats']['wishlist_count']), "Dashboard contains stats.wishlist_count");
    check(isset($r['body']['data']['stats']['active_requests']), "Dashboard contains stats.active_requests");
    check(isset($r['body']['data']['recommended_listings']), "Dashboard contains recommended_listings");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 4: Wishlist Endpoints
    // ═══════════════════════════════════════════════════════════════════════
    section("4. Wishlist Endpoints (/api/buyer/wishlist.php)");

    // 4a. Add to wishlist
    $r = apiRequest("$baseUrl/buyer/wishlist.php", 'POST', [
        'listing_id' => $listingAvail1,
        'action'     => 'add'
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 200, "Add to wishlist returns 200");
    check(($r['body']['data']['is_wishlisted'] ?? false) === true, "is_wishlisted is true");

    // 4b. Duplicate add handling
    $r = apiRequest("$baseUrl/buyer/wishlist.php", 'POST', [
        'listing_id' => $listingAvail1,
        'action'     => 'add'
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 200, "Duplicate add handled idempotently");

    // 4c. GET wishlist
    $r = apiRequest("$baseUrl/buyer/wishlist.php", 'GET', [], [], $jarB1);
    check($r['code'] === 200, "GET wishlist returns 200");
    check(($r['body']['data']['total'] ?? 0) >= 1, "Wishlist total >= 1");

    // 4d. Remove from wishlist
    $r = apiRequest("$baseUrl/buyer/wishlist.php", 'POST', [
        'listing_id' => $listingAvail1,
        'action'     => 'remove'
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 200, "Remove from wishlist returns 200");
    check(($r['body']['data']['is_wishlisted'] ?? true) === false, "is_wishlisted is false after removal");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 5: Purchase Request Creation & Invariants
    // ═══════════════════════════════════════════════════════════════════════
    section("5. Purchase Request Creation & Invariants (/api/buyer/purchase-request.php)");

    // 5a. Unauthenticated create
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', ['listing_id' => $listingAvail1]);
    check($r['code'] === 401, "Unauthenticated purchase request returns 401");

    // 5b. Seller cannot submit buyer purchase request
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', ['listing_id' => $listingAvail1], ["X-CSRF-Token: $csrfS1"], $jarS1);
    check($r['code'] === 403, "Seller role cannot submit purchase request (403)");

    // 5c. Missing CSRF token
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => date('Y-m-d', strtotime('+2 days')),
    ], [], $jarB1);
    check($r['code'] === 403, "Missing CSRF on purchase request returns 403");

    // 5d. Validation error: date in past
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => '2020-01-01',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 422, "Past preferred_date rejected with 422");

    // 5e. Validation error: non Cash on Meet payment rejected
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => date('Y-m-d', strtotime('+2 days')),
        'payment_method'   => 'bkash',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 422, "bKash / non Cash on Meet payment rejected with 422");

    // 5f. Sold listing request rejected
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingSold,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => date('Y-m-d', strtotime('+2 days')),
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 422, "Request for sold listing rejected with 422");

    // 5g. Unapproved listing request rejected
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingUnapproved,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => date('Y-m-d', strtotime('+2 days')),
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 422, "Request for unapproved listing rejected with 422");

    // 5h. Valid purchase request submission
    $r = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Library 3rd Floor',
        'preferred_date'   => date('Y-m-d', strtotime('+2 days')),
        'note'             => 'Will inspect book before handing over cash.',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 201, "Valid purchase request returns 201 Created");
    $req1Id = (int) ($r['body']['data']['request']['id'] ?? 0);
    $fixtureRequestIds[] = $req1Id;
    check($req1Id > 0, "Received new purchase request ID ($req1Id)");
    check(($r['body']['data']['request']['status'] ?? '') === 'pending', "Request status is pending");

    // 5i. Invariant: Duplicate/Conflicting request by same buyer for same listing rejected
    $rDup = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Main Gate',
        'preferred_date'   => date('Y-m-d', strtotime('+3 days')),
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($rDup['code'] === 422, "Duplicate active request by same buyer rejected with 422");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 6: Buyer Request List & Detail & Participant Isolation
    // ═══════════════════════════════════════════════════════════════════════
    section("6. Buyer Request List & Participant Isolation");

    // 6a. Buyer lists own requests
    $r = apiRequest("$baseUrl/buyer/my-requests.php", 'GET', [], [], $jarB1);
    check($r['code'] === 200, "Buyer GET my-requests returns 200");
    check(($r['body']['data']['total'] ?? 0) >= 1, "my-requests total >= 1");

    // 6b. Participant isolation on detail: Buyer 2 (unrelated) blocked from Buyer 1's request
    $r = apiRequest("$baseUrl/buyer/request-detail.php?id=$req1Id", 'GET', [], [], $jarB2);
    check($r['code'] === 403, "Unrelated buyer viewing request detail returns 403");

    // 6c. Participant isolation on detail: Seller 2 (unrelated) blocked from request
    $r = apiRequest("$baseUrl/buyer/request-detail.php?id=$req1Id", 'GET', [], [], $jarS2);
    check($r['code'] === 403, "Unrelated seller viewing request detail returns 403");

    // 6d. Authorized participants (Buyer 1 & Seller 1) can view request detail
    $rDetailB1 = apiRequest("$baseUrl/buyer/request-detail.php?id=$req1Id", 'GET', [], [], $jarB1);
    check($rDetailB1['code'] === 200, "Buyer 1 can view request detail (200)");
    $rDetailS1 = apiRequest("$baseUrl/buyer/request-detail.php?id=$req1Id", 'GET', [], [], $jarS1);
    check($rDetailS1['code'] === 200, "Seller 1 can view request detail (200)");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 7: Buyer Cancellation
    // ═══════════════════════════════════════════════════════════════════════
    section("7. Buyer Request Cancellation (/api/buyer/cancel-request.php)");

    // 7a. Non-owner cannot cancel
    $r = apiRequest("$baseUrl/buyer/cancel-request.php", 'POST', ['request_id' => $req1Id], ["X-CSRF-Token: $csrfB2"], $jarB2);
    check($r['code'] === 403, "Buyer 2 cannot cancel Buyer 1's request (403)");

    // 7b. Buyer 1 cancels own pending request
    $r = apiRequest("$baseUrl/buyer/cancel-request.php", 'POST', ['request_id' => $req1Id], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 200, "Buyer 1 cancels own request successfully (200)");
    check(($r['body']['data']['status'] ?? '') === 'cancelled', "Request status changed to cancelled");

    // 7c. Cannot re-cancel an already cancelled request
    $r = apiRequest("$baseUrl/buyer/cancel-request.php", 'POST', ['request_id' => $req1Id], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 422, "Cannot cancel already cancelled request (422)");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 8: Seller Actions, Concurrency & Invariants
    // ═══════════════════════════════════════════════════════════════════════
    section("8. Seller Actions, State Transitions & Invariants");

    // Create a new request on listingAvail1 by Buyer 1
    $rNew1 = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Cafeteria',
        'preferred_date'   => date('Y-m-d', strtotime('+3 days')),
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    $req2Id = (int) ($rNew1['body']['data']['request']['id'] ?? 0);
    $fixtureRequestIds[] = $req2Id;
    check($req2Id > 0, "Created fresh request #$req2Id on listing $listingAvail1");

    // Create a second request on the same listing by Buyer 2
    $rNew2 = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail1,
        'meeting_location' => 'UIU Main Gate',
        'preferred_date'   => date('Y-m-d', strtotime('+3 days')),
    ], ["X-CSRF-Token: $csrfB2"], $jarB2);
    $req3Id = (int) ($rNew2['body']['data']['request']['id'] ?? 0);
    $fixtureRequestIds[] = $req3Id;
    check($req3Id > 0, "Created competing request #$req3Id on same listing $listingAvail1");

    // 8a. Seller 1 views received requests
    $r = apiRequest("$baseUrl/buyer/seller-requests.php", 'GET', [], [], $jarS1);
    check($r['code'] === 200, "Seller 1 views seller-requests (200)");
    check(($r['body']['data']['total'] ?? 0) >= 2, "Seller sees at least 2 requests");

    // 8b. Seller 2 (unrelated) cannot act on Seller 1's request
    $r = apiRequest("$baseUrl/buyer/seller-request-action.php", 'POST', [
        'request_id' => $req2Id,
        'action'     => 'accept'
    ], ["X-CSRF-Token: $csrfS2"], $jarS2);
    check($r['code'] === 403, "Unrelated seller acting on request returns 403");

    // 8c. Seller 1 accepts request #$req2Id
    $r = apiRequest("$baseUrl/buyer/seller-request-action.php", 'POST', [
        'request_id' => $req2Id,
        'action'     => 'accept'
    ], ["X-CSRF-Token: $csrfS1"], $jarS1);
    check($r['code'] === 200, "Seller 1 accepts request #$req2Id (200)");
    check(($r['body']['data']['status'] ?? '') === 'accepted', "Status changed to accepted");

    // 8d. Invariant check: Seller cannot accept a second request for the same listing while one is accepted
    $rConf = apiRequest("$baseUrl/buyer/seller-request-action.php", 'POST', [
        'request_id' => $req3Id,
        'action'     => 'accept'
    ], ["X-CSRF-Token: $csrfS1"], $jarS1);
    check($rConf['code'] === 422, "Accept of competing request #$req3Id blocked with 422");

    // 8e. Seller 1 completes transaction for accepted request #$req2Id
    $rComp = apiRequest("$baseUrl/buyer/seller-request-action.php", 'POST', [
        'request_id' => $req2Id,
        'action'     => 'complete'
    ], ["X-CSRF-Token: $csrfS1"], $jarS1);
    check($rComp['code'] === 200, "Seller 1 completes transaction (200)");
    check(($rComp['body']['data']['status'] ?? '') === 'completed', "Request #$req2Id status is completed");
    check(($rComp['body']['data']['listing_status'] ?? '') === 'sold', "Listing status changed to sold");

    // 8f. Invariant: Competing request #$req3Id was automatically declined because listing is sold
    $stmtCheck = $pdo->prepare('SELECT status FROM purchase_requests WHERE id = ?');
    $stmtCheck->execute([$req3Id]);
    $req3Status = $stmtCheck->fetchColumn();
    check($req3Status === 'declined', "Competing request #$req3Id automatically marked declined upon listing sale");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 8-B: Genuine Simultaneous Concurrency Testing (cURL Multi)
    // ═══════════════════════════════════════════════════════════════════════
    section("8-B. Genuine Simultaneous Concurrency Testing (cURL Multi with Independent Sessions)");

    // Setup dedicated listing for simultaneous acceptance test
    $listingSim = createTestListing($pdo, $seller1Id, "Simultaneous Race Book $tag", 450.00, 'available', $fixtureListingIds);

    // Buyer 1 and Buyer 2 both create pending requests
    $rSimReq1 = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingSim,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => date('Y-m-d', strtotime('+3 days')),
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    $simReq1Id = (int) ($rSimReq1['body']['data']['request']['id'] ?? 0);
    $fixtureRequestIds[] = $simReq1Id;

    $rSimReq2 = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingSim,
        'meeting_location' => 'UIU Cafeteria',
        'preferred_date'   => date('Y-m-d', strtotime('+3 days')),
    ], ["X-CSRF-Token: $csrfB2"], $jarB2);
    $simReq2Id = (int) ($rSimReq2['body']['data']['request']['id'] ?? 0);
    $fixtureRequestIds[] = $simReq2Id;

    check($simReq1Id > 0 && $simReq2Id > 0, "Created 2 pending requests on race listing $listingSim");

    // Fire two simultaneous 'accept' requests at the EXACT same instant using multiApiRequest
    // Using two independent sessions ($jarS1_A and $jarS1_B) with their respective CSRF tokens
    $simAcceptResults = multiApiRequest([
        'callA' => [
            'url'     => "$baseUrl/buyer/seller-request-action.php",
            'data'    => ['request_id' => $simReq1Id, 'action' => 'accept'],
            'headers' => ["X-CSRF-Token: $csrfS1_A"],
            'jar'     => $jarS1_A,
        ],
        'callB' => [
            'url'     => "$baseUrl/buyer/seller-request-action.php",
            'data'    => ['request_id' => $simReq2Id, 'action' => 'accept'],
            'headers' => ["X-CSRF-Token: $csrfS1_B"],
            'jar'     => $jarS1_B,
        ],
    ]);

    $codes = [$simAcceptResults['callA']['code'], $simAcceptResults['callB']['code']];
    sort($codes);
    check($codes === [200, 422], "Simultaneous race (independent sessions): exactly one 200 OK and one 422 Conflict", "Got: " . json_encode($codes));

    // Verify database state: exactly ONE accepted request in DB
    $stmtSimCount = $pdo->prepare("SELECT COUNT(*) FROM purchase_requests WHERE listing_id = ? AND status = 'accepted'");
    $stmtSimCount->execute([$listingSim]);
    $simAcceptedCount = (int) $stmtSimCount->fetchColumn();
    check($simAcceptedCount === 1, "Database invariant holds: exactly 1 accepted request under simultaneous race");

    // Test simultaneous completion: fire two simultaneous complete requests on the accepted request
    // using independent sessions ($jarS1_A and $jarS1_B) with their respective CSRF tokens
    $acceptedReqId = ($simAcceptResults['callA']['code'] === 200) ? $simReq1Id : $simReq2Id;
    $simCompleteResults = multiApiRequest([
        'compA' => [
            'url'     => "$baseUrl/buyer/seller-request-action.php",
            'data'    => ['request_id' => $acceptedReqId, 'action' => 'complete'],
            'headers' => ["X-CSRF-Token: $csrfS1_A"],
            'jar'     => $jarS1_A,
        ],
        'compB' => [
            'url'     => "$baseUrl/buyer/seller-request-action.php",
            'data'    => ['request_id' => $acceptedReqId, 'action' => 'complete'],
            'headers' => ["X-CSRF-Token: $csrfS1_B"],
            'jar'     => $jarS1_B,
        ],
    ]);

    $compCodes = [$simCompleteResults['compA']['code'], $simCompleteResults['compB']['code']];
    sort($compCodes);
    check($compCodes === [200, 422], "Simultaneous completion (independent sessions): exactly one 200 and one 422 (cannot double-complete)", "Got: " . json_encode($compCodes));

    // Verify listing is now sold and request is completed
    $stmtSoldCheck = $pdo->prepare("SELECT status FROM listings WHERE id = ?");
    $stmtSoldCheck->execute([$listingSim]);
    check($stmtSoldCheck->fetchColumn() === 'sold', "Race listing is marked 'sold'");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 9: Campus Messaging & Isolation (/api/messages/)
    // ═══════════════════════════════════════════════════════════════════════
    section("9. Campus Messaging & Isolation (/api/messages/)");

    // 9a. Unauthenticated messaging
    $r = apiRequest("$baseUrl/messages/conversations.php", 'GET');
    check($r['code'] === 401, "Unauthenticated conversations returns 401");

    // 9b. Send message to self blocked
    $r = apiRequest("$baseUrl/messages/send.php", 'POST', [
        'receiver_id'  => $buyer1Id,
        'message_text' => 'Talking to myself',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 422, "Sending message to self blocked with 422");

    // 9c. Send valid message from Buyer 1 to Seller 1
    $r = apiRequest("$baseUrl/messages/send.php", 'POST', [
        'receiver_id'  => $seller1Id,
        'listing_id'   => $listingAvail1,
        'message_text' => 'Hello! Can we meet at the UIU Library?',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($r['code'] === 201, "Send message returns 201 Created");
    $msg1Id = (int) ($r['body']['data']['message']['id'] ?? 0);
    $fixtureMessageIds[] = $msg1Id;

    // 9d. Seller 1 replies to Buyer 1
    $r = apiRequest("$baseUrl/messages/send.php", 'POST', [
        'receiver_id'  => $buyer1Id,
        'listing_id'   => $listingAvail1,
        'message_text' => 'Sure, 2:00 PM tomorrow works for me.',
    ], ["X-CSRF-Token: $csrfS1"], $jarS1);
    check($r['code'] === 201, "Seller 1 reply returns 201 Created");
    $msg2Id = (int) ($r['body']['data']['message']['id'] ?? 0);
    $fixtureMessageIds[] = $msg2Id;

    // 9e. Buyer 1 views thread with Seller 1
    $rThread = apiRequest("$baseUrl/messages/thread.php?with_user_id=$seller1Id", 'GET', [], [], $jarB1);
    check($rThread['code'] === 200, "Buyer 1 views thread with Seller 1 (200)");
    check(($rThread['body']['data']['total'] ?? 0) === 2, "Thread contains 2 messages");

    // 9f. Participant isolation: Buyer 2 viewing thread with Seller 1 sees 0 messages
    $rIso = apiRequest("$baseUrl/messages/thread.php?with_user_id=$seller1Id", 'GET', [], [], $jarB2);
    check($rIso['code'] === 200, "Buyer 2 thread query returns 200");
    check(($rIso['body']['data']['total'] ?? 0) === 0, "Buyer 2 isolated: cannot see Buyer 1's messages");

    // ═══════════════════════════════════════════════════════════════════════
    // Section 10: Reviews & Rating Aggregation (/api/reviews/)
    // ═══════════════════════════════════════════════════════════════════════
    section("10. Reviews & Rating Aggregation (/api/reviews/)");

    // 10a. Review rejected before purchase completion
    // Create an uncompleted request to test
    $rPending = apiRequest("$baseUrl/buyer/purchase-request.php", 'POST', [
        'listing_id'       => $listingAvail2,
        'meeting_location' => 'UIU Library',
        'preferred_date'   => date('Y-m-d', strtotime('+3 days')),
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    $pendingReqId = (int) ($rPending['body']['data']['request']['id'] ?? 0);
    $fixtureRequestIds[] = $pendingReqId;

    $rRevPre = apiRequest("$baseUrl/reviews/create.php", 'POST', [
        'purchase_request_id' => $pendingReqId,
        'rating'              => 5,
        'comment'             => 'Premature review attempt',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($rRevPre['code'] === 422, "Review before completion rejected with 422");

    // 10b. Non-buyer cannot review
    $rWrongReviewer = apiRequest("$baseUrl/reviews/create.php", 'POST', [
        'purchase_request_id' => $req2Id, // belonged to Buyer 1
        'rating'              => 5,
    ], ["X-CSRF-Token: $csrfB2"], $jarB2);
    check($rWrongReviewer['code'] === 403, "Unrelated buyer cannot review someone else's purchase (403)");

    // 10c. Rating bounds (< 1 or > 5) rejected
    $rBadRating = apiRequest("$baseUrl/reviews/create.php", 'POST', [
        'purchase_request_id' => $req2Id,
        'rating'              => 6,
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($rBadRating['code'] === 422, "Rating 6 rejected with 422");

    // 10d. Valid review submission for completed purchase #$req2Id
    $rRevOk = apiRequest("$baseUrl/reviews/create.php", 'POST', [
        'purchase_request_id' => $req2Id,
        'rating'              => 5,
        'comment'             => 'Great condition textbook, punctual meetup on campus!',
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($rRevOk['code'] === 201, "Review submitted successfully (201 Created)");
    $review1Id = (int) ($rRevOk['body']['data']['review']['id'] ?? 0);
    $fixtureReviewIds[] = $review1Id;

    // 10e. Duplicate review for same purchase request rejected
    $rDupRev = apiRequest("$baseUrl/reviews/create.php", 'POST', [
        'purchase_request_id' => $req2Id,
        'rating'              => 4,
    ], ["X-CSRF-Token: $csrfB1"], $jarB1);
    check($rDupRev['code'] === 422, "Duplicate review for same purchase request rejected with 422");

    // 10f. Rating aggregation on seller profile
    $rSellerRev = apiRequest("$baseUrl/reviews/seller-reviews.php?seller_id=$seller1Id", 'GET');
    check($rSellerRev['code'] === 200, "GET seller-reviews returns 200");
    check(($rSellerRev['body']['data']['seller']['total_reviews'] ?? 0) === 1, "Seller total_reviews equals 1");
    check(($rSellerRev['body']['data']['seller']['average_rating'] ?? 0) == 5.0, "Seller average_rating equals 5.0");

    // 10g. Buyer my-reviews list
    $rMyRev = apiRequest("$baseUrl/reviews/my-reviews.php", 'GET', [], [], $jarB1);
    check($rMyRev['code'] === 200, "Buyer GET my-reviews returns 200");
    check(($rMyRev['body']['data']['total'] ?? 0) >= 1, "Buyer my-reviews total >= 1");

} finally {
    // ═══════════════════════════════════════════════════════════════════════
    // Guaranteed Cleanup — Disposable Fixtures Only
    // ═══════════════════════════════════════════════════════════════════════
    section("11. Guaranteed Cleanup of Disposable Fixtures");

    try {
        // 1. Reviews
        if (!empty($fixtureReviewIds)) {
            $in = implode(',', array_map('intval', $fixtureReviewIds));
            $c = $pdo->exec("DELETE FROM reviews WHERE id IN ($in)");
            echo "   Deleted $c test review(s)\n";
        }

        // 2. Messages
        if (!empty($fixtureMessageIds)) {
            $in = implode(',', array_map('intval', $fixtureMessageIds));
            $c = $pdo->exec("DELETE FROM messages WHERE id IN ($in)");
            echo "   Deleted $c test message(s)\n";
        }
        // Also cleanup any messages referencing fixture users
        if (!empty($fixtureUserIds)) {
            $inUsers = implode(',', array_map('intval', $fixtureUserIds));
            $pdo->exec("DELETE FROM messages WHERE sender_id IN ($inUsers) OR receiver_id IN ($inUsers)");
        }

        // 3. Purchase requests
        if (!empty($fixtureRequestIds)) {
            $in = implode(',', array_map('intval', $fixtureRequestIds));
            $c = $pdo->exec("DELETE FROM purchase_requests WHERE id IN ($in)");
            echo "   Deleted $c test purchase request(s)\n";
        }
        if (!empty($fixtureUserIds)) {
            $inUsers = implode(',', array_map('intval', $fixtureUserIds));
            $pdo->exec("DELETE FROM purchase_requests WHERE buyer_id IN ($inUsers) OR seller_id IN ($inUsers)");
        }

        // 4. Wishlists
        if (!empty($fixtureUserIds)) {
            $inUsers = implode(',', array_map('intval', $fixtureUserIds));
            $c = $pdo->exec("DELETE FROM wishlists WHERE user_id IN ($inUsers)");
            echo "   Deleted $c test wishlist item(s)\n";
        }

        // 5. Listings
        if (!empty($fixtureListingIds)) {
            $in = implode(',', array_map('intval', $fixtureListingIds));
            $c = $pdo->exec("DELETE FROM listings WHERE id IN ($in)");
            echo "   Deleted $c test listing(s)\n";
        }

        // 6. Users
        if (!empty($fixtureUserIds)) {
            $in = implode(',', array_map('intval', $fixtureUserIds));
            $c = $pdo->exec("DELETE FROM users WHERE id IN ($in)");
            echo "   Deleted $c test user(s)\n";
        }

        // Verify baseline demo users (1-5) and listings (1-7) are intact
        $demoUserCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id IN (1, 2, 3, 4, 5)")->fetchColumn();
        $demoListingCount = (int) $pdo->query("SELECT COUNT(*) FROM listings WHERE id IN (1, 2, 3, 4, 5, 6, 7)")->fetchColumn();

        check($demoUserCount === 5, "Baseline demo users (IDs 1-5) preserved ($demoUserCount/5)");
        check($demoListingCount === 7, "Baseline listings (IDs 1-7) preserved ($demoListingCount/7)");

        // Verify all test users were deleted
        $remUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%{$tag}%'")->fetchColumn();
        check($remUsers === 0, "All disposable test users cleaned up ($remUsers remaining)");

    } catch (Throwable $e) {
        echo "   [ERROR during cleanup]: " . $e->getMessage() . "\n";
        check(false, "Cleanup completed without errors", $e->getMessage());
    }

    // Clean up temporary cookie jars
    foreach ($cookieJars as $jar) {
        if (file_exists($jar)) {
            @unlink($jar);
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Final Results
// ═══════════════════════════════════════════════════════════════════════
echo "\n====================================================================\n";
echo "SUMMARY : $pass PASSED / $fail FAILED / $total TOTAL\n";
echo ($fail === 0)
    ? "STATUS  : ALL BUYER / MESSAGES / REVIEWS TESTS PASSED!\n"
    : "STATUS  : $fail TEST(S) FAILED - See details above.\n";
echo "====================================================================\n";

exit($fail > 0 ? 1 : 0);
