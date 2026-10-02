/**
 * api-integration.js  –  BookBridge Frontend API Integration
 * ============================================================
 * Provides centralized API client helpers and session synchronization
 * for BookBridge's original integrated React frontend.
 *
 * Base URL: http://localhost/UIU-Used-Textbook-Marketplace/api/
 */

(function () {
  'use strict';

  const BASE = (() => {
    const s = document.currentScript?.src || location.href;
    return new URL('api/', new URL('.', s)).href;
  })();

  function apiUrl(path) {
    return new URL(path, BASE).href;
  }

  function apiFetch(path, options = {}) {
    const auth = window.BookBridgeAuth;
    if (auth && typeof auth.request === 'function') {
      return auth.request(path, options);
    }
    return fetch(apiUrl(path), {
      credentials: 'include',
      headers: { Accept: 'application/json' },
      ...options,
    }).then(r => r.json());
  }

  function roleToView(role) {
    if (role === 'seller') return 'seller-dashboard';
    if (role === 'admin')  return 'admin-dashboard';
    if (role === 'buyer')  return 'buyer-dashboard';
    return 'marketplace';
  }

  window.BookBridgeAPI = {
    /* Marketplace (Public & Guest) */
    listings: (params = '') =>
      apiFetch(`marketplace/listings.php${params}`),
    listingDetail: id =>
      apiFetch(`marketplace/listing-details.php?id=${encodeURIComponent(id)}`),
    categories: (dept = '') =>
      apiFetch(`marketplace/categories.php${dept ? `?department=${encodeURIComponent(dept)}` : ''}`),

    /* Auth */
    me: () => apiFetch('auth/me.php'),
    login: body =>
      apiFetch('auth/login.php', { method: 'POST', body }),
    logout: () =>
      apiFetch('auth/logout.php', { method: 'POST', body: {} }),
    register: body =>
      apiFetch('auth/register.php', { method: 'POST', body }),

    /* Buyer */
    buyerDashboard: () => apiFetch('buyer/dashboard.php'),
    buyerProfile: () => apiFetch('buyer/profile.php'),
    wishlist: () => apiFetch('buyer/wishlist.php'),
    wishlistToggle: bookId =>
      apiFetch('buyer/wishlist.php', {
        method: 'POST',
        body: { listing_id: bookId },
      }),
    purchaseRequests: (status = '') =>
      apiFetch(`buyer/my-requests.php${status ? `?status=${encodeURIComponent(status)}` : ''}`),
    sendPurchaseRequest: body =>
      apiFetch('buyer/purchase-request.php', {
        method: 'POST',
        body,
      }),
    cancelPurchaseRequest: requestId =>
      apiFetch('buyer/cancel-request.php', {
        method: 'POST',
        body: { request_id: requestId },
      }),

    /* Seller */
    sellerDashboard: () => apiFetch('seller/dashboard.php'),
    sellerListings: (params = '') =>
      apiFetch(`seller/listings.php${params}`),
    sellerSales: (page = 1) =>
      apiFetch(`seller/sales-history.php?page=${page}`),
    addListing: body =>
      apiFetch('seller/add-listing.php', { method: 'POST', body }),
    editListing: body =>
      apiFetch('seller/edit-listing.php', { method: 'PUT', body }),
    markSold: listingId =>
      apiFetch('seller/mark-sold.php', {
        method: 'POST',
        body: { listing_id: listingId },
      }),
    markUnsold: listingId =>
      apiFetch('seller/mark-unsold.php', {
        method: 'POST',
        body: { listing_id: listingId },
      }),
    deleteListing: listingId =>
      apiFetch('seller/delete-listing.php', {
        method: 'POST',
        body: { listing_id: listingId },
      }),
    sellerRequests: (status = '') =>
      apiFetch(`buyer/seller-requests.php${status ? `?status=${encodeURIComponent(status)}` : ''}`),
    sellerRequestAction: (requestId, action) =>
      apiFetch('buyer/seller-request-action.php', {
        method: 'POST',
        body: { request_id: requestId, action },
      }),

    /* Messages & Reviews */
    conversations: () => apiFetch('messages/conversations.php'),
    thread: userId =>
      apiFetch(`messages/thread.php?with_user_id=${encodeURIComponent(userId)}`),
    sendMessage: body =>
      apiFetch('messages/send.php', { method: 'POST', body }),
    sellerReviews: sellerId =>
      apiFetch(`reviews/seller-reviews.php?seller_id=${encodeURIComponent(sellerId)}`),
    submitReview: body =>
      apiFetch('reviews/create.php', { method: 'POST', body }),

    /* Admin */
    adminDashboard: () => apiFetch('admin/dashboard.php'),
    adminPendingListings: (params = '') =>
      apiFetch(`admin/pending-listings.php${params}`),
    adminReviewListing: (listingId, action, note = '') =>
      apiFetch('admin/review-listing.php', {
        method: 'POST',
        body: {
          listing_id: listingId,
          action,
          feedback: note,
          reason: note,
        },
      }),
    adminCategories: () => apiFetch('admin/categories.php'),
    adminAddCategory: body =>
      apiFetch('admin/categories.php', { method: 'POST', body }),
    adminUpdateCategory: body =>
      apiFetch('admin/categories.php', { method: 'PUT', body }),
    adminRemoveCategory: id =>
      apiFetch('admin/categories.php', { method: 'DELETE', body: { id } }),
    adminSalesReport: (period = 'monthly') =>
      apiFetch(`admin/sales-report.php?period=${encodeURIComponent(period)}`),
  };

  /* Event bridge: dispatch navigate when auth session arrives */
  document.addEventListener('bookbridge:session', function (event) {
    const user = event.detail && event.detail.user;
    if (!user) return;
    const view = roleToView(user.role);
    document.dispatchEvent(
      new CustomEvent('bookbridge:navigate', { detail: { view, user } })
    );
  });
})();
