// Generic modal open/close (covers modals like #messagesModal that aren't
// wired to a single dedicated overlay variable)
document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
        document.getElementById(opener.dataset.modalOpen)?.classList.add('open');
    }
    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
        const closed=closer.closest('.modal-overlay') || closer.closest('.chat-widget');closed?.classList.remove('open');
        if(closed?.id==='messagesModal'){closed.setAttribute('aria-hidden','true');document.getElementById('messagesModalBody')?.replaceChildren();}
    }
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open, .chat-widget.open').forEach(m => {m.classList.remove('open');if(m.id==='messagesModal')m.setAttribute('aria-hidden','true');});
        document.getElementById('messagesModalBody')?.replaceChildren();
    }
});

// Dropdowns (notifications, etc.)
document.querySelectorAll('[data-dropdown-toggle]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const panel = document.getElementById(btn.dataset.dropdownToggle);
        const isOpen = panel.classList.contains('open');
        document.querySelectorAll('.dropdown-panel.open').forEach(p => p.classList.remove('open'));
        if (!isOpen) panel.classList.add('open');
    });
});
document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-panel.open').forEach(p => p.classList.remove('open'));
});

// ── Messages modal ────────────────────────────────────────────────────────────
const MESSAGES_BASE_URL = '/buyer/messages';

function loadMessagesPanel(url) {
    const body = document.getElementById('messagesModalBody');
    if (!body) return;
    const target=new URL(url||MESSAGES_BASE_URL,window.location.origin);
    if(target.origin!==window.location.origin||target.pathname!==MESSAGES_BASE_URL)return;
    target.searchParams.set('embedded','1');
    const frame=document.createElement('iframe');frame.className='buyer-messages-frame';frame.title='Messages with sellers';frame.src=target.href;
    body.replaceChildren(frame);
}

function openMessagesModal(url) {
    // The dedicated Messages page already has a conversation panel.
    if (window.location.pathname.replace(/\/+$/, '') === MESSAGES_BASE_URL) {
        window.location.assign(url || MESSAGES_BASE_URL);
        return;
    }
    const messagesWidget = document.getElementById('messagesModal');
    messagesWidget?.classList.add('open');
    messagesWidget?.setAttribute('aria-hidden', 'false');
    const assistant=document.getElementById('pfAssistantPanel');if(assistant)assistant.hidden=true;
    document.querySelector('.pf-assistant-launch')?.setAttribute('aria-expanded','false');
    loadMessagesPanel(url || MESSAGES_BASE_URL);
}

document.querySelectorAll('[data-messages-trigger]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const widget = document.getElementById('messagesModal');
        const url = btn.dataset.messagesUrl || btn.getAttribute('href') || MESSAGES_BASE_URL;
        // Clicking the toggle again while already open (and not switching
        // to a different conversation) just closes the widget.
        if (widget?.classList.contains('open') && widget.dataset.currentUrl === url) {
            widget.classList.remove('open');
            widget.setAttribute('aria-hidden','true');
            document.getElementById('messagesModalBody')?.replaceChildren();
            return;
        }
        if (widget) widget.dataset.currentUrl = url;
        openMessagesModal(url);
    });
});

// Only conversations inside the floating widget reload its panel in place.
// Full-page conversation links retain ordinary browser navigation.
document.addEventListener('click', (e) => {
    const link = e.target.closest('a.chat-list-item, a.chat-mobile-back');
    if (link?.closest('#messagesModal') && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey && e.button === 0) {
        e.preventDefault();
        openMessagesModal(link.getAttribute('href'));
    }
});

// Logout modal
const logoutOverlay = document.getElementById('logoutOverlay');
document.querySelectorAll('[data-logout]').forEach(btn => {
    btn.addEventListener('click', () => logoutOverlay.classList.add('open'));
});
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => logoutOverlay?.classList.remove('open'));
});
logoutOverlay?.addEventListener('click', e => {
    if (e.target === logoutOverlay) logoutOverlay.classList.remove('open');
});

// ── Cart variant modal ────────────────────────────────────────────────────────
const cartOverlay = document.getElementById('cartOverlay');
let cmQty       = 1;
let cmPrice     = 0;
let cmOrigin    = null;
let cmProductId = null;
let cmImg       = null;
let cmName      = null;

const CART_ICON_PATH = 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 5m12-5l2 5M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z';

