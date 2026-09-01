<?php
/**
 * Admin — Bulk Sales / Walk-in Customers (Selling Point)
 * For customers who buy at the farm gate, from agents, or via phone orders
 * Tracks: customer info, product sold, quantity, price, payment status
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Bulk Sales & Walk-in - Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = getDB();
$tab = $_GET['tab'] ?? 'sales';
$validTabs = ['sales','customers'];
if (!in_array($tab, $validTabs, true)) $tab = 'sales';

$customers = $pdo ? $pdo->query("SELECT * FROM walk_in_customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.6rem;color:var(--admin-text-heading);font-weight:800;">Bulk Sales & Walk-in</h1>
        <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Record sales at the farm gate, from agents, or via phone. Tracks customer info and payment status.</p>
    </div>
</div>

<!-- Tab Bar -->
<div style="display:flex;gap:4px;background:#f1f5f9;padding:5px;border-radius:10px;margin-bottom:24px;overflow-x:auto;">
    <a href="?tab=sales" style="display:flex;align-items:center;gap:7px;padding:9px 18px;border-radius:7px;text-decoration:none;font-weight:600;font-size:0.86rem;<?= $tab==='sales'?'background:#fff;color:var(--admin-primary);box-shadow:0 1px 6px rgba(15,23,42,0.08);':'color:#64748b;' ?>"><i data-lucide="receipt" style="width:15px;height:15px;"></i> Sales</a>
    <a href="?tab=customers" style="display:flex;align-items:center;gap:7px;padding:9px 18px;border-radius:7px;text-decoration:none;font-weight:600;font-size:0.86rem;<?= $tab==='customers'?'background:#fff;color:var(--admin-primary);box-shadow:0 1px 6px rgba(15,23,42,0.08);':'color:#64748b;' ?>"><i data-lucide="users" style="width:15px;height:15px;"></i> Customers</a>
</div>

<?php if ($tab === 'sales'): ?>
<div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;">All Bulk Sales</h3>
        <div style="display:flex;gap:8px;align-items:center;">
            <input class="admin-form-control" type="date" id="bs-from" onchange="loadBulkSales()" style="max-width:160px;">
            <input class="admin-form-control" type="date" id="bs-to" onchange="loadBulkSales()" style="max-width:160px;">
            <a href="/Backend/api/export.php?module=bulk_sales" class="btn btn-outline" id="bs-export"><i data-lucide="download" style="width:14px;height:14px;"></i> Export CSV</a>
            <button class="btn btn-primary" onclick="openBulkSaleModal()"><i data-lucide="plus-circle" style="width:16px;height:16px;"></i> New Sale</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr>
                <th>Date</th><th>Sale No</th><th>Customer</th><th>Product</th>
                <th>Qty</th><th>Unit Price</th><th>Total</th>
                <th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody id="bs-body">
                <tr><td colspan="11" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php else: /* customers */ ?>
<div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;">Walk-in Customers</h3>
        <div style="display:flex;gap:8px;">
            <a href="/Backend/api/export.php?module=customers" class="btn btn-outline"><i data-lucide="download" style="width:14px;height:14px;"></i> Export CSV</a>
            <button class="btn btn-primary" onclick="openCustomerModal()"><i data-lucide="plus" style="width:15px;height:15px;"></i> Add Customer</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Phone</th><th>Type</th><th>Address</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="6" style="text-align:center;padding:28px;color:#94a3b8;">No customers yet.</td></tr>
            <?php else: foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge-pill badge-pill-info"><?= htmlspecialchars($c['customer_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($c['address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(substr($c['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button class="btn btn-trans btn-sm" onclick='openCustomerModal(<?= json_encode($c) ?>)'><i data-lucide="pencil" style="width:13px;height:13px;"></i></button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Bulk Sale Modal -->
