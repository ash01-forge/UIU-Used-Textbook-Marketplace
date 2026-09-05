const STORAGE_KEY = "bookbridgeSellerListings";

function readListings() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
  catch { return []; }
}

const form = document.getElementById("listingForm");
if (form) {
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const listing = {
      id: Date.now(),
      title: document.getElementById("title").value.trim(),
      courseCode: document.getElementById("courseCode").value.trim().toUpperCase(),
      department: document.getElementById("department").value,
      condition: document.getElementById("condition").value,
      price: Number(document.getElementById("price").value),
      description: document.getElementById("description").value.trim(),
      status: "Pending Approval"
    };
    const listings = readListings();
    listings.push(listing);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(listings));
    location.href = "seller-dashboard.html";
  });
}

const listingBody = document.getElementById("sellerListings");
if (listingBody) {
  const stored = readListings();
  const seeded = [{title:"Data Structures and Algorithms",courseCode:"CSE-2101",price:450,condition:"Like New",status:"Sold"}, ...stored];
  listingBody.innerHTML = seeded.map(item => `<tr><td>${item.title}</td><td>${item.courseCode}</td><td>৳${item.price}</td><td>${item.condition}</td><td><span class="badge ${item.status === "Sold" ? "sold" : ""}">${item.status}</span></td></tr>`).join("");
  document.getElementById("totalListings").textContent = seeded.length;
  document.getElementById("activeListings").textContent = stored.filter(item => item.status !== "Sold").length;
}
