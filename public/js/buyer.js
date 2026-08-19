// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const shell   = document.getElementById('appShell');
document.querySelectorAll('[data-sidebar-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        shell.classList.toggle('collapsed');
    });
});

// Logout modal
const logoutOverlay = document.getElementById('logoutOverlay');
document.querySelectorAll('[data-logout]').forEach(btn => {
    btn.addEventListener('click', () => logoutOverlay.classList.add('open'));
});
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => logoutOverlay.classList.remove('open'));
});
logoutOverlay?.addEventListener('click', e => {
    if (e.target === logoutOverlay) logoutOverlay.classList.remove('open');
});

// ── Cart variant modal ────────────────────────────────────────────────────────
const cartOverlay = document.getElementById('cartOverlay');
let cmQty       = 1;
let cmPrice     = 0;
let cmIsBuy     = false;
let cmOrigin    = null;
let cmProductId = null;
let cmImg       = null;
let cmName      = null;

const CART_ICON_PATH = 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z';

function openCart(productName, price, colors, sizes, isBuyNow, triggerEl, productId, img) {
    cmQty       = 1;
    cmPrice     = price;
    cmIsBuy     = isBuyNow;
    cmOrigin    = triggerEl || null;
    cmProductId = productId || null;
    cmImg       = img || null;
    cmName      = productName;

    // header icon
    const iconWrap = document.getElementById('cmIconWrap');
    const headerIcon = document.getElementById('cmHeaderIcon');
    iconWrap.style.background = isBuyNow ? 'var(--pink)' : 'var(--pink-soft)';
    headerIcon.style.stroke   = isBuyNow ? '#fff' : 'var(--pink-dark)';
    headerIcon.innerHTML      = isBuyNow
        ? `<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>`
        : `<path d="${CART_ICON_PATH}"/>`;

    document.getElementById('cmTitle').textContent       = isBuyNow ? 'Buy Now' : 'Add to Cart';
    document.getElementById('cmSub').textContent         = productName;
    document.getElementById('cmQty').textContent         = 1;
    document.getElementById('cmConfirmLabel').textContent = isBuyNow ? 'Buy Now' : 'Add to Cart';

    // confirm button icon
    const confirmIcon = document.getElementById('cmConfirmIcon');
    confirmIcon.innerHTML = isBuyNow
        ? `<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>`
        : `<path d="${CART_ICON_PATH}"/>`;

    updateCmTotal();

    // Colors
    const colorGroup = document.getElementById('cmColorGroup');
    const colorEl    = document.getElementById('cmColors');
    if (colors && colors.length) {
        colorEl.innerHTML = colors.map((c, i) =>
            `<button class="cm-opt${i===0?' active':''}" onclick="cmSelect(this)">${c}</button>`
        ).join('');
        colorGroup.style.display = '';
    } else {
        colorGroup.style.display = 'none';
    }

    // Sizes
    const sizeGroup = document.getElementById('cmSizeGroup');
    const sizeEl    = document.getElementById('cmSizes');
    const sizeLabel = document.getElementById('cmSizeLabel');
    if (sizes && sizes.length) {
        sizeLabel.textContent = sizes.some(s => s.includes('Switch')) ? 'Switch Type' : 'Size';
        sizeEl.innerHTML = sizes.map((s, i) =>
            `<button class="cm-opt${i===0?' active':''}" onclick="cmSelect(this)">${s}</button>`
        ).join('');
        sizeGroup.style.display = '';
    } else {
        sizeGroup.style.display = 'none';
    }

    cartOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    cartOverlay.classList.remove('open');
    document.body.style.overflow = '';
}

