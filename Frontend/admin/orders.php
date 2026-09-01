<?php
/**
 * Admin — Online Orders
 * Mirrors the kind_orders_report CSV structure:
 *   Order ID, Order Number, Customer, Email, Amount, Status, Date
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Online Orders - Admin';
include __DIR__ . '/includes/admin_header.php';
?>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.6rem;color:var(--admin-text-heading);font-weight:800;">Online Orders</h1>
        <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Manage orders placed through the public website. Match the kind_orders_report CSV export structure.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/Backend/api/export.php?module=orders" class="btn btn-outline"><i data-lucide="download" style="width:15px;height:15px;"></i> Export CSV</a>
        <button class="btn btn-primary" onclick="openOrderModal()"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Order</button>
    </div>
</div>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-info"><small>Orders Today</small><strong id="ord-kpi-today">—</strong></div><div class="stat-card-icon accent"><i data-lucide="shopping-cart" style="width:22px;height:22px;"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Pending</small><strong id="ord-kpi-pending">—</strong></div><div class="stat-card-icon" style="background:#E9F2DC;color:#2C6B31;"><i data-lucide="clock" style="width:22px;height:22px;"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Revenue This Month</small><strong id="ord-kpi-month">—</strong></div><div class="stat-card-icon info"><i data-lucide="banknote" style="width:22px;height:22px;"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Avg Order Value</small><strong id="ord-kpi-avg">—</strong></div><div class="stat-card-icon" style="background:#D3E8B8;color:#3E8A3A;"><i data-lucide="trending-up" style="width:22px;height:22px;"></i></div></div>
</div>

<div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;">All Online Orders</h3>
        <div style="display:flex;gap:8px;">
            <select class="admin-form-control" id="ord-filter-status" onchange="loadOrders()" style="max-width:160px;">
                <option value="">All status</option>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <input class="admin-form-control" type="date" id="ord-from" onchange="loadOrders()" style="max-width:160px;">
            <input class="admin-form-control" type="date" id="ord-to" onchange="loadOrders()" style="max-width:160px;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr>
                <th>Order ID</th><th>Order Number</th><th>Customer</th>
                <th>Email</th><th>Phone</th><th>Amount</th>
                <th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody id="orders-body">
                <tr><td colspan="9" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function loadOrders() {
    const tbody = document.getElementById('orders-body');
    const status = document.getElementById('ord-filter-status').value;
    const from = document.getElementById('ord-from').value;
    const to = document.getElementById('ord-to').value;
    let url = '/Backend/api/admin_actions.php?action=list_orders';
    if (status) url += '&status=' + status;
    if (from) url += '&from=' + from;
    if (to) url += '&to=' + to;
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>';
    try {
        const res = await fetch(url);
        const r = await res.json();
        if (!r.success) { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#dc2626;">' + (r.message||'Failed') + '</td></tr>'; return; }
        const data = r.data || [];
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:28px;color:#94a3b8;">No orders yet.</td></tr>'; updateKpis([]); return; }
        tbody.innerHTML = data.map(o => {
            const sc = {pending:'badge-pill-warning',paid:'badge-pill-info',processing:'badge-pill-info',shipped:'badge-pill-success',completed:'badge-pill-success',cancelled:'badge-pill-danger'}[o.status] || 'badge-pill-info';
            return `<tr>
                <td>${o.id}</td>
                <td><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;">${o.order_number}</code></td>
                <td><strong>${escapeHtml(o.customer_name||'Guest')}</strong></td>
                <td>${escapeHtml(o.customer_email||'—')}</td>
                <td>${escapeHtml(o.phone_contact||'—')}</td>
                <td><strong>KES ${parseFloat(o.total_amount).toFixed(2)}</strong></td>
                <td><span class="badge-pill ${sc}">${o.status}</span></td>
                <td>${o.created_at?.split(' ')[0]||o.created_at}</td>
                <td>
                    <button class="btn btn-info btn-sm" onclick="viewOrder(${o.id})" title="View / Print Invoice"><i data-lucide="eye" style="width:13px;height:13px;"></i></button>
                    <button class="btn btn-primary btn-sm" onclick="updateOrderStatus(${o.id},'processing')" title="Mark Processing"><i data-lucide="play" style="width:13px;height:13px;"></i></button>
                    <button class="btn btn-success btn-sm" onclick="updateOrderStatus(${o.id},'completed')" title="Mark Completed"><i data-lucide="check" style="width:13px;height:13px;"></i></button>
                </td>
            </tr>`;
        }).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
        updateKpis(data);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#dc2626;">Network error</td></tr>';
    }
}

function updateKpis(data) {
    const today = new Date().toISOString().split('T')[0];
    const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const todayCount = data.filter(o => o.created_at?.startsWith(today)).length;
    const pending = data.filter(o => o.status==='pending' || o.status==='paid').length;
    const monthRev = data.filter(o => o.created_at?.startsWith(monthStart.slice(0,7))).reduce((s,o)=>s+parseFloat(o.total_amount||0),0);
    const totalRev = data.reduce((s,o)=>s+parseFloat(o.total_amount||0),0);
    const avg = data.length ? totalRev/data.length : 0;
    document.getElementById('ord-kpi-today').textContent = todayCount;
    document.getElementById('ord-kpi-pending').textContent = pending;
    document.getElementById('ord-kpi-month').textContent = 'KES ' + monthRev.toFixed(0).toLocaleString();
    document.getElementById('ord-kpi-avg').textContent = 'KES ' + avg.toFixed(0).toLocaleString();
}

let currentOrderData = null;
async function viewOrder(id) {
    try {
        const res = await fetch('/Backend/api/admin_actions.php?action=get_order&id=' + id);
        const r = await res.json();
        if (!r.success || !r.data) { alert(r.message || 'Could not load order details'); return; }
        const o = r.data;
        currentOrderData = o;
        
        let rowIdx = 0;
        const rows = (o.items || []).map(it => {
            rowIdx++;
            const bg = rowIdx % 2 === 1 ? '#ffffff' : '#f8fafc';
            const price = parseFloat(it.price_at_purchase || 0);
            const qty = parseFloat(it.quantity || 0);
            const lineTot = price * qty;
            return `<tr style="background:${bg};">
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;color:#64748b;">${rowIdx}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;font-weight:600;color:#0f172a;">${escapeHtml(it.name)}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;color:#0f172a;">${qty.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;color:#0f172a;">${price.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;font-weight:700;color:#0f172a;">${lineTot.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
            </tr>`;
        }).join('');

        const sc = {pending:'badge-pill-warning',paid:'badge-pill-info',processing:'badge-pill-info',shipped:'badge-pill-success',completed:'badge-pill-success',cancelled:'badge-pill-danger'}[o.status] || 'badge-pill-info';

        document.getElementById('order-modal-body').innerHTML = `
            <div id="printable-order" style="font-family:'Inter',sans-serif;color:#0f172a;max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
                <!-- Header Banner -->
                <div style="background:#1E3A5F;padding:22px 28px;color:#ffffff;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #D97706;gap:20px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:16px;">
                        <?php $order_logo = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg'; ?>
                        <div style="background:#ffffff;padding:6px 10px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                            <img src="<?php echo htmlspecialchars($order_logo, ENT_QUOTES, 'UTF-8'); ?>" style="height:44px;max-width:140px;object-fit:contain;" alt="Logo" onerror="this.parentElement.style.display='none'">
                        </div>
                        <div>
                            <h2 style="margin:0;font-family:'Outfit',sans-serif;font-weight:800;font-size:1.35rem;color:#ffffff;letter-spacing:-0.02em;">Kind Commodities Ltd</h2>
                            <div style="font-size:0.82rem;color:#cbd5e1;margin-top:2px;">Quality Agricultural Commodities &amp; Farm Management</div>
                            <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">
                                <span>Tel: +254 700 000 000</span> &bull; 
                                <span>Email: info@kindcommoditiesltd.com</span> &bull; 
                                <span>Kenya</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="padding:28px;">
                    <!-- Symmetrical 2-Column Info Grid -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                        <!-- Left: Customer / Shipping -->
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;">
                            <div>
                                <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:#1E3A5F;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">CUSTOMER &amp; DELIVERY</div>
                                <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;">${escapeHtml(o.customer_name || 'Guest Customer')}</div>
                                ${o.customer_email ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Email:</strong> ' + escapeHtml(o.customer_email) + '</div>' : ''}
                                ${o.phone_contact ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Phone:</strong> ' + escapeHtml(o.phone_contact) + '</div>' : ''}
                                ${o.shipping_address ? '<div style="font-size:0.85rem;color:#475569;margin-top:3px;"><strong style="color:#64748b;font-weight:500;">Shipping Address:</strong> ' + escapeHtml(o.shipping_address) + '</div>' : ''}
                            </div>
                        </div>

                        <!-- Right: Order Details -->
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;text-align:right;display:flex;flex-direction:column;justify-content:space-between;">
                            <div>
                                <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.3rem;color:#1E3A5F;letter-spacing:0.04em;">ORDER RECEIPT / INVOICE</div>
                                <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:2px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">#${escapeHtml(o.order_number)}</div>
                                
                                <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                    <span style="color:#64748b;">Order Date:</span>
                                    <strong style="color:#0f172a;">${o.created_at}</strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                    <span style="color:#64748b;">Payment Method:</span>
                                    <strong style="color:#0f172a;">${escapeHtml(o.payment_method || 'Standard')}</strong>
                                </div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                                <span style="font-size:0.82rem;color:#64748b;">Order Status:</span>
                                <span class="badge-pill ${sc}" style="text-transform:uppercase;font-weight:700;font-size:0.75rem;padding:4px 10px;">${escapeHtml(o.status)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:20px;">
                        <thead>
                            <tr style="background:#1E3A5F;color:#ffffff;">
                                <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;width:35px;">#</th>
                                <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;">Product / Item</th>
                                <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:80px;">Qty</th>
                                <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:120px;">Unit Price</th>
                                <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:130px;">Amount (KES)</th>
                            </tr>
                        </thead>
                        <tbody>${rows || '<tr><td colspan="5" style="text-align:center;padding:16px;color:#94a3b8;">No items</td></tr>'}</tbody>
                    </table>

                    <!-- Totals Block -->
                    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
                        <div style="width:280px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                            <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:8px;">
                                <span>Subtotal:</span>
                                <strong style="color:#0f172a;">KES ${parseFloat(o.total_amount || 0).toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                            </div>
                            <div style="background:#1E3A5F;color:#ffffff;margin:-4px -16px -16px -16px;padding:12px 16px;border-bottom-left-radius:7px;border-bottom-right-radius:7px;display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                                <span style="font-weight:700;font-size:0.9rem;letter-spacing:0.04em;">TOTAL PAID / DUE</span>
                                <span style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#ffffff;">KES ${parseFloat(o.total_amount || 0).toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Signoff -->
                    <div style="border-top:1px solid #e2e8f0;padding-top:18px;margin-top:16px;display:flex;justify-content:space-between;align-items:flex-end;font-size:0.8rem;color:#64748b;">
                        <div>
                            <div>Thank you for your business!</div>
                            <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">For inquiries: +254 700 000 000 &bull; info@kindcommoditiesltd.com</div>
                        </div>
                        <div style="text-align:right;">
                            <div>Kind Commodities Management System</div>
                        </div>
                    </div>
                </div>
            </div>`;
        document.getElementById('view-order-modal').style.display = 'flex';
    } catch (e) { alert('Failed to load order details'); }
}

async function updateOrderStatus(id, status) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', status);
    const res = await fetch('/Backend/api/admin_actions.php?action=update_order_status', {method:'POST', body:fd});
    const r = await res.json();
    if (r.success) loadOrders(); else alert(r.message);
}

function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

document.addEventListener('DOMContentLoaded', () => {
    loadOrders();
    if (typeof lucide !== 'undefined') lucide.createIcons();
    // Update orders export link with date range
    const ordExportLink = document.querySelector('a[href*="export.php?module=orders"]');
    if (ordExportLink) {
        const update = () => {
            const f = document.getElementById('ord-from')?.value || '';
            const t = document.getElementById('ord-to')?.value || '';
            ordExportLink.href = '/Backend/api/export.php?module=orders' + (f?'&from='+f:'') + (t?'&to='+t:'');
        };
        document.getElementById('ord-from')?.addEventListener('change', update);
        document.getElementById('ord-to')?.addEventListener('change', update);
    }
});

document.addEventListener('click', e => {
    const m = document.getElementById('view-order-modal');
    if (m && e.target === m) m.style.display = 'none';
});
</script>

<!-- View Order Invoice / Receipt Modal -->
<div id="view-order-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:820px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:92vh;overflow-y:auto;margin:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 22px;border-bottom:1px solid var(--admin-border);position:sticky;top:0;background:#fff;z-index:5;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;">Order Invoice / Receipt</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print</button>
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('view-order-modal').style.display='none'">Close</button>
            </div>
        </div>
        <div id="order-modal-body" style="padding:28px;"></div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #view-order-modal, #view-order-modal > div, #order-modal-body, #printable-order, #printable-order * { visibility: visible !important; }
    #view-order-modal {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: auto !important;
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        z-index: 99999 !important;
    }
    #view-order-modal > div {
        max-width: 100% !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
    #view-order-modal > div > div:first-child { display: none !important; }
    #order-modal-body { padding: 0 !important; }
    #printable-order {
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
