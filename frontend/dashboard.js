// Struga Events — Customer Dashboard

document.addEventListener('DOMContentLoaded', () => {
    checkDashboardAuth();
    loadReservations();
    loadCalendar();
    loadNotifications();
});

async function checkDashboardAuth() {
    try {
        const res = await fetch(`${API}/auth_check.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.logged_in) { window.location = 'index.html'; return; }
        if (data.role === 'admin' || data.role === 'employee') { window.location = 'admin.html'; return; }
        const name = `${data.first_name} ${data.last_name}`;
        setEl('sidebarName', name);
        setEl('navUser', name);
        const avatar = document.getElementById('sidebarAvatar');
        if (avatar) avatar.textContent = data.first_name.charAt(0).toUpperCase();
    } catch (e) { window.location = 'index.html'; }
}

async function loadReservations() {
    const list = document.getElementById('reservationsList');
    if (!list) return;
    try {
        const res = await fetch(`${API}/my_reservations.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.length) {
            list.innerHTML = '<div class="loading-state">No reservations yet.</div>';
            return;
        }
        list.innerHTML = '';
        data.forEach(r => {
            const items = (r.items || []).map(i =>
                `${escHtml(i.item_name)} × ${i.quantity} (€${parseFloat(i.subtotal).toFixed(2)})`
            ).join('<br>');
            const div = document.createElement('div');
            div.className = 'submission-item';
            const addr = r.delivery_address || r.event_location || '—';
            div.innerHTML = `
                <div class="submission-info">
                    <h4>${escHtml(r.event_name)}</h4>
                    <p>${formatDate(r.event_date)} — ${formatDate(r.end_date)} · ${escHtml(addr)}</p>
                    <p>${items}</p>
                    <p><strong>Total:</strong> €${parseFloat(r.total_price || r.total_amount || 0).toFixed(2)}</p>
                    <span class="status-badge status-${r.status}">${r.status}</span>
                </div>
                <div class="submission-actions">
                    ${r.status === 'pending' ? `<button class="btn-danger" onclick="cancelReservation(${r.id})">Cancel</button>` : ''}
                    ${r.status === 'approved' || r.status === 'active' || r.status === 'completed'
                        ? `<a class="btn-ghost" href="invoice.html?reservation_id=${r.id}">Invoice (PDF)</a>` : ''}
                </div>
            `;
            list.appendChild(div);
        });
    } catch (e) {
        list.innerHTML = '<div class="loading-state">Error loading reservations.</div>';
    }
}

async function cancelReservation(id) {
    if (!confirm('Cancel this pending reservation?')) return;
    const fd = new FormData();
    fd.append('reservation_id', id);
    const res = await fetch(`${API}/cancel_reservation.php`, { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) loadReservations();
    else alert(data.error || 'Could not cancel');
}

async function loadCalendar() {
    const list = document.getElementById('calendarList');
    if (!list) return;
    const from = new Date(); from.setDate(1);
    const to = new Date(from.getFullYear(), from.getMonth() + 3, 0);
    const params = `from=${from.toISOString().slice(0,10)}&to=${to.toISOString().slice(0,10)}`;
    try {
        const res = await fetch(`${API}/get_calendar_events.php?${params}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.length) { list.innerHTML = '<div class="loading-state">No scheduled events.</div>'; return; }
        list.innerHTML = '';
        data.forEach(ev => {
            const div = document.createElement('div');
            div.className = 'notif-item';
            div.innerHTML = `<p class="notif-msg"><strong>${escHtml(ev.title)}</strong></p>
                <p class="notif-time">${formatDateTime(ev.start_datetime)} — ${escHtml(ev.location || '')}</p>`;
            list.appendChild(div);
        });
    } catch (e) { list.innerHTML = '<div class="loading-state">Error loading calendar.</div>'; }
}

async function loadNotifications() {
    const list = document.getElementById('notificationsList');
    if (!list) return;
    try {
        const res = await fetch(`${API}/notifications.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.length) { list.innerHTML = '<div class="loading-state">No notifications.</div>'; return; }
        list.innerHTML = '';
        data.forEach(n => {
            const div = document.createElement('div');
            div.className = 'notif-item' + (n.is_read ? '' : ' unread');
            div.innerHTML = `<p class="notif-msg">${escHtml(n.message)}</p><p class="notif-time">${formatDateTime(n.created_at)}</p>`;
            list.appendChild(div);
        });
        await fetch(`${API}/notifications.php`, {
            method: 'POST', body: new URLSearchParams({ mark_read: '1' }), credentials: 'include'
        });
    } catch (e) { list.innerHTML = '<div class="loading-state">Error.</div>'; }
}

function showTab(tabId, linkEl) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    if (linkEl) linkEl.classList.add('active');
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
function formatDateTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