<div id="bs-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;padding:32px;border-radius:12px;width:100%;max-width:640px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:90vh;overflow-y:auto;margin:20px;">
        <h3 id="bs-modal-title" style="margin:0 0 22px;font-family:'Outfit',sans-serif;">New Bulk Sale</h3>
        <form id="bs-form">
            <input type="hidden" id="bs-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="admin-form-group"><label class="admin-form-label">Date *</label><input class="admin-form-control" type="date" id="bs-date" required value="<?= date('Y-m-d') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">Existing Customer</label>
                    <select class="admin-form-control" id="bs-customer" onchange="fillCustomer()">
                        <option value="">— New customer or quick sale —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8') ?>" data-phone="<?= htmlspecialchars($c['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Customer Name *</label><input class="admin-form-control" id="bs-name" required placeholder="Walk-in customer name"></div>
                <div class="admin-form-group"><label class="admin-form-label">Phone</label><input class="admin-form-control" id="bs-phone" placeholder="07XX XXX XXX"></div>
                <div class="admin-form-group"><label class="admin-form-label">Product *</label>
                    <select class="admin-form-control" id="bs-product" required>
                        <option value="eggs">Eggs (crates)</option>
                        <option value="broiler">Broiler (kg)</option>
                        <option value="kienyeji">Kienyeji (kg)</option>
                        <option value="manure">Manure (bag)</option>
                        <option value="feed">Feed (bag)</option>
                        <option value="chicks">Chicks (piece)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Quantity *</label><input class="admin-form-control" type="number" step="0.01" id="bs-qty" required min="0.01"></div>
                <div class="admin-form-group"><label class="admin-form-label">Unit</label>
                    <select class="admin-form-control" id="bs-unit">
                        <option value="crate">Crate</option>
                        <option value="kg">kg</option>
                        <option value="bag">Bag</option>
                        <option value="piece">Piece</option>
                        <option value="litre">Litre</option>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Unit Price (KES) *</label><input class="admin-form-control" type="number" step="0.01" id="bs-price" required min="0.01"></div>
                <div class="admin-form-group"><label class="admin-form-label">Amount Paid (KES)</label><input class="admin-form-control" type="number" step="0.01" id="bs-paid" value="0"></div>
                <div class="admin-form-group"><label class="admin-form-label">Payment Method</label>
                    <select class="admin-form-control" id="bs-method">
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="credit">Credit (Pay Later)</option>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Payment Status</label>
                    <select class="admin-form-control" id="bs-status">
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="admin-form-group" style="grid-column:span 2"><label class="admin-form-label">Notes</label><textarea class="admin-form-control" id="bs-notes" rows="2"></textarea></div>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeBulkSaleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i data-lucide="save" style="width:15px;height:15px;"></i> Record Sale</button>
            </div>
        </form>
    </div>
</div>

<!-- Customer Modal -->
<div id="cust-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:32px;border-radius:12px;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0,0,0.15);">
        <h3 id="cust-modal-title" style="margin:0 0 22px;font-family:'Outfit',sans-serif;">Add Customer</h3>
        <form id="cust-form">
            <input type="hidden" id="c-id">
            <div class="admin-form-group"><label class="admin-form-label">Customer Name *</label><input class="admin-form-control" id="c-name" required></div>
            <div class="admin-form-group"><label class="admin-form-label">Phone</label><input class="admin-form-control" id="c-phone"></div>
            <div class="admin-form-group"><label class="admin-form-label">Type</label>
                <select class="admin-form-control" id="c-type">
                    <option value="retail">Retail</option>
                    <option value="wholesale">Wholesale</option>
                    <option value="institution">Institution</option>
                    <option value="agent">Agent</option>
                </select>
            </div>
            <div class="admin-form-group"><label class="admin-form-label">Address</label><input class="admin-form-control" id="c-addr"></div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeCustomerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i data-lucide="save" style="width:15px;height:15px;"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBulkSaleModal(d) {
    document.getElementById('bs-modal-title').textContent = d?.id ? 'Edit Sale' : 'New Bulk Sale';
    document.getElementById('bs-id').value = d?.id || '';
    document.getElementById('bs-date').value = d?.sale_date || new Date().toISOString().split('T')[0];
    document.getElementById('bs-customer').value = d?.customer_id || '';
    document.getElementById('bs-name').value = d?.customer_name || '';
    document.getElementById('bs-phone').value = d?.customer_phone || '';
    document.getElementById('bs-product').value = d?.product_type || 'eggs';
    document.getElementById('bs-qty').value = d?.quantity || '';
    document.getElementById('bs-unit').value = d?.unit || 'crate';
    document.getElementById('bs-price').value = d?.unit_price || '';
    document.getElementById('bs-paid').value = d?.amount_paid || 0;
    document.getElementById('bs-method').value = d?.payment_method || 'cash';
    document.getElementById('bs-status').value = d?.payment_status || 'paid';
    document.getElementById('bs-notes').value = d?.notes || '';
    document.getElementById('bs-modal').style.display = 'flex';
}
function closeBulkSaleModal() { document.getElementById('bs-modal').style.display = 'none'; }

