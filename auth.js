(() => {
  "use strict";

  const projectBase = new URL(".", document.currentScript.src);
  const authBase = new URL("api/auth/", projectBase);
  const apiBase = new URL("api/", projectBase);
  const root = document.getElementById("root");
  let csrfToken = null;
  let currentUser = null;
  let authRequestPending = false;

  function pageUrl(file, query = "") {
    return new URL(`${file}${query}`, projectBase).href;
  }

  function showGlobalMessage(message, isError = false) {
    let notice = document.getElementById("bookbridge-auth-notice");
    if (!notice) {
      notice = document.createElement("div");
      notice.id = "bookbridge-auth-notice";
      notice.setAttribute("role", isError ? "alert" : "status");
      notice.style.cssText = "position:relative;z-index:1000;padding:12px 20px;text-align:center;font:14px/1.5 sans-serif;background:#eff6ff;color:#12304a;border-bottom:1px solid #bfdbfe";
      document.body.prepend(notice);
    }
    notice.replaceChildren(document.createTextNode(message));
    notice.style.background = isError ? "#fef2f2" : "#eff6ff";
    notice.style.color = isError ? "#991b1b" : "#12304a";
    if (currentUser) {
      const logoutButton = document.createElement("button");
      logoutButton.type = "button";
      logoutButton.dataset.authLogout = "true";
      logoutButton.textContent = "Sign out";
      logoutButton.style.cssText = "margin-left:12px;padding:4px 10px;border:1px solid currentColor;background:transparent;color:inherit;cursor:pointer";
      notice.append(" ", logoutButton);
    }
  }

  function showFormMessage(form, message, isError = true) {
    let notice = form.querySelector("[data-auth-message]");
    if (!notice) {
      notice = document.createElement("p");
      notice.dataset.authMessage = "true";
      notice.style.cssText = "margin:8px 0;color:#991b1b;font-size:14px;line-height:1.5";
      form.append(notice);
    }
    notice.setAttribute("role", isError ? "alert" : "status");
    notice.textContent = message;
    notice.style.color = isError ? "#991b1b" : "#166534";
  }

  async function request(endpoint, { method = "GET", body, base = authBase } = {}) {
    const headers = { Accept: "application/json" };
    const options = { method, headers, credentials: "include", cache: "no-store" };

    if (body !== undefined) {
      if (body instanceof FormData) {
        options.body = body;
      } else {
        headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(body);
      }
    }

    if (method !== "GET") {
      if (!csrfToken) await refreshCsrfToken();
      headers["X-CSRF-Token"] = csrfToken;
    }

    let response;
    try {
      response = await fetch(new URL(endpoint, base), options);
    } catch {
      throw new Error("Authentication service is unavailable. Start XAMPP Apache/MySQL and open this project through localhost.");
    }

    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.success === false) {
      if (response.status === 401) {
        currentUser = null;
        csrfToken = null;
        if (pageRoles()) {
          hideUntilSessionIsChecked();
          window.location.replace(pageUrl("index.html", "?auth=expired"));
        }
      }
      const details = payload?.errors ? Object.values(payload.errors).join(" ") : "";
      const error = new Error([payload?.message || `Request failed (HTTP ${response.status}).`, details].filter(Boolean).join(" "));
      error.status = response.status;
      error.payload = payload;
      throw error;
    }
    return payload;
  }

  async function requestWithoutCsrf(endpoint) {
    let response;
    try {
      response = await fetch(new URL(endpoint, authBase), {
        method: "GET",
        headers: { Accept: "application/json" },
        credentials: "include",
        cache: "no-store"
      });
    } catch {
      throw new Error("Authentication service is unavailable. Start XAMPP Apache/MySQL and open this project through localhost.");
    }
    const payload = await response.json().catch(() => null);
    if (!response.ok || payload?.success === false) {
      throw new Error(payload?.message || `Request failed (HTTP ${response.status}).`);
    }
    return payload;
  }

  async function refreshCsrfToken() {
    const result = await requestWithoutCsrf("csrf.php");
    csrfToken = result.data?.csrf_token || null;
    if (!csrfToken) throw new Error("The server did not provide a CSRF token. Please try again.");
  }

  function userDestination(user) {
    // Intended destination for all roles is the original integrated SPA on index.html
    return "index.html";
  }

  function routeUser(user) {
    currentUser = user;
    const file = window.location.pathname.split("/").pop();
    const isHome = file === "" || file === "index.html";
    if (isHome) {
      window.__bookBridgeUser = user;
      document.dispatchEvent(new CustomEvent("bookbridge:session", {
        detail: { user, csrf: csrfToken }
      }));
      return;
    }
    window.location.assign(pageUrl("index.html"));
  }

  function setSubmitting(form, submitting) {
    const buttons = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')];
    if (submitting) {
      form.dataset.authSubmitting = "true";
      form.dataset.authButtons = JSON.stringify(buttons.map(button => button.disabled));
      buttons.forEach(button => { button.disabled = true; });
    } else {
      delete form.dataset.authSubmitting;
      const previous = JSON.parse(form.dataset.authButtons || "[]");
      buttons.forEach((button, index) => { button.disabled = Boolean(previous[index]); });
      delete form.dataset.authButtons;
    }
  }

  async function submitAuthForm(form, endpoint, body) {
    if (form.dataset.authSubmitting) return;
    setSubmitting(form, true);
    showFormMessage(form, "", false);
    try {
      const result = await request(endpoint, { method: "POST", body });
      currentUser = result.data?.user || null;
      csrfToken = result.data?.csrf_token || csrfToken;
      if (!currentUser) throw new Error("The server response did not include an authenticated user.");
      routeUser(currentUser);
    } catch (error) {
      showFormMessage(form, error.message || "Authentication failed. Please try again.");
    } finally {
      setSubmitting(form, false);
    }
  }

  function fieldValue(form, name) {
    return form.querySelector(`[name="${name}"]`)?.value.trim() || "";
  }

  function isReactLoginForm(form) {
    return Boolean(form.closest("#root") && form.querySelector('input[type="email"]') && form.querySelector('input[type="password"]'));
  }

  function addRegistrationLink() {
    if (!root) return;
    root.querySelectorAll("*").forEach(element => {
      if (element.childElementCount === 0 && element.textContent.trim() === "Manage categories and users") {
        element.textContent = "Manage categories and subjects";
      }
    });
    root.querySelectorAll("button").forEach(button => {
      if (/^Demo Login/i.test(button.textContent.trim())) button.hidden = true;
    });
    root.querySelectorAll("*").forEach(element => {
      if (element.childElementCount === 0 && /^or use demo credentials$/i.test(element.textContent.trim())) {
        element.hidden = true;
      }
    });
    const form = [...root.querySelectorAll("form")].find(isReactLoginForm);
    if (!form || form.nextElementSibling?.dataset.authRegisterLink) return;
    const link = document.createElement("p");
    link.dataset.authRegisterLink = "true";
    link.style.cssText = "margin:12px 0;text-align:center;font-size:14px";
    const anchor = document.createElement("a");
    anchor.href = pageUrl("register.html");
    anchor.textContent = "Create a buyer or seller account";
    link.append(anchor);
    form.insertAdjacentElement("afterend", link);
  }

  function pageRoles() {
    const file = window.location.pathname.split("/").pop();
    if (["buyer-dashboard.html", "purchase-request.html"].includes(file)) return ["buyer"];
    if (["seller-dashboard.html", "add-listing.html", "seller-sales.html"].includes(file)) return ["seller"];
    if (file === "messages.html") return ["buyer", "seller"];
    if (file === "admin-dashboard.html") return ["admin"];
    return null;
  }

  function dashboardRole() {
    const roles = pageRoles();
    return roles?.length === 1 ? roles[0] : null;
  }

  function configureAdminLogin() {
    const query = new URLSearchParams(window.location.search);
    if (window.location.pathname.split("/").pop() !== "seller-login.html" || query.get("portal") !== "admin") return;
    document.title = "Admin Login | UIU Books";
    const eyebrow = document.querySelector(".auth-card .eyebrow");
    const heading = document.querySelector(".auth-card h1");
    const description = document.querySelector(".auth-card > p:not(.eyebrow)");
    if (eyebrow) eyebrow.textContent = "ADMIN PORTAL";
    if (heading) heading.textContent = "Administrator sign in";
    if (description) description.textContent = "Sign in with an authorized administrator account.";
    if (query.get("auth") === "unavailable") {
      showGlobalMessage("Your session could not be verified. Sign in again when the authentication service is available.", true);
    }
  }

  function hideUntilSessionIsChecked() {
    document.body.style.visibility = "hidden";
    if (root) root.style.visibility = "hidden";
    if (dashboardRole()) document.body.setAttribute("data-auth-checking", "");
  }

  function showAfterSessionIsChecked() {
    document.body.style.removeProperty("visibility");
    document.body.removeAttribute("data-auth-checking");
    if (root) root.style.visibility = "visible";
  }

  function hasAuthenticatedDashboardView() {
    if (!root) return false;
    const heading = root.querySelector("main h1")?.textContent || "";
    const content = root.innerText || "";
    return /^(Buyer|Seller|Admin) Dashboard$/i.test(heading.trim()) &&
      /My Listings|Purchase Requests|Sales History/.test(content);
  }

  function updateDashboardIdentity(user) {
    const heading = document.querySelector("main h1");
    if (heading && /^Hello,/.test(heading.textContent)) {
      heading.textContent = `Hello, ${user.full_name}`;
    }
  }

  async function logout() {
    if (authRequestPending) return;
    authRequestPending = true;
    try {
      await request("logout.php", { method: "POST", body: {} });
    } catch (error) {
      if (currentUser) showGlobalMessage(error.message || "Could not sign out. Please try again.", true);
      if (/401|not logged in|authentication required/i.test(error.message || "")) {
        window.location.assign(pageUrl("index.html", "?auth=expired"));
      }
      return;
    } finally {
      authRequestPending = false;
    }
    currentUser = null;
    csrfToken = null;
    window.location.assign(pageUrl("index.html", "?auth=logged-out"));
  }

  document.addEventListener("submit", event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const isRegister = form.id === "registerForm";
    const isStaticLogin = form.id === "loginForm";
    const isBundleLogin = isReactLoginForm(form);
    if (!isRegister && !isStaticLogin && !isBundleLogin) return;

    event.preventDefault();
    event.stopImmediatePropagation();

    if (isRegister) {
      const role = fieldValue(form, "role");
      if (role !== "buyer" && role !== "seller") {
        showFormMessage(form, "Choose either Buyer or Seller.");
        return;
      }
      submitAuthForm(form, "register.php", {
        full_name: fieldValue(form, "full_name"),
        email: fieldValue(form, "email"),
        password: form.querySelector('[name="password"]')?.value || "",
        role,
        student_id: fieldValue(form, "student_id"),
        department: fieldValue(form, "department")
      });
      return;
    }

    submitAuthForm(form, "login.php", {
      email: fieldValue(form, "email") || form.querySelector('input[type="email"]')?.value.trim() || "",
      password: form.querySelector('[name="password"], input[type="password"]')?.value || ""
    });
  }, true);

  document.addEventListener("click", event => {
    const target = event.target instanceof Element ? event.target : null;
    const demoLoginButton = target?.closest("#root button");
    if (demoLoginButton && /^Demo Login/i.test(demoLoginButton.textContent.trim())) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const form = demoLoginButton.closest("#root")?.querySelector("form");
      if (form) showFormMessage(form, "Demo login is disabled. Sign in with an account registered on the server.");
      return;
    }
    const logoutButton = target?.closest("[data-auth-logout], #root button");
    if (!logoutButton) return;
    const isReactLogout = logoutButton.closest("#root") && logoutButton.textContent.trim() === "Sign Out";
    if (!logoutButton.hasAttribute("data-auth-logout") && !isReactLogout) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    logout();
  }, true);

  if (root) {
    new MutationObserver(addRegistrationLink).observe(root, { childList: true, subtree: true });
  }

  async function initialize() {
    let keepHidden = false;
    hideUntilSessionIsChecked();
    currentUser = null;
    csrfToken = null;

    const file = window.location.pathname.split("/").pop();
    const expectedRoles = pageRoles();
    const isAuthPage = file === "seller-login.html" || file === "register.html";
    const isHome = file === "" || file === "index.html";
    configureAdminLogin();

    try {
      const result = await requestWithoutCsrf("me.php");
      currentUser = result.data?.user || null;
      csrfToken = result.data?.csrf_token || null;
      if (currentUser) {
        if (expectedRoles && !expectedRoles.includes(currentUser.role)) {
          const destination = userDestination(currentUser);
          keepHidden = true;
          window.location.replace(pageUrl(destination || "index.html"));
          return;
        }
        if (expectedRoles) {
          updateDashboardIdentity(currentUser);
          document.body.removeAttribute("data-auth-checking");
          return;
        }
        if (isAuthPage) {
          keepHidden = true;
          routeUser(currentUser);
          return;
        }
        if (isHome) {
          // Authenticated user on index.html — let the React SPA handle routing.
          // Dispatch an event so app.js can pick up the session without polling.
          window.__bookBridgeUser = currentUser;
          window.__bookBridgeCsrf = csrfToken;
          document.dispatchEvent(new CustomEvent('bookbridge:session', {
            detail: { user: currentUser, csrf: csrfToken }
          }));
          // Keep page visible — React will show the right dashboard.
        }
      }
    } catch (error) {
      const unauthenticated = /401|authentication required|not logged in/i.test(error.message || "");
      if (!unauthenticated) {
        showGlobalMessage(error.message || "Authentication service is unavailable.", true);
      }
      if (expectedRoles && unauthenticated) {
        keepHidden = true;
        const loginUrl = expectedRoles.includes("admin")
          ? pageUrl("seller-login.html", "?portal=admin")
          : pageUrl("index.html", "?auth=expired");
        window.location.replace(loginUrl);
        return;
      }
      if (expectedRoles && !unauthenticated) {
        keepHidden = true;
        const loginUrl = expectedRoles.includes("admin")
          ? pageUrl("seller-login.html", "?portal=admin&auth=unavailable")
          : pageUrl("index.html", "?auth=expired");
        window.location.replace(loginUrl);
        return;
      }
      if (isHome && unauthenticated && hasAuthenticatedDashboardView()) {
        keepHidden = true;
        window.location.replace(pageUrl("index.html", "?auth=expired"));
        return;
      }
    } finally {
      if (!keepHidden) showAfterSessionIsChecked();
    }

    const query = new URLSearchParams(window.location.search);
    if (isHome && query.get("auth") === "expired") {
      showGlobalMessage("Your session has expired. Sign in again to continue.", true);
    } else if (isHome && query.get("auth") === "logged-out") {
      showGlobalMessage("You have been signed out.");
    }
  }

  window.addEventListener("pagehide", hideUntilSessionIsChecked);
  window.addEventListener("pageshow", event => {
    if (!event.persisted) return;
    initialize();
  });
  hideUntilSessionIsChecked();
  const ready = initialize();
  window.BookBridgeAuth = {
    get currentUser() { return currentUser; },
    async requireRole(roles) {
      await ready;
      const allowedRoles = Array.isArray(roles) ? roles : [roles];
      if (!currentUser) {
        window.location.replace(pageUrl("index.html", "?auth=expired"));
        return null;
      }
      if (!allowedRoles.includes(currentUser.role)) {
        routeUser(currentUser);
        return null;
      }
      return currentUser;
    },
    request(endpoint, options = {}) {
      return request(endpoint, { ...options, base: apiBase });
    },
    logout,
    ready
  };
})();