// ============================================================
// Struga Events Rental — Main App (SEEU document §5.2, §6.3)
// ============================================================

const API = '../backend';
const CART_KEY = 'struga_reservation_cart';
let currentUser = null;
let selectedItem = null;

document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadStats();
    loadCategories();
    renderCart();

    const sf = document.getElementById('searchForm');
    if (sf) sf.addEventListener('submit', handleSearch);

    const lf = document.getElementById('loginForm');
    if (lf) lf.addEventListener('submit', handleLogin);

    const rf = document.getElementById('registerForm');
    if (rf) rf.addEventListener('submit', handleRegister);

    const bf = document.getElementById('bookForm');
    if (bf) bf.addEventListener('submit', handleBookSubmit);

    window.addEventListener('scroll', () => {
        const nb = document.getElementById('navbar');
        if (nb) nb.style.background = window.scrollY > 50
            ? 'rgba(13,13,11,0.98)'
            : 'rgba(13,13,11,0.92)';
    });
});

// ── Cart (multi-item reservation) ───────────────────────────
function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch (e) { return []; }
}
function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    renderCart();
}
function addToCart(item, qty = 1) {
    const cart = getCart();
    const existing = cart.find(c => c.item_id === item.id);
    if (existing) existing.quantity += qty;
    else cart.push({
        item_id: item.id,
        name: item.name,
        daily_price: parseFloat(item.daily_price),
        quantity: qty
    });
    saveCart(cart);
}
function clearCart() {
    localStorage.removeItem(CART_KEY);
    renderCart();
}
function renderCart() {
    const section = document.getElementById('cartSection');
    const container = document.getElementById('cartItems');
    const totalEl = document.getElementById('cartTotal');
    if (!section || !container) return;

    const cart = getCart();
    if (!cart.length) {
        section.style.display = 'none';
        return;
    }
    section.style.display = 'block';
    container.innerHTML = '';
    let est = 0;
    cart.forEach((line, idx) => {
        est += line.daily_price * line.quantity;
        const row = document.createElement('div');
        row.className = 'submission-item';
        row.innerHTML = `
            <div class="submission-info">
                <h4>${escHtml(line.name)}</h4>
                <p>Qty: ${line.quantity} · €${line.daily_price.toFixed(2)}/day (estimate per day)</p>
            </div>
            <button class="btn-ghost" onclick="removeCartLine(${idx})">Remove</button>
        `;
        container.appendChild(row);
    });
    if (totalEl) totalEl.textContent = `Estimated daily subtotal: €${est.toFixed(2)} (final total includes rental days + VAT on approval)`;
}
function removeCartLine(index) {
    const cart = getCart();
    cart.splice(index, 1);
    saveCart(cart);
}

function openCheckoutModal() {
    if (!currentUser) { openModal('loginModal'); return; }
    if (currentUser.role !== 'customer') {
        alert('Please use the admin panel for staff bookings.');
        return;
    }
    const cart = getCart();
    if (!cart.length) { alert('Your cart is empty.'); return; }

    const preview = document.getElementById('checkoutCartPreview');
    if (preview) {
        preview.innerHTML = '<strong>Cart:</strong><br>' + cart.map(c =>
            `• ${escHtml(c.name)} × ${c.quantity}`
        ).join('<br>');
    }
    document.getElementById('bookForm').reset();
    openModal('bookModal');
}

// ── Auth ──────────────────────────────────────────────────
async function checkAuth() {
    try {
        const res = await fetch(`${API}/auth_check.php`, { credentials: 'include' });
        const data = await res.json();
        if (data.logged_in) {
            currentUser = data;
            updateNavForUser(data);
        }
    } catch (e) {}
}

