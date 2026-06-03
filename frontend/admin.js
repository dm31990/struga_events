// Struga Events — Admin / Employee Panel

let adminRole = 'employee';
let calendarInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    checkAdminAuth();
    loadAdminDashboard();
    loadPending();
    loadCustomers();
});

async function checkAdminAuth() {
    try {
        const res = await fetch(`${API}/auth_check.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.logged_in || (data.role !== 'admin' && data.role !== 'employee')) {
            window.location = 'index.html';
            return;
        }
        adminRole = data.role;
        setEl('adminName', `${data.first_name} ${data.last_name}`);
        if (data.role !== 'admin') {
            ['reportsNav', 'usersNav'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
        } else {
            loadUsers();
            const rf = document.getElementById('reportFrom');
            const rt = document.getElementById('reportTo');
            if (rf) rf.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
            if (rt) rt.value = new Date().toISOString().slice(0, 10);
        }
        loadInventory();
    } catch (e) {
        window.location = 'index.html';
    }
}

async function loadAdminDashboard() {
    try {
        const res = await fetch(`${API}/admin_stats.php`, { credentials: 'include' });
        const data = await res.json();
        setEl('pendingCount', data.pending ?? '0');
        setEl('approvedCount', data.approved ?? '0');
        setEl('revenueCount', '€' + (parseFloat(data.revenue) || 0).toFixed(0));
        setEl('customersCount', data.customers ?? '0');
        setEl('lowStockCount', data.low_stock ?? '0');
        if (data.pending > 0) {
            const b = document.getElementById('pendingBadge');
            if (b) { b.textContent = data.pending; b.style.display = 'inline-flex'; }
        }
    } catch (e) {}
}

async function loadPending() {
    const list = document.getElementById('pendingList');
    if (!list) return;
    try {
        const res = await fetch(`${API}/admin_get_pending.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.length) {
            list.innerHTML = '<div class="loading-state">No pending bookings.</div>';
            return;
        }
        list.innerHTML = '';
        data.forEach(r => {
            const items = (r.items || []).map(i => `${escHtml(i.item_name)} × ${i.quantity}`).join(', ');
            const addr = r.delivery_address || r.event_location || '—';
            const item = document.createElement('div');
            item.className = 'admin-item';
            item.id = `pending-${r.id}`;
            item.innerHTML = `
                <div class="submission-info">
                    <h4>${escHtml(r.event_name)}</h4>
                    <p>${formatDate(r.event_date)} — ${formatDate(r.end_date)} · ${escHtml(addr)}</p>
                    <p>Customer: ${escHtml(r.customer_name)} (${escHtml(r.customer_email)})</p>
                    <p>Items: ${items}</p>
                    <p><strong>Total:</strong> €${parseFloat(r.total_price || r.total_amount || 0).toFixed(2)}</p>
                </div>
                <div class="submission-actions">
                    <button class="btn-success" onclick="moderateReservation(${r.id}, 'approve')">✓ Approve</button>
                    <button class="btn-danger" onclick="moderateReservation(${r.id}, 'reject')">✕ Reject</button>
                </div>
            `;
            list.appendChild(item);
        });
    } catch (e) {
        list.innerHTML = '<div class="loading-state">Error loading bookings.</div>';
    }
}

