(() => {
  "use strict";

  const apiBase = new URL("api/marketplace/", window.location.href);
  const $ = selector => document.querySelector(selector);
  let page = 1;
  let requestNumber = 0;
  let activeController = null;
  let searchTimer = null;
  let taxonomy = { departments: [], subjects: [], categories: [] };

  function visible(element, show) {
    element.hidden = !show;
  }

  function message(element, text) {
    element.replaceChildren(document.createTextNode(text));
    visible(element, true);
  }

  async function request(endpoint, params = {}, signal) {
    const url = new URL(endpoint, apiBase);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) url.searchParams.set(key, String(value));
    });
    let response;
    try {
      response = await fetch(url, {
        method: "GET",
        headers: { Accept: "application/json" },
        credentials: "same-origin",
        cache: "no-store",
        signal
      });
    } catch (error) {
      if (error.name === "AbortError") throw error;
      throw new Error("Marketplace is unreachable. Check that XAMPP Apache and MySQL are running.");
    }
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.success === false) {
      const details = payload?.errors ? Object.values(payload.errors).join(" ") : "";
      throw new Error([payload?.message || `Request failed (HTTP ${response.status}).`, details].filter(Boolean).join(" "));
    }
    return payload?.data || {};
  }

  function option(select, value, label) {
    const element = document.createElement("option");
    element.value = value;
    element.textContent = label;
    select.append(element);
  }

  function fillSelect(select, firstLabel, items, valueOf, labelOf) {
    const previous = select.value;
    select.replaceChildren();
    option(select, "", firstLabel);
    items.forEach(item => option(select, String(valueOf(item)), labelOf(item)));
    if ([...select.options].some(item => item.value === previous)) select.value = previous;
  }

  function updateSubjects() {
    const department = $("#department").value;
    const subjects = taxonomy.subjects.filter(subject => !department || subject.departments.includes(department));
    fillSelect($("#subject"), "All subjects", subjects, subject => subject.name, subject => subject.name);
  }

  async function loadTaxonomy() {
    try {
      const data = await request("categories.php");
      taxonomy = {
        departments: Array.isArray(data.departments) ? data.departments : [],
        subjects: Array.isArray(data.subjects) ? data.subjects : [],
        categories: Array.isArray(data.categories) ? data.categories : []
      };
      fillSelect($("#department"), "All departments", taxonomy.departments, item => item.name, item => item.name);
      updateSubjects();
      fillSelect($("#categoryId"), "All categories", taxonomy.categories, item => item.id, item => `${item.name} · ${item.type}`);
      fillSelect($("#itemType"), "All types", data.item_types || [], item => item, item => item);
      fillSelect($("#condition"), "All conditions", data.conditions || [], item => item, item => item);
    } catch (error) {
      message($("#filtersError"), error.message);
    }
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

  function formatMoney(value) {
    const price = Number(value);
    return Number.isFinite(price)
      ? new Intl.NumberFormat("en-BD", { style: "currency", currency: "BDT", maximumFractionDigits: 2 }).format(price)
      : "Price unavailable";
  }

  function renderListing(listing) {
    const card = document.createElement("article");
    card.className = "card marketplace-card";
    const imageUrl = safeImageUrl(listing.image_url);
    if (imageUrl) {
      const image = document.createElement("img");
      image.src = imageUrl;
      image.alt = listing.title || "Book cover";
      image.loading = "lazy";
      image.addEventListener("error", () => image.remove(), { once: true });
      card.append(image);
    }
    const content = document.createElement("div");
    content.className = "card-body";
    const course = document.createElement("span");
    course.className = "tag";
    course.textContent = [listing.course_code, listing.department].filter(Boolean).join(" · ");
    const title = document.createElement("h2");
    title.textContent = listing.title || "Untitled listing";
    const details = document.createElement("p");
    details.className = "muted";
    details.textContent = [listing.author, listing.subject, listing.item_type, listing.condition_type].filter(Boolean).join(" · ");
    const seller = document.createElement("p");
    seller.className = "muted";
    seller.textContent = listing.seller_rating === null || listing.seller_rating === undefined
      ? `Seller: ${listing.seller_name}`
      : `Seller: ${listing.seller_name} · ${listing.seller_rating}/5`;
    const price = document.createElement("span");
    price.className = "price";
    price.textContent = formatMoney(listing.price);
    const link = document.createElement("a");
    link.className = "primary marketplace-detail-link";
    link.href = `listing-details.html?id=${encodeURIComponent(listing.id)}`;
    link.textContent = "View details";
    content.append(course, title, details, seller, price, link);
    card.append(content);
    return card;
  }

  function updatePagination(pagination) {
    const container = $("#pagination");
    container.replaceChildren();
    const totalPages = Number(pagination?.total_pages) || 0;
    const currentPage = Number(pagination?.page) || page;
    visible(container, totalPages > 1);
    if (totalPages <= 1) return;
    const previous = document.createElement("button");
    previous.type = "button";
    previous.className = "secondary";
    previous.textContent = "Previous";
    previous.disabled = currentPage <= 1;
    previous.addEventListener("click", () => loadListings(currentPage - 1));
    const label = document.createElement("span");
    label.textContent = `Page ${currentPage} of ${totalPages}`;
    const next = document.createElement("button");
    next.type = "button";
    next.className = "secondary";
    next.textContent = "Next";
    next.disabled = currentPage >= totalPages;
    next.addEventListener("click", () => loadListings(currentPage + 1));
    container.append(previous, label, next);
  }

  function currentFilters() {
    return {
      search: $("#search").value.trim(),
      department: $("#department").value,
      subject: $("#subject").value,
      category_id: $("#categoryId").value,
      type: $("#itemType").value,
      condition: $("#condition").value,
      min_price: $("#minPrice").value,
      max_price: $("#maxPrice").value,
      sort: $("#sort").value,
      direction: $("#direction").value,
      page,
      per_page: $("#perPage").value
    };
  }

  async function loadListings(nextPage = 1) {
    page = nextPage;
    requestNumber += 1;
    const thisRequest = requestNumber;
    if (activeController) activeController.abort();
    activeController = new AbortController();
    const signal = activeController.signal;
    visible($("#resultsError"), false);
    visible($("#resultsEmpty"), false);
    visible($("#pagination"), false);
    visible($("#resultsLoading"), true);
    $("#bookGrid").replaceChildren();
    $("#resultCount").textContent = "Loading available listings…";
    try {
      const data = await request("listings.php", currentFilters(), signal);
      if (thisRequest !== requestNumber) return;
      const listings = Array.isArray(data.listings) ? data.listings : [];
      listings.forEach(listing => $("#bookGrid").append(renderListing(listing)));
      const total = Number(data.total);
      $("#resultCount").textContent = Number.isFinite(total) ? `${total} available listing${total === 1 ? "" : "s"}` : "Available listings";
      visible($("#resultsEmpty"), listings.length === 0);
      updatePagination(data.pagination);
    } catch (error) {
      if (error.name === "AbortError" || thisRequest !== requestNumber) return;
      message($("#resultsError"), error.message);
      $("#resultCount").textContent = "Listings could not be loaded";
    } finally {
      if (thisRequest === requestNumber) visible($("#resultsLoading"), false);
    }
  }

  $("#filtersForm").addEventListener("submit", event => {
    event.preventDefault();
    loadListings(1);
  });
  $("#resetFilters").addEventListener("click", () => {
    $("#filtersForm").reset();
    page = 1;
    updateSubjects();
    clearFilterError();
    loadListings(1);
  });
  $("#department").addEventListener("change", () => {
    updateSubjects();
    loadListings(1);
  });
  $("#perPage").addEventListener("change", () => loadListings(1));
  $("#search").addEventListener("input", () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadListings(1), 250);
  });

  function clearFilterError() {
    $("#filtersError").replaceChildren();
    visible($("#filtersError"), false);
  }

  loadTaxonomy();
  loadListings();
})();
