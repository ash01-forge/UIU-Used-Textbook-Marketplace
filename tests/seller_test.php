<?php
/**
 * BookBridge Seller API Integration Test Suite
 * CLI Only:
 *   C:\xampp\php\php.exe tests\seller_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "403 Forbidden\nThis test runner is CLI-only.\n";
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$baseUrl = 'http://localhost/UIU-Used-Textbook-Marketplace/api';
$pass = 0;
$fail = 0;

$jars = [];
$userIds = [];
$listingIds = [];
$purchaseIds = [];
$uploadedFiles = [];

$db = getDbConnection();
$tag = strtoupper(bin2hex(random_bytes(4)));
$password = 'TestFixturePass_' . bin2hex(random_bytes(6));

$seller1Email = 'seller1_test_' . strtolower($tag) . '@uiu.ac.bd';
$seller2Email = 'seller2_test_' . strtolower($tag) . '@uiu.ac.bd';
$buyerEmail   = 'buyer_test_' . strtolower($tag) . '@uiu.ac.bd';
$adminEmail   = 'admin_test_' . strtolower($tag) . '@uiu.ac.bd';

function check(bool $condition, string $label, string $details = ''): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$label}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$label}" . ($details !== '' ? " — {$details}" : '') . "\n";
    }
}

function apiRequest(string $url, string $method = 'GET', $body = null, array $headers = [], string $jar = ''): array
{
    $curl = curl_init($url);
    $reqHeaders = array_merge(['Accept: application/json'], $headers);

    $isMultipart = false;
    if (is_array($body)) {
        foreach ($body as $v) {
            if ($v instanceof CURLFile) {
                $isMultipart = true;
                break;
            }
        }
    }

    if ($isMultipart) {
        $postData = $body;
    } elseif (is_array($body) && !isset($headers['Content-Type'])) {
        $reqHeaders[] = 'Content-Type: application/json';
        $postData = json_encode($body);
    } else {
        $postData = $body;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $reqHeaders,
    ]);

    if ($postData !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    }

    if ($jar !== '') {
        curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
    }

    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    return [
        'status' => $status,
        'json'   => json_decode($raw === false ? '' : $raw, true) ?? [],
        'raw'    => $raw,
        'curl_error' => $err,
    ];
}

function loginUser(string $email, string $jar): array
{
    global $baseUrl, $password;
    return apiRequest($baseUrl . '/auth/login.php', 'POST', [
        'email'    => $email,
        'password' => $password,
    ], [], $jar);
}

function createFixtureUser(string $name, string $email, string $role, ?string $dept = 'CSE'): int
{
    global $db, $password, $userIds;
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, role, student_id, phone, department, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([$name, $email, $hash, $role, '011' . rand(100000, 999999), '01700000000', $dept]);
    $id = (int) $db->lastInsertId();
    $userIds[] = $id;
    return $id;
}

echo "====================================================================\n";
echo "BOOKBRIDGE SELLER MODULE TEST SUITE (CLI)\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Run Tag  : {$tag}\n";
echo "====================================================================\n\n";

try {
    // 1. Create fixture users
    $seller1Id = createFixtureUser("Seller One {$tag}", $seller1Email, 'seller');
    $seller2Id = createFixtureUser("Seller Two {$tag}", $seller2Email, 'seller');
    $buyerId   = createFixtureUser("Buyer One {$tag}", $buyerEmail, 'buyer');
    $adminId   = createFixtureUser("Admin One {$tag}", $adminEmail, 'admin');

    $seller1Jar = tempnam(sys_get_temp_dir(), 'bb_sel1_'); $jars[] = $seller1Jar;
    $seller2Jar = tempnam(sys_get_temp_dir(), 'bb_sel2_'); $jars[] = $seller2Jar;
    $buyerJar   = tempnam(sys_get_temp_dir(), 'bb_buy_');  $jars[] = $buyerJar;
    $adminJar   = tempnam(sys_get_temp_dir(), 'bb_adm_');  $jars[] = $adminJar;

    $loginSel1 = loginUser($seller1Email, $seller1Jar);
    $csrfSel1  = $loginSel1['json']['data']['csrf_token'] ?? '';
    check($loginSel1['status'] === 200 && !empty($csrfSel1), 'Seller 1 login succeeds and yields CSRF token');

    $loginSel2 = loginUser($seller2Email, $seller2Jar);
    $csrfSel2  = $loginSel2['json']['data']['csrf_token'] ?? '';
    check($loginSel2['status'] === 200 && !empty($csrfSel2), 'Seller 2 login succeeds and yields CSRF token');

    $loginBuyer = loginUser($buyerEmail, $buyerJar);
    $csrfBuyer  = $loginBuyer['json']['data']['csrf_token'] ?? '';
    check($loginBuyer['status'] === 200, 'Buyer login succeeds');
    $loginAdmin = loginUser($adminEmail, $adminJar);
    $csrfAdmin = $loginAdmin['json']['data']['csrf_token'] ?? '';
    check($loginAdmin['status'] === 200 && !empty($csrfAdmin), 'Admin fixture login succeeds');

    // -----------------------------------------------------------------
    // Section 1: Authentication & Role Guards
    // -----------------------------------------------------------------
    echo "\n-- Section 1: Authentication & Role Guards --\n";
    $guestDash = apiRequest("{$baseUrl}/seller/dashboard.php", 'GET');
    check($guestDash['status'] === 401, 'Guest blocked from seller dashboard (401)');

    $buyerDash = apiRequest("{$baseUrl}/seller/dashboard.php", 'GET', null, [], $buyerJar);
    check($buyerDash['status'] === 403, 'Buyer blocked from seller dashboard (403)');

    $guestAdd = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', ['title' => 'Test']);
    check($guestAdd['status'] === 401, 'Guest blocked from add-listing (401)');

    $buyerAdd = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', ['title' => 'Test'], ['X-CSRF-Token: ' . $csrfBuyer], $buyerJar);
    check($buyerAdd['status'] === 403, 'Buyer blocked from add-listing (403)');

    // -----------------------------------------------------------------
    // Section 2: CSRF Validation
    // -----------------------------------------------------------------
    echo "\n-- Section 2: CSRF Validation --\n";
    $noCsrfAdd = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', ['title' => 'No CSRF'], [], $seller1Jar);
    check($noCsrfAdd['status'] === 403, 'State-modifying POST rejects missing CSRF (403)');

    $badCsrfAdd = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', ['title' => 'Bad CSRF'], ['X-CSRF-Token: invalid_token_123'], $seller1Jar);
    check($badCsrfAdd['status'] === 403, 'State-modifying POST rejects invalid CSRF (403)');

    // -----------------------------------------------------------------
    // Section 3: Seller Profile
    // -----------------------------------------------------------------
    echo "\n-- Section 3: Seller Profile --\n";
    $profGet = apiRequest("{$baseUrl}/seller/profile.php", 'GET', null, [], $seller1Jar);
    check($profGet['status'] === 200, 'Seller reads own profile (200)');
    check(($profGet['json']['data']['profile']['email'] ?? '') === $seller1Email, 'Profile email matches authenticated seller');

    $profUpdate = apiRequest("{$baseUrl}/seller/profile.php", 'PUT', [
        'full_name'  => "Updated Seller 1 {$tag}",
        'phone'      => '01899999999',
        'avatar_url' => 'https://example.com/avatar.jpg',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($profUpdate['status'] === 200, 'Seller updates profile fields (200)');
    check(($profUpdate['json']['data']['user']['full_name'] ?? '') === "Updated Seller 1 {$tag}", 'Profile name updated');

    $profEscalate = apiRequest("{$baseUrl}/seller/profile.php", 'PUT', [
        'role' => 'admin',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($profEscalate['status'] === 422, 'Privileged role escalation blocked on profile update (422)');

    // -----------------------------------------------------------------
    // Section 4: Listing Creation & Pending Approval Status
    // -----------------------------------------------------------------
    echo "\n-- Section 4: Listing Creation & Pending Approval --\n";
    $invalidAdd = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', [
        'title' => '',
        'price' => -50,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($invalidAdd['status'] === 422 && isset($invalidAdd['json']['errors']['title']), 'Add listing rejects invalid inputs (422)');

    $validListingPayload = [
        'title'          => "Algorithms 101 {$tag}",
        'author'         => 'Cormen et al.',
        'edition'        => '3rd Edition',
        'course_code'    => 'CSE-2201',
        'department'     => 'CSE',
        'subject'        => 'Algorithms',
        'item_type'      => 'Textbook',
        'condition_type' => 'Like New',
        'price'          => 450,
        'description'    => 'Well maintained textbook with no missing pages.',
        // Attacker attempts to assign approved status and someone else as seller
        'status'         => 'available',
        'seller_id'      => 999,
        'reviewed_by'    => 1,
    ];

    $addRes = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', $validListingPayload, ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($addRes['status'] === 201, 'Valid listing created (201)');

    $listing1Id = (int) ($addRes['json']['data']['listing']['id'] ?? 0);
    $listingIds[] = $listing1Id;

    check(($addRes['json']['data']['listing']['status'] ?? '') === 'pending_approval', 'New listing status is strictly pending_approval');
    check(($addRes['json']['data']['listing']['seller_id'] ?? 0) === $seller1Id, 'Listing seller_id is derived from session, ignoring payload override');
    check(($addRes['json']['data']['listing']['reviewed_by'] ?? null) === null, 'Moderation fields cannot be set by seller on creation');

    // Create a listing for Seller 2 for cross-seller isolation checks
    $addRes2 = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', [
        'title'          => "Seller 2 Book {$tag}",
        'course_code'    => 'EEE-1101',
        'department'     => 'EEE',
        'price'          => 300,
        'description'    => 'Seller 2 private listing description.',
    ], ['X-CSRF-Token: ' . $csrfSel2], $seller2Jar);
    $listing2Id = (int) ($addRes2['json']['data']['listing']['id'] ?? 0);
    $listingIds[] = $listing2Id;
    check($addRes2['status'] === 201, 'Seller 2 listing created');

    // -----------------------------------------------------------------
    // Section 5: Ownership Isolation (Cross-Seller Protection)
    // -----------------------------------------------------------------
    echo "\n-- Section 5: Cross-Seller Ownership Isolation --\n";
    $seller1Listings = apiRequest("{$baseUrl}/seller/listings.php", 'GET', null, [], $seller1Jar);
    $seller1ListingIds = array_column($seller1Listings['json']['data']['listings'] ?? [], 'id');
    check(in_array($listing1Id, $seller1ListingIds, true), 'Seller 1 listing appears in Seller 1 listings query');
    check(!in_array($listing2Id, $seller1ListingIds, true), 'Seller 2 listing is excluded from Seller 1 listings query');

    $crossView = apiRequest("{$baseUrl}/seller/listings.php?id={$listing2Id}", 'GET', null, [], $seller1Jar);
    check($crossView['status'] === 403, 'Cross-seller view rejected with 403 Forbidden');

    $crossEdit = apiRequest("{$baseUrl}/seller/edit-listing.php", 'PUT', [
        'id'    => $listing2Id,
        'title' => 'Tampered Title',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($crossEdit['status'] === 403, 'Cross-seller edit rejected with 403 Forbidden');

    $crossSold = apiRequest("{$baseUrl}/seller/mark-sold.php", 'POST', [
        'id' => $listing2Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($crossSold['status'] === 403, 'Cross-seller mark-sold rejected with 403 Forbidden');

    $crossDelete = apiRequest("{$baseUrl}/seller/delete-listing.php", 'POST', [
        'id' => $listing2Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($crossDelete['status'] === 403, 'Cross-seller delete rejected with 403 Forbidden');

    // -----------------------------------------------------------------
    // Section 6: Listing Edits & State Transitions
    // -----------------------------------------------------------------
    echo "\n-- Section 6: Listing Edits & State Transitions --\n";
    $editPending = apiRequest("{$baseUrl}/seller/edit-listing.php", 'PUT', [
        'id'    => $listing1Id,
        'title' => "Algorithms 101 (Revised Edition) {$tag}",
        'price' => 420,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($editPending['status'] === 200, 'Listing in pending_approval edited successfully');
    check(($editPending['json']['data']['listing']['status'] ?? '') === 'pending_approval', 'Status remains pending_approval after edit');
    check((float) ($editPending['json']['data']['listing']['price'] ?? 0) === 420.0, 'Updated price reflected');

    // Simulate Admin requesting changes on listing1
    $db->prepare("UPDATE listings SET status = 'changes_requested', admin_feedback = 'Please clarify edition.' WHERE id = ?")->execute([$listing1Id]);

    $editAfterChanges = apiRequest("{$baseUrl}/seller/edit-listing.php", 'PUT', [
        'id'          => $listing1Id,
        'description' => 'Updated edition details and condition notes.',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($editAfterChanges['status'] === 200, 'Seller edits listing in changes_requested');
    check(($editAfterChanges['json']['data']['listing']['status'] ?? '') === 'pending_approval', 'Editing changes_requested transitions back to pending_approval');

    // -----------------------------------------------------------------
    // -----------------------------------------------------------------
    // Section 7: Sold & Unsold State Transitions
    // -----------------------------------------------------------------
    echo "\n-- Section 7: Sold & Unsold Transitions --\n";
    // Attempting to mark a pending_approval listing as sold must be rejected
    $badMarkSold = apiRequest("{$baseUrl}/seller/mark-sold.php", 'POST', [
        'id' => $listing1Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($badMarkSold['status'] === 422, 'Pending listing cannot be marked sold (422)');

    $approval = apiRequest("{$baseUrl}/admin/review-listing.php", 'POST', [
        'listing_id' => $listing1Id, 'action' => 'approve',
    ], ['X-CSRF-Token: ' . $csrfAdmin], $adminJar);
    check($approval['status'] === 200, 'Admin approves listing through real moderation API');

    // Create an accepted purchase request for listing1
    $prStmt = $db->prepare("INSERT INTO purchase_requests (listing_id, buyer_id, seller_id, meeting_location, preferred_date, payment_method, note, status, created_at, updated_at) VALUES (?, ?, ?, 'UIU Library', CURDATE(), 'cash_on_meet', 'Looking forward to meeting.', 'accepted', NOW(), NOW())");
    $prStmt->execute([$listing1Id, $buyerId, $seller1Id]);
    $prId = (int) $db->lastInsertId();
    $purchaseIds[] = $prId;

    // Accepted meetups must be completed through request actions, not manual mark-sold.
    $markSold = apiRequest("{$baseUrl}/seller/mark-sold.php", 'POST', [
        'id' => $listing1Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($markSold['status'] === 409, 'Accepted request blocks manual mark-sold (409)');

    // A blocked action must preserve the listing and request.
    $checkListing = $db->prepare('SELECT status FROM listings WHERE id = ?');
    $checkListing->execute([$listing1Id]);
    check($checkListing->fetchColumn() === 'available', 'Blocked mark-sold leaves listing available');

    // Verify database state: purchase request was NOT mutated by mark-sold (respects Tanvir module ownership)
    $checkPr = $db->prepare('SELECT status, completed_at FROM purchase_requests WHERE id = ?');
    $checkPr->execute([$prId]);
    $prRow = $checkPr->fetch();
    check(($prRow['status'] ?? '') === 'accepted' && empty($prRow['completed_at']), 'Marking listing sold does NOT complete or mutate purchase requests (Tanvir module ownership)');

    $requests = apiRequest("{$baseUrl}/buyer/seller-requests.php", 'GET', null, [], $seller1Jar);
    $acceptedRows = array_values(array_filter($requests['json']['data']['requests'] ?? [], fn($row) => $row['id'] === $prId));
    check(($acceptedRows[0]['can_decline'] ?? false) === true, 'Accepted request exposes can_decline capability');
    $decline = apiRequest("{$baseUrl}/buyer/seller-request-action.php", 'POST', ['request_id' => $prId, 'action' => 'decline'], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($decline['status'] === 200, 'Seller can decline an accepted request');
    $markSold = apiRequest("{$baseUrl}/seller/mark-sold.php", 'POST', ['id' => $listing1Id], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($markSold['status'] === 200, 'Manual mark-sold succeeds after declining accepted request');

    // Attempting to mark an already sold listing as sold again -> 409 Conflict
    $repeatSold = apiRequest("{$baseUrl}/seller/mark-sold.php", 'POST', [
        'id' => $listing1Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($repeatSold['status'] === 409, 'Repeated mark-sold returns 409 Conflict');

    // Attempting to edit a sold listing -> 409 Conflict
    $editSold = apiRequest("{$baseUrl}/seller/edit-listing.php", 'PUT', [
        'id'    => $listing1Id,
        'title' => 'Trying to edit sold item',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($editSold['status'] === 409, 'Editing sold listing blocked (409 Conflict)');

    // Mark sold listing as unsold -> returns to available
    $markUnsold = apiRequest("{$baseUrl}/seller/mark-unsold.php", 'POST', [
        'id' => $listing1Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($markUnsold['status'] === 200, 'Sold listing marked as unsold (200)');

    $checkListing = $db->prepare('SELECT status FROM listings WHERE id = ?');
    $checkListing->execute([$listing1Id]);
    check($checkListing->fetchColumn() === 'available', 'Listing status returned to available');

    // Stale unsold transition check: listing is now 'available', marking unsold again returns 409 Conflict
    $repeatUnsold = apiRequest("{$baseUrl}/seller/mark-unsold.php", 'POST', [
        'id' => $listing1Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($repeatUnsold['status'] === 409, 'Marking non-sold listing as unsold returns 409 Conflict');

    $newRequest = apiRequest("{$baseUrl}/buyer/purchase-request.php", 'POST', [
        'listing_id' => $listing1Id, 'meeting_location' => 'UIU Library',
        'preferred_date' => date('Y-m-d', strtotime('+1 day')), 'payment_method' => 'cash_on_meet',
    ], ['X-CSRF-Token: ' . $csrfBuyer], $buyerJar);
    check($newRequest['status'] === 201, 'Buyer creates purchase request through real API');
    $completePrId = (int) ($newRequest['json']['data']['request']['id'] ?? 0);
    $purchaseIds[] = $completePrId;
    $accept = apiRequest("{$baseUrl}/buyer/seller-request-action.php", 'POST', ['request_id' => $completePrId, 'action' => 'accept'], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($accept['status'] === 200, 'Seller accepts buyer request');
    $complete = apiRequest("{$baseUrl}/buyer/seller-request-action.php", 'POST', ['request_id' => $completePrId, 'action' => 'complete'], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($complete['status'] === 200 && ($complete['json']['data']['listing_status'] ?? '') === 'sold', 'Complete meetup sells listing through real API');
    $checkPr->execute([$completePrId]);
    $completed = $checkPr->fetch();
    check(($completed['status'] ?? '') === 'completed' && !empty($completed['completed_at']), 'Complete meetup records completed request and timestamp');

    // -----------------------------------------------------------------
    // Section 8: Delete Behavior & Related Records Handling
    // -----------------------------------------------------------------
    echo "\n-- Section 8: Delete Behavior & Related Records --\n";
    // Attempting to delete a sold listing -> 409 Conflict
    $deleteSold = apiRequest("{$baseUrl}/seller/delete-listing.php", 'POST', [
        'id' => $listing1Id,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($deleteSold['status'] === 409, 'Deleting sold listing blocked (409 Conflict)');

    // Create a separate disposable listing with a completed purchase request fixture
    $completedPrListingRes = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', [
        'title'       => "Completed PR Book {$tag}",
        'course_code' => 'CSE-3301',
        'department'  => 'CSE',
        'price'       => 350,
        'description' => 'Listing with completed PR fixture for delete test.',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    $completedPrListingId = (int) ($completedPrListingRes['json']['data']['listing']['id'] ?? 0);
    $listingIds[] = $completedPrListingId;
    $db->prepare("UPDATE listings SET status = 'available', reviewed_by = ? WHERE id = ?")->execute([$adminId, $completedPrListingId]);

    // Insert disposable completed purchase request fixture
    $compPrStmt = $db->prepare("INSERT INTO purchase_requests (listing_id, buyer_id, seller_id, meeting_location, preferred_date, payment_method, note, status, completed_at, created_at, updated_at) VALUES (?, ?, ?, 'UIU Library', CURDATE(), 'cash_on_meet', 'Completed purchase fixture', 'completed', NOW(), NOW(), NOW())");
    $compPrStmt->execute([$completedPrListingId, $buyerId, $seller1Id]);
    $compPrId = (int) $db->lastInsertId();
    $purchaseIds[] = $compPrId;

    $deleteCompletedPrListing = apiRequest("{$baseUrl}/seller/delete-listing.php", 'POST', [
        'id' => $completedPrListingId,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($deleteCompletedPrListing['status'] === 409, 'Deleting listing with completed purchase request blocked (409 Conflict)');

    // Create a disposable listing without any purchase requests
    $delListingRes = apiRequest("{$baseUrl}/seller/add-listing.php", 'POST', [
        'title'       => "Disposable Book {$tag}",
        'course_code' => 'MAT-1101',
        'department'  => 'Mathematics',
        'price'       => 200,
        'description' => 'Temporary listing for deletion test.',
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    $disposableListingId = (int) ($delListingRes['json']['data']['listing']['id'] ?? 0);
    $listingIds[] = $disposableListingId;
    check($delListingRes['status'] === 201, 'Disposable listing created');

    $deleteSuccess = apiRequest("{$baseUrl}/seller/delete-listing.php", 'POST', [
        'id' => $disposableListingId,
    ], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($deleteSuccess['status'] === 200, 'Unreferenced listing deleted successfully (200)');

    $verifyDeleted = $db->prepare('SELECT COUNT(*) FROM listings WHERE id = ?');
    $verifyDeleted->execute([$disposableListingId]);
    check((int) $verifyDeleted->fetchColumn() === 0, 'Listing row completely removed from database');

    // -----------------------------------------------------------------
    // Section 9: Sales History & Dashboard Metrics
    // -----------------------------------------------------------------
    echo "\n-- Section 9: Sales History & Dashboard Metrics --\n";
    // History now uses the real completed meetup from Section 7.

    $salesHistory = apiRequest("{$baseUrl}/seller/sales-history.php", 'GET', null, [], $seller1Jar);
    check($salesHistory['status'] === 200, 'Seller sales history retrieved (200)');
    $salesRows = $salesHistory['json']['data']['sales'] ?? [];
    check(count($salesRows) >= 1, 'Sales history contains completed transaction');
    check(($salesRows[0]['buyer_name'] ?? '') === "Buyer One {$tag}", 'Sales history includes buyer name from completed request fixture');
    check(!isset($salesRows[0]['password_hash']), 'Sales history excludes sensitive account data');

    $dashStats = apiRequest("{$baseUrl}/seller/dashboard.php", 'GET', null, [], $seller1Jar);
    $stats = $dashStats['json']['data']['stats'] ?? [];
    check($dashStats['status'] === 200, 'Seller dashboard metrics retrieved');
    check(($stats['sold_listings'] ?? 0) >= 1, 'Dashboard reflects sold listing count');
    check(array_key_exists('revenue', $stats) && is_numeric($stats['revenue']), 'Dashboard returns recorded revenue');
    check(!empty($stats['revenue_note']), 'Dashboard includes explanatory revenue note');
    $relistCompleted = apiRequest("{$baseUrl}/seller/mark-unsold.php", 'POST', ['id' => $listing1Id], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
    check($relistCompleted['status'] === 200, 'Completed-sale listing can be relisted');
    $afterRelist = apiRequest("{$baseUrl}/seller/sales-history.php", 'GET', null, [], $seller1Jar);
    check(in_array($completePrId, array_column($afterRelist['json']['data']['sales'] ?? [], 'purchase_request_id'), true), 'Relisting preserves completed sale in history');

    // -----------------------------------------------------------------
    // Section 10: Secure Image Upload Handling
    // -----------------------------------------------------------------
    echo "\n-- Section 10: Secure Image Upload Handling --\n";
    // 10a. Valid image upload (synthetic JPEG without requiring GD)
    $tmpImgPath = tempnam(sys_get_temp_dir(), 'bb_img_') . '.jpg';
    $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
    file_put_contents($tmpImgPath, $jpegBytes);

    $cFile = new CURLFile($tmpImgPath, 'image/jpeg', 'book_cover.jpg');
    $uploadRes = apiRequest("{$baseUrl}/seller/upload-image.php", 'POST', ['image' => $cFile], [
        'X-CSRF-Token: ' . $csrfSel1,
    ], $seller1Jar);
    @unlink($tmpImgPath);

    check($uploadRes['status'] === 201, 'Valid JPEG image uploaded (201)');
    $uploadedUrl = $uploadRes['json']['data']['image_url'] ?? '';
    check(strpos($uploadedUrl, 'uploads/listings/img_') === 0, 'Upload path is inside uploads/listings/ with safe prefix');

    if ($uploadedUrl !== '') {
        $fullPath = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadedUrl);
        $uploadedFiles[] = $fullPath;
        check(file_exists($fullPath), 'Uploaded file exists on filesystem');
        $coverEdit = apiRequest("{$baseUrl}/seller/edit-listing.php", 'PUT', ['id' => $listing1Id, 'image_url' => $uploadedUrl], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
        check($coverEdit['status'] === 200 && ($coverEdit['json']['data']['listing']['image_url'] ?? '') === $uploadedUrl, 'Edit listing saves uploaded cover image');
        $keepCover = apiRequest("{$baseUrl}/seller/edit-listing.php", 'PUT', ['id' => $listing1Id, 'title' => "Cover preserved {$tag}"], ['X-CSRF-Token: ' . $csrfSel1], $seller1Jar);
        check($keepCover['status'] === 200 && ($keepCover['json']['data']['listing']['image_url'] ?? '') === $uploadedUrl, 'Editing details without image_url preserves existing cover');
    }

    // 10b. Invalid file upload (fake PHP executable)
    $tmpPhpPath = tempnam(sys_get_temp_dir(), 'bb_bad_') . '.php';
    file_put_contents($tmpPhpPath, '<?php echo "evil"; ?>');
    $cBadFile = new CURLFile($tmpPhpPath, 'text/plain', 'shell.php');
    $badUploadRes = apiRequest("{$baseUrl}/seller/upload-image.php", 'POST', ['image' => $cBadFile], [
        'X-CSRF-Token: ' . $csrfSel1,
    ], $seller1Jar);
    @unlink($tmpPhpPath);
    check($badUploadRes['status'] === 422, 'Malicious/non-image upload rejected by MIME validation (422)');

    // 10c. Verify .htaccess protects upload directory
    $htaccessPath = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess';
    check(file_exists($htaccessPath), '.htaccess file exists in uploads/ directory');

} catch (Throwable $e) {
    $fail++;
    echo "  [FAIL] Test runner exception: " . $e->getMessage() . "\n";
} finally {
    echo "\n-- Cleanup --\n";
    if (isset($db) && $db instanceof PDO) {
        try {
            foreach ($purchaseIds as $id) {
                $db->prepare('DELETE FROM purchase_requests WHERE id = ?')->execute([$id]);
            }
            foreach ($listingIds as $id) {
                $db->prepare('DELETE FROM listings WHERE id = ?')->execute([$id]);
            }
            foreach ($userIds as $id) {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            }
            echo "  Cleaned up fixture DB records successfully.\n";
        } catch (Throwable $e) {
            $fail++;
            echo "  Cleanup error: " . $e->getMessage() . "\n";
        }
    }

    foreach ($uploadedFiles as $filePath) {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    foreach ($jars as $jar) {
        if (is_file($jar)) {
            @unlink($jar);
        }
    }
    echo "  Cleaned up temporary cookies and upload files.\n";
}

echo "\n====================================================================\n";
echo "RESULTS: {$pass} PASSED / {$fail} FAILED\n";
echo "STATUS : " . ($fail === 0 ? "ALL TESTS PASSED" : "TESTS FAILED") . "\n";
echo "====================================================================\n";

exit($fail === 0 ? 0 : 1);