function cmSelect(btn) {
    btn.closest('.cm-options').querySelectorAll('.cm-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function cmChangeQty(d) {
    cmQty = Math.max(1, cmQty + d);
    document.getElementById('cmQty').textContent = cmQty;
    updateCmTotal();
}

function updateCmTotal() {
    document.getElementById('cmTotal').textContent = '₱' + (cmPrice * cmQty).toLocaleString();
}

function cmConfirm() {
    const color = document.querySelector('#cmColors .cm-opt.active')?.textContent.trim() || '';
    const size  = document.querySelector('#cmSizes .cm-opt.active')?.textContent.trim() || '';
    closeCart();
    if (!cmIsBuy) {
        // POST to server
        fetch('/buyer/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
            },
            body: JSON.stringify({ product_id: cmProductId, name: cmName, price: cmPrice, color, size, qty: cmQty, img: cmImg })
        })
        .then(async r => {
            const data = await r.json();
            if (!r.ok) throw new Error(data.message || 'Unable to add this item to your cart.');
            return data;
        })
        .then(data => {
            // update cart badge if present
            const badge = document.getElementById('cartBadge');
            if (badge) { badge.textContent = data.count; badge.style.display = ''; }
            flyToCart(cmOrigin);
        })
        .catch(error => {
            showToast(error.message || 'Unable to add this item to your cart.', 'error');
        });
    } else {
        showToast('Proceeding to checkout…', 'buy');
    }
}

function flyToCart(originEl) {
    const cartBtn = document.getElementById('cartIconBtn');
    const fly     = document.getElementById('flyItem');
    if (!cartBtn || !fly) return;

    // start position: from the trigger button, or center of screen
    const startRect = originEl
        ? originEl.getBoundingClientRect()
        : { left: window.innerWidth/2 - 14, top: window.innerHeight/2 - 14, width: 28, height: 28 };
    const endRect   = cartBtn.getBoundingClientRect();

    const startX = startRect.left + startRect.width / 2 - 14;
    const startY = startRect.top + startRect.height / 2 - 14;
    const deltaX = endRect.left + endRect.width / 2 - 14 - startX;
    const deltaY = endRect.top + endRect.height / 2 - 14 - startY;
    const arcX = deltaX * .48;
    const arcY = deltaY * .28 - 80;

    fly.getAnimations().forEach(animation => animation.cancel());
    fly.style.left = startX + 'px';
    fly.style.top = startY + 'px';
    fly.style.opacity = '1';

    const animation = fly.animate([
        { transform: 'translate(0, 0) scale(1)', opacity: 1 },
        { transform: `translate(${arcX}px, ${arcY}px) scale(1.18) rotate(-14deg)`, opacity: 1, offset: .42 },
        { transform: `translate(${deltaX}px, ${deltaY}px) scale(.45) rotate(8deg)`, opacity: 1, offset: .82 },
        { transform: `translate(${deltaX}px, ${deltaY}px) scale(.2)`, opacity: 0 }
    ], {
        duration: 1200,
        easing: 'cubic-bezier(.16,.84,.44,1)',
        fill: 'forwards'
    });

    animation.finished.then(() => {
        fly.style.opacity   = '0';
        fly.style.transform = '';
        cartBtn.classList.add('cart-bounce');
        setTimeout(() => cartBtn.classList.remove('cart-bounce'), 400);
        showToast('Added to cart!', 'cart');
    }).catch(() => {});
}

function showToast(msg, type) {
    const stack = document.getElementById('toastStack');
    const t = document.createElement('div');
    t.className = 'toast';
    const icon = type === 'cart'
        ? `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>`
        : `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>`;
    t.innerHTML = icon + msg;
    stack.appendChild(t);
    setTimeout(() => t.remove(), 2800);
}

cartOverlay?.addEventListener('click', e => {
    if (e.target === cartOverlay) closeCart();
});

// ── Browse filter ─────────────────────────────────────────────────────────────
function filterBrowse() {
    const q   = (document.getElementById('browseSearch')?.value || '').toLowerCase();
    const cat = (document.getElementById('browseCategory')?.value || '').toLowerCase();
    const cards = document.querySelectorAll('#browseGrid .product-card');
    let visible = 0;
    cards.forEach(card => {
        const name    = card.dataset.name || '';
        const cardCat = (card.dataset.cat || '').toLowerCase();
        const matchQ   = !q   || name.includes(q);
        const matchCat = !cat || cardCat.includes(cat);
        card.style.display = matchQ && matchCat ? '' : 'none';
        if (matchQ && matchCat) visible++;
    });
    const empty = document.getElementById('browseEmpty');
    if (empty) empty.style.display = visible === 0 ? '' : 'none';
}
