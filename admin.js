(() => {
  "use strict";

  const auth = window.BookBridgeAuth;
  const $ = selector => document.querySelector(selector);
  const state = {
    pendingPage: 1,
    reportPage: 1,
    pendingTotalPages: 1,
    reportTotalPages: 1,
    categories: [],
    departments: [],
    requestVersions: {},
    deletingCategories: new Set(),
    submittingReview: false,
    submittingCategory: false
  };

  function startRequest(section) {
    state.requestVersions[section] = (state.requestVersions[section] || 0) + 1;
    return state.requestVersions[section];
  }

  function isLatest(section, version) {
    return state.requestVersions[section] === version;
  }

  const modalOpeners = new WeakMap();
  function setVisible(element, visible) {
    if (!element) return;
    if (element.matches('[role="dialog"]')) {
      if (visible && element.hidden) modalOpeners.set(element, document.activeElement);
      if (!visible && !element.hidden) modalOpeners.get(element)?.focus();
    }
    element.hidden = !visible;
  }

  function showError(element, error, retry) {
    if (!element) return;
    element.replaceChildren(document.createTextNode(error?.message || "The request failed. Please try again."));
    if (retry) {
      const button = document.createElement("button");
      button.type = "button";
      button.textContent = "Retry";
      button.addEventListener("click", retry, { once: true });
      element.append(button);
    }
    setVisible(element, true);
  }

  function clearMessage(element) {
    if (!element) return;
    element.replaceChildren();
    setVisible(element, false);
  }

  function addCell(row, value) {
    const cell = document.createElement("td");
    cell.textContent = value === null || value === undefined || value === "" ? "—" : String(value);
    row.append(cell);
    return cell;
  }

  function displayCount(value) {
    return Number.isInteger(Number(value)) && value !== null && value !== undefined ? String(value) : "—";
  }

  function formatDate(value) {
    if (!value) return "—";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short"
    }).format(date);
  }

  function formatMoney(value) {
    if (value === null || value === undefined || value === "") return "—";
    const amount = Number(value);
    return Number.isFinite(amount) ? new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: "BDT",
      maximumFractionDigits: 2
    }).format(amount) : "—";
  }

  function queryString(values) {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) params.set(key, String(value));
    });
    return params.toString();
  }

  async function getData(endpoint, params = {}) {
    const query = queryString(params);
    const result = await auth.request(`${endpoint}${query ? `?${query}` : ""}`);
    return result.data || {};
  }

  function setPagination(container, page, totalPages, onPage) {
    container.replaceChildren();
    setVisible(container, totalPages > 1);
    if (totalPages <= 1) return;
    const previous = document.createElement("button");
    previous.type = "button";
    previous.textContent = "Previous";
    previous.disabled = page <= 1;
    previous.addEventListener("click", () => onPage(page - 1));
    const label = document.createElement("span");
    label.textContent = `Page ${page} of ${totalPages}`;
    const next = document.createElement("button");
    next.type = "button";
    next.textContent = "Next";
    next.disabled = page >= totalPages;
    next.addEventListener("click", () => onPage(page + 1));
    container.append(previous, label, next);
  }

  async function loadDashboard() {
    const version = startRequest("dashboard");
    const loading = $("#dashboardLoading");
    const stats = $("#dashboardStats");
    const userStats = $("#dashboardUserStats");
    const errorBox = $("#dashboardError");
    clearMessage(errorBox);
    setVisible(stats, false);
    setVisible(userStats, false);
    setVisible(loading, true);
    try {
      const data = await getData("admin/dashboard.php");
      if (!isLatest("dashboard", version)) return;
      const listings = data.listing_counts || {};
      const users = data.user_counts || {};
      $("#statPending").textContent = displayCount(data.pending_review_count);
      $("#statAvailable").textContent = displayCount(listings.available);
      $("#statSold").textContent = displayCount(listings.sold);
      $("#statSales").textContent = displayCount(data.completed_sales_count);
      $("#statRevenue").textContent = formatMoney(data.revenue);
      $("#statBuyers").textContent = displayCount(users.buyer);
      $("#statSellers").textContent = displayCount(users.seller);
      $("#statAdmins").textContent = displayCount(users.admin);
      $("#statChanges").textContent = displayCount(listings.changes_requested);
      $("#statRejected").textContent = displayCount(listings.rejected);
      setVisible(stats, true);
      setVisible(userStats, true);
      $("#revenueNote").textContent = data.revenue_note || "";
      setVisible($("#revenueNote"), Boolean(data.revenue_note));
    } catch (error) {
      if (!isLatest("dashboard", version)) return;
      showError(errorBox, error, loadDashboard);
    } finally {
      if (isLatest("dashboard", version)) setVisible(loading, false);
    }
  }

  async function loadPending() {
    const version = startRequest("pending");
    const loading = $("#modLoading");
    const errorBox = $("#modError");
    clearMessage(errorBox);
    setVisible($("#modEmpty"), false);
    setVisible($("#modTableWrap"), false);
    setVisible($("#modPagination"), false);
    setVisible(loading, true);
    const params = {
      page: state.pendingPage,
      per_page: 10,
      search: $("#modSearch").value.trim(),
      department: $("#modDept").value.trim(),
      type: $("#modType").value,
      sort: $("#modSort").value,
      direction: $("#modDir").value
    };
    try {
      const data = await getData("admin/pending-listings.php", params);
      if (!isLatest("pending", version)) return;
      const pagination = data.pagination || { page: 1, total: 0, total_pages: 0 };
      if (pagination.total_pages > 0 && state.pendingPage > pagination.total_pages) {
        state.pendingPage = pagination.total_pages;
        return loadPending();
      }
      state.pendingPage = pagination.page || state.pendingPage;
      state.pendingTotalPages = Math.max(1, pagination.total_pages || 1);
      const body = $("#modTbody");
      body.replaceChildren();
      (data.listings || []).forEach(listing => {
        const row = document.createElement("tr");
        const titleCell = addCell(row, listing.title);
        if (listing.course_code || listing.subject) {
          const detail = document.createElement("small");
          detail.className = "subline";
          detail.textContent = [listing.course_code, listing.subject].filter(Boolean).join(" · ");
          titleCell.append(document.createElement("br"), detail);
        }
        addCell(row, listing.item_type);
        addCell(row, listing.seller_name);
        addCell(row, formatMoney(listing.price));
        addCell(row, formatDate(listing.created_at));
        const actions = document.createElement("td");
        actions.className = "row-actions";
        const review = document.createElement("button");
        review.type = "button";
        review.textContent = "Review";
        review.addEventListener("click", () => openReview(listing));
        actions.append(review);
        row.append(actions);
        body.append(row);
      });
      const hasRows = (data.listings || []).length > 0;
      setVisible($("#modTableWrap"), hasRows);
      setVisible($("#modEmpty"), !hasRows);
      setPagination($("#modPagination"), state.pendingPage, state.pendingTotalPages, page => {
        state.pendingPage = page;
        loadPending();
      });
    } catch (error) {
      if (!isLatest("pending", version)) return;
      showError(errorBox, error, loadPending);
    } finally {
      if (isLatest("pending", version)) setVisible(loading, false);
    }
  }

  function appendDetail(dl, label, value, link = false) {
    if (value === null || value === undefined || value === "") return;
    const term = document.createElement("dt");
    term.textContent = label;
    const description = document.createElement("dd");
    if (link) {
      try {
        const url = new URL(String(value), window.location.href);
        if (url.protocol === "http:" || url.protocol === "https:") {
          const anchor = document.createElement("a");
          anchor.href = url.href;
          anchor.target = "_blank";
          anchor.rel = "noopener noreferrer";
          anchor.textContent = "Open listing image";
          description.append(anchor);
        } else {
          description.textContent = String(value);
        }
      } catch {
        description.textContent = String(value);
      }
    } else {
      description.textContent = String(value);
    }
    dl.append(term, description);
  }

  function openReview(listing) {
    $("#reviewListingId").value = String(listing.id);
    $("#modalListingTitle").textContent = listing.title || "Untitled listing";
    const dl = document.createElement("dl");
    appendDetail(dl, "Author", listing.author);
    appendDetail(dl, "Edition", listing.edition);
    appendDetail(dl, "Course", listing.course_code);
    appendDetail(dl, "Department", listing.department);
    appendDetail(dl, "Subject", listing.subject);
    appendDetail(dl, "Category", [listing.category_name, listing.category_type].filter(Boolean).join(" · "));
    appendDetail(dl, "Type", listing.item_type);
    appendDetail(dl, "Condition", listing.condition_type);
    appendDetail(dl, "Price", formatMoney(listing.price));
    appendDetail(dl, "Seller", listing.seller_name);
    appendDetail(dl, "Submitted", formatDate(listing.created_at));
    appendDetail(dl, "Description", listing.description);
    appendDetail(dl, "Current feedback", listing.admin_feedback);
    appendDetail(dl, "Image", listing.image_url, true);
    $("#modalDetails").replaceChildren(dl);
    $("#reviewForm").reset();
    $("#reviewListingId").value = String(listing.id);
    clearMessage($("#modalError"));
    setVisible($("#feedbackGroup"), false);
    setVisible($("#reviewModal"), true);
    $("#reviewModal").querySelector('input[name="action"]').focus();
  }

  function closeReview() {
    if (state.submittingReview) return;
    setVisible($("#reviewModal"), false);
  }

  async function submitReview(event) {
    event.preventDefault();
    if (state.submittingReview) return;
    const form = event.currentTarget;
    const action = form.querySelector('input[name="action"]:checked')?.value || "";
    const feedback = $("#reviewFeedback").value.trim();
    if (!action) {
      showError($("#modalError"), new Error("Choose a moderation decision."));
      return;
    }
    if ((action === "reject" || action === "changes_requested") && !feedback) {
      setVisible($("#feedbackGroup"), true);
      $("#reviewFeedback").focus();
      showError($("#modalError"), new Error("Feedback is required when rejecting or requesting changes."));
      return;
    }
    state.submittingReview = true;
    const button = $("#reviewSubmitBtn");
    button.disabled = true;
    clearMessage($("#modalError"));
    try {
      await auth.request("admin/review-listing.php", {
        method: "POST",
        body: {
          listing_id: Number($("#reviewListingId").value),
          action,
          ...(action === "approve" ? {} : { admin_feedback: feedback })
        }
      });
      setVisible($("#reviewModal"), false);
      await Promise.all([loadPending(), loadDashboard()]);
    } catch (error) {
      showError($("#modalError"), error);
      if (error.status === 409) {
        setVisible($("#reviewModal"), false);
        await Promise.all([loadPending(), loadDashboard()]);
        showError($("#modError"), new Error("This listing was already reviewed. The queue has been refreshed."));
      }
    } finally {
      state.submittingReview = false;
      button.disabled = false;
    }
  }

  function fillDepartmentSelect(select, includeAll) {
    const selected = select.value;
    select.replaceChildren();
    const first = document.createElement("option");
    first.value = "";
    first.textContent = includeAll ? "All departments" : "— select department —";
    select.append(first);
    state.departments.forEach(category => {
      const option = document.createElement("option");
      option.value = category.name;
      option.textContent = category.name;
      select.append(option);
    });
    if ([...select.options].some(option => option.value === selected)) select.value = selected;
  }

  async function loadCategories() {
    const version = startRequest("categories");
    const loading = $("#catLoading");
    const errorBox = $("#catError");
    clearMessage(errorBox);
    clearMessage($("#catSuccess"));
    setVisible($("#catEmpty"), false);
    setVisible($("#catTableWrap"), false);
    setVisible(loading, true);
    try {
      const [data, departments] = await Promise.all([
        getData("admin/categories.php", {
          search: $("#catSearch").value.trim(),
          type: $("#catFilterType").value,
          department: $("#catFilterDepartment").value
        }),
        getData("admin/categories.php", { type: "Department" })
      ]);
      if (!isLatest("categories", version)) return;
      state.categories = data.categories || [];
      state.departments = departments.categories || [];
      fillDepartmentSelect($("#catFilterDepartment"), true);
      fillDepartmentSelect($("#catFormDept"), false);
      const body = $("#catTbody");
      body.replaceChildren();
      state.categories.forEach(category => {
        const row = document.createElement("tr");
        addCell(row, category.name);
        addCell(row, category.type);
        addCell(row, category.type === "Subject" ? category.department : "—");
        const actions = document.createElement("td");
        actions.className = "row-actions";
        const edit = document.createElement("button");
        edit.type = "button";
        edit.textContent = "Rename";
        edit.addEventListener("click", () => openCategory(category));
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "danger";
        remove.textContent = "Delete";
        remove.addEventListener("click", () => deleteCategory(category, remove));
        actions.append(edit, remove);
        row.append(actions);
        body.append(row);
      });
      const hasRows = state.categories.length > 0;
      setVisible($("#catTableWrap"), hasRows);
      setVisible($("#catEmpty"), !hasRows);
    } catch (error) {
      if (!isLatest("categories", version)) return;
      showError(errorBox, error, loadCategories);
    } finally {
      if (isLatest("categories", version)) setVisible(loading, false);
    }
  }

  function openCategory(category = null) {
    const form = $("#catForm");
    form.reset();
    clearMessage($("#catModalError"));
    const editing = Boolean(category);
    $("#catFormId").value = editing ? String(category.id) : "";
    $("#catFormName").value = editing ? category.name : "";
    $("#catModalTitle").textContent = editing
      ? `Rename ${category.type.toLowerCase()}${category.type === "Subject" && category.department ? ` in ${category.department}` : ""}`
      : "Add Category";
    setVisible($("#catTypeGroup"), !editing);
    setVisible($("#catDeptGroup"), !editing && $("#catFormType").value === "Subject");
    setVisible($("#catModal"), true);
    $("#catFormName").focus();
  }

  function closeCategory() {
    if (state.submittingCategory) return;
    setVisible($("#catModal"), false);
  }

  async function submitCategory(event) {
    event.preventDefault();
    if (state.submittingCategory) return;
    const id = $("#catFormId").value;
    const name = $("#catFormName").value.trim();
    if (!name || name.length > 100) {
      showError($("#catModalError"), new Error("Enter a category name between 1 and 100 characters."));
      return;
    }
    const body = id
      ? { id: Number(id), name }
      : {
          name,
          type: $("#catFormType").value,
          ...($("#catFormType").value === "Subject" ? { department: $("#catFormDept").value } : {})
        };
    if (!id && body.type === "Subject" && !body.department) {
      showError($("#catModalError"), new Error("Choose an existing parent department for this subject."));
      return;
    }
    state.submittingCategory = true;
    const button = $("#catSubmitBtn");
    button.disabled = true;
    clearMessage($("#catModalError"));
    try {
      await auth.request("admin/categories.php", { method: id ? "PUT" : "POST", body });
      setVisible($("#catModal"), false);
      await loadCategories();
      const success = $("#catSuccess");
      success.textContent = id ? "Category renamed; linked records remain associated." : "Category created.";
      setVisible(success, true);
    } catch (error) {
      showError($("#catModalError"), error);
    } finally {
      state.submittingCategory = false;
      button.disabled = false;
    }
  }

  async function deleteCategory(category, button) {
    if (state.deletingCategories.has(category.id)) return;
    if (!window.confirm(`Delete ${category.type.toLowerCase()} “${category.name}”? Referenced categories cannot be deleted.`)) return;
    state.deletingCategories.add(category.id);
    button.disabled = true;
    clearMessage($("#catError"));
    try {
      await auth.request("admin/categories.php", { method: "DELETE", body: { id: category.id } });
      await loadCategories();
      const success = $("#catSuccess");
      success.textContent = "Category deleted.";
      setVisible(success, true);
    } catch (error) {
      showError($("#catError"), error);
    } finally {
      state.deletingCategories.delete(category.id);
      button.disabled = false;
    }
  }

  async function loadReport() {
    const version = startRequest("report");
    const loading = $("#reportLoading");
    const errorBox = $("#reportError");
    clearMessage(errorBox);
    setVisible($("#reportEmpty"), false);
    setVisible($("#reportTableWrap"), false);
    setVisible($("#reportSummary"), false);
    setVisible($("#reportPagination"), false);
    setVisible(loading, true);
    const from = $("#reportFrom").value;
    const to = $("#reportTo").value;
    if (from && to && from > to) {
      setVisible(loading, false);
      showError(errorBox, new Error("The From date must be on or before the To date."));
      return;
    }
    try {
      const data = await getData("admin/sales-report.php", { from, to, page: state.reportPage, per_page: 10 });
      if (!isLatest("report", version)) return;
      const pagination = data.pagination || { page: 1, total: 0, total_pages: 0 };
      if (pagination.total_pages > 0 && state.reportPage > pagination.total_pages) {
        state.reportPage = pagination.total_pages;
        return loadReport();
      }
      state.reportPage = pagination.page || state.reportPage;
      state.reportTotalPages = Math.max(1, pagination.total_pages || 1);
      $("#reportTotalSales").textContent = displayCount(data.summary?.completed_sales_count);
      $("#reportRevenue").textContent = formatMoney(data.revenue);
      $("#reportRevenueNote").textContent = data.revenue_note || "";
      setVisible($("#reportSummary"), true);
      const body = $("#reportTbody");
      body.replaceChildren();
      (data.transactions || []).forEach(transaction => {
        const row = document.createElement("tr");
        addCell(row, transaction.purchase_request_id);
        addCell(row, transaction.listing_title);
        addCell(row, transaction.buyer_name);
        addCell(row, transaction.seller_name);
        addCell(row, formatDate(transaction.completed_at));
        addCell(row, transaction.sale_price == null ? "Not recorded" : formatMoney(transaction.sale_price));
        body.append(row);
      });
      const hasRows = (data.transactions || []).length > 0;
      setVisible($("#reportTableWrap"), hasRows);
      setVisible($("#reportEmpty"), !hasRows);
      setPagination($("#reportPagination"), state.reportPage, state.reportTotalPages, page => {
        state.reportPage = page;
        loadReport();
      });
    } catch (error) {
      if (!isLatest("report", version)) return;
      showError(errorBox, error, loadReport);
    } finally {
      if (isLatest("report", version)) setVisible(loading, false);
    }
  }

  function activateTab(name) {
    document.querySelectorAll("[data-tab]").forEach(button => {
      const selected = button.dataset.tab === name;
      button.classList.toggle("active", selected);
      button.setAttribute("aria-selected", String(selected));
      button.tabIndex = selected ? 0 : -1;
    });
    document.querySelectorAll(".tab-panel").forEach(panel => {
      setVisible(panel, panel.id === `tab-${name}`);
    });
    if (name === "dashboard") loadDashboard();
    if (name === "moderation") loadPending();
    if (name === "categories") loadCategories();
    if (name === "reports") loadReport();
  }

  function wireEvents() {
    document.querySelectorAll("[data-tab]").forEach(button => {
      button.addEventListener("click", () => activateTab(button.dataset.tab));
      button.addEventListener("keydown", event => {
        if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
        event.preventDefault();
        const tabs = [...document.querySelectorAll("[data-tab]")];
        const index = tabs.indexOf(button);
        const offset = event.key === "ArrowRight" ? 1 : -1;
        const next = tabs[(index + offset + tabs.length) % tabs.length];
        next.focus();
        activateTab(next.dataset.tab);
      });
    });
    $("#modFiltersForm").addEventListener("submit", event => {
      event.preventDefault();
      state.pendingPage = 1;
      loadPending();
    });
    $("#modResetBtn").addEventListener("click", () => {
      $("#modFiltersForm").reset();
      state.pendingPage = 1;
      loadPending();
    });
    document.querySelectorAll('input[name="action"]').forEach(radio => {
      radio.addEventListener("change", () => {
        if (!radio.checked) return;
        const needsFeedback = radio.value !== "approve";
        if (!needsFeedback) $("#reviewFeedback").value = "";
        setVisible($("#feedbackGroup"), needsFeedback);
      });
    });
    $("#reviewForm").addEventListener("submit", submitReview);
    $("#reviewCancelBtn").addEventListener("click", closeReview);
    $("#reviewModal").addEventListener("click", event => {
      if (event.target === $("#reviewModal")) closeReview();
    });
    $("#catFiltersForm").addEventListener("submit", event => {
      event.preventDefault();
      loadCategories();
    });
    $("#catResetBtn").addEventListener("click", () => {
      $("#catFiltersForm").reset();
      loadCategories();
    });
    $("#catAddBtn").addEventListener("click", () => openCategory());
    $("#catFormType").addEventListener("change", () => {
      setVisible($("#catDeptGroup"), $("#catFormType").value === "Subject");
    });
    $("#catForm").addEventListener("submit", submitCategory);
    $("#catCancelBtn").addEventListener("click", closeCategory);
    $("#catModal").addEventListener("click", event => {
      if (event.target === $("#catModal")) closeCategory();
    });
    $("#reportFiltersForm").addEventListener("submit", event => {
      event.preventDefault();
      state.reportPage = 1;
      loadReport();
    });
    $("#reportResetBtn").addEventListener("click", () => {
      $("#reportFiltersForm").reset();
      state.reportPage = 1;
      loadReport();
    });
    document.addEventListener("keydown", event => {
      const modal = document.querySelector('[role="dialog"]:not([hidden])');
      if (event.key === "Tab" && modal) {
        const controls = [...modal.querySelectorAll('a[href], button, input, select, textarea')]
          .filter(element => !element.disabled && element.type !== "hidden" && element.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault(); last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault(); first?.focus();
        }
      }
      if (event.key !== "Escape") return;
      if (!$("#reviewModal").hidden) closeReview();
      if (!$("#catModal").hidden) closeCategory();
    });
  }

  async function initialize() {
    if (!auth) return;
    await auth.ready;
    if (auth.currentUser?.role !== "admin") return;
    const name = auth.currentUser.full_name || "Administrator";
    $("#adminNavName").textContent = name;
    $("#adminGreeting").textContent = `Welcome, ${name}`;
    wireEvents();
    document.body.removeAttribute("data-auth-checking");
    activateTab("dashboard");
  }

  if (auth?.ready) {
    initialize().catch(error => {
      showError($("#dashboardError"), error, initialize);
      document.body.removeAttribute("data-auth-checking");
    });
  }
})();