async function moderateReservation(id, action) {
    const fd = new FormData();
    fd.append('reservation_id', id);
    fd.append('action', action);
    try {
        const res = await fetch(`${API}/moderate_reservation.php`, { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            const el = document.getElementById(`pending-${id}`);
            if (el) {
                el.style.opacity = '0.5';
                el.querySelector('.submission-actions').innerHTML =
                    `<span class="status-badge status-${data.status}">${data.status}</span>`;
            }
            loadAdminDashboard();
            initFullCalendar();
        } else alert(data.error || 'Failed');
    } catch (e) { alert('Connection error.'); }
}

async function setReservationActive(id) {
    const fd = new FormData();
    fd.append('reservation_id', id);
    fd.append('status', 'active');
    await fetch(`${API}/update_reservation_status.php`, { method: 'POST', body: fd, credentials: 'include' });
    loadPending();
}

async function loadCustomers() {
    const list = document.getElementById('customersList');
    if (!list) return;
    try {
        const res = await fetch(`${API}/admin_customers.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.length) { list.innerHTML = '<div class="loading-state">No customers.</div>'; return; }
        list.innerHTML = '';
        data.forEach(c => {
            const item = document.createElement('div');
            item.className = 'admin-item';
            item.innerHTML = `
                <div class="submission-info">
                    <h4>${escHtml(c.first_name)} ${escHtml(c.last_name)}</h4>
                    <p>${escHtml(c.email)} · ${escHtml(c.phone || '—')}</p>
                    <p>Bookings: ${c.reservation_count} · Spent: €${parseFloat(c.total_spent).toFixed(2)}</p>
                </div>`;
            list.appendChild(item);
        });
    } catch (e) { list.innerHTML = '<div class="loading-state">Error.</div>'; }
}

async function initFullCalendar() {
    const el = document.getElementById('fullCalendar');
    if (!el || typeof FullCalendar === 'undefined') return;

    const from = new Date();
    from.setMonth(from.getMonth() - 1);
    const to = new Date();
    to.setMonth(to.getMonth() + 12);
    const params = `from=${from.toISOString().slice(0,10)}&to=${to.toISOString().slice(0,10)}`;

    const res = await fetch(`${API}/get_calendar_events.php?${params}`, { credentials: 'include' });
    const events = await res.json();

    if (calendarInstance) calendarInstance.destroy();
    calendarInstance = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        events: events.map(ev => ({
            title: ev.title,
            start: ev.start_datetime,
            end: ev.end_datetime,
            color: ev.color || '#2e6b8a'
        }))
    });
    calendarInstance.render();
}

async function loadReports() {
    if (adminRole !== 'admin') return;
    const from = document.getElementById('reportFrom').value;
    const to = document.getElementById('reportTo').value;
    const out = document.getElementById('reportOutput');
    try {
        const res = await fetch(`${API}/admin_reports.php?from=${from}&to=${to}`, { credentials: 'include' });
        const data = await res.json();
        let text = `Report ${data.from} — ${data.to}\n`;
        text += `Reservations: ${data.summary.reservation_count}\n`;
        text += `Approved: ${data.summary.approved_count}\n`;
        text += `Cancelled: ${data.summary.cancelled_count}\n`;
        text += `Revenue (invoiced): €${parseFloat(data.revenue).toFixed(2)}\n\nTop items:\n`;
        (data.top_items || []).forEach(i => { text += `  - ${i.name}: ${i.times_rented} rentals\n`; });
        out.textContent = text;
    } catch (e) { out.textContent = 'Error generating report.'; }
}

async function loadUsers() {
    const list = document.getElementById('usersList');
    if (!list) return;
    try {
        const res = await fetch(`${API}/admin_users.php`, { credentials: 'include' });
        const data = await res.json();
        list.innerHTML = '';
        data.forEach(u => {
            const row = document.createElement('div');
            row.className = 'admin-item';
            row.innerHTML = `
                <div class="submission-info">
                    <h4>${escHtml(u.first_name)} ${escHtml(u.last_name)}</h4>
                    <p>${escHtml(u.email)} · Role: ${u.role} · Status: ${u.status}</p>
                </div>
                ${u.id && u.role !== 'admin' ? `
                <div class="submission-actions">
                    <button class="btn-ghost" onclick="toggleUser(${u.id}, '${u.status === 'active' ? 'inactive' : 'active'}')">
                        ${u.status === 'active' ? 'Deactivate' : 'Activate'}
                    </button>
                </div>` : ''}`;
            list.appendChild(row);
        });
    } catch (e) { list.innerHTML = '<div class="loading-state">Error.</div>'; }
}

async function toggleUser(id, status) {
    const fd = new FormData();
    fd.append('user_id', id);
    fd.append('status', status);
    await fetch(`${API}/admin_users.php`, { method: 'POST', body: fd, credentials: 'include' });
    loadUsers();
}

async function loadInventory() {
    const list = document.getElementById('inventoryList');
    if (!list) return;
    try {
        const res = await fetch(`${API}/admin_inventory.php`, { credentials: 'include' });
        const data = await res.json();
        list.innerHTML = '';
        data.forEach(item => {
            const row = document.createElement('div');
            row.className = 'admin-item';
            row.innerHTML = `
                <div class="submission-info">
                    <h4>${escHtml(item.name)}</h4>
                    <p>${escHtml(item.category_name)} · €${parseFloat(item.daily_price).toFixed(2)}/day · ${item.status}</p>
                    <p>Stock: ${item.quantity_available} / ${item.quantity_total}</p>
                    ${item.image_path ? `<img src="../uploads/${escHtml(item.image_path)}" style="height:60px;margin-top:0.5rem;border-radius:4px">` : ''}
                </div>
                <div class="submission-actions" style="flex-direction:column;align-items:flex-end">
                    <input type="number" min="0" value="${item.quantity_available}" id="qty-${item.id}" style="width:70px;margin-bottom:0.3rem">
                    <input type="number" step="0.01" value="${item.daily_price}" id="price-${item.id}" style="width:90px;margin-bottom:0.3rem">
                    <button class="btn-ghost" onclick="updateInventory(${item.id})">Save</button>
                    <label class="btn-ghost" style="cursor:pointer;margin-top:0.3rem">
                        Upload Photo
                        <input type="file" accept="image/jpeg,image/png" style="display:none" onchange="uploadPhoto(${item.id}, this)">
                    </label>
                </div>`;
            list.appendChild(row);
        });
    } catch (e) { list.innerHTML = '<div class="loading-state">Error.</div>'; }
}

async function updateInventory(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('quantity_available', document.getElementById(`qty-${id}`).value);
    fd.append('daily_price', document.getElementById(`price-${id}`).value);
    const res = await fetch(`${API}/admin_inventory.php`, { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    alert(data.success ? 'Saved.' : (data.error || 'Failed'));
}

async function uploadPhoto(itemId, input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('item_id', itemId);
    fd.append('photo', input.files[0]);
    const res = await fetch(`${API}/upload_item_photo.php`, { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) loadInventory();
    else alert(data.error || 'Upload failed');
}

function showAdminTab(tabId, linkEl) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    if (linkEl) linkEl.classList.add('active');
    if (tabId === 'calendarTab') initFullCalendar();
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
function formatDateTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
