(function () {
  async function loadPartial(targetId, path) {
    const target = document.getElementById(targetId);
    if (!target) return;
    const res = await fetch(path, { cache: "no-store" });
    const html = await res.text();
    target.innerHTML = html;
  }

  function setActiveNav() {
    const path = window.location.pathname.split("/").pop() || "index.html";
    const key = path === "index.html" ? "home" : path.replace(".html", "");
    const active = document.querySelector(`[data-nav="${key}"]`);
    if (active) active.classList.add("active");
  }

  Promise.all([
    loadPartial("site-header", "partials/header.html"),
    loadPartial("site-footer", "partials/footer.html"),
  ]).then(setActiveNav);
})();
