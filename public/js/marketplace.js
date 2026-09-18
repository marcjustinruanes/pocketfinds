document.addEventListener("DOMContentLoaded", () => {

    // ── Hero photo rotation ──
    const heroFrame = document.getElementById("heroPhotoFrame");
    if (heroFrame) {
        const slides = [...heroFrame.querySelectorAll(".hero-slide")];
        if (slides.length > 1) {
            let heroIdx = 0;
            setInterval(() => {
                slides[heroIdx].classList.remove("active");
                heroIdx = (heroIdx + 1) % slides.length;
                slides[heroIdx].classList.add("active");
            }, 5000);
        }
    }

    // ── Search ──
    const s = document.querySelector("[data-search]");
    const p = [...document.querySelectorAll("[data-product]")];
    s?.addEventListener("input", () => {
        const q = s.value.trim().toLowerCase();
        p.forEach(x => x.hidden = !!q && !x.dataset.product.toLowerCase().includes(q));
    });

    // ── Auth modal ──
    const modal = document.getElementById("authModal");
    const openModal = (tab = "signin") => { modal.classList.add("open"); switchTab(tab); };
    const closeModal = () => modal.classList.remove("open");
    document.getElementById("authModalClose")?.addEventListener("click", closeModal);
    modal?.addEventListener("click", e => { if (e.target === modal) closeModal(); });

    // ── Tab switching ──
    const panes = { signin: document.getElementById("amSignin"), register: document.getElementById("amRegister") };
    function switchTab(name) {
        Object.entries(panes).forEach(([k, el]) => el && (el.style.display = k === name ? "" : "none"));
    }
    document.querySelectorAll(".am-switch").forEach(btn =>
        btn.addEventListener("click", () => switchTab(btn.dataset.tab))
    );

    // ── Password toggle inside modal ──
    document.querySelectorAll("[data-password-toggle]").forEach(btn => {
        btn.addEventListener("click", () => {
            const inp = document.getElementById(btn.dataset.passwordToggle);
            if (!inp) return;
            inp.type = inp.type === "password" ? "text" : "password";
            btn.textContent = inp.type === "password" ? "Show" : "Hide";
        });
    });

    // ── Protected actions ── (preventDefault too: some of these sit inside a product-card
    // <a>, e.g. the wishlist heart, and must not also navigate the browser to the product)
    document.querySelectorAll("[data-protected]").forEach(b =>
        b.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        })
    );

    // ── Category icon map ──
    const categoryIcons = {
        // Pet & animals
        pet:         `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 015 5v3.5a3.5 3.5 0 01-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 018 10z"/></svg>`,
        // Electronics & tech
        electronics: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>`,
        gadget:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>`,
        phone:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>`,
        computer:    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>`,
        // Women's apparel — female restroom pictogram
        women:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 8h6l2 7h-4v7h-2v-7H7l2-7z"/></svg>`,
        // Men's apparel — male restroom pictogram
        men:         `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M9 8h6v7h-2v7h-2v-7H9V8z"/></svg>`,
        // Kids & baby — pacifier (ring, shield, teat)
        kid:         `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/></svg>`,
        baby:        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/></svg>`,
        children:    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="3.2" ry="2.8"/><ellipse cx="12" cy="10.2" rx="5.5" ry="2.3"/><ellipse cx="12" cy="15" rx="2.6" ry="3.2"/></svg>`,
        // Home & garden
        home:        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-3"/><path d="M12 13c-3 0-5-2-5-5 3 0 5 2 5 5z"/><path d="M12 13c3 0 5-2 5-5-3 0-5 2-5 5z"/><path d="M7 21h10l-1-5H8l-1 5z"/></svg>`,
        garden:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M5 12C5 7 8 4 12 4c4 0 7 3 7 8"/><path d="M5 12c0-3 2-5 7-5"/></svg>`,
        living:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 9V7a2 2 0 00-2-2H6a2 2 0 00-2 2v2M2 11h20v8a2 2 0 01-2 2H4a2 2 0 01-2-2v-8z"/><path d="M6 19v2M18 19v2"/></svg>`,
        furniture:   `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 9V7a2 2 0 00-2-2H6a2 2 0 00-2 2v2M2 11h20v8a2 2 0 01-2 2H4a2 2 0 01-2-2v-8z"/><path d="M6 19v2M18 19v2"/></svg>`,
        // Food & beverage
        food:        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>`,
        drink:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        beverage:    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l1 7H7L8 2z"/><path d="M7 9c0 5 2 8 5 8s5-3 5-8"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>`,
        // Health & beauty
        health:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`,
        beauty:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="14" width="6" height="8" rx="1"/><rect x="8" y="10" width="8" height="4" rx="1"/><path d="M10 10V6c0-1 .5-3 2-4 1.5 1 2 3 2 4v4"/></svg>`,
        cosmetic:    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="14" width="6" height="8" rx="1"/><rect x="8" y="10" width="8" height="4" rx="1"/><path d="M10 10V6c0-1 .5-3 2-4 1.5 1 2 3 2 4v4"/></svg>`,
        // Automotive
        automotive:  `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>`,
        vehicle:     `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>`,
        // Fashion & clothing
        fashion:     `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        clothing:    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        apparel:     `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.57a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.57a2 2 0 00-1.34-2.23z"/></svg>`,
        // Sports & outdoors
        sports:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M7 5H4a3 3 0 003 3"/><path d="M17 5h3a3 3 0 01-3 3"/></svg>`,
        outdoors:    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l4-8 4 5 3-3 4 6H3z"/><circle cx="18" cy="5" r="2"/></svg>`,
        fitness:     `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v6M18 4v6M4 7h4M16 7h4M6 20v-6M18 20v-6M4 17h4M16 17h4M10 11h4v2h-4z"/></svg>`,
        // Toys & games
        toys:        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14.5c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.83 8 21v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><rect x="2" y="10" width="20" height="4" rx="2"/></svg>`,
        games:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4M15 11h.01M17 13h.01"/></svg>`,
        // Books & stationery
        books:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`,
        book:        `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`,
        stationery:  `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="2" x2="22" y2="6"/><path d="M7.5 20.5L19 9l-4-4L3.5 16.5 2 22l5.5-1.5z"/><line x1="15" y1="5" x2="19" y2="9"/></svg>`,
        school:      `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="2" x2="22" y2="6"/><path d="M7.5 20.5L19 9l-4-4L3.5 16.5 2 22l5.5-1.5z"/></svg>`,
        // Music
        music:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`,
        // Art & craft
        art:         `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 011.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>`,
        craft:       `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 13.5V20a2 2 0 002 2h16a2 2 0 002-2v-6.5"/><path d="M22 10l-5.5-8h-9L2 10h20z"/><path d="M12 2v8"/><path d="M2 10h20"/></svg>`,
        // Default fallback
        default:     `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>`,
    };

    function getCategoryIcon(name) {
        const lower = name.toLowerCase();
        for (const [key, svg] of Object.entries(categoryIcons)) {
            if (key !== "default" && lower.includes(key)) return svg;
        }
        return categoryIcons.default;
    }

    // ── Load categories from API ──
    const grid = document.getElementById("browseCategories");
    if (grid) {
        fetch("/register/categories")
            .then(r => r.json())
            .then(data => {
                grid.innerHTML = "";
                data.forEach(cat => {
                    const a = document.createElement("a");
                    a.className = "cat-card";
                    a.href = "/?category=" + cat.id;
                    a.innerHTML = `<span class="cat-icon">${getCategoryIcon(cat.name)}</span><span class="cat-name">${cat.name}</span>`;
                    grid.appendChild(a);
                });
                if (!data.length) grid.innerHTML = `<p style="color:var(--muted);font-size:13px;grid-column:1/-1">No categories found.</p>`;
            })
            .catch(() => {
                grid.innerHTML = `<p style="color:var(--muted);font-size:13px;grid-column:1/-1">Could not load categories.</p>`;
            });
    }

});