function fillCustomer() {
    const sel = document.getElementById('bs-customer');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('bs-name').value = opt.dataset.name || '';
        document.getElementById('bs-phone').value = opt.dataset.phone || '';
    }
}

document.getElementById('bs-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id', document.getElementById('bs-id').value);
    fd.append('sale_date', document.getElementById('bs-date').value);
    fd.append('customer_id', document.getElementById('bs-customer').value);
    fd.append('customer_name', document.getElementById('bs-name').value);
    fd.append('customer_phone', document.getElementById('bs-phone').value);
    fd.append('product_type', document.getElementById('bs-product').value);
    fd.append('quantity', document.getElementById('bs-qty').value);
    fd.append('unit', document.getElementById('bs-unit').value);
    fd.append('unit_price', document.getElementById('bs-price').value);
    fd.append('amount_paid', document.getElementById('bs-paid').value);
    fd.append('payment_method', document.getElementById('bs-method').value);
    fd.append('payment_status', document.getElementById('bs-status').value);
    fd.append('notes', document.getElementById('bs-notes').value);
    const res = await fetch('/Backend/api/admin_poultry_v2.php?action=save_bulk_sale', {method:'POST', body:fd});
    const r = await res.json();
    if (r.success) { alert('Sale recorded. Total: KES ' + r.total.toFixed(2) + ', Balance: KES ' + r.balance.toFixed(2)); closeBulkSaleModal(); loadBulkSales(); }
    else alert('Error: ' + r.message);
});

