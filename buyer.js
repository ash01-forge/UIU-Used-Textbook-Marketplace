const REQUEST_KEY = "bookbridgePurchaseRequests";
const form = document.getElementById("purchaseForm");
if (form) form.addEventListener("submit", event => {
  event.preventDefault();
  const requests = JSON.parse(localStorage.getItem(REQUEST_KEY) || "[]");
  requests.push({book:"Data Structures and Algorithms", meeting:meeting.value, date:date.value, payment:payment.value, note:note.value.trim(), status:"Request Sent"});
  localStorage.setItem(REQUEST_KEY, JSON.stringify(requests));
  document.getElementById("result").textContent = "Request sent successfully. The seller can now reply in Messages.";
  form.querySelector("button").disabled = true;
});
const count = document.getElementById("requestCount");
if (count) count.textContent = JSON.parse(localStorage.getItem(REQUEST_KEY) || "[]").length;

const composer = document.getElementById("composer");
if (composer) composer.addEventListener("submit", event => {
  event.preventDefault();
  const input = document.getElementById("messageInput");
  if (!input.value.trim()) return;
  const bubble = document.createElement("div");
  bubble.className = "bubble me";
  bubble.textContent = input.value.trim();
  document.getElementById("messageList").appendChild(bubble);
  input.value = "";
});
