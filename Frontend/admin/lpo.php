<?php
/**
 * Admin — LPO (Local Purchase Orders), Quotations & Invoices
 * One place for all customer documents: draft a quotation, receive an LPO,
 * convert to an invoice, track payment. Print-ready, CSV exportable.
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'LPO & Invoicing - Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = getDB();
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'quotation', 'lpo', 'invoice'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// Product names for the line-item autocomplete (kept in a <datalist>).
$productSuggestions = [];
if ($pdo) {
    try {
        $productSuggestions = safeQueryAll($pdo, "SELECT name FROM products WHERE status='active' ORDER BY name LIMIT 300");
    } catch (Exception $e) { $productSuggestions = []; }
}
?>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.6rem;color:var(--admin-text-heading);font-weight:800;">LPO &amp; Invoicing</h1>
        <p style="margin:4px 0 0;color:#64748b;font-size:0.9rem;">Quotations, Local Purchase Orders (LPO) and invoices — all in one place. Create, print and track each document.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/Backend/api/export.php?module=lpo_documents" class="btn btn-outline"><i data-lucide="download" style="width:14px;height:14px;"></i> Export CSV</a>
        <button class="btn btn-primary" onclick="openDocModal()"><i data-lucide="plus-circle" style="width:16px;height:16px;"></i> New Document</button>
    </div>
</div>

<!-- Tab Bar -->
<div style="display:flex;gap:4px;background:#f1f5f9;padding:5px;border-radius:10px;margin-bottom:24px;overflow-x:auto;scrollbar-width:none;">
    <a href="?tab=all" style="display:flex;align-items:center;gap:7px;padding:9px 16px;border-radius:7px;text-decoration:none;white-space:nowrap;font-weight:600;font-size:0.84rem;<?= $tab==='all'?'background:#fff;color:var(--admin-primary);box-shadow:0 1px 6px rgba(15,23,42,0.08);':'color:#64748b;' ?>"><i data-lucide="files" style="width:15px;height:15px;"></i> All Documents</a>
    <a href="?tab=quotation" style="display:flex;align-items:center;gap:7px;padding:9px 16px;border-radius:7px;text-decoration:none;white-space:nowrap;font-weight:600;font-size:0.84rem;<?= $tab==='quotation'?'background:#fff;color:var(--admin-primary);box-shadow:0 1px 6px rgba(15,23,42,0.08);':'color:#64748b;' ?>"><i data-lucide="file-text" style="width:15px;height:15px;"></i> Quotations</a>
    <a href="?tab=lpo" style="display:flex;align-items:center;gap:7px;padding:9px 16px;border-radius:7px;text-decoration:none;white-space:nowrap;font-weight:600;font-size:0.84rem;<?= $tab==='lpo'?'background:#fff;color:var(--admin-primary);box-shadow:0 1px 6px rgba(15,23,42,0.08);':'color:#64748b;' ?>"><i data-lucide="clipboard-list" style="width:15px;height:15px;"></i> Local Purchase Orders</a>
    <a href="?tab=invoice" style="display:flex;align-items:center;gap:7px;padding:9px 16px;border-radius:7px;text-decoration:none;white-space:nowrap;font-weight:600;font-size:0.84rem;<?= $tab==='invoice'?'background:#fff;color:var(--admin-primary);box-shadow:0 1px 6px rgba(15,23,42,0.08);':'color:#64748b;' ?>"><i data-lucide="receipt" style="width:15px;height:15px;"></i> Invoices</a>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr>
                <th>Doc #</th><th>Customer</th><th>Type</th><th>Status</th>
                <th>Total (KES)</th><th>Issue Date</th><th>Due Date</th><th>Actions</th>
            </tr></thead>
            <tbody id="lpo-body">
                <tr><td colspan="8" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── New / Edit Document Modal ── -->
<div id="doc-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;padding:32px;border-radius:12px;width:100%;max-width:860px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:92vh;overflow-y:auto;margin:20px;box-sizing:border-box;">
        <h3 id="doc-modal-title" style="margin:0 0 22px;font-family:'Outfit',sans-serif;">New Document</h3>
        <form id="doc-form">
            <input type="hidden" id="d-id">
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;">
                <div class="admin-form-group"><label class="admin-form-label">Document Type *</label>
                    <select class="admin-form-control" id="d-type" required onchange="onDocTypeChange()">
                        <option value="quotation">Quotation</option>
                        <option value="lpo">Local Purchase Order (LPO)</option>
                        <option value="invoice">Invoice</option>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Status</label>
                    <select class="admin-form-control" id="d-status">
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="invoiced">Invoiced</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="admin-form-group"><label class="admin-form-label">Issue Date *</label><input class="admin-form-control" type="date" id="d-date" required value="<?= date('Y-m-d') ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">Customer Name *</label><input class="admin-form-control" id="d-cust" required placeholder="e.g. Kariuki Wholesalers"></div>
                <div class="admin-form-group"><label class="admin-form-label">Phone</label><input class="admin-form-control" id="d-phone" placeholder="07xx xxx xxx"></div>
                <div class="admin-form-group"><label class="admin-form-label">Email</label><input class="admin-form-control" type="email" id="d-email" placeholder="customer@email.com"></div>
                <div class="admin-form-group" style="grid-column:span 2"><label class="admin-form-label">Address / Location</label><input class="admin-form-control" id="d-addr" placeholder="e.g. Town, Block B"></div>
                <div class="admin-form-group"><label class="admin-form-label">Due / Valid Until</label><input class="admin-form-control" type="date" id="d-due"></div>
            </div>

            <h4 style="margin:20px 0 8px;font-family:'Outfit',sans-serif;font-size:0.95rem;">Items</h4>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#f8fafc;">
                    <th style="text-align:left;padding:8px;font-size:0.82rem;">Description</th>
                    <th style="text-align:left;padding:8px;font-size:0.82rem;width:90px;">Qty</th>
                    <th style="text-align:left;padding:8px;font-size:0.82rem;width:80px;">Unit</th>
                    <th style="text-align:left;padding:8px;font-size:0.82rem;width:110px;">Unit Price</th>
                    <th style="text-align:left;padding:8px;font-size:0.82rem;width:120px;">Line Total</th>
                    <th style="padding:8px;width:40px;"></th>
                </tr></thead>
                <tbody id="doc-lines"></tbody>
            </table>
            <button type="button" class="btn btn-outline btn-sm" style="margin-top:10px;" onclick="addDocLine()"><i data-lucide="plus" style="width:13px;height:13px;"></i> Add Item</button>

            <datalist id="product-suggest">
                <?php foreach ($productSuggestions as $p): ?>
                    <option value="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px;">
                <div class="admin-form-group"><label class="admin-form-label">Tax Rate (%)</label><input class="admin-form-control" type="number" step="0.01" min="0" id="d-tax" value="0" oninput="calcTotals()"></div>
                <div class="admin-form-group"><label class="admin-form-label">Discount (KES)</label><input class="admin-form-control" type="number" step="0.01" min="0" id="d-disc" value="0" oninput="calcTotals()"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:#f8fafc;border-radius:8px;margin-top:4px;">
                <strong style="font-family:'Outfit',sans-serif;font-size:1.05rem;">Total:</strong>
                <strong id="doc-total-display" style="font-size:1.4rem;color:var(--admin-primary);">KES 0.00</strong>
            </div>

            <div class="admin-form-group" style="margin-top:14px;"><label class="admin-form-label">Notes / Terms</label><textarea class="admin-form-control" id="d-notes" rows="2" placeholder="e.g. Payment due within 7 days, collection at the farm gate."></textarea></div>

            <div style="display:flex;gap:12px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="closeDocModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;"><i data-lucide="save" style="width:15px;height:15px;"></i> Save Document</button>
            </div>
        </form>
    </div>
</div>

<!-- ── View / Print Modal ── -->
<div id="view-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:820px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:92vh;overflow-y:auto;margin:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 22px;border-bottom:1px solid var(--admin-border);position:sticky;top:0;background:#fff;z-index:5;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;">Document Preview</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-success btn-sm" onclick="downloadPdf(currentViewDoc ? currentViewDoc.id : 0)" title="Download PDF"><i data-lucide="file-down" style="width:14px;height:14px;"></i> PDF</button>
                <button class="btn btn-primary btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print</button>
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('view-modal').style.display='none'">Close</button>
            </div>
        </div>
        <div id="doc-preview" style="padding:34px;"></div>
    </div>
</div>

<script>
const currentTab = '<?= $tab ?>';
const typeMeta = {
    quotation: { label: 'QUOTATION', plural: 'Quotations', prefix: 'QT' },
    lpo:       { label: 'LOCAL PURCHASE ORDER', plural: 'LPOs', prefix: 'LPO' },
    invoice:   { label: 'INVOICE', plural: 'Invoices', prefix: 'INV' }
};
const statusPill = {
    draft: 'badge-pill-warning', sent: 'badge-pill-info', accepted: 'badge-pill-success',
    invoiced: 'badge-pill-info', paid: 'badge-pill-success', cancelled: 'badge-pill-danger'
};
const typePill = { quotation: 'badge-pill-info', lpo: 'badge-pill-warning', invoice: 'badge-pill-success' };

async function loadDocs() {
    const tbody = document.getElementById('lpo-body');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:28px;color:#94a3b8;">Loading...</td></tr>';
    try {
        const url = '/Backend/api/admin_business.php?action=list_lpo_documents' + (currentTab !== 'all' ? '&type=' + currentTab : '');
        const res = await fetch(url);
        const r = await res.json();
        if (!r.success) { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#dc2626;">' + (r.message||'Failed') + '</td></tr>'; return; }
        const data = r.data || [];
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:28px;color:#94a3b8;">No documents yet. Click "New Document" to create a quotation, LPO or invoice.</td></tr>'; return; }
        tbody.innerHTML = data.map(d => `<tr>
            <td><strong>#${escapeHtml(d.doc_number)}</strong></td>
            <td>${escapeHtml(d.customer_name)}${d.customer_phone ? '<div style="font-size:0.78rem;color:#64748b;">' + escapeHtml(d.customer_phone) + '</div>' : ''}</td>
            <td><span class="badge-pill ${typePill[d.doc_type]}">${typeMeta[d.doc_type] ? typeMeta[d.doc_type].plural : d.doc_type}</span></td>
            <td><span class="badge-pill ${statusPill[d.status]||'badge-pill-warning'}">${d.status}</span></td>
            <td><strong>KES ${parseFloat(d.total_amount).toLocaleString(undefined,{minimumFractionDigits:2})}</strong></td>
            <td>${d.issue_date}</td>
            <td>${d.due_date || '—'}</td>
            <td>
                <div class="tbl-actions">
                    <a class="btn btn-success btn-sm" href="/Backend/api/lpo_pdf.php?id=${d.id}" target="_blank" title="Download PDF"><i data-lucide="file-down" style="width:13px;height:13px;"></i></a>
                    <button class="btn btn-info btn-sm" onclick='viewDoc(${JSON.stringify(d.id)})' title="View / Print"><i data-lucide="eye" style="width:13px;height:13px;"></i></button>
                    <button class="btn btn-trans btn-sm" onclick='openDocModal(${JSON.stringify(d.id)})' title="Edit"><i data-lucide="pencil" style="width:13px;height:13px;"></i></button>
                    <select class="form-control" style="padding:5px 6px;font-size:0.78rem;border:1px solid #e2e8f0;border-radius:5px;outline:none;max-width:110px;" onchange="setStatus(${d.id}, this.value)">
                        <option value="">Status…</option>
                        ${['draft','sent','accepted','invoiced','paid','cancelled'].map(s => '<option value="'+s+'">'+s+'</option>').join('')}
                    </select>
                    <button class="btn btn-danger btn-sm" onclick="deleteDoc(${d.id}, '${escapeJs(d.doc_number)}')" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
                </div>
            </td>
        </tr>`).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#dc2626;">Network error</td></tr>';
    }
}

/* ── line items ── */
function addDocLine(line) {
    line = line || {};
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="padding:6px;"><input class="admin-form-control" style="padding:7px 10px;" list="product-suggest" placeholder="Item description" value="${escapeHtml(line.description||'')}"></td>
        <td style="padding:6px;"><input class="admin-form-control" style="padding:7px 10px;" type="number" min="0" step="0.01" value="${line.quantity!=null?line.quantity:1}" oninput="calcTotals()"></td>
        <td style="padding:6px;"><input class="admin-form-control" style="padding:7px 10px;" value="${escapeHtml(line.unit||'pcs')}" oninput="calcTotals()"></td>
        <td style="padding:6px;"><input class="admin-form-control" style="padding:7px 10px;" type="number" min="0" step="0.01" value="${line.unit_price!=null?line.unit_price:0}" oninput="calcTotals()"></td>
        <td style="padding:6px;"><span class="line-total" style="font-weight:600;">0.00</span></td>
        <td style="padding:6px;"><button type="button" class="btn btn-trans btn-sm" onclick="this.closest('tr').remove();calcTotals();"><i data-lucide="x" style="width:13px;height:13px;"></i></button></td>`;
    document.getElementById('doc-lines').appendChild(tr);
    calcTotals();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function lineValues() {
    return [...document.querySelectorAll('#doc-lines tr')].map(tr => {
        const ins = tr.querySelectorAll('input');
        return {
            description: ins[0] ? ins[0].value.trim() : '',
            quantity: parseFloat(ins[1] ? ins[1].value : 0) || 0,
            unit: ins[2] ? ins[2].value.trim() : 'pcs',
            unit_price: parseFloat(ins[3] ? ins[3].value : 0) || 0
        };
    }).filter(l => l.description !== '' && l.quantity > 0);
}

function calcTotals() {
    let subtotal = 0;
    document.querySelectorAll('#doc-lines tr').forEach(tr => {
        const ins = tr.querySelectorAll('input');
        const qty = parseFloat(ins[1] ? ins[1].value : 0) || 0;
        const price = parseFloat(ins[3] ? ins[3].value : 0) || 0;
        const total = qty * price;
        const span = tr.querySelector('.line-total');
        if (span) span.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2});
        subtotal += total;
    });
    const taxRate = parseFloat(document.getElementById('d-tax').value) || 0;
    const discount = parseFloat(document.getElementById('d-disc').value) || 0;
    const tax = subtotal * taxRate / 100;
    const total = subtotal + tax - discount;
    document.getElementById('doc-total-display').textContent = 'KES ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
    return { subtotal, taxRate, tax, discount, total };
}

/* ── modal open/close ── */
function onDocTypeChange() {
    const t = document.getElementById('d-type').value;
    const meta = typeMeta[t];
    document.getElementById('doc-modal-title').textContent = 'New ' + meta.plural.slice(0, -1);
}

async function openDocModal(id) {
    document.getElementById('doc-lines').innerHTML = '';
    document.getElementById('doc-modal-title').textContent = 'New Document';
    document.getElementById('d-id').value = '';
    document.getElementById('d-type').value = 'quotation';
    document.getElementById('d-status').value = 'draft';
    document.getElementById('d-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('d-due').value = '';
    document.getElementById('d-cust').value = '';
    document.getElementById('d-phone').value = '';
    document.getElementById('d-email').value = '';
    document.getElementById('d-addr').value = '';
    document.getElementById('d-tax').value = 0;
    document.getElementById('d-disc').value = 0;
    document.getElementById('d-notes').value = '';
    addDocLine();

    if (id) {
        const res = await fetch('/Backend/api/admin_business.php?action=get_lpo_document&id=' + id);
        const r = await res.json();
        if (r.success && r.data) {
            const d = r.data;
            document.getElementById('doc-modal-title').textContent = 'Edit ' + (typeMeta[d.doc_type] ? typeMeta[d.doc_type].plural.slice(0,-1) : 'Document');
            document.getElementById('d-id').value = d.id;
            document.getElementById('d-type').value = d.doc_type;
            document.getElementById('d-status').value = d.status;
            document.getElementById('d-date').value = d.issue_date;
            document.getElementById('d-due').value = d.due_date || '';
            document.getElementById('d-cust').value = d.customer_name;
            document.getElementById('d-phone').value = d.customer_phone || '';
            document.getElementById('d-email').value = d.customer_email || '';
            document.getElementById('d-addr').value = d.customer_address || '';
            document.getElementById('d-tax').value = d.tax_rate || 0;
            document.getElementById('d-disc').value = d.discount || 0;
            document.getElementById('d-notes').value = d.notes || '';
            document.getElementById('doc-lines').innerHTML = '';
            (d.items || []).forEach(it => addDocLine(it));
            if (!(d.items||[]).length) addDocLine();
        } else {
            alert('Could not load document: ' + (r.message || 'unknown error'));
            return;
        }
    }
    document.getElementById('doc-modal').style.display = 'flex';
}
function closeDocModal() { document.getElementById('doc-modal').style.display = 'none'; }

document.getElementById('doc-form').addEventListener('submit', async e => {
    e.preventDefault();
    const items = lineValues();
    if (!items.length) { alert('Add at least one item.'); return; }
    const t = calcTotals();
    const fd = new FormData();
    fd.append('id', document.getElementById('d-id').value);
    fd.append('doc_type', document.getElementById('d-type').value);
    fd.append('status', document.getElementById('d-status').value);
    fd.append('customer_name', document.getElementById('d-cust').value);
    fd.append('customer_phone', document.getElementById('d-phone').value);
    fd.append('customer_email', document.getElementById('d-email').value);
    fd.append('customer_address', document.getElementById('d-addr').value);
    fd.append('issue_date', document.getElementById('d-date').value);
    fd.append('due_date', document.getElementById('d-due').value);
    fd.append('tax_rate', document.getElementById('d-tax').value);
    fd.append('discount', document.getElementById('d-disc').value);
    fd.append('notes', document.getElementById('d-notes').value);
    fd.append('items', JSON.stringify(items));
    const res = await fetch('/Backend/api/admin_business.php?action=save_lpo_document', {method: 'POST', body: fd});
    const r = await res.json();
    if (r.success) { closeDocModal(); loadDocs(); }
    else alert('Error: ' + (r.message || 'failed'));
});

async function setStatus(id, status) {
    if (!status) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', status);
    const res = await fetch('/Backend/api/admin_business.php?action=set_lpo_status', {method: 'POST', body: fd});
    const r = await res.json();
    if (r.success) loadDocs(); else alert('Error: ' + (r.message || 'failed'));
}

async function deleteDoc(id, num) {
    if (!confirm('Delete document #' + num + '? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('/Backend/api/admin_business.php?action=delete_lpo_document', {method: 'POST', body: fd});
    const r = await res.json();
    if (r.success) loadDocs(); else alert('Error: ' + (r.message || 'failed'));
}

/* ── printable preview ── */
let currentViewDoc = null;
async function viewDoc(id) {
    const res = await fetch('/Backend/api/admin_business.php?action=get_lpo_document&id=' + id);
    const r = await res.json();
    if (!r.success || !r.data) { alert('Could not load document'); return; }
    currentViewDoc = r.data;
    const d = r.data;
    const meta = typeMeta[d.doc_type] || {label: d.doc_type, plural: d.doc_type};
    
    let rowIndex = 0;
    const rows = (d.items || []).map(it => {
        rowIndex++;
        const bg = rowIndex % 2 === 1 ? '#ffffff' : '#f8fafc';
        return `<tr style="background:${bg};">
            <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;color:#64748b;">${rowIndex}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;font-weight:500;color:#0f172a;">${escapeHtml(it.description)}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;color:#0f172a;">${parseFloat(it.quantity).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.85rem;text-align:center;color:#64748b;">${escapeHtml(it.unit || 'pcs')}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;color:#0f172a;">${parseFloat(it.unit_price).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;font-weight:700;color:#0f172a;">${parseFloat(it.line_total).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
        </tr>`;
    }).join('');

    document.getElementById('doc-preview').innerHTML = `
        <div id="printable-doc" style="font-family:'Inter',sans-serif;color:#0f172a;max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
            <!-- Header Banner -->
            <div style="background:#1E3A5F;padding:22px 28px;color:#ffffff;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #D97706;gap:20px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <?php $lpo_logo = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg'; ?>
                    <div style="background:#ffffff;padding:6px 10px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo htmlspecialchars($lpo_logo, ENT_QUOTES, 'UTF-8'); ?>" style="height:44px;max-width:140px;object-fit:contain;" alt="Logo" onerror="this.parentElement.style.display='none'">
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
                    <!-- Left: Bill To -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:#1E3A5F;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">BILL TO / RECIPIENT</div>
                            <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;">${escapeHtml(d.customer_name)}</div>
                            ${d.customer_phone ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Phone:</strong> ' + escapeHtml(d.customer_phone) + '</div>' : ''}
                            ${d.customer_email ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Email:</strong> ' + escapeHtml(d.customer_email) + '</div>' : ''}
                            ${d.customer_address ? '<div style="font-size:0.85rem;color:#475569;margin-top:3px;"><strong style="color:#64748b;font-weight:500;">Address:</strong> ' + escapeHtml(d.customer_address) + '</div>' : ''}
                        </div>
                    </div>

                    <!-- Right: Doc Details -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;text-align:right;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.3rem;color:#1E3A5F;letter-spacing:0.04em;">${meta.label}</div>
                            <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:2px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">#${escapeHtml(d.doc_number)}</div>
                            
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Issue Date:</span>
                                <strong style="color:#0f172a;">${d.issue_date}</strong>
                            </div>
                            ${d.due_date ? `
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">${d.doc_type==='quotation'?'Valid Until:':'Due Date:'}</span>
                                <strong style="color:#0f172a;">${d.due_date}</strong>
                            </div>` : ''}
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                            <span style="font-size:0.82rem;color:#64748b;">Status:</span>
                            <span class="badge-pill ${statusPill[d.status]||'badge-pill-warning'}" style="text-transform:uppercase;font-weight:700;font-size:0.75rem;padding:4px 10px;">${escapeHtml(d.status)}</span>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:20px;">
                    <thead>
                        <tr style="background:#1E3A5F;color:#ffffff;">
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;width:35px;">#</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;">Item Description</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:75px;">Qty</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:center;width:75px;">Unit</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:110px;">Unit Price</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:120px;">Amount (KES)</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>

                <!-- Totals & Notes Block -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:20px;">
                    <div style="flex:1;min-width:260px;">
                        ${d.notes ? `
                        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;font-size:0.85rem;color:#78350F;">
                            <div style="font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;color:#92400E;margin-bottom:6px;">Terms &amp; Payment Notes</div>
                            <div style="white-space:pre-wrap;line-height:1.45;">${escapeHtml(d.notes)}</div>
                        </div>` : ''}
                    </div>
                    
                    <div style="width:280px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:8px;">
                            <span>Subtotal:</span>
                            <strong style="color:#0f172a;">KES ${parseFloat(d.subtotal).toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>
                        ${parseFloat(d.tax_rate) > 0 || parseFloat(d.tax_amount) > 0 ? `
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:8px;">
                            <span>Tax (${parseFloat(d.tax_rate)}%):</span>
                            <strong style="color:#0f172a;">KES ${parseFloat(d.tax_amount).toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>` : ''}
                        ${parseFloat(d.discount) > 0 ? `
                        <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:8px;">
                            <span>Discount:</span>
                            <strong style="color:#dc2626;">- KES ${parseFloat(d.discount).toLocaleString(undefined,{minimumFractionDigits:2})}</strong>
                        </div>` : ''}
                        <div style="background:#1E3A5F;color:#ffffff;margin:-4px -16px -16px -16px;padding:12px 16px;border-bottom-left-radius:7px;border-bottom-right-radius:7px;display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                            <span style="font-weight:700;font-size:0.9rem;letter-spacing:0.04em;">TOTAL DUE</span>
                            <span style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#ffffff;">KES ${parseFloat(d.total_amount).toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer / Signature -->
                <div style="border-top:1px solid #e2e8f0;padding-top:18px;margin-top:16px;display:flex;justify-content:space-between;align-items:flex-end;font-size:0.8rem;color:#64748b;">
                    <div>
                        <div style="border-bottom:1px solid #cbd5e1;width:180px;margin-bottom:6px;"></div>
                        <div>Authorized Signature / Stamp</div>
                        <div style="margin-top:4px;font-size:0.75rem;">Prepared by: <strong style="color:#0f172a;">${escapeHtml(d.created_by_name || 'Admin')}</strong></div>
                    </div>
                    <div style="text-align:right;">
                        <div>Generated on ${new Date().toLocaleDateString(undefined,{day:'numeric',month:'short',year:'numeric'})}</div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Kind Commodities Management System</div>
                    </div>
                </div>
            </div>
        </div>`;
    document.getElementById('view-modal').style.display = 'flex';
}

document.addEventListener('click', e => {
    const m = document.getElementById('view-modal');
    if (m && e.target === m) m.style.display = 'none';
    const dm = document.getElementById('doc-modal');
    if (dm && e.target === dm) dm.style.display = 'none';
});

function downloadPdf(id) {
    if (!id) return;
    window.open('/Backend/api/lpo_pdf.php?id=' + id + '&inline=1', '_blank');
}

function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
function escapeJs(s){ if(s==null) return ''; return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/\n/g,' '); }

document.addEventListener('DOMContentLoaded', () => { loadDocs(); if (typeof lucide !== 'undefined') lucide.createIcons(); });
</script>

<style>
@media print {
    body * { visibility: hidden !important; }
    #view-modal, #view-modal > div, #doc-preview, #printable-doc, #printable-doc * { visibility: visible !important; }
    #view-modal {
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
    #view-modal > div {
        max-width: 100% !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
    #view-modal > div > div:first-child { display: none !important; }
    #doc-preview { padding: 0 !important; }
    #printable-doc {
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
