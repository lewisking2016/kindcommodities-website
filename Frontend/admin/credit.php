<?php
/**
 * Admin — Customer Credit (Money Owed to You)
 * Track who bought on credit, how much they owe, and when they pay.
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Customer Credit (Owed) - Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = getDB();
$customers = ($pdo && tableExists($pdo, 'walk_in_customers'))
    ? $pdo->query("SELECT id, customer_name, phone, customer_type FROM walk_in_customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.6rem;color:var(--admin-text-heading);font-weight:800;">Customer Credit (Money Owed)</h1>
        <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Track customers who took goods on credit. See who owes you, what's overdue, and record payments when they pay.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/Backend/api/export.php?module=credit" class="btn btn-outline"><i data-lucide="download" style="width:14px;height:14px;"></i> Export CSV</a>
        <button class="btn btn-primary" onclick="openCreditModal()"><i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Record Credit Sale</button>
    </div>
</div>

<!-- Summary -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-info"><small>Total Owed to You</small><strong id="cr-total-owed" style="color:#dc2626;">KES 0</strong></div><div class="stat-card-icon" style="background:#fee2e2;color:#dc2626;"><i data-lucide="hand-coins" style="width:22px;height:22px;"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Overdue (Past Due Date)</small><strong id="cr-overdue" style="color:#dc2626;">KES 0</strong></div><div class="stat-card-icon" style="background:#fee2e2;color:#dc2626;"><i data-lucide="alert-triangle" style="width:22px;height:22px;"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Active Customers w/ Credit</small><strong id="cr-active">0</strong></div><div class="stat-card-icon info"><i data-lucide="users" style="width:22px;height:22px;"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Collected This Month</small><strong id="cr-collected" style="color:#3E8A3A;">KES 0</strong></div><div class="stat-card-icon" style="background:#D3E8B8;color:#3E8A3A;"><i data-lucide="check-circle" style="width:22px;height:22px;"></i></div></div>
</div>

<div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;">All Credit Sales</h3>
        <select class="admin-form-control" id="cr-filter-status" onchange="loadCredit()" style="max-width:180px;">
            <option value="">All</option>
            <option value="unpaid">Not Paid</option>
            <option value="partial">Partly Paid</option>
            <option value="paid">Fully Paid</option>
            <option value="overdue">Overdue</option>
        </select>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr>
                <th>Date</th><th>Customer</th><th>Phone</th>
                <th>What They Took</th><th>Total</th>
                <th>Paid</th><th>Balance</th>
                <th>Due Date</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody id="cr-body">
                <tr><td colspan="10" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- New Credit Modal -->
<div id="credit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;padding:32px;border-radius:12px;width:100%;max-width:520px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:90vh;overflow-y:auto;margin:20px;">
        <h3 style="margin:0 0 8px;font-family:'Outfit',sans-serif;">New Credit Sale</h3>
        <p style="margin:0 0 18px;color:#64748b;font-size:0.85rem;">Customer takes goods now and promises to pay later. Always set a due date!</p>
        <form id="credit-form">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="admin-form-group"><label class="admin-form-label">Date *</label><input class="admin-form-control" type="date" id="cr-date" required value="<?= date('Y-m-d') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">Due Date</label><input class="admin-form-control" type="date" id="cr-due" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
                <div class="admin-form-group" style="grid-column:span 2">
                    <label class="admin-form-label">Existing Customer</label>
                    <select class="admin-form-control" id="cr-cust" onchange="fillCust()">
                        <option value="">— New customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8') ?>" data-phone="<?= htmlspecialchars($c['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Customer Name *</label><input class="admin-form-control" id="cr-name" required></div>
                <div class="admin-form-group"><label class="admin-form-label">Phone</label><input class="admin-form-control" id="cr-phone"></div>
                <div class="admin-form-group" style="grid-column:span 2"><label class="admin-form-label">What they took *</label><input class="admin-form-control" id="cr-item" required placeholder="e.g. 10 crates eggs, 2 bags feed"></div>
                <div class="admin-form-group"><label class="admin-form-label">Total (KES) *</label><input class="admin-form-control" type="number" step="0.01" id="cr-total" min="0.01" required></div>
                <div class="admin-form-group"><label class="admin-form-label">Paid Now (KES)</label><input class="admin-form-control" type="number" step="0.01" id="cr-paid" value="0" min="0"></div>
                <div class="admin-form-group" style="grid-column:span 2"><label class="admin-form-label">Note</label><input class="admin-form-control" id="cr-notes" placeholder="Optional"></div>
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('credit-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i data-lucide="save" style="width:15px;height:15px;"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Modal -->
<div id="pay-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:32px;border-radius:12px;width:100%;max-width:420px;box-shadow:0 20px 40px rgba(0,0,0,0.15);">
        <h3 style="margin:0 0 8px;font-family:'Outfit',sans-serif;color:#3E8A3A;">Record Payment</h3>
        <p style="margin:0 0 18px;color:#64748b;font-size:0.85rem;">Customer is paying back their credit.</p>
        <form id="pay-form">
            <input type="hidden" id="pay-credit-id">
            <div class="admin-form-group"><label class="admin-form-label">Date *</label><input class="admin-form-control" type="date" id="pay-date" required value="<?= date('Y-m-d') ?>"></div>
            <div class="admin-form-group"><label class="admin-form-label">Amount (KES) *</label><input class="admin-form-control" type="number" step="0.01" id="pay-amount" min="0.01" required></div>
            <div class="admin-form-group"><label class="admin-form-label">How Paid</label>
                <select class="admin-form-control" id="pay-method">
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="bank">Bank</option>
                </select>
            </div>
            <div class="admin-form-group"><label class="admin-form-label">Reference (e.g. M-Pesa code)</label><input class="admin-form-control" id="pay-ref"></div>
            <div class="admin-form-group"><label class="admin-form-label">Note</label><input class="admin-form-control" id="pay-notes"></div>
            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="document.getElementById('pay-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-success" style="flex:1;"><i data-lucide="check" style="width:15px;height:15px;"></i> Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function fillCust() {
    const sel = document.getElementById('cr-cust');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('cr-name').value = opt.dataset.name || '';
        document.getElementById('cr-phone').value = opt.dataset.phone || '';
    }
}

function openCreditModal() { document.getElementById('credit-modal').style.display = 'flex'; }

document.getElementById('credit-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('customer_id', document.getElementById('cr-cust').value);
    fd.append('customer_name', document.getElementById('cr-name').value);
    fd.append('customer_phone', document.getElementById('cr-phone').value);
    fd.append('credit_date', document.getElementById('cr-date').value);
    fd.append('due_date', document.getElementById('cr-due').value);
    fd.append('item_description', document.getElementById('cr-item').value);
    fd.append('total_amount', document.getElementById('cr-total').value);
    fd.append('amount_paid', document.getElementById('cr-paid').value);
    fd.append('notes', document.getElementById('cr-notes').value);
    const res = await fetch('/Backend/api/admin_business.php?action=add_credit', {method:'POST', body:fd});
    const r = await res.json();
    if (r.success) { alert('Credit sale recorded. Balance: KES ' + r.balance.toFixed(2)); document.getElementById('credit-modal').style.display='none'; document.getElementById('credit-form').reset(); document.getElementById('cr-date').value = new Date().toISOString().split('T')[0]; loadCredit(); }
    else alert('Error: ' + r.message);
});

function openPayModal(id) {
    document.getElementById('pay-credit-id').value = id;
    document.getElementById('pay-modal').style.display = 'flex';
}

document.getElementById('pay-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('credit_id', document.getElementById('pay-credit-id').value);
    fd.append('payment_date', document.getElementById('pay-date').value);
    fd.append('amount', document.getElementById('pay-amount').value);
    fd.append('paid_through', document.getElementById('pay-method').value);
    fd.append('reference_no', document.getElementById('pay-ref').value);
    fd.append('notes', document.getElementById('pay-notes').value);
    const res = await fetch('/Backend/api/admin_business.php?action=add_credit_payment', {method:'POST', body:fd});
    const r = await res.json();
    if (r.success) { alert('Payment recorded'); document.getElementById('pay-modal').style.display='none'; document.getElementById('pay-form').reset(); document.getElementById('pay-date').value = new Date().toISOString().split('T')[0]; loadCredit(); }
    else alert('Error: ' + r.message);
});

async function loadCredit() {
    const status = document.getElementById('cr-filter-status').value;
    let url = '/Backend/api/admin_business.php?action=list_credit';
    if (status) url += '&status=' + status;
    const tbody = document.getElementById('cr-body');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>';
    try {
        const res = await fetch(url);
        const r = await res.json();
        if (!r.success) { tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#dc2626;">' + (r.message||'Failed') + '</td></tr>'; return; }
        const data = r.data || [];
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:28px;color:#94a3b8;">No credit sales yet.</td></tr>'; updateKpis([]); return; }
        const today = new Date().toISOString().split('T')[0];
        tbody.innerHTML = data.map(c => {
            const isOverdue = c.status !== 'paid' && c.due_date && c.due_date < today;
            const statusBadge = c.status==='paid' ? 'badge-pill-success' : (isOverdue ? 'badge-pill-danger' : (c.status==='partial' ? 'badge-pill-warning' : 'badge-pill-info'));
            const statusTxt = c.status==='paid' ? 'Paid' : (isOverdue ? 'OVERDUE' : (c.status==='partial' ? 'Partly Paid' : 'Not Paid'));
            return `<tr>
                <td>${c.credit_date}</td>
                <td><strong>${escapeHtml(c.customer_name)}</strong></td>
                <td>${escapeHtml(c.customer_phone||'—')}</td>
                <td>${escapeHtml(c.item_description)}</td>
                <td>KES ${parseFloat(c.total_amount).toFixed(2)}</td>
                <td style="color:#3E8A3A;">KES ${parseFloat(c.amount_paid).toFixed(2)}</td>
                <td><strong style="color:${c.balance>0?'#dc2626':'#3E8A3A'};">KES ${parseFloat(c.balance).toFixed(2)}</strong></td>
                <td>${c.due_date||'—'}</td>
                <td><span class="badge-pill ${statusBadge}">${statusTxt}</span></td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <button class="btn btn-info btn-sm" onclick='viewCredit(${JSON.stringify(c)})' title="View / Print Statement"><i data-lucide="eye" style="width:13px;height:13px;"></i></button>
                        ${c.status!=='paid' ? `<button class="btn btn-success btn-sm" onclick="openPayModal(${c.id})" title="Record Payment"><i data-lucide="hand-coins" style="width:13px;height:13px;"></i> Pay</button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
        // Summary
        const totalOwed = data.filter(c => c.status!=='paid').reduce((s,c) => s + parseFloat(c.balance), 0);
        const overdue = data.filter(c => c.status!=='paid' && c.due_date && c.due_date < today).reduce((s,c) => s + parseFloat(c.balance), 0);
        const activeCustomers = new Set(data.filter(c => c.status!=='paid').map(c => c.customer_name)).size;
        const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
        const collectedMonth = data.filter(c => c.last_payment_date && c.last_payment_date >= monthStart).reduce((s,c) => s + parseFloat(c.amount_paid), 0);
        document.getElementById('cr-total-owed').textContent = 'KES ' + totalOwed.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:0});
        document.getElementById('cr-overdue').textContent = 'KES ' + overdue.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:0});
        document.getElementById('cr-active').textContent = activeCustomers;
        document.getElementById('cr-collected').textContent = 'KES ' + collectedMonth.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:0});
    } catch (e) { tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#dc2626;">Network error</td></tr>'; }
}

function viewCredit(c) {
    const total = parseFloat(c.total_amount || 0);
    const paid = parseFloat(c.amount_paid || 0);
    const bal = parseFloat(c.balance || (total - paid));
    const today = new Date().toISOString().split('T')[0];
    const isOverdue = c.status !== 'paid' && c.due_date && c.due_date < today;

    document.getElementById('credit-preview-body').innerHTML = `
        <div id="printable-credit" style="font-family:'Inter',sans-serif;color:#0f172a;max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
            <!-- Header Banner -->
            <div style="background:#1E3A5F;padding:22px 28px;color:#ffffff;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #D97706;gap:20px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <?php $credit_logo = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg'; ?>
                    <div style="background:#ffffff;padding:6px 10px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo htmlspecialchars($credit_logo, ENT_QUOTES, 'UTF-8'); ?>" style="height:44px;max-width:140px;object-fit:contain;" alt="Logo" onerror="this.parentElement.style.display='none'">
                    </div>
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-weight:800;font-size:1.35rem;color:#ffffff;letter-spacing:-0.02em;">Kind Commodities Ltd</h2>
                        <div style="font-size:0.82rem;color:#cbd5e1;margin-top:2px;">Agricultural Commodity Trading &amp; Grain Supply</div>
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
                            <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:#1E3A5F;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">DEBTOR / CUSTOMER</div>
                            <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;">${escapeHtml(c.customer_name)}</div>
                            ${c.customer_phone ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Phone:</strong> ' + escapeHtml(c.customer_phone) + '</div>' : ''}
                        </div>
                    </div>

                    <!-- Right: Statement Meta -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;text-align:right;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#1E3A5F;letter-spacing:0.04em;">CREDIT SALE INVOICE</div>
                            <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:2px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">Ref: #CR-${c.id}</div>
                            
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Sale Date:</span>
                                <strong style="color:#0f172a;">${c.credit_date}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Due Date:</span>
                                <strong style="color:${isOverdue?'#dc2626':'#0f172a'};">${c.due_date || 'Immediate'}</strong>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                            <span style="font-size:0.82rem;color:#64748b;">Status:</span>
                            <span class="badge-pill ${c.status==='paid'?'badge-pill-success':(isOverdue?'badge-pill-danger':'badge-pill-warning')}" style="text-transform:uppercase;font-weight:700;font-size:0.75rem;padding:4px 10px;">${isOverdue ? 'OVERDUE' : (c.status==='paid' ? 'PAID' : (c.status==='partial' ? 'PARTIALLY PAID' : 'OUTSTANDING'))}</span>
                        </div>
                    </div>
                </div>

                <!-- Goods Table -->
                <table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:20px;">
                    <thead>
                        <tr style="background:#1E3A5F;color:#ffffff;">
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;width:40px;">#</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;">Item / Goods Taken</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:150px;">Total Value (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#ffffff;">
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;color:#64748b;">1</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.95rem;font-weight:600;color:#0f172a;">${escapeHtml(c.item_description)}</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.95rem;text-align:right;font-weight:700;color:#0f172a;">${total.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Summary Breakdown -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:20px;">
                    <div style="flex:1;min-width:260px;">
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;font-size:0.85rem;color:#334155;">
                            <div style="font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;color:#1E3A5F;margin-bottom:6px;">Remittance &amp; Payment Details</div>
                            <div style="margin-bottom:4px;"><strong>Bank Transfer / Cash:</strong> Kind Commodities Ltd</div>
                            <div style="margin-bottom:4px;"><strong>M-Pesa / Reference:</strong> Quote Ref #CR-${c.id}</div>
                            ${c.notes ? '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #cbd5e1;color:#64748b;">Note: ' + escapeHtml(c.notes) + '</div>' : ''}
                        </div>
                    </div>

                    <div style="width:280px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:6px;">
                            <span>Original Amount:</span>
                            <strong style="color:#0f172a;">KES ${total.toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#16a34a;margin-bottom:8px;">
                            <span>Total Paid:</span>
                            <strong>KES ${paid.toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>
                        <div style="background:${bal>0?'#dc2626':'#1E3A5F'};color:#ffffff;margin:-4px -16px -16px -16px;padding:12px 16px;border-bottom-left-radius:7px;border-bottom-right-radius:7px;display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                            <span style="font-weight:700;font-size:0.9rem;letter-spacing:0.04em;">${bal>0?'OUTSTANDING BALANCE':'BALANCE CLEARED'}</span>
                            <span style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#ffffff;">KES ${bal.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer / Signoff -->
                <div style="border-top:1px solid #e2e8f0;padding-top:18px;margin-top:16px;display:flex;justify-content:space-between;align-items:flex-end;font-size:0.8rem;color:#64748b;">
                    <div>
                        <div>Kind Commodities Accounts Department</div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Tel: +254 700 000 000 &bull; accounts@kindcommoditiesltd.com</div>
                    </div>
                    <div style="text-align:right;">
                        <div>Statement generated on ${new Date().toLocaleDateString(undefined,{day:'numeric',month:'short',year:'numeric'})}</div>
                    </div>
                </div>
            </div>
        </div>`;
    document.getElementById('view-credit-modal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

document.addEventListener('click', e => {
    const m = document.getElementById('view-credit-modal');
    if (m && e.target === m) m.style.display = 'none';
});

function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

document.addEventListener('DOMContentLoaded', () => { loadCredit(); if (typeof lucide !== 'undefined') lucide.createIcons(); });
</script>

<!-- View Credit Statement Modal -->
<div id="view-credit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:820px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:92vh;overflow-y:auto;margin:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 22px;border-bottom:1px solid var(--admin-border);position:sticky;top:0;background:#fff;z-index:5;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;">Credit Sale Invoice / Statement</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print</button>
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('view-credit-modal').style.display='none'">Close</button>
            </div>
        </div>
        <div id="credit-preview-body" style="padding:28px;"></div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #view-credit-modal, #view-credit-modal > div, #credit-preview-body, #printable-credit, #printable-credit * { visibility: visible !important; }
    #view-credit-modal {
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
    #view-credit-modal > div {
        max-width: 100% !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
    #view-credit-modal > div > div:first-child { display: none !important; }
    #credit-preview-body { padding: 0 !important; }
    #printable-credit {
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
