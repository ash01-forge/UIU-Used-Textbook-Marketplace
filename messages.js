(() => {
  "use strict";

  const auth = window.BookBridgeAuth;
  const $ = selector => document.querySelector(selector);
  const state = { partnerId: null, listingId: null, sending: false };

  function visible(element, show) {
    element.hidden = !show;
  }

  function showError(error, target = "#messagesError") {
    const box = $(target);
    box.textContent = error?.message || "The request failed. Please try again.";
    visible(box, true);
  }

  function clearError(target = "#messagesError") {
    const box = $(target);
    box.textContent = "";
    visible(box, false);
  }

  function formatTime(value) {
    if (!value) return "";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium", timeStyle: "short"
    }).format(date);
  }

  function renderMessage(message) {
    const bubble = document.createElement("article");
    bubble.className = `bubble${message.is_me ? " me" : ""}`;
    const text = document.createElement("p");
    text.textContent = message.message_text;
    const time = document.createElement("small");
    time.textContent = formatTime(message.created_at);
    bubble.append(text, time);
    return bubble;
  }

  async function getData(path) {
    const result = await auth.request(path);
    return result.data || {};
  }

  async function loadConversations() {
    clearError();
    visible($("#conversationsLoading"), true);
    visible($("#conversationsEmpty"), false);
    const container = $("#conversationList");
    container.replaceChildren();
    try {
      const data = await getData("messages/conversations.php");
      const conversations = Array.isArray(data.conversations) ? data.conversations : [];
      conversations.forEach(conversation => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "contact workflow-contact";
        button.dataset.partnerId = String(conversation.partner_id);
        button.dataset.listingId = conversation.listing_id ? String(conversation.listing_id) : "";
        const name = document.createElement("strong");
        name.textContent = conversation.partner_name;
        const preview = document.createElement("small");
        preview.textContent = conversation.last_message || "No messages";
        button.append(name, preview);
        if (conversation.unread_count > 0) {
          const unread = document.createElement("span");
          unread.className = "workflow-unread";
          unread.textContent = String(conversation.unread_count);
          button.append(unread);
        }
        button.addEventListener("click", () => selectConversation(conversation.partner_id, conversation.listing_id));
        container.append(button);
      });
      visible($("#conversationsEmpty"), conversations.length === 0);
      if (!state.partnerId && conversations.length) {
        await selectConversation(conversations[0].partner_id, conversations[0].listing_id);
      }
    } catch (error) {
      showError(error);
    } finally {
      visible($("#conversationsLoading"), false);
    }
  }

  async function selectConversation(partnerId, listingId = null) {
    state.partnerId = Number(partnerId);
    state.listingId = Number(listingId) || null;
    document.querySelectorAll(".workflow-contact").forEach(button => {
      button.classList.toggle("selected", Number(button.dataset.partnerId) === state.partnerId);
    });
    await loadThread();
  }

  async function loadThread() {
    if (!state.partnerId) return;
    clearError("#sendError");
    visible($("#threadLoading"), true);
    visible($("#threadEmpty"), false);
    $("#messageList").replaceChildren();
    try {
      const data = await getData(`messages/thread.php?with_user_id=${encodeURIComponent(state.partnerId)}`);
      $("#currentPartner").textContent = data.partner?.full_name || "Conversation";
      const messages = Array.isArray(data.messages) ? data.messages : [];
      const listingTitle = messages.find(message => message.listing_title)?.listing_title;
      $("#currentListing").textContent = listingTitle || (state.listingId ? `Listing #${state.listingId}` : "");
      messages.forEach(message => $("#messageList").append(renderMessage(message)));
      visible($("#threadEmpty"), messages.length === 0);
      $("#messageInput").disabled = false;
      $("#sendMessage").disabled = false;
      $("#messageList").scrollTop = $("#messageList").scrollHeight;
    } catch (error) {
      showError(error, "#sendError");
      $("#messageInput").disabled = true;
      $("#sendMessage").disabled = true;
    } finally {
      visible($("#threadLoading"), false);
    }
  }

  async function sendMessage(event) {
    event.preventDefault();
    if (state.sending || !state.partnerId) return;
    const input = $("#messageInput");
    const messageText = input.value.trim();
    if (!messageText || messageText.length > 2000) return;
    state.sending = true;
    input.disabled = true;
    $("#sendMessage").disabled = true;
    clearError("#sendError");
    try {
      await auth.request("messages/send.php", {
        method: "POST",
        body: {
          receiver_id: state.partnerId,
          ...(state.listingId ? { listing_id: state.listingId } : {}),
          message_text: messageText
        }
      });
      input.value = "";
      await Promise.all([loadThread(), loadConversations()]);
    } catch (error) {
      showError(error, "#sendError");
    } finally {
      state.sending = false;
      input.disabled = !state.partnerId;
      $("#sendMessage").disabled = !state.partnerId;
    }
  }

  async function initialize() {
    const user = await auth.requireRole(["buyer", "seller"]);
    if (!user) return;
    $("#messagesDashboardLink").href = user.role === "seller" ? "seller-dashboard.html" : "buyer-dashboard.html";
    document.body.removeAttribute("data-auth-checking");
    $("#composer").addEventListener("submit", sendMessage);
    const params = new URLSearchParams(window.location.search);
    const partnerId = Number(params.get("with_user_id"));
    state.listingId = Number(params.get("listing_id")) || null;
    await loadConversations();
    if (partnerId > 0) await selectConversation(partnerId, state.listingId);
  }

  if (auth?.ready) {
    initialize().catch(error => showError(error));
  }
})();
