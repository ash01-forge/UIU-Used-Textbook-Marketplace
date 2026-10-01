<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * GET / POST /api/buyer/wishlist.php
 *
 * Buyer wishlist management:
 * - GET: Returns all listings saved by the logged-in buyer.
 * - POST: Adds or removes a listing from the buyer's wishlist (or toggles).
 *
 * Access: Authenticated Buyer only
 * State-modifying requests require CSRF token.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$buyer = requireRole('buyer');
$buyerId = (int) $buyer['id'];

$db = getDbConnection();

// -------------------------------------------------------------------------
// GET: Retrieve all saved wishlist items for the buyer
// -------------------------------------------------------------------------
if ($method === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT 
                w.id AS wishlist_id,
                w.created_at AS saved_at,
                l.id AS listing_id,
                l.title,
                l.author,
                l.edition,
                l.course_code,
                l.department,
                l.subject,
                l.item_type,
                l.condition_type,
                l.price,
                l.image_url,
                l.status AS listing_status,
                u.id AS seller_id,
                u.full_name AS seller_name
            FROM wishlists w
            JOIN listings l ON w.listing_id = l.id
            JOIN users u ON l.seller_id = u.id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$buyerId]);
        $items = $stmt->fetchAll();

        sendSuccessResponse('Wishlist retrieved successfully', [
            'total' => count($items),
            'items' => $items,
        ]);
    } catch (PDOException $e) {
        error_log('Wishlist retrieval error: ' . $e->getMessage());
        sendErrorResponse('Failed to retrieve wishlist items.', 500);
    }
}

// -------------------------------------------------------------------------
// POST: Add, remove, or toggle a listing in wishlist
// -------------------------------------------------------------------------
if ($method === 'POST') {
    requireCsrfToken();

    $body = getJsonRequestBody();
    $listingId = isset($body['listing_id']) ? (int) $body['listing_id'] : 0;
    $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : 'toggle';

    if ($listingId <= 0) {
        sendErrorResponse('A valid listing ID is required.', 422, ['listing_id' => 'Valid listing_id is required.']);
    }

    try {
        // Verify listing exists
        $stmtListing = $db->prepare('SELECT id, title, status FROM listings WHERE id = ?');
        $stmtListing->execute([$listingId]);
        $listing = $stmtListing->fetch();

        if (!$listing) {
            sendErrorResponse('The requested listing does not exist.', 404);
        }

        // Check current wishlist status
        $stmtCheck = $db->prepare('SELECT id FROM wishlists WHERE user_id = ? AND listing_id = ?');
        $stmtCheck->execute([$buyerId, $listingId]);
        $existing = $stmtCheck->fetch();

        $isWishlisted = false;

        if ($action === 'add') {
            if (!$existing) {
                $stmtAdd = $db->prepare('INSERT INTO wishlists (user_id, listing_id, created_at) VALUES (?, ?, NOW())');
                $stmtAdd->execute([$buyerId, $listingId]);
            }
            $isWishlisted = true;
            $msg = 'Listing added to wishlist';
        } elseif ($action === 'remove') {
            if ($existing) {
                $stmtRemove = $db->prepare('DELETE FROM wishlists WHERE user_id = ? AND listing_id = ?');
                $stmtRemove->execute([$buyerId, $listingId]);
            }
            $isWishlisted = false;
            $msg = 'Listing removed from wishlist';
        } else {
            // Toggle
            if ($existing) {
                $stmtRemove = $db->prepare('DELETE FROM wishlists WHERE user_id = ? AND listing_id = ?');
                $stmtRemove->execute([$buyerId, $listingId]);
                $isWishlisted = false;
                $msg = 'Listing removed from wishlist';
            } else {
                $stmtAdd = $db->prepare('INSERT INTO wishlists (user_id, listing_id, created_at) VALUES (?, ?, NOW())');
                $stmtAdd->execute([$buyerId, $listingId]);
                $isWishlisted = true;
                $msg = 'Listing added to wishlist';
            }
        }

        // Count current total items in wishlist
        $stmtCount = $db->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
        $stmtCount->execute([$buyerId]);
        $wishlistCount = (int) $stmtCount->fetchColumn();

        sendSuccessResponse($msg, [
            'listing_id'     => $listingId,
            'is_wishlisted'  => $isWishlisted,
            'wishlist_count' => $wishlistCount,
        ]);

    } catch (PDOException $e) {
        error_log('Wishlist modify error: ' . $e->getMessage());
        sendErrorResponse('Failed to update wishlist.', 500);
    }
}

sendErrorResponse('Method not allowed. Use GET or POST.', 405);
