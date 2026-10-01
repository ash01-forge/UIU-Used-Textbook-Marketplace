(() => {
  "use strict";

  const auth = window.BookBridgeAuth;
  const $ = selector => document.querySelector(selector);
  const pending = new Set();

  function visible(element, show) {
    if (element) element.hidden = !show;
  }

  function renderError(selector, error) {
    const box = $(selector);
    box.textContent = error?.message || "The request failed. Please try again.";
    visible(box, true);
  }

  function clearError(selector) {
    const box = $(selector);
    if (!box) return;
    box.textContent = "";
    visible(box, false);
  }

  async function getData(path, params = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") query.set(key, String(value));
    });
    const result = await auth.request(`${path}${query.size ? `?${query}` : ""}`);
    return result.data || {};
  }

  function formatMoney(value) {
    const amount = Number(value);
    return Number.isFinite(amount) ? new Intl.NumberFormat("en-BD", {
      style: "currency", currency: "BDT", maximumFractionDigits: 2
    }).format(amount) : "Unavailable";
  }

  function formatDate(value, dateOnly = false) {
    if (!value) return "—";
    const raw = String(value);
    const date = new Date(dateOnly ? `${raw}T00:00:00` : raw.replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? raw : new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      ...(dateOnly ? {} : { timeStyle: "short" })
    }).format(date);
  }

  function appendText(parent, tag, text, className) {
    const element = document.createElement(tag);
    element.textContent = text === null || text === undefined || text === "" ? "—" : String(text);
    if (className) element.className = className;
    parent.append(element);
    return element;
  }

  function safeImageUrl(value) {
    if (!value) return "";
    try {
      const url = new URL(String(value), window.location.href);
      return ["http:", "https:"].includes(url.protocol) ? url.href : "";
    } catch {
      return "";
    }
  }

  function listingLink(listing) {
    const params = new URLSearchParams({
      listing_id: String(listing.id ?? listing.listing_id),
      title: listing.title || listing.book_title || "",
      price: String(listing.price ?? "")
    });
    return `purchase-request.html?${params}`;
  }

  function renderListingCard(listing, mode) {
    const card = document.createElement("article");
    card.className = "workflow-card";
    const imageUrl = safeImageUrl(listing.image_url);
    if (imageUrl) {
      const image = document.createElement("img");
      image.src = imageUrl;
      image.alt = listing.title || "Book cover";
      image.loading = "lazy";
      card.append(image);
    }
    const content = document.createElement("div");
    content.className = "workflow-card-content";
    appendText(content, "p", [listing.course_code, listing.department, listing.item_type].filter(Boolean).join(" · "), "workflow-kicker");
    appendText(content, "h3", listing.title || "Untitled listing");
    appendText(content, "p", [listing.author, listing.condition_type, listing.seller_name].filter(Boolean).join(" · "), "workflow-muted");
    appendText(content, "strong", formatMoney(listing.price), "workflow-price");
    if (mode === "wishlist" && listing.listing_status !== "available") {
      appendText(content, "p", `Status: ${listing.listing_status || "unavailable"}`, "workflow-status");
    }
    const actions = document.createElement("div");
    actions.className = "workflow-actions";
    const listingId = Number(listing.id ?? listing.listing_id);
    const detailLink = document.createElement("a");
    detailLink.className = "secondary";
    detailLink.href = `listing-details.html?id=${encodeURIComponent(listingId)}`;
    detailLink.textContent = "View listing";
    actions.append(detailLink);
    const wishlist = document.createElement("button");
    wishlist.type = "button";
    wishlist.className = "secondary";
    wishlist.dataset.wishlistAction = mode === "wishlist" ? "remove" : "add";
    wishlist.dataset.listingId = String(listingId);
    wishlist.textContent = mode === "wishlist" ? "Remove" : "Save";
    actions.append(wishlist);
    if (mode !== "wishlist" || listing.listing_status === "available") {
      const requestLink = document.createElement("a");
      requestLink.className = "primary";
      requestLink.href = listingLink({ ...listing, id: listingId });
      requestLink.textContent = "Request";
      actions.append(requestLink);
    }
    content.append(actions);
    card.append(content);
    return card;
  }

  async function loadDashboard() {
    clearError("#buyerDashboardError");
    visible($("#buyerDashboardLoading"), true);
    visible($("#buyerStats"), false);
    visible($("#recommendationsError"), false);
    visible($("#recommendationsLoading"), true);
    visible($("#recommendationsEmpty"), false);
    try {
      const data = await getData("buyer/dashboard.php");
      const stats = data.stats || {};
      $("#wishlistCount").textContent = String(stats.wishlist_count ?? "—");
      $("#requestCount").textContent = String(stats.active_requests ?? "—");
      $("#completedCount").textContent = String(stats.completed_purchases ?? "—");
      $("#savedCount").textContent = stats.money_saved === undefined ? "Unavailable" : formatMoney(stats.money_saved);
      visible($("#buyerStats"), true);
      const grid = $("#recommendationsGrid");
      grid.replaceChildren();
      const listings = Array.isArray(data.recommended_listings) ? data.recommended_listings : [];
      listings.forEach(listing => grid.append(renderListingCard(listing, "recommendation")));
      visible($("#recommendationsEmpty"), listings.length === 0);
    } catch (error) {
      renderError("#buyerDashboardError", error);
      renderError("#recommendationsError", error);
    } finally {
      visible($("#buyerDashboardLoading"), false);
      visible($("#recommendationsLoading"), false);
    }
  }

  async function loadWishlist() {
    clearError("#wishlistError");
    visible($("#wishlistLoading"), true);
    visible($("#wishlistEmpty"), false);
    const grid = $("#wishlistGrid");
    grid.replaceChildren();
    try {
      const data = await getData("buyer/wishlist.php");
      const items = Array.isArray(data.items) ? data.items : [];
      items.forEach(item => grid.append(renderListingCard(item, "wishlist")));
      visible($("#wishlistEmpty"), items.length === 0);
    } catch (error) {
      renderError("#wishlistError", error);
    } finally {
      visible($("#wishlistLoading"), false);
    }
  }

  function renderRequest(request) {
    const item = document.createElement("article");
    item.className = "workflow-list-item";
    const heading = document.createElement("div");
    heading.className = "workflow-item-heading";
    appendText(heading, "h3", request.book_title || "Listing no longer available");
    appendText(heading, "span", String(request.status || "unknown").replaceAll("_", " "), `workflow-status status-${request.status || "unknown"}`);
    item.append(heading);
    appendText(item, "p", `${formatMoney(request.price)} · ${request.seller_name || "Seller unavailable"}`, "workflow-muted");
    appendText(item, "p", `Meet: ${request.meeting_location || "—"} · Preferred date: ${formatDate(request.preferred_date, true)}`, "workflow-muted");
    if (request.note) appendText(item, "p", `Note: ${request.note}`, "workflow-note");
    const actions = document.createElement("div");
    actions.className = "workflow-actions";
    if (request.seller_id && ["accepted", "completed"].includes(request.status)) {
      const message = document.createElement("a");
      message.className = "secondary";
      message.href = `messages.html?${new URLSearchParams({ with_user_id: String(request.seller_id), listing_id: String(request.listing_id) })}`;
      message.textContent = "Message seller";
      actions.append(message);
    }
    if (request.can_cancel) {
      const cancel = document.createElement("button");
      cancel.type = "button";
      cancel.className = "secondary";
      cancel.dataset.cancelRequest = String(request.id);
      cancel.textContent = "Cancel request";
      actions.append(cancel);
    }
    if (request.can_review) {
      const review = document.createElement("form");
      review.className = "workflow-review-form";
      review.dataset.reviewRequest = String(request.id);
      const ratingLabel = document.createElement("label");
      ratingLabel.textContent = "Rating";
      const select = document.createElement("select");
      select.name = "rating";
      select.required = true;
      [5, 4, 3, 2, 1].forEach(rating => {
        const option = document.createElement("option");
        option.value = String(rating);
        option.textContent = `${rating} star${rating === 1 ? "" : "s"}`;
        select.append(option);
      });
      ratingLabel.append(select);
      const commentLabel = document.createElement("label");
      commentLabel.textContent = "Comment (optional)";
      const comment = document.createElement("textarea");
      comment.name = "comment";
      comment.maxLength = 1000;
      comment.rows = 2;
      commentLabel.append(comment);
      const submit = document.createElement("button");
      submit.type = "submit";
      submit.className = "primary";
      submit.textContent = "Submit review";
      review.append(ratingLabel, commentLabel, submit);
      item.append(review);
    } else if (request.has_reviewed) {
      appendText(item, "p", "Review submitted", "workflow-success-text");
    }
    item.append(actions);
    return item;
  }

  async function loadRequests() {
    clearError("#requestsError");
    visible($("#requestsLoading"), true);
    visible($("#requestsEmpty"), false);
    const list = $("#requestsList");
    list.replaceChildren();
    try {
      const data = await getData("buyer/my-requests.php");
      const requests = Array.isArray(data.requests) ? data.requests : [];
      requests.forEach(request => list.append(renderRequest(request)));
      visible($("#requestsEmpty"), requests.length === 0);
    } catch (error) {
      renderError("#requestsError", error);
    } finally {
      visible($("#requestsLoading"), false);
    }
  }

  async function loadMyReviews() {
    clearError("#myReviewsError");
    visible($("#myReviewsLoading"), true);
    visible($("#myReviewsEmpty"), false);
    const list = $("#myReviewsList");
    list.replaceChildren();
    try {
      const data = await getData("reviews/my-reviews.php");
      const reviews = Array.isArray(data.reviews) ? data.reviews : [];
      reviews.forEach(review => {
        const item = document.createElement("article");
        item.className = "workflow-list-item";
        appendText(item, "h3", review.book_title || "Completed purchase");
        appendText(item, "p", `${"★".repeat(review.rating)}${"☆".repeat(Math.max(0, 5 - review.rating))} · ${review.seller_name || "Seller"}`, "workflow-rating");
        if (review.comment) appendText(item, "p", review.comment);
        appendText(item, "small", formatDate(review.created_at), "workflow-muted");
        list.append(item);
      });
      visible($("#myReviewsEmpty"), reviews.length === 0);
    } catch (error) {
      renderError("#myReviewsError", error);
    } finally {
      visible($("#myReviewsLoading"), false);
    }
  }

  async function toggleWishlist(button) {
    const listingId = Number(button.dataset.listingId);
    const action = button.dataset.wishlistAction;
    const key = `wishlist:${listingId}`;
    if (!listingId || pending.has(key)) return;
    pending.add(key);
    button.disabled = true;
    try {
      await auth.request("buyer/wishlist.php", { method: "POST", body: { listing_id: listingId, action } });
      await Promise.all([loadWishlist(), loadDashboard()]);
    } catch (error) {
      renderError("#wishlistError", error);
    } finally {
      pending.delete(key);
      button.disabled = false;
    }
  }

  async function cancelRequest(button) {
    const requestId = Number(button.dataset.cancelRequest);
    const key = `cancel:${requestId}`;
    if (!requestId || pending.has(key) || !window.confirm("Cancel this purchase request?")) return;
    pending.add(key);
    button.disabled = true;
    try {
      await auth.request("buyer/cancel-request.php", { method: "POST", body: { request_id: requestId } });
      await Promise.all([loadRequests(), loadDashboard()]);
    } catch (error) {
      renderError("#requestsError", error);
      await loadRequests();
    } finally {
      pending.delete(key);
    }
  }

  async function submitReview(form) {
    const requestId = Number(form.dataset.reviewRequest);
    const key = `review:${requestId}`;
    if (!requestId || pending.has(key) || !form.reportValidity()) return;
    pending.add(key);
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    const item = form.closest(".workflow-list-item");
    let errorBox = item.querySelector("[data-review-error]");
    if (!errorBox) {
      errorBox = document.createElement("p");
      errorBox.dataset.reviewError = "true";
      errorBox.setAttribute("role", "alert");
      errorBox.className = "workflow-alert workflow-error";
      form.append(errorBox);
    }
    errorBox.hidden = true;
    try {
      await auth.request("reviews/create.php", {
        method: "POST",
        body: {
          purchase_request_id: requestId,
          rating: Number(form.elements.rating.value),
          comment: form.elements.comment.value.trim()
        }
      });
      await Promise.all([loadRequests(), loadMyReviews()]);
    } catch (error) {
      errorBox.textContent = error.message || "Unable to submit review.";
      errorBox.hidden = false;
      if (error.status === 422) await loadRequests();
    } finally {
      pending.delete(key);
      button.disabled = false;
    }
  }

  async function submitPurchaseRequest() {
    const form = $("#purchaseForm");
    const user = await auth.requireRole("buyer");
    if (!user) return;
    document.body.removeAttribute("data-auth-checking");
    const params = new URLSearchParams(window.location.search);
    const listingId = Number(params.get("listing_id"));
    $("#purchaseListingTitle").textContent = params.get("title") || "Selected listing";
    $("#purchaseListingPrice").textContent = params.get("price") ? formatMoney(params.get("price")) : "Price confirmed when request is submitted";
    if (!listingId) {
      renderError("#purchaseError", new Error("Open a listing from your buyer dashboard to create a request."));
      $("#purchaseSubmit").disabled = true;
      return;
    }
    const date = $("#date");
    const today = new Date();
    date.min = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, "0"), String(today.getDate()).padStart(2, "0")].join("-");
    form.addEventListener("submit", async event => {
      event.preventDefault();
      const key = `purchase:${listingId}`;
      if (pending.has(key) || !form.reportValidity()) return;
      pending.add(key);
      $("#purchaseSubmit").disabled = true;
      clearError("#purchaseError");
      visible($("#purchaseSuccess"), false);
      try {
        const result = await auth.request("buyer/purchase-request.php", {
          method: "POST",
          body: {
            listing_id: listingId,
            meeting_location: $("#meeting").value.trim(),
            preferred_date: date.value,
            payment_method: "cash_on_meet",
            note: $("#note").value.trim()
          }
        });
        $("#purchaseSuccess").textContent = result.message || "Purchase request submitted.";
        visible($("#purchaseSuccess"), true);
        form.reset();
        $("#purchaseSubmit").hidden = true;
      } catch (error) {
        renderError("#purchaseError", error);
      } finally {
        pending.delete(key);
        if (!$("#purchaseSubmit").hidden) $("#purchaseSubmit").disabled = false;
      }
    });
  }

  async function loadListingDetails() {
    const id = Number(new URLSearchParams(window.location.search).get("id"));
    if (!id) throw new Error("Choose a listing from your buyer dashboard.");
    visible($("#listingDetailsLoading"), true);
    visible($("#listingDetailsContent"), false);
    try {
      const [dashboard, wishlist] = await Promise.all([
        getData("buyer/dashboard.php"),
        getData("buyer/wishlist.php")
      ]);
      const recommended = (dashboard.recommended_listings || []).find(listing => Number(listing.id) === id);
      const saved = (wishlist.items || []).find(listing => Number(listing.listing_id) === id);
      const listing = saved || recommended;
      if (!listing) throw new Error("This listing is no longer available in your current recommendations or wishlist.");
      $("#listingDetailTitle").textContent = listing.title || "Untitled listing";
      $("#listingDetailMeta").textContent = [listing.course_code, listing.department, listing.item_type, listing.condition_type].filter(Boolean).join(" · ");
      $("#listingDetailAuthor").textContent = [listing.author, listing.edition].filter(Boolean).join(" · ") || "Author details not provided";
      $("#listingDetailDescription").textContent = listing.description || "No further description is available.";
      $("#listingDetailSeller").textContent = listing.seller_name || "Seller";
      $("#listingDetailPrice").textContent = formatMoney(listing.price);
      const image = $("#listingDetailImage");
      const imageUrl = safeImageUrl(listing.image_url);
      image.hidden = !imageUrl;
      if (imageUrl) {
        image.src = imageUrl;
        image.alt = listing.title || "Book cover";
      }
      $("#listingRequestLink").href = listingLink({ ...listing, id });
      $("#listingRequestLink").hidden = Boolean(listing.listing_status && listing.listing_status !== "available");
      const messageLink = $("#listingMessageLink");
      messageLink.hidden = !listing.seller_id;
      if (listing.seller_id) {
        messageLink.href = `messages.html?${new URLSearchParams({ with_user_id: String(listing.seller_id), listing_id: String(id) })}`;
      }
      const wishlistButton = $("#listingWishlistButton");
      wishlistButton.textContent = saved ? "Remove from wishlist" : "Save to wishlist";
      wishlistButton.dataset.action = saved ? "remove" : "add";
      wishlistButton.dataset.listingId = String(id);
      visible($("#listingDetailsContent"), true);
      document.body.removeAttribute("data-auth-checking");
    } catch (error) {
      renderError("#listingDetailsError", error);
      document.body.removeAttribute("data-auth-checking");
    } finally {
      visible($("#listingDetailsLoading"), false);
    }
  }

  async function initialize() {
    if (!auth) return;
    const file = window.location.pathname.split("/").pop();
    if (file === "purchase-request.html") {
      await submitPurchaseRequest();
      return;
    }
    const user = await auth.requireRole("buyer");
    if (!user) return;
    if (file === "listing-details.html") {
      await loadListingDetails();
      $("#listingWishlistButton").addEventListener("click", async event => {
        const button = event.currentTarget;
        const listingId = Number(button.dataset.listingId);
        button.disabled = true;
        clearError("#listingDetailsError");
        try {
          const result = await auth.request("buyer/wishlist.php", {
            method: "POST",
            body: { listing_id: listingId, action: button.dataset.action }
          });
          button.dataset.action = result.data?.is_wishlisted ? "remove" : "add";
          button.textContent = result.data?.is_wishlisted ? "Remove from wishlist" : "Save to wishlist";
        } catch (error) {
          renderError("#listingDetailsError", error);
        } finally {
          button.disabled = false;
        }
      });
      return;
    }
    const greeting = $("#buyerGreeting");
    if (greeting) greeting.textContent = `Hello, ${user.full_name}`;
    document.body.removeAttribute("data-auth-checking");
    if (!$("#buyerStats")) return;
    $("#wishlistGrid").addEventListener("click", event => {
      const button = event.target.closest("[data-wishlist-action]");
      if (button) toggleWishlist(button);
    });
    $("#recommendationsGrid").addEventListener("click", event => {
      const button = event.target.closest("[data-wishlist-action]");
      if (button) toggleWishlist(button);
    });
    $("#requestsList").addEventListener("click", event => {
      const button = event.target.closest("[data-cancel-request]");
      if (button) cancelRequest(button);
    });
    $("#requestsList").addEventListener("submit", event => {
      const form = event.target.closest("[data-review-request]");
      if (!form) return;
      event.preventDefault();
      submitReview(form);
    });
    await Promise.all([loadDashboard(), loadWishlist(), loadRequests(), loadMyReviews()]);
  }

  if (auth?.ready) {
    initialize().catch(error => {
      const box = $("#buyerDashboardError") || $("#purchaseError");
      if (box) renderError(`#${box.id}`, error);
      document.body.removeAttribute("data-auth-checking");
    });
  }
})();