function updateNavForUser(user) {
    ['loginBtn','registerBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.style.display = 'inline-flex';
    if (user.role === 'admin' || user.role === 'employee') {
        const adminLink = document.getElementById('adminLink');
        if (adminLink) adminLink.style.display = 'inline';
    } else {
        const dashLink = document.getElementById('dashboardLink');
        if (dashLink) dashLink.style.display = 'inline';
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const form = e.target;
    const errEl = document.getElementById('loginError');
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
        const res = await fetch(`${API}/login.php`, { method: 'POST', body: new FormData(form), credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            closeModal('loginModal');
            window.location = (data.role === 'admin' || data.role === 'employee') ? 'admin.html' : 'dashboard.html';
        } else showError(errEl, data.error || 'Invalid credentials');
    } catch (e) { showError(errEl, 'Connection error.'); }
    btn.disabled = false;
}

async function handleRegister(e) {
    e.preventDefault();
    const form = e.target;
    const errEl = document.getElementById('registerError');
    const sucEl = document.getElementById('registerSuccess');
    try {
        const res = await fetch(`${API}/register.php`, { method: 'POST', body: new FormData(form), credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            showSuccess(sucEl, 'Account created! Sign in to continue.');
            hideEl(errEl);
            form.reset();
            setTimeout(() => switchModal('registerModal', 'loginModal'), 2000);
        } else showError(errEl, data.error || 'Registration failed');
    } catch (e) { showError(errEl, 'Connection error.'); }
}

async function logout() {
    await fetch(`${API}/logout.php`, { credentials: 'include' });
    window.location = 'index.html';
}

async function loadStats() {
    try {
        const res = await fetch(`${API}/stats.php`);
        const data = await res.json();
        setEl('statItems', data.items ?? '0');
        setEl('statReservations', data.reservations ?? '0');
        setEl('statCustomers', data.customers ?? '0');
    } catch (e) {}
}

async function loadCategories() {
    try {
        const res = await fetch(`${API}/get_categories.php`);
        const data = await res.json();
        const sel = document.getElementById('categoryFilter');
        if (!sel) return;
        data.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
    } catch (e) {}
}

async function handleSearch(e) {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(e.target)).toString();
    const resultsDiv = document.getElementById('searchResults');
    const msgDiv = document.getElementById('searchMsg');
    resultsDiv.innerHTML = '';
    msgDiv.innerHTML = '<div class="loading-state">Loading catalog...</div>';
    try {
        const res = await fetch(`${API}/search_items.php?${params}`);
        const results = await res.json();
        msgDiv.innerHTML = '';
        if (!results.length) {
            msgDiv.innerHTML = '<div class="loading-state">No items match your search.</div>';
            return;
        }
        results.forEach(item => resultsDiv.appendChild(createItemCard(item)));
    } catch (err) {
        msgDiv.innerHTML = '<div class="loading-state">Error loading catalog.</div>';
    }
}

function createItemCard(item) {
    const card = document.createElement('div');
    card.className = 'grave-card';
    const photoHtml = item.image_path
        ? `<div class="grave-card-photo"><img src="../uploads/${escHtml(item.image_path)}" alt="" loading="lazy"></div>`
        : `<div class="grave-card-no-photo">◆</div>`;
    card.innerHTML = `
        ${photoHtml}
        <div class="grave-card-body">
            <h3 class="grave-card-name">${escHtml(item.name)}</h3>
            <p class="grave-card-dates">€${parseFloat(item.daily_price).toFixed(2)} / day · Deposit €${parseFloat(item.deposit_amount || 0).toFixed(2)}</p>
            <p class="grave-card-cemetery">${escHtml(item.category_name)} · ${item.quantity_available} available</p>
            <div class="grave-card-actions">
                <button class="btn-map" type="button" data-item-id="${item.id}">Details</button>
                <button type="button" data-add-id="${item.id}">Add to Cart</button>
            </div>
        </div>
    `;
    card.querySelector('[data-item-id]').addEventListener('click', () => openItem(item));
    card.querySelector('[data-add-id]').addEventListener('click', () => {
        if (!currentUser) { openModal('loginModal'); return; }
        addToCart(item, 1);
    });
    return card;
}

function openItem(item) {
    selectedItem = item;
    const img = item.image_path
        ? `<img src="../uploads/${item.image_path}" class="grave-modal-photo" alt="">`
        : '';
    document.getElementById('itemModalContent').innerHTML = `
        ${img}
        <h2 class="grave-modal-name">${escHtml(item.name)}</h2>
        <p class="grave-modal-dates">${escHtml(item.category_name)}</p>
        <div class="grave-modal-bio">${escHtml(item.description || '')}</div>
        <div class="grave-modal-actions">
            <button class="btn-primary" type="button" id="modalAddCart">Add to Cart</button>
        </div>
    `;
    document.getElementById('modalAddCart').onclick = () => { addToCart(item, 1); closeModal('itemModal'); };
    openModal('itemModal');
}

async function handleBookSubmit(e) {
    e.preventDefault();
    if (!currentUser) { openModal('loginModal'); return; }

    const cart = getCart();
    if (!cart.length) { alert('Cart is empty.'); return; }

    const form = e.target;
    const errEl = document.getElementById('bookError');
    const fd = new FormData();
    fd.append('event_name', form.event_name.value);
    fd.append('delivery_address', form.delivery_address.value);
    fd.append('event_date', form.event_date.value);
    fd.append('end_date', form.end_date.value);
    fd.append('notes', form.notes.value);
    fd.append('items', JSON.stringify(cart.map(c => ({ item_id: c.item_id, quantity: c.quantity }))));

    try {
        const res = await fetch(`${API}/create_reservation.php`, { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            clearCart();
            closeModal('bookModal');
            alert(`Reservation submitted! Estimated total: €${parseFloat(data.total_price).toFixed(2)}. Awaiting approval.`);
            window.location = 'dashboard.html';
        } else showError(errEl, data.error || 'Could not submit');
    } catch (e) { showError(errEl, 'Connection error.'); }
}

function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}
function switchModal(from, to) { closeModal(from); openModal(to); }
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});
function toggleMenu() {
    document.getElementById('navLinks').classList.toggle('open');
}

function showError(el, msg) { if (el) { el.textContent = msg; el.classList.add('visible'); } }
function showSuccess(el, msg) { if (el) { el.textContent = msg; el.classList.add('visible'); } }
function hideEl(el) { if (el) el.classList.remove('visible'); }
function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
