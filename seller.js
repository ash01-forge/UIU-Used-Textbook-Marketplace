(() => {
  "use strict";

  const auth = window.BookBridgeAuth;
  const $ = selector => document.querySelector(selector);
  const pending = new Set();
  const state = { listingsPage: 1, listingsPages: 1, salesPage: 1, salesPages: 1 };

  function visible(element, show) {
    if (element) element.hidden = !show;
  }

  function showError(selector, error) {
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

  function money(value) {
    const amount = Number(value);
    return Number.isFinite(amount) ? new Intl.NumberFormat("en-BD", { style: "currency", currency: "BDT", maximumFractionDigits: 2 }).format(amount) : "Unavailable";
  }

  function date(value) {
    if (!value) return "—";
    const parsed = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(parsed.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(parsed);
  }

  function cell(row, value) {
    const element = document.createElement("td");
    element.textContent = value === null || value === undefined || value === "" ? "—" : String(value);
    row.append(element);
    return element;
  }

  function actionButton(label, callback, className = "secondary") {
    const button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.textContent = label;
    button.addEventListener("click", callback);
    return button;
  }

  function pagination(container, page, pages, callback) {
    container.replaceChildren();
    visible(container, pages > 1);
    if (pages <= 1) return;
    const previous = actionButton("Previous", () => callback(page - 1));
    previous.disabled = page <= 1;
    const label = document.createElement("span");
    label.textContent = `Page ${page} of ${pages}`;
    const next = actionButton("Next", () => callback(page + 1));
    next.disabled = page >= pages;
    container.append(previous, label, next);
  }

  async function loadDashboard() {
    clearError("#sellerDashboardError");
    visible($("#sellerDashboardLoading"), true);
    visible($("#sellerStats"), false);
    try {
      const data = await getData("seller/dashboard.php");
      const stats = data.stats || {};
      $("#totalListings").textContent = String(stats.total_listings ?? "—");
      $("#activeListings").textContent = String(stats.active_listings ?? "—");
      $("#pendingListings").textContent = String(stats.pending_approval ?? "—");
      $("#soldListings").textContent = String(stats.sold_listings ?? "—");
      $("#sellerRevenue").textContent = stats.revenue === null ? "Unavailable" : money(stats.revenue);
      $("#sellerRating").textContent = stats.seller_rating === undefined ? "—" : `${stats.seller_rating} / 5 (${stats.review_count ?? 0})`;
      $("#activeSellerRequests").textContent = String(stats.active_requests_count ?? "—");
      $("#sellerRevenueNote").textContent = stats.revenue_note || "";
      visible($("#sellerRevenueNote"), Boolean(stats.revenue_note));
      visible($("#sellerStats"), true);
    } catch (error) {
      showError("#sellerDashboardError", error);
    } finally {
      visible($("#sellerDashboardLoading"), false);
    }
  }

  async function loadListings() {
    clearError("#sellerListingsError");
    visible($("#sellerListingsLoading"), true);
    visible($("#sellerListingsEmpty"), false);
    visible($("#sellerListingsWrap"), false);
    const params = {
      page: state.listingsPage,
      per_page: 20,
      status: $("#sellerListingStatus").value,
      search: $("#sellerListingSearch").value.trim()
    };
    try {
      const data = await getData("seller/listings.php", params);
      const paging = data.pagination || { page: 1, total_pages: 1 };
      if (paging.total_pages > 0 && state.listingsPage > paging.total_pages) {
        state.listingsPage = paging.total_pages;
        return loadListings();
      }
      state.listingsPage = paging.page || state.listingsPage;
      state.listingsPages = Math.max(1, paging.total_pages || 1);
      const body = $("#sellerListings");
      body.replaceChildren();
      const listings = Array.isArray(data.listings) ? data.listings : [];
      listings.forEach(listing => {
        const row = document.createElement("tr");
        const titleCell = cell(row, listing.title);
        const meta = document.createElement("small");
        meta.className = "workflow-muted";
        meta.textContent = [listing.course_code, listing.department, listing.item_type].filter(Boolean).join(" · ");
        titleCell.append(document.createElement("br"), meta);
        cell(row, money(listing.price));
        cell(row, listing.condition_type);
        const status = cell(row, "");
        const badge = document.createElement("span");
        badge.className = `workflow-status status-${listing.status}`;
        badge.textContent = String(listing.status).replaceAll("_", " ");
        status.append(badge);
        if (listing.admin_feedback) {
          const feedback = document.createElement("p");
          feedback.className = "workflow-note";
          feedback.textContent = `Admin feedback: ${listing.admin_feedback}`;
          status.append(feedback);
        }
        const actions = cell(row, "");
        actions.className = "workflow-actions";
        const edit = document.createElement("a");
        edit.className = "secondary";
        edit.href = `add-listing.html?edit_id=${encodeURIComponent(listing.id)}`;
        edit.textContent = "Edit";
        if (listing.status !== "sold") actions.append(edit);
        if (listing.status === "available") actions.append(actionButton("Mark sold", () => changeListingStatus(listing, "sold")));
        if (listing.status === "sold") actions.append(actionButton("Mark available", () => changeListingStatus(listing, "available")));
        if (listing.status !== "sold") actions.append(actionButton("Delete", () => deleteListing(listing), "danger"));
        row.append(actions);
        body.append(row);
      });
      const hasListings = listings.length > 0;
      visible($("#sellerListingsWrap"), hasListings);
      visible($("#sellerListingsEmpty"), !hasListings);
      pagination($("#sellerListingsPagination"), state.listingsPage, state.listingsPages, page => {
        state.listingsPage = page;
        loadListings();
      });
    } catch (error) {
      showError("#sellerListingsError", error);
    } finally {
      visible($("#sellerListingsLoading"), false);
    }
  }

  async function changeListingStatus(listing, status) {
    const key = `listing:${listing.id}`;
    if (pending.has(key)) return;
    const isSold = status === "sold";
    if (!window.confirm(isSold ? `Mark “${listing.title}” as sold?` : `Mark “${listing.title}” as available again?`)) return;
    pending.add(key);
    try {
      await auth.request(`seller/mark-${isSold ? "sold" : "unsold"}.php`, { method: "POST", body: { listing_id: listing.id } });
      await Promise.all([loadListings(), loadDashboard(), loadSellerRequests()]);
    } catch (error) {
      if (error.status === 409) await Promise.all([loadListings(), loadDashboard(), loadSellerRequests()]);
      showError("#sellerListingsError", error);
    } finally {
      pending.delete(key);
    }
  }

  async function deleteListing(listing) {
    const key = `delete:${listing.id}`;
    if (pending.has(key) || !window.confirm(`Delete “${listing.title}”? Listings with active or completed requests cannot be deleted.`)) return;
    pending.add(key);
    try {
      await auth.request("seller/delete-listing.php", { method: "POST", body: { listing_id: listing.id } });
      await Promise.all([loadListings(), loadDashboard(), loadSellerRequests()]);
    } catch (error) {
      if (error.status === 409) await loadListings();
      showError("#sellerListingsError", error);
    } finally {
      pending.delete(key);
    }
  }

  async function loadSellerRequests() {
    clearError("#sellerRequestsError");
    visible($("#sellerRequestsLoading"), true);
    visible($("#sellerRequestsEmpty"), false);
    const list = $("#sellerRequestsList");
    list.replaceChildren();
    try {
      const data = await getData("buyer/seller-requests.php", { status: $("#sellerRequestStatus").value });
      const requests = Array.isArray(data.requests) ? data.requests : [];
      requests.forEach(request => {
        const item = document.createElement("article");
        item.className = "workflow-list-item";
        const heading = document.createElement("div");
        heading.className = "workflow-item-heading";
        const title = document.createElement("h3");
        title.textContent = request.book_title || "Listing";
        const status = document.createElement("span");
        status.className = `workflow-status status-${request.status}`;
        status.textContent = String(request.status).replaceAll("_", " ");
        heading.append(title, status);
        item.append(heading);
        const details = document.createElement("p");
        details.className = "workflow-muted";
        details.textContent = `${request.buyer_name} · ${request.buyer_student_id || "Student ID unavailable"} · ${money(request.price)}`;
        item.append(details);
        const meeting = document.createElement("p");
        meeting.className = "workflow-muted";
        meeting.textContent = `Meet: ${request.meeting_location} · Preferred date: ${request.preferred_date || "—"} · Cash on Meet`;
        item.append(meeting);
        if (request.buyer_phone) {
          const phone = document.createElement("p");
          phone.className = "workflow-muted";
          phone.textContent = `Buyer phone: ${request.buyer_phone}`;
          item.append(phone);
        }
        if (request.note) {
          const note = document.createElement("p");
          note.className = "workflow-note";
          note.textContent = request.note;
          item.append(note);
        }
        const actions = document.createElement("div");
        actions.className = "workflow-actions";
        if (request.can_accept) actions.append(actionButton("Accept", () => requestAction(request, "accept"), "primary"));
        if (["pending", "accepted"].includes(request.status)) actions.append(actionButton("Decline", () => requestAction(request, "decline"), "secondary"));
        if (request.can_complete && request.listing_status === "available") actions.append(actionButton("Complete meetup", () => requestAction(request, "complete"), "primary"));
        if (request.buyer_id && ["accepted", "completed"].includes(request.status)) {
          const message = document.createElement("a");
          message.className = "secondary";
          message.href = `messages.html?${new URLSearchParams({ with_user_id: String(request.buyer_id), listing_id: String(request.listing_id) })}`;
          message.textContent = "Message buyer";
          actions.append(message);
        }
        if (actions.childElementCount) item.append(actions);
        list.append(item);
      });
      visible($("#sellerRequestsEmpty"), requests.length === 0);
    } catch (error) {
      showError("#sellerRequestsError", error);
    } finally {
      visible($("#sellerRequestsLoading"), false);
    }
  }

  async function requestAction(request, action) {
    const key = `request:${request.id}`;
    if (pending.has(key)) return;
    if (action === "complete" && !window.confirm("Confirm the Cash on Meet transaction is complete? This marks the listing sold and closes other pending requests.")) return;
    pending.add(key);
    try {
      await auth.request("buyer/seller-request-action.php", { method: "POST", body: { request_id: request.id, action } });
      await Promise.all([loadSellerRequests(), loadListings(), loadDashboard()]);
    } catch (error) {
      if (error.status === 409 || error.status === 422) await Promise.all([loadSellerRequests(), loadListings(), loadDashboard()]);
      showError("#sellerRequestsError", error);
    } finally {
      pending.delete(key);
    }
  }

  async function loadSellerReviews(sellerId) {
    clearError("#sellerReviewsError");
    visible($("#sellerReviewsLoading"), true);
    visible($("#sellerReviewsEmpty"), false);
    const list = $("#sellerReviewsList");
    list.replaceChildren();
    try {
      const data = await getData("reviews/seller-reviews.php", { seller_id: sellerId });
      const reviews = Array.isArray(data.reviews) ? data.reviews : [];
      reviews.forEach(review => {
        const item = document.createElement("article");
        item.className = "workflow-list-item";
        const title = document.createElement("h3");
        title.textContent = review.book_title || "Buyer review";
        item.append(title);
        const rating = document.createElement("p");
        rating.className = "workflow-rating";
        rating.textContent = `${"★".repeat(review.rating)}${"☆".repeat(Math.max(0, 5 - review.rating))} · ${review.reviewer_name}`;
        item.append(rating);
        if (review.comment) {
          const comment = document.createElement("p");
          comment.textContent = review.comment;
          item.append(comment);
        }
        const created = document.createElement("small");
        created.className = "workflow-muted";
        created.textContent = date(review.created_at);
        item.append(created);
        list.append(item);
      });
      visible($("#sellerReviewsEmpty"), reviews.length === 0);
    } catch (error) {
      showError("#sellerReviewsError", error);
    } finally {
      visible($("#sellerReviewsLoading"), false);
    }
  }

  async function initDashboard(user) {
    $("#sellerGreeting").textContent = `Hello, ${user.full_name}`;
    $("#sellerListingFilters").addEventListener("submit", event => {
      event.preventDefault();
      state.listingsPage = 1;
      loadListings();
    });
    $("#sellerListingReset").addEventListener("click", () => {
      $("#sellerListingFilters").reset();
      state.listingsPage = 1;
      loadListings();
    });
    $("#sellerRequestFilters").addEventListener("submit", event => {
      event.preventDefault();
      loadSellerRequests();
    });
    document.body.removeAttribute("data-auth-checking");
    await Promise.all([loadDashboard(), loadListings(), loadSellerRequests(), loadSellerReviews(user.id)]);
  }

  async function initListingForm() {
    const form = $("#listingForm");
    const editId = Number(new URLSearchParams(window.location.search).get("edit_id"));
    const idField = $("#listingId");
    const message = $("#listingMessage");
    const error = $("#listingError");
    if (editId > 0) {
      visible($("#listingLoading"), true);
      try {
        const data = await getData("seller/listings.php", { id: editId });
        const listing = data.listing;
        idField.value = String(listing.id);
        $("#title").value = listing.title || "";
        $("#author").value = listing.author || "";
        $("#edition").value = listing.edition || "";
        $("#courseCode").value = listing.course_code || "";
        $("#department").value = listing.department || "";
        $("#subject").value = listing.subject || "";
        $("#itemType").value = listing.item_type || "Textbook";
        $("#condition").value = listing.condition_type || "Good";
        $("#price").value = String(listing.price ?? "");
        $("#description").value = listing.description || "";
        $("#imageUrl").value = listing.image_url || "";
        $("#listingHeading").textContent = "Edit listing";
        $("#listingSubmit").textContent = "Save changes";
        if (listing.admin_feedback) {
          $("#listingFeedback").textContent = `Admin feedback: ${listing.admin_feedback}`;
          visible($("#listingFeedback"), true);
        }
      } catch (requestError) {
        showError("#listingError", requestError);
        form.querySelectorAll("input,select,textarea,button").forEach(control => { control.disabled = true; });
      } finally {
        visible($("#listingLoading"), false);
      }
    }
    document.body.removeAttribute("data-auth-checking");
    form.addEventListener("submit", async event => {
      event.preventDefault();
      if (!form.reportValidity() || pending.has("listing-submit")) return;
      pending.add("listing-submit");
      $("#listingSubmit").disabled = true;
      clearError("#listingError");
      visible(message, false);
      try {
        let imageUrl = $("#imageUrl").value.trim() || null;
        const imageFile = $("#listingImage").files[0];
        if (imageFile) {
          const upload = new FormData();
          upload.append("image", imageFile);
          const result = await auth.request("seller/upload-image.php", { method: "POST", body: upload });
          imageUrl = result.data?.image_url || null;
          $("#imageUrl").value = imageUrl || "";
          $("#listingImage").value = "";
        }
        const body = {
          title: $("#title").value.trim(),
          author: $("#author").value.trim() || null,
          edition: $("#edition").value.trim() || null,
          course_code: $("#courseCode").value.trim().toUpperCase(),
          department: $("#department").value.trim(),
          subject: $("#subject").value.trim() || null,
          item_type: $("#itemType").value,
          condition_type: $("#condition").value,
          price: Number($("#price").value),
          description: $("#description").value.trim(),
          image_url: imageUrl
        };
        const listingId = Number(idField.value);
        const result = listingId
          ? await auth.request("seller/edit-listing.php", { method: "PUT", body: { id: listingId, ...body } })
          : await auth.request("seller/add-listing.php", { method: "POST", body });
        message.textContent = result.message || (listingId ? "Listing updated." : "Listing submitted for review.");
        visible(message, true);
        if (!listingId) form.reset();
      } catch (requestError) {
        showError("#listingError", requestError);
      } finally {
        pending.delete("listing-submit");
        $("#listingSubmit").disabled = false;
      }
    });
  }

  async function loadSales() {
    clearError("#sellerSalesError");
    visible($("#sellerSalesLoading"), true);
    visible($("#sellerSalesEmpty"), false);
    const body = $("#sellerSales");
    body.replaceChildren();
    try {
      const data = await getData("seller/sales-history.php", { page: state.salesPage, per_page: 20 });
      const paging = data.pagination || { page: 1, total_pages: 1 };
      if (state.salesPage > Math.max(1, paging.total_pages || 1)) {
        state.salesPage = Math.max(1, paging.total_pages || 1);
        return loadSales();
      }
      state.salesPage = paging.page || state.salesPage;
      state.salesPages = Math.max(1, paging.total_pages || 1);
      const sales = Array.isArray(data.sales) ? data.sales : [];
      sales.forEach(sale => {
        const row = document.createElement("tr");
        cell(row, sale.title);
        cell(row, sale.buyer_name || "Buyer unavailable");
        cell(row, sale.completed_at ? date(sale.completed_at) : date(sale.sold_at));
        cell(row, sale.meeting_location);
        cell(row, sale.purchase_request_id ? "Completed" : "Marked sold; no completed request recorded");
        body.append(row);
      });
      visible($("#sellerSalesEmpty"), sales.length === 0);
      pagination($("#sellerSalesPagination"), state.salesPage, state.salesPages, page => {
        state.salesPage = page;
        loadSales();
      });
    } catch (error) {
      showError("#sellerSalesError", error);
    } finally {
      visible($("#sellerSalesLoading"), false);
    }
  }

  async function initialize() {
    const file = window.location.pathname.split("/").pop();
    const user = await auth.requireRole("seller");
    if (!user) return;
    if (file === "add-listing.html") {
      await initListingForm();
      return;
    }
    if (file === "seller-sales.html") {
      document.body.removeAttribute("data-auth-checking");
      await loadSales();
      return;
    }
    await initDashboard(user);
  }

  if (auth?.ready) initialize().catch(error => {
    const target = $("#sellerDashboardError") ? "#sellerDashboardError" : "#listingError";
    if ($(target)) showError(target, error);
    document.body.removeAttribute("data-auth-checking");
  });
})();
