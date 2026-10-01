# UIU Used Textbook Marketplace

A responsive marketplace prototype where UIU students can browse, buy, sell, and manage used textbooks, notes, and lab manuals.

## Demo roles

- Guest: browse, search, and filter listings
- Buyer: wishlist, purchase request, payment choice, and messaging
- Seller: dashboard, listing submission, listing management, and sales history
- Admin: listing approval, category management, and reports

## Payment choices

- Cash on Meet
- bKash
- Nagad

## Run locally

For authentication, start XAMPP Apache and MySQL and open `http://localhost/UIU-Used-Textbook-Marketplace/`. PHP authentication does not work from Live Server or by opening the HTML file directly. An internet connection is needed for remote listing images.

Sign in with an existing account from the main marketplace or `seller-login.html`. Create buyer or seller accounts at `register.html`; the PHP session is authoritative and restored on page load. When the PHP API is unavailable, the guest marketplace demo remains browseable, but sign-in and registration show an explicit error rather than faking authentication.

## Project status

Authentication is connected to the PHP backend. Dashboard values, listings, purchase requests, messages, transactions, and reports remain mock/demo data until their marketplace, buyer, seller, messaging, and admin APIs are implemented.

## Technology

- HTML
- CSS
- JavaScript
- React (compiled production build)
