document.addEventListener("DOMContentLoaded", () => {
    const scrollKey = "pocketfinds-category-scroll";
    try {
        const saved = JSON.parse(sessionStorage.getItem(scrollKey) || "null");
        sessionStorage.removeItem(scrollKey);
        if (!window.pocketfindsReloadAtHero && saved && saved.url === window.location.pathname + window.location.search) {
            const restore = () => window.scrollTo({ top: saved.top, left: 0, behavior: "instant" });
            requestAnimationFrame(restore);
        }
    } catch (_) { /* Browsing still works if session storage is unavailable. */ }
    document.querySelectorAll("[data-browse-category]").forEach((link) => {
        link.addEventListener("click", (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            const target = new URL(link.href);
            target.hash = "";
            try {
                sessionStorage.setItem(scrollKey, JSON.stringify({ url: target.pathname + target.search, top: window.scrollY }));
            } catch (_) { /* Continue normally without scroll restoration. */ }
            window.location.assign(target.href);
        });
    });
    const productFilters = document.querySelector("#products .pf-product-filters");
    productFilters?.querySelectorAll('input[name="category"]').forEach((choice) => {
        choice.addEventListener("change", () => productFilters.requestSubmit());
    });
    const dealSection = document.getElementById("deals");
    const shopList = document.querySelector("#shops .pf-shop-row");
    if (shopList) {
        const firstSix = [...shopList.querySelectorAll(".pf-shop-card")].slice(0, 6);
        const sizeShopList = () => {
            if (!firstSix.length) return;
            const style = getComputedStyle(shopList);
            const height = firstSix.reduce((total, card) => total + card.getBoundingClientRect().height, 0)
                + (parseFloat(style.rowGap) || 0) * (firstSix.length - 1)
                + (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0);
            shopList.style.maxHeight = `${Math.ceil(height)}px`;
        };
        sizeShopList();
        if (typeof ResizeObserver !== "undefined") {
            const observer = new ResizeObserver(sizeShopList);
            firstSix.forEach((card) => observer.observe(card));
        }
        window.addEventListener("resize", sizeShopList);
    }
    const clearBrowseForDeals = (event) => {
        const url = new URL(window.location.href);
        if (!Number(url.searchParams.get("browse_category"))) return;
        const form = dealSection.querySelector(".pf-deal-price-form");
        const minimum = form?.elements.deal_min_price.value || "";
        const maximum = form?.elements.deal_max_price.value || "";
        if (form) {
            form.elements.deal_max_price.setCustomValidity(minimum !== "" && maximum !== "" && Number(maximum) < Number(minimum) ? "Maximum price must be at least the minimum price." : "");
            if (!form.reportValidity()) { event.preventDefault(); event.stopImmediatePropagation(); return; }
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        url.searchParams.delete("browse_category");
        url.searchParams.set("deal_category", dealSection.querySelector("[data-deal-category]")?.value || "");
        url.searchParams.set("deal_min_price", minimum);
        url.searchParams.set("deal_max_price", maximum);
        url.hash = "";
        try { sessionStorage.setItem(scrollKey, JSON.stringify({ url: url.pathname + url.search, top: window.scrollY })); } catch (_) {}
        window.location.assign(url.href);
    };
    dealSection?.querySelector("[data-deal-category]")?.addEventListener("change", clearBrowseForDeals, true);
    dealSection?.querySelector(".pf-deal-price-form")?.addEventListener("submit", clearBrowseForDeals, true);

    const navbar = document.querySelector(".market-header");
    const account = document.getElementById("pf-acct-btn");
    const accountToggle = document.getElementById("pf-acct-toggle");
    const closeAccount = () => {
        account?.classList.remove("open");
        accountToggle?.setAttribute("aria-expanded", "false");
    };
    if (account && accountToggle) {
        accountToggle.setAttribute("aria-expanded", "false");
        accountToggle.setAttribute("aria-controls", "pf-acct-dropdown");
        accountToggle.addEventListener("click", () => {
            account.classList.toggle("open");
            accountToggle.setAttribute("aria-expanded", String(account.classList.contains("open")));
        });
        document.addEventListener("click", (event) => { if (!account.contains(event.target)) closeAccount(); });
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && account.classList.contains("open")) { closeAccount(); accountToggle.focus(); }
        });
    }
    if (navbar) {
        const updateNavbarHeight = () => document.documentElement.style.setProperty("--guest-nav-height", `${navbar.getBoundingClientRect().height}px`);
        updateNavbarHeight();
        if (typeof ResizeObserver !== "undefined") new ResizeObserver(updateNavbarHeight).observe(navbar);
        const links = [...navbar.querySelectorAll(".nav-link")];
        links.forEach((link) => link.addEventListener("click", (event) => {
            const url = new URL(link.href);
            if (url.pathname !== window.location.pathname || url.search !== window.location.search) return;
            const target = document.getElementById(url.hash.slice(1));
            if (!target) return;
            event.preventDefault();
            // Finish the intro first so changing hero height cannot shift the target.
            document.documentElement.classList.remove("hero-intro", "hero-text-visible", "hero-revealed");
            target.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "instant" : "smooth", block: "start" });
            history.replaceState(null, "", url.hash);
            links.forEach((item) => item === link ? item.setAttribute("aria-current", "location") : item.removeAttribute("aria-current"));
        }));
    }

    const categoryTrack = document.getElementById("browseCategories");
    const categoryControls = document.querySelector(".pf-category-controls");
    if (categoryTrack && categoryControls) {
        const previous = categoryControls.querySelector("[data-category-prev]");
        const next = categoryControls.querySelector("[data-category-next]");
        const updateControls = () => {
            const remaining = categoryTrack.scrollWidth - categoryTrack.clientWidth;
            categoryControls.hidden = remaining <= 2;
            previous.disabled = categoryTrack.scrollLeft <= 2;
            next.disabled = categoryTrack.scrollLeft >= remaining - 2;
        };
        const moveCategories = (direction) => {
            const card = categoryTrack.querySelector(".cat-card");
            const gap = parseFloat(getComputedStyle(categoryTrack).gap) || 0;
            categoryTrack.scrollBy({
                left: direction * ((card?.getBoundingClientRect().width || 120) + gap) * 2,
                behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "instant" : "smooth",
            });
        };
        previous.addEventListener("click", () => moveCategories(-1));
        next.addEventListener("click", () => moveCategories(1));
        categoryTrack.addEventListener("scroll", updateControls, { passive: true });
        window.addEventListener("resize", updateControls);
        updateControls();
    }

    document.querySelectorAll("[data-deal-showcase]").forEach((showcase) => {
        const panels = [...showcase.querySelectorAll("[data-deal-panel]")];
        const options = [...showcase.querySelectorAll("[data-deal-select]")];
        const categoryFilter = showcase.closest("#deals")?.querySelector("[data-deal-category]");
        const priceForm = showcase.closest("#deals")?.querySelector(".pf-deal-price-form");
        const savedDealFilters = new URLSearchParams(window.location.search);
        if (categoryFilter && savedDealFilters.has("deal_category")) categoryFilter.value = savedDealFilters.get("deal_category");
        if (priceForm) {
            priceForm.elements.deal_min_price.value = savedDealFilters.get("deal_min_price") || "";
            priceForm.elements.deal_max_price.value = savedDealFilters.get("deal_max_price") || "";
        }
        const categoryChoices = [...showcase.closest("#deals").querySelectorAll('input[name="deal_category"]')];
        const emptyMessage = showcase.closest("#deals").querySelector("[data-deal-filter-empty]");
        const gallery = showcase.closest(".pf-deal-gallery-frame");
        const previousDeal = gallery?.querySelector("[data-deal-prev]");
        const nextDeal = gallery?.querySelector("[data-deal-next]");
        let dealMotionTimer = null;
        let requestedDeal = null;
        const selectDeal = (selected) => {
            const visible = panels.filter((panel) => !panel.hidden);
            const selectedPanel = panels.find((panel) => panel.dataset.dealPanel === selected);
            const position = visible.indexOf(selectedPanel);
            // Capture every card before changing layout, including interrupted motion.
            const previousFrames = new Map(panels.map((panel) => {
                const style = getComputedStyle(panel);
                return [panel, { left: style.left, transform: style.transform, opacity: style.opacity, visibility: style.visibility }];
            }));
            panels.forEach((panel) => panel.getAnimations().forEach((animation) => animation.cancel()));
            panels.forEach((panel) => {
                const active = panel.dataset.dealPanel === selected;
                panel.classList.toggle("is-active", active);
                let offset = visible.indexOf(panel) - position;
                if (offset > visible.length / 2) offset -= visible.length;
                if (offset < -visible.length / 2) offset += visible.length;
                const distance = Math.abs(offset);
                const outside = distance > 2;
                const initialized = panel.dataset.dealOffset !== undefined;
                const oldOffset = Number(panel.dataset.dealOffset ?? offset);
                panel.dataset.dealOffset = offset;
                panel.classList.remove("is-repositioning");
                const travel = distance === 0 ? 0 : distance === 1 ? 24 : 42 + (distance - 2) * 24;
                panel.style.setProperty("--deal-position", `${50 + Math.sign(offset) * travel}%`);
                panel.inert = outside;
                panel.style.setProperty("--deal-scale", distance === 0 ? 1 : distance === 1 ? .9 : .8);
                panel.style.setProperty("--deal-rise", distance === 0 ? "28px" : "0px");
                panel.style.setProperty("--deal-opacity", 1);
                panel.style.setProperty("--deal-depth", Math.max(0, 3 - distance));
                panel.style.setProperty("--deal-tilt", `${Math.sign(offset) * -26}deg`);
                panel.classList.toggle("is-outside", Math.abs(offset) > 2);
                panel.querySelector(".pf-deal-fold-details").hidden = !active;
                if (initialized && !panel.hidden && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                    const style = getComputedStyle(panel);
                    let start = previousFrames.get(panel);
                    let end = { left: style.left, transform: style.transform, opacity: style.opacity, visibility: style.visibility };
                    const wrapped = Math.abs(oldOffset - offset) > visible.length / 2;
                    if (!active && (wrapped || start.visibility === "hidden")) {
                        // Recycled cards enter/exit at the perimeter; never cross the center.
                        if (outside) {
                            end = { ...start, left: `${(oldOffset < 0 ? -.2 : 1.2) * showcase.clientWidth}px`, opacity: "0", visibility: "visible" };
                        } else {
                            start = { ...end, left: `${(offset < 0 ? -.2 : 1.2) * showcase.clientWidth}px`, opacity: "0", visibility: "visible" };
                        }
                    }
                    panel.animate([
                        start,
                        end,
                    ], { duration: 280, easing: "cubic-bezier(.25,.8,.25,1)" });
                }
            });
            options.forEach((option) => {
                option.setAttribute("aria-pressed", String(option.dataset.dealSelect === selected));
                option.setAttribute("aria-expanded", String(option.dataset.dealSelect === selected));
            });
            if (previousDeal) previousDeal.disabled = visible.length < 2;
            if (nextDeal) nextDeal.disabled = visible.length < 2;
            const counter = gallery?.querySelector("[data-deal-position]");
            if (counter) counter.textContent = `${Math.max(0, position + 1)} / ${visible.length}`;
        };
        const animateToDeal = (selected) => {
            window.clearTimeout(dealMotionTimer);
            dealMotionTimer = null;
            requestedDeal = selected;
            selectDeal(selected);
        };
        const moveDeal = (direction) => {
            const visible = panels.filter((panel) => !panel.hidden);
            const position = visible.findIndex((panel) => panel.classList.contains("is-active"));
            const target = visible[(position + direction + visible.length) % visible.length];
            if (target) animateToDeal(target.dataset.dealPanel);
        };
        previousDeal?.addEventListener("click", () => moveDeal(-1));
        nextDeal?.addEventListener("click", () => moveDeal(1));
        options.forEach((option) => {
            option.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                animateToDeal(option.dataset.dealSelect);
            });
        });
        const applyDealFilters = () => {
            window.clearTimeout(dealMotionTimer);
            dealMotionTimer = null;
            requestedDeal = null;
            const minimum = priceForm?.elements.deal_min_price.value ?? "";
            const maximum = priceForm?.elements.deal_max_price.value ?? "";
            if (priceForm) {
                priceForm.elements.deal_max_price.setCustomValidity(minimum !== "" && maximum !== "" && Number(maximum) < Number(minimum) ? "Maximum price must be at least the minimum price." : "");
                if (!priceForm.reportValidity()) return;
            }
            categoryChoices.forEach((choice) => { choice.checked = choice.value === categoryFilter.value; });
            options.forEach((option) => {
                const price = Number(option.dataset.dealPrice);
                option.hidden = (!!categoryFilter?.value && option.dataset.dealCategoryId !== categoryFilter.value)
                    || (minimum !== "" && price < Number(minimum))
                    || (maximum !== "" && price > Number(maximum));
                option.closest("[data-deal-panel]").hidden = option.hidden;
            });
            const first = options.find((option) => !option.hidden);
            showcase.hidden = !first;
            if (gallery) gallery.hidden = !first;
            if (emptyMessage) emptyMessage.hidden = !!first;
            selectDeal(first?.dataset.dealSelect ?? "");
        };
        categoryFilter?.addEventListener("change", applyDealFilters);
        priceForm?.addEventListener("submit", (event) => { event.preventDefault(); applyDealFilters(); });
        priceForm?.addEventListener("input", () => priceForm.elements.deal_max_price.setCustomValidity(""));
        priceForm?.querySelector("[data-clear-deal-range]")?.addEventListener("click", () => { priceForm.reset(); applyDealFilters(); });
        selectDeal(options.find((option) => !option.hidden)?.dataset.dealSelect ?? "");
        if (savedDealFilters.has("deal_category") || savedDealFilters.has("deal_min_price") || savedDealFilters.has("deal_max_price")) applyDealFilters();
        categoryChoices.forEach((choice) => choice.addEventListener("change", () => {
            if (!categoryFilter) return;
            categoryFilter.value = choice.value;
            categoryFilter.dispatchEvent(new Event("change"));
        }));
    });

    const root = document.documentElement;
    const hero = document.querySelector(".pf-hero-banner");
    const categories = document.getElementById("categories");
    if (hero && categories && root.classList.contains("hero-intro")) {
        window.scrollTo({ top: 0, left: 0, behavior: "instant" });
        const measureHero = () => {
            if (!root.classList.contains("hero-intro")) return;
            const viewport = window.visualViewport?.height || window.innerHeight;
            const headerHeight = hero.getBoundingClientRect().top + window.scrollY;
            root.style.setProperty("--hero-header-height", `${headerHeight}px`);
            const categorySpace = categories.getBoundingClientRect().height + 30;
            const content = hero.querySelector(".pf-hero-content");
            const minimumHeight = content.getBoundingClientRect().height + 52;
            root.style.setProperty("--hero-compact-height", `${Math.max(minimumHeight, viewport - headerHeight - categorySpace)}px`);
        };
        measureHero();
        document.fonts?.ready.then(measureHero);
        const photoReady = new Promise((resolve) => {
            const background = getComputedStyle(hero).backgroundImage;
            const photoUrl = background.match(/url\(["']?(.*?)["']?\)/)?.[1];
            if (!photoUrl) return resolve();
            const photo = new Image();
            photo.onload = photo.onerror = resolve;
            photo.src = photoUrl;
            if (photo.complete) resolve();
            window.setTimeout(resolve, 2000);
        });
        photoReady.then(() => {
            window.setTimeout(() => {
                if (!root.classList.contains("hero-intro")) return;
                root.classList.add("hero-text-visible");
                window.setTimeout(() => {
                    if (!root.classList.contains("hero-intro")) return;
                    measureHero();
                    root.classList.add("hero-revealed");
                }, 1600);
            }, 900);
        });
        window.addEventListener("resize", measureHero);
        // Keep the final measured layout after the intro's safety timeout.
        hero.addEventListener("transitionend", (event) => {
            if (event.target === hero && event.propertyName === "height" && root.classList.contains("hero-revealed")) {
                hero.style.height = "var(--hero-compact-height)";
                hero.style.minHeight = "0";
                root.classList.remove("hero-intro", "hero-text-visible", "hero-revealed");
            }
        });
    }

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

});
