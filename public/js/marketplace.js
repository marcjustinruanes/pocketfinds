document.addEventListener("DOMContentLoaded", () => {

    // ── Scroll reveal ── (one-time fade+rise as sections enter the viewport;
    // respects prefers-reduced-motion by simply not arming at all)
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const revealTargets = document.querySelectorAll(".pf-reveal");
    if (revealTargets.length && !reduceMotion && "IntersectionObserver" in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("pf-revealed");
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -40px 0px" });
        revealTargets.forEach(el => io.observe(el));
    } else {
        revealTargets.forEach(el => el.classList.add("pf-revealed"));
    }

    // ── Cursor glow ── (a soft radial wash that follows the pointer, used on
    // the hero and the "Start Selling" CTA banner. Under reduced-motion, skip
    // attaching the listener entirely: each element's default --glow-x/--glow-y
    // still renders one static ambient glow, just nothing tracks the cursor.)
    function attachCursorGlow(el) {
        if (!el) return;
        let frame = null;
        el.addEventListener("mousemove", (e) => {
            const rect = el.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            if (frame) return;
            frame = requestAnimationFrame(() => {
                el.style.setProperty("--glow-x", x + "%");
                el.style.setProperty("--glow-y", y + "%");
                frame = null;
            });
        });
    }
    if (!reduceMotion) {
        attachCursorGlow(document.querySelector(".pfx-hero"));
        attachCursorGlow(document.querySelector(".pfx-cta-banner"));
    }

    // ── Deals carousel ── prev/next buttons scroll the track by one card width;
    // no autoplay, the user always drives it. Buttons disable at each end so it
    // never looks like it's stuck. Touch/trackpad swipe already works natively
    // via overflow-x, this just adds a mouse-friendly affordance on top.
    document.querySelectorAll("[data-carousel]").forEach(track => {
        const section = track.closest("section");
        const prevBtn = section?.querySelector("[data-carousel-prev]");
        const nextBtn = section?.querySelector("[data-carousel-next]");
        if (!prevBtn || !nextBtn) return;

        const updateCarouselButtons = () => {
            const max = track.scrollWidth - track.clientWidth - 1;
            prevBtn.disabled = track.scrollLeft <= 0;
            nextBtn.disabled = max <= 0 || track.scrollLeft >= max;
        };
        const scrollCarousel = (dir) => {
            const card = track.querySelector(".pfx-product-card");
            const amount = card ? card.getBoundingClientRect().width + 20 : 260;
            track.scrollBy({ left: dir * amount, behavior: reduceMotion ? "auto" : "smooth" });
        };
        prevBtn.addEventListener("click", () => scrollCarousel(-1));
        nextBtn.addEventListener("click", () => scrollCarousel(1));
        track.addEventListener("scroll", updateCarouselButtons, { passive: true });
        window.addEventListener("resize", updateCarouselButtons);
        updateCarouselButtons();
    });

    // ── Bounded first-row stagger ── (category chips / product cards; only the
    // first 8 items in a group get an incremental delay, so a long grid never
    // makes you wait row after row as you keep scrolling)
    const STAGGER_STEP_MS = 45;
    const STAGGER_MAX_INDEX = 7;
    const staggerGroups = document.querySelectorAll(".pf-stagger-group");
    const revealStaggerGroup = (group) => {
        group.querySelectorAll(".pf-stagger-item").forEach((el, i) => {
            el.style.setProperty("--stagger-delay", `${Math.min(i, STAGGER_MAX_INDEX) * STAGGER_STEP_MS}ms`);
            el.classList.add("pf-revealed");
        });
    };
    if (staggerGroups.length && !reduceMotion && "IntersectionObserver" in window) {
        const groupIo = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    revealStaggerGroup(entry.target);
                    groupIo.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -30px 0px" });
        staggerGroups.forEach(el => groupIo.observe(el));
    } else {
        staggerGroups.forEach(revealStaggerGroup);
    }

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
    // <a>, e.g. the wishlist heart, and must not also navigate the browser to the product.
    // Wishlist/bag buttons get a brief tactile pop before the sign-in modal opens — real
    // click feedback, not a fake "added to cart" state, since nothing is actually added
    // until the guest signs in.)
    document.querySelectorAll("[data-protected]").forEach(b =>
        b.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            if (b.matches(".pfx-product-wish, .pfx-product-bag, .pfx-showcase-wish, .pfx-showcase-bag")) {
                b.classList.add("pfx-added");
                setTimeout(() => b.classList.remove("pfx-added"), 500);
            }
            openModal();
        })
    );

    // ── Category showcase: in-stock filter + sort ── (rearranges the curated
    // preview under "Shop by Category". Real per-item data-attributes only —
    // no fetch, no fabricated ordering. Purely a JS enhancement: with JS off,
    // the server-rendered hero + list already show correctly.)
    (function () {
        const showcase = document.querySelector("[data-showcase]");
        if (!showcase) return;

        const heroSlot   = showcase.querySelector("[data-hero-slot]");
        const listSlot    = showcase.querySelector("[data-list-slot]");
        const emptyNotice = showcase.querySelector("[data-showcase-empty]");
        const instock     = document.querySelector("[data-instock-toggle]");
        const sortSelect  = document.querySelector("[data-sort-select]");
        const visibleEl   = document.querySelector("[data-visible-count]");
        const totalEl     = document.querySelector("[data-total-count]");
        const clearBtn    = showcase.querySelector("[data-clear-instock]");
        if (!heroSlot || !listSlot) return;

        // Original, server-rendered order = "newest first" — captured once so
        // the "Newest first" sort option can restore it without re-fetching.
        const items = [...showcase.querySelectorAll("[data-showcase-item]")];
        items.forEach((el, i) => el.dataset.originalIndex = i);

        function render() {
            const onlyInStock = instock?.checked;
            const sortBy = sortSelect?.value || "newest";

            let visible = items.filter(el => !onlyInStock || Number(el.dataset.stock) > 0);
            const hiddenItems = items.filter(el => !visible.includes(el));

            const sorters = {
                newest:     (a, b) => Number(a.dataset.originalIndex) - Number(b.dataset.originalIndex),
                price_asc:  (a, b) => Number(a.dataset.price) - Number(b.dataset.price),
                price_desc: (a, b) => Number(b.dataset.price) - Number(a.dataset.price),
                rating:     (a, b) => Number(b.dataset.rating) - Number(a.dataset.rating),
            };
            visible = visible.sort(sorters[sortBy] || sorters.newest);

            if (visible.length) {
                heroSlot.appendChild(visible[0]);
                visible.slice(1).forEach(el => listSlot.appendChild(el));
            }
            hiddenItems.forEach(el => { el.hidden = true; listSlot.appendChild(el); });
            visible.forEach(el => { el.hidden = false; });

            if (emptyNotice) emptyNotice.hidden = visible.length > 0;
            if (visibleEl) visibleEl.textContent = visible.length;
            if (totalEl) totalEl.textContent = visible.length;
        }

        instock?.addEventListener("change", render);
        sortSelect?.addEventListener("change", render);
        clearBtn?.addEventListener("click", () => {
            if (instock) instock.checked = false;
            render();
        });
    })();

});
