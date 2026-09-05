const books = [
  {title:"Data Structures and Algorithms",course:"CSE-2101",department:"CSE",type:"Textbook",condition:"Like New",price:450,image:"https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&h=400&fit=crop"},
  {title:"Calculus: Early Transcendentals",course:"MAT-1101",department:"Mathematics",type:"Textbook",condition:"Good",price:380,image:"https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&h=400&fit=crop"},
  {title:"Digital Electronics Handwritten Notes",course:"EEE-2203",department:"EEE",type:"Notes",condition:"New",price:150,image:"https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&h=400&fit=crop"},
  {title:"Business Communication",course:"BUS-1105",department:"BBA",type:"Textbook",condition:"Fair",price:200,image:"https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&h=400&fit=crop"},
  {title:"Object Oriented Programming in Java",course:"CSE-1201",department:"CSE",type:"Textbook",condition:"Like New",price:320,image:"https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&h=400&fit=crop"},
  {title:"Engineering Physics Lab Manual",course:"PHY-1101",department:"EEE",type:"Lab Manual",condition:"Good",price:120,image:"https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=600&h=400&fit=crop"}
];

function renderBooks() {
  const query = document.getElementById("search").value.trim().toLowerCase();
  const department = document.getElementById("department").value;
  const type = document.querySelector('input[name="type"]:checked').value;
  const results = books.filter(book => (!query || `${book.title} ${book.course} ${book.type}`.toLowerCase().includes(query)) && (department === "all" || book.department === department) && (type === "all" || book.type === type));
  document.getElementById("bookGrid").innerHTML = results.map(book => `<article class="card"><img src="${book.image}" alt="${book.title}"><div class="card-body"><span class="tag">${book.course}</span><h2>${book.title}</h2><p class="muted">${book.type} · ${book.condition}</p><span class="price">৳${book.price}</span></div></article>`).join("");
  document.getElementById("empty").style.display = results.length ? "none" : "block";
}

document.getElementById("search").addEventListener("input", renderBooks);
document.getElementById("department").addEventListener("change", renderBooks);
document.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener("change", renderBooks));
renderBooks();
