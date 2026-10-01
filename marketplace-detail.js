(() => {
  "use strict";

  const auth = window.BookBridgeAuth;
  const $ = selector => document.querySelector(selector);

  function visible(element, show) {
    element.hidden = !show;
  }

  function errorMessage(selector, text) {
    const element = $(selector);
    element.textContent = text;
    visible(element, true);
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

  function money(value) {
    const price = Number(value);
    return Number.isFinite(price)
      ? new Intl.NumberFormat("en-BD", { style: "currency", currency: "BDT", maximumFractionDigits: 2 }).format(price)
      : "Price unavailable";
  }

  async function loadListing() {
    const id = new URLSearchParams(window.location.search).get("id");
    if (!id || !/^[1-9][0-9]*$/.test(id)) {
      throw new Error("Open this page from a listing in guest browse.");
    }
    const response = await auth.request(`marketplace/listing-details.php?id=${encodeURIComponent(id)}`);
    return response.data?.listing;
  }

  async function loadWishlistState(listingId) {
    const response = await auth.request("buyer/wishlist.php");
    const items = response.data?.items || [];
    return items.some(item => Number(item.listing_id) === Number(listingId));
  }

  async function renderListing() {
    await auth.ready;
    const user = auth.currentUser;
    const listing = await loadListing();
    if (!listing) throw new Error("Listing not found.");

    $("#listingDetailTitle").textContent = listing.title || "Untitled listing";
    $("#listingDetailMeta").textContent = [listing.course_code, listing.department, listing.subject, listing.item_type, listing.condition_type].filter(Boolean).join(" · ");
    $("#listingDetailAuthor").textContent = [listing.author, listing.edition].filter(Boolean).join(" · ") || "Author details not provided";
    $("#listingDetailDescription").textContent = listing.description || "No description provided.";
    $("#listingDetailSeller").textContent = listing.seller_name || "Seller";
    $("#listingDetailPrice").textContent = money(listing.price);
    $("#listingDetailRating").textContent = listing.seller_rating === null || listing.seller_rating === undefined
      ? "No seller reviews yet"
      : `${listing.seller_rating}/5 seller rating`;

    const image = $("#listingDetailImage");
    const imageUrl = safeImageUrl(listing.image_url);
    image.hidden = !imageUrl;
    if (imageUrl) {
      image.src = imageUrl;
      image.alt = listing.title ? `Cover for ${listing.title}` : "Book cover";
      image.addEventListener("error", () => { image.hidden = true; }, { once: true });
    }

    const requestLink = $("#listingRequestLink");
    const wishlistButton = $("#listingWishlistButton");
    const messageLink = $("#listingMessageLink");
    const actionNote = $("#listingActionNote");
    requestLink.hidden = true;
    wishlistButton.hidden = true;
    messageLink.hidden = true;
    actionNote.hidden = true;

    if (user?.role === "buyer") {
      const params = new URLSearchParams({ listing_id: String(listing.id), title: listing.title || "", price: String(listing.price ?? "") });
      requestLink.href = `purchase-request.html?${params}`;
      requestLink.textContent = "Send buy request";
      requestLink.hidden = false;
      wishlistButton.dataset.listingId = String(listing.id);
      wishlistButton.hidden = false;
      try {
        wishlistButton.dataset.saved = String(await loadWishlistState(listing.id));
        wishlistButton.textContent = wishlistButton.dataset.saved === "true" ? "Remove from wishlist" : "Save to wishlist";
      } catch (error) {
        errorMessage("#listingActionError", error.message || "Could not load wishlist status.");
        wishlistButton.disabled = true;
      }
    } else if (!user) {
      requestLink.href = "index.html";
      requestLink.textContent = "Sign in to request";
      requestLink.hidden = false;
      actionNote.textContent = "Sign in as a buyer to send a purchase request or save this listing.";
      actionNote.hidden = false;
    } else {
      actionNote.textContent = "Purchase requests are available to buyer accounts.";
      actionNote.hidden = false;
    }

    if (["buyer", "seller"].includes(user?.role) && Number(user.id) !== Number(listing.seller_id)) {
      messageLink.href = `messages.html?${new URLSearchParams({ with_user_id: String(listing.seller_id), listing_id: String(listing.id) })}`;
      messageLink.hidden = false;
    }

    wishlistButton.addEventListener("click", async () => {
      if (wishlistButton.disabled) return;
      wishlistButton.disabled = true;
      visible($("#listingActionError"), false);
      try {
        const action = wishlistButton.dataset.saved === "true" ? "remove" : "add";
        const result = await auth.request("buyer/wishlist.php", {
          method: "POST",
          body: { listing_id: Number(listing.id), action }
        });
        wishlistButton.dataset.saved = String(Boolean(result.data?.is_wishlisted));
        wishlistButton.textContent = result.data?.is_wishlisted ? "Remove from wishlist" : "Save to wishlist";
      } catch (error) {
        errorMessage("#listingActionError", error.message || "Could not update wishlist.");
      } finally {
        wishlistButton.disabled = false;
      }
    });

    visible($("#listingDetailsContent"), true);
  }

  async function initialize() {
    visible($("#listingDetailsLoading"), true);
    visible($("#listingDetailsContent"), false);
    try {
      await renderListing();
    } catch (error) {
      errorMessage("#listingDetailsError", error.message || "Could not load this listing.");
    } finally {
      visible($("#listingDetailsLoading"), false);
    }
  }

  if (auth?.ready) initialize();
})();