function openCart(productName, price, colors, sizes, triggerEl, productId, img) {
    cmQty       = 1;
    cmPrice     = price;
    cmOrigin    = triggerEl || null;
    cmProductId = productId || null;
    cmImg       = img || null;
    cmName      = productName;

    // header icon
    const iconWrap = document.getElementById('cmIconWrap');
    const headerIcon = document.getElementById('cmHeaderIcon');
    iconWrap.style.background = 'var(--pink-soft)';
    headerIcon.style.stroke   = 'var(--pink-dark)';
    headerIcon.innerHTML      = `<path d="${CART_ICON_PATH}"/>`;

    document.getElementById('cmTitle').textContent       = 'Add to Cart';
    document.getElementById('cmSub').textContent         = productName;
    document.getElementById('cmQty').textContent         = 1;
    document.getElementById('cmConfirmLabel').textContent = 'Add to Cart';

    // confirm button icon
    const confirmIcon = document.getElementById('cmConfirmIcon');
    confirmIcon.innerHTML = `<path d="${CART_ICON_PATH}"/>`;

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
}

function flyToCart(originEl) {
    const actions = originEl?.closest('.pc-actions');
    let product = {};
    try { product = JSON.parse(actions?.dataset.wishlistProduct || '{}'); } catch (_) {}
    showProductFeedback({
        id: 'cartFeedbackCard', title: 'Added to cart', origin: originEl,
        name: originEl?.dataset.productName || product.name || actions?.dataset.name || (typeof cmName !== 'undefined' ? cmName : ''),
        image: (originEl?.id === 'pdAddCartBtn' ? document.getElementById('pdMainImg')?.src : '') || originEl?.dataset.productImg || product.img || actions?.dataset.img || (typeof cmImg !== 'undefined' ? cmImg : ''),
        target: document.getElementById('cartIconBtn') || document.querySelector('a[href$="/buyer/cart"]'),
        animate: true,
    });
}

// Cart and wishlist deliberately share the same product card and motion.
function showProductFeedback({id, title, name, image, origin, target, animate = false}) {
    document.getElementById('cartFeedbackCard')?.remove();
    document.getElementById('wishlistFeedbackCard')?.remove();
    const card = document.createElement('div');
    card.id = id; card.setAttribute('role', 'status');
    card.style.cssText = 'position:fixed;right:24px;bottom:30px;z-index:20500;display:flex;align-items:center;gap:12px;width:300px;max-width:calc(100vw - 48px);box-sizing:border-box;padding:14px;border:1px solid #eadfe6;border-radius:10px;background:white;box-shadow:0 8px 28px #59334626;color:#593346;font:13px Inter,system-ui,sans-serif';
    if (image) { const img = document.createElement('img'); img.src = image; img.alt = ''; img.style.cssText = 'width:64px;height:48px;object-fit:cover;border-radius:5px;flex:none'; card.append(img); }
    const copy = document.createElement('div'), heading = document.createElement('strong'), productName = document.createElement('div');
    copy.style.minWidth = '0'; heading.textContent = title; productName.textContent = name;
    productName.style.cssText = 'font-size:11px;color:#8c7381;margin-top:5px;overflow-wrap:anywhere';
    copy.append(heading, productName); card.append(copy); document.body.append(card);
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduced && card.animate) card.animate([{opacity:0,transform:'translateY(16px)'},{opacity:1,transform:'translateY(0)'}],{duration:220});
    if (animate && image && origin && target && !reduced && card.animate) {
        const from = origin.getBoundingClientRect(), to = target.getBoundingClientRect();
        const flying = document.createElement('img'); flying.src = image; flying.alt = '';
        flying.style.cssText = `position:fixed;left:${from.left}px;top:${from.top}px;width:64px;height:48px;object-fit:cover;border-radius:5px;z-index:20600;pointer-events:none`;
        document.body.append(flying);
        flying.animate([{transform:'translate(0,0)',opacity:1},{transform:`translate(${to.left-from.left}px,${to.top-from.top}px) scale(.35)`,opacity:0}],{duration:650,easing:'ease-in-out'}).onfinish = () => flying.remove();
        setTimeout(() => flying.remove(), 1000);
    }
    setTimeout(() => card.remove(), 3500);
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

// ── Product card helpers (data-attribute based) ─────────────────────────────
function pcCart(btn) {
    const d = btn.closest('.pc-actions').dataset;
    openCart(d.name, parseFloat(d.price), [], [], btn, d.id, d.img);
}

// ── Dashboard hero photo rotation — same behavior as the guest homepage hero ──
(() => {
    const heroFrame = document.getElementById('dashHeroPhotoFrame');
    if (!heroFrame) return;
    const slides = [...heroFrame.querySelectorAll('.dash-hero-slide')];
    if (slides.length <= 1) return;
    let heroIdx = 0;
    setInterval(() => {
        slides[heroIdx].classList.remove('active');
        heroIdx = (heroIdx + 1) % slides.length;
        slides[heroIdx].classList.add('active');
    }, 5000);
})();