async function loadBulkSales() {
    const tbody = document.getElementById('bs-body');
    if (!tbody) return;
    const from = document.getElementById('bs-from')?.value || '';
    const to = document.getElementById('bs-to')?.value || '';
    let url = '/Backend/api/admin_poultry_v2.php?action=get_bulk_sales';
    if (from) url += '&from=' + from;
    if (to) url += '&to=' + to;
    tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>';
    try {
        const res = await fetch(url);
        const r = await res.json();
        if (!r.success) { tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#dc2626;">' + (r.message||'Failed') + '</td></tr>'; return; }
        const data = r.data || [];
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:28px;color:#94a3b8;">No sales yet.</td></tr>'; return; }
        tbody.innerHTML = data.map(s => {
            const statusBadge = s.payment_status==='paid'?'badge-pill-success':(s.payment_status==='partial'?'badge-pill-warning':(s.payment_status==='pending'?'badge-pill-info':'badge-pill-danger'));
            return `<tr>
                <td>${s.sale_date}</td>
                <td><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;">${s.sale_number}</code></td>
                <td><strong>${escapeHtml(s.customer_name)}</strong>${s.customer_phone?'<br><small style="color:#64748b;">'+s.customer_phone+'</small>':''}</td>
                <td><span class="badge-pill badge-pill-info">${s.product_type}</span></td>
                <td>${parseFloat(s.quantity).toFixed(2)} ${s.unit}</td>
                <td>KES ${parseFloat(s.unit_price).toFixed(2)}</td>
                <td><strong>KES ${parseFloat(s.total_amount).toFixed(2)}</strong></td>
                <td>KES ${parseFloat(s.amount_paid).toFixed(2)}</td>
                <td style="color:${s.balance>0?'#dc2626':'#3E8A3A'};">KES ${parseFloat(s.balance).toFixed(2)}</td>
                <td><span class="badge-pill ${statusBadge}">${s.payment_status}</span><br><small style="color:#64748b;">${s.payment_method}</small></td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <button class="btn btn-info btn-sm" onclick='viewBulkSale(${JSON.stringify(s)})' title="View / Print Receipt"><i data-lucide="eye" style="width:13px;height:13px;"></i></button>
                        <button class="btn btn-trans btn-sm" onclick='openBulkSaleModal(${JSON.stringify(s)})' title="Edit"><i data-lucide="pencil" style="width:13px;height:13px;"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteBulkSale(${s.id})" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#dc2626;">Network error</td></tr>';
    }
}

function viewBulkSale(s) {
    const total = parseFloat(s.total_amount || 0);
    const paid = parseFloat(s.amount_paid || 0);
    const bal = parseFloat(s.balance || (total - paid));
    const qty = parseFloat(s.quantity || 0);
    const price = parseFloat(s.unit_price || 0);

    const statusBadge = s.payment_status==='paid'?'badge-pill-success':(s.payment_status==='partial'?'badge-pill-warning':(s.payment_status==='pending'?'badge-pill-info':'badge-pill-danger'));

    document.getElementById('bulk-preview-body').innerHTML = `
        <div id="printable-bulk-sale" style="font-family:'Inter',sans-serif;color:#0f172a;max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
            <!-- Header Banner -->
            <div style="background:#1E3A5F;padding:22px 28px;color:#ffffff;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #D97706;gap:20px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <?php $bs_logo = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg'; ?>
                    <div style="background:#ffffff;padding:6px 10px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo htmlspecialchars($bs_logo, ENT_QUOTES, 'UTF-8'); ?>" style="height:44px;max-width:140px;object-fit:contain;" alt="Logo" onerror="this.parentElement.style.display='none'">
                    </div>
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-weight:800;font-size:1.35rem;color:#ffffff;letter-spacing:-0.02em;">Kind Commodities Ltd</h2>
                        <div style="font-size:0.82rem;color:#cbd5e1;margin-top:2px;">Farm Gate &amp; Wholesale Commodities Sales</div>
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
                    <!-- Left: Customer -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:#1E3A5F;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">CUSTOMER / BUYER</div>
                            <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;">${escapeHtml(s.customer_name || 'Cash Customer')}</div>
                            ${s.customer_phone ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Phone:</strong> ' + escapeHtml(s.customer_phone) + '</div>' : ''}
                        </div>
                    </div>

                    <!-- Right: Receipt Meta -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;text-align:right;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#1E3A5F;letter-spacing:0.04em;">SALES RECEIPT</div>
                            <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:2px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">#${escapeHtml(s.sale_number)}</div>
                            
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Sale Date:</span>
                                <strong style="color:#0f172a;">${s.sale_date}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Payment Method:</span>
                                <strong style="color:#0f172a;">${escapeHtml(s.payment_method || 'Cash')}</strong>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                            <span style="font-size:0.82rem;color:#64748b;">Payment Status:</span>
                            <span class="badge-pill ${statusBadge}" style="text-transform:uppercase;font-weight:700;font-size:0.75rem;padding:4px 10px;">${escapeHtml(s.payment_status)}</span>
                        </div>
                    </div>
                </div>

                <!-- Goods Table -->
                <table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:20px;">
                    <thead>
                        <tr style="background:#1E3A5F;color:#ffffff;">
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;width:40px;">#</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;">Product / Description</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:90px;">Quantity</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:110px;">Unit Price</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:130px;">Amount (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#ffffff;">
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;color:#64748b;">1</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.95rem;font-weight:600;color:#0f172a;">${escapeHtml(s.product_type)}</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.9rem;text-align:right;color:#0f172a;">${qty.toLocaleString(undefined,{minimumFractionDigits:2})} ${escapeHtml(s.unit || 'units')}</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.9rem;text-align:right;color:#0f172a;">${price.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.95rem;text-align:right;font-weight:700;color:#0f172a;">${total.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Summary Breakdown -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:20px;">
                    <div style="flex:1;min-width:260px;">
                        ${s.notes ? `
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;font-size:0.85rem;color:#334155;">
                            <div style="font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;color:#1E3A5F;margin-bottom:4px;">Notes / Remarks</div>
                            <div style="white-space:pre-wrap;line-height:1.45;">${escapeHtml(s.notes)}</div>
                        </div>` : ''}
                    </div>

                    <div style="width:280px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:6px;">
                            <span>Total Amount:</span>
                            <strong style="color:#0f172a;">KES ${total.toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#16a34a;margin-bottom:8px;">
                            <span>Amount Paid:</span>
                            <strong>KES ${paid.toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>
                        <div style="background:#1E3A5F;color:#ffffff;margin:-4px -16px -16px -16px;padding:12px 16px;border-bottom-left-radius:7px;border-bottom-right-radius:7px;display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                            <span style="font-weight:700;font-size:0.9rem;letter-spacing:0.04em;">${bal > 0 ? 'BALANCE DUE' : 'TOTAL PAID'}</span>
                            <span style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#ffffff;">KES ${(bal > 0 ? bal : total).toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer / Signoff -->
                <div style="border-top:1px solid #e2e8f0;padding-top:18px;margin-top:16px;display:flex;justify-content:space-between;align-items:flex-end;font-size:0.8rem;color:#64748b;">
                    <div>
                        <div>Thank you for doing business with Kind Commodities!</div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Tel: +254 700 000 000 &bull; info@kindcommoditiesltd.com</div>
                    </div>
                    <div style="text-align:right;">
                        <div>Issued on ${new Date().toLocaleDateString(undefined,{day:'numeric',month:'short',year:'numeric'})}</div>
                    </div>
                </div>
            </div>
        </div>`;
    document.getElementById('view-bulk-modal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

document.addEventListener('click', e => {
    const m = document.getElementById('view-bulk-modal');
    if (m && e.target === m) m.style.display = 'none';
});

async function deleteBulkSale(id) {
    if (!confirm('Delete this sale?')) return;
    const fd = new FormData(); fd.append('id', id);
    const res = await fetch('/Backend/api/admin_poultry_v2.php?action=delete_bulk_sale', {method:'POST', body:fd});
    const r = await res.json();
    if (r.success) loadBulkSales(); else alert(r.message);
}

function openCustomerModal(d) {
    document.getElementById('cust-modal-title').textContent = d?.id ? 'Edit Customer' : 'Add Customer';
    document.getElementById('c-id').value = d?.id || '';
    document.getElementById('c-name').value = d?.customer_name || '';
    document.getElementById('c-phone').value = d?.phone || '';
    document.getElementById('c-type').value = d?.customer_type || 'retail';
    document.getElementById('c-addr').value = d?.address || '';
    document.getElementById('cust-modal').style.display = 'flex';
}
function closeCustomerModal() { document.getElementById('cust-modal').style.display = 'none'; }

document.getElementById('cust-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id', document.getElementById('c-id').value);
    fd.append('customer_name', document.getElementById('c-name').value);
    fd.append('phone', document.getElementById('c-phone').value);
    fd.append('customer_type', document.getElementById('c-type').value);
    fd.append('address', document.getElementById('c-addr').value);
    const res = await fetch('/Backend/api/admin_poultry_v2.php?action=save_walkin_customer', {method:'POST', body:fd});
    const r = await res.json();
    if (r.success) location.reload();
    else alert('Error: ' + r.message);
});

function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

document.addEventListener('DOMContentLoaded', () => {
    loadBulkSales();
    if (typeof lucide !== 'undefined') lucide.createIcons();
    const exp = document.getElementById('bs-export');
    if (exp) {
        const update = () => {
            const f = document.getElementById('bs-from')?.value || '';
            const t = document.getElementById('bs-to')?.value || '';
            exp.href = '/Backend/api/export.php?module=bulk_sales' + (f?'&from='+f:'') + (t?'&to='+t:'');
        };
        document.getElementById('bs-from')?.addEventListener('change', update);
        document.getElementById('bs-to')?.addEventListener('change', update);
    }
});
</script>

<!-- View Bulk Sale Modal -->
<div id="view-bulk-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:820px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:92vh;overflow-y:auto;margin:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 22px;border-bottom:1px solid var(--admin-border);position:sticky;top:0;background:#fff;z-index:5;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;">Bulk Sale Receipt</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print</button>
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('view-bulk-modal').style.display='none'">Close</button>
            </div>
        </div>
        <div id="bulk-preview-body" style="padding:28px;"></div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #view-bulk-modal, #view-bulk-modal > div, #bulk-preview-body, #printable-bulk-sale, #printable-bulk-sale * { visibility: visible !important; }
    #view-bulk-modal {
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
    #view-bulk-modal > div {
        max-width: 100% !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
    #view-bulk-modal > div > div:first-child { display: none !important; }
    #bulk-preview-body { padding: 0 !important; }
    #printable-bulk-sale {
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
