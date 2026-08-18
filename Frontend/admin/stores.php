<?php
declare(strict_types=1);
/**
 * Admin — Stores / Raw Materials Module
 * Mirrors "STORES TRACKING 2026" spreadsheet with two views:
 *  - Feed Ingredients (maize, soya, lime, premix etc.)
 *  - Drugs & Other Items (Amin Vit, Tylodoxy, Agritonic etc.)
 * Tracks: opening balance, received, used_production, transfer, sales
 */
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Stores & Raw Materials - Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = getDB();
$tab = $_GET['tab'] ?? 'ingredients';
$validTabs = ['ingredients','drugs','movements','suppliers'];
if (!in_array($tab, $validTabs, true)) $tab = 'ingredients';

// Loadments will be fetched via safe API calls; protect against missing tables
?>
<style>
/* --- Stores / Stock Management Premium Design --- */
.sm-hero {
    background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 55%, #1B5E20 100%);
    border-radius: 16px;
    padding: 32px 36px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
}
.sm-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.sm-hero::after {
    content: '';
    position: absolute;
    bottom: -70px; left: 30%;
    width: 300px; height: 300px;
    background: rgba(27,94,32,0.12);
    border-radius: 50%;
}
.sm-hero-text { position: relative; z-index: 1; }
.sm-hero-text h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.85rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    letter-spacing: -0.4px;
}
.sm-hero-text p { color: rgba(255,255,255,0.7); margin: 0; font-size: 0.93rem; }
.sm-hero-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; position: relative; z-index: 1; }
.sm-btn-white {
    display: inline-flex; align-items: center; gap: 8px;
    background: #fff; color: #1a3a5c;
    padding: 11px 22px; border-radius: 10px;
    font-size: 0.9rem; font-weight: 700; border: none; cursor: pointer;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transition: all 0.2s ease; text-decoration: none;
}
.sm-btn-white:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
.sm-btn-green {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%); color: #fff;
    padding: 11px 22px; border-radius: 10px;
    font-size: 0.9rem; font-weight: 700; border: none; cursor: pointer;
    box-shadow: 0 4px 12px rgba(27,94,32,0.3);
    transition: all 0.2s ease;
}
.sm-btn-green:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(27,94,32,0.4); }
/* Stats */
.sm-stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
.sm-stat-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 20px 22px; display: flex; align-items: center; gap: 16px;
    box-shadow: 0 2px 12px rgba(15,23,42,0.04); transition: all 0.2s ease;
}
.sm-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,23,42,0.08); }
.sm-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sm-stat-info small { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.sm-stat-info strong { display: block; font-family: 'Outfit', sans-serif; font-size: 1.55rem; font-weight: 800; color: #0f172a; margin-top: 2px; line-height: 1; }
/* Tab Bar */
.sm-tab-bar {
    display: flex; gap: 4px; background: #f1f5f9; padding: 6px; border-radius: 14px;
    margin-bottom: 24px; overflow-x: auto;
}
.sm-tab {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 10px;
    text-decoration: none; font-weight: 600; font-size: 0.87rem;
    color: #64748b; white-space: nowrap; transition: all 0.2s;
    border: 1.5px solid transparent;
}
.sm-tab:hover { color: #1a3a5c; background: rgba(255,255,255,0.6); }
.sm-tab.active {
    background: #fff; color: #1B5E20;
    box-shadow: 0 2px 12px rgba(15,23,42,0.09);
    border-color: #e2e8f0;
}
/* Card */
.sm-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
    overflow: hidden; box-shadow: 0 4px 20px rgba(15,23,42,0.04);
    margin-bottom: 24px;
}
.sm-card-header {
    padding: 20px 24px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.sm-card-title { font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0; }
/* Table */
.sm-thead th {
    padding: 14px 18px; background: #f8fafc;
    font-size: 0.72rem; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.07em;
    border-bottom: 1px solid #e2e8f0; white-space: nowrap;
}
.sm-tbody td {
    padding: 15px 18px; border-bottom: 1px solid #f1f5f9;
    vertical-align: middle; font-size: 0.88rem; color: #334155;
}
.sm-tbody tr:last-child td { border-bottom: none; }
.sm-tbody tr:hover td { background: #fafbfc; }
.sm-code {
    background: #f1f5f9; padding: 3px 8px; border-radius: 5px;
    font-family: monospace; font-size: 0.8rem; color: #475569;
}
.sm-badge {
    display: inline-flex; align-items: center; padding: 4px 11px;
    border-radius: 20px; font-size: 0.73rem; font-weight: 700;
}
.sm-badge-green { background: #dcfce7; color: #15803d; }
.sm-badge-red { background: #fee2e2; color: #b91c1c; }
.sm-badge-blue { background: #dbeafe; color: #1d4ed8; }
.sm-badge-yellow { background: #fef9c3; color: #854d0e; }
.sm-badge-category {
    background: #f0f9ff; color: #0369a1;
    border: 1px solid #bae6fd; padding: 3px 9px;
    border-radius: 6px; font-size: 0.72rem; font-weight: 600;
}
.sm-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer;
    transition: all 0.2s;
}
.sm-action-btn:hover { transform: scale(1.1); }
.sm-action-edit { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.sm-action-edit:hover { background: #dcfce7; }
.sm-action-add { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.sm-action-add:hover { background: #dbeafe; }
/* --- Premium Modal --- */
.sm-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(10,15,30,0.65);
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    z-index: 9999; overflow-y: auto; padding: 32px 16px;
}
.sm-modal {
    background: #fff; border-radius: 20px;
    width: 100%; max-width: 580px; margin: 0 auto;
    box-shadow: 0 32px 64px -12px rgba(0,0,0,0.28); overflow: hidden;
}
.sm-modal-header {
    padding: 24px 28px; display: flex; align-items: center; justify-content: space-between;
}
.sm-modal-header.blue-header { background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 100%); }
.sm-modal-header.green-header { background: linear-gradient(135deg, #0B2310 0%, #1B5E20 100%); }
.sm-modal-header h3 {
    font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700;
    color: #fff; margin: 0; display: flex; align-items: center; gap: 10px;
}
.sm-modal-close {
    width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,0.15);
    border: none; color: #fff; font-size: 1.1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.sm-modal-close:hover { background: rgba(255,255,255,0.25); transform: scale(1.1); }
.sm-modal-body { padding: 28px; max-height: 70vh; overflow-y: auto; }
.sm-modal-body::-webkit-scrollbar { width: 5px; }
.sm-modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.sm-modal-footer {
    padding: 18px 28px; border-top: 1px solid #f1f5f9;
    display: flex; justify-content: flex-end; gap: 12px; background: #fafafa;
}
.sm-form-group { margin-bottom: 18px; }
.sm-form-label {
    display: block; font-size: 0.8rem; font-weight: 700;
    color: #374151; margin-bottom: 6px;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.sm-form-control {
    width: 100%; padding: 11px 14px; border: 1.5px solid #e2e8f0;
    border-radius: 10px; font-family: inherit; font-size: 0.9rem;
    color: #1e293b; background: #f8fafc; outline: none;
    transition: all 0.2s ease; box-sizing: border-box;
}
.sm-form-control:focus { border-color: #1B5E20; background: #fff; box-shadow: 0 0 0 3px rgba(27,94,32,0.12); }
.sm-section-title {
    font-size: 0.78rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.08em; color: #1a3a5c;
    margin: 18px 0 12px; padding-bottom: 7px; border-bottom: 2px solid #dbeafe;
    display: flex; align-items: center; gap: 7px;
}
.sm-modal-submit {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%); color: #fff;
    padding: 12px 26px; border-radius: 10px; font-size: 0.9rem; font-weight: 700;
    border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(27,94,32,0.3);
    transition: all 0.2s;
}
.sm-modal-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(27,94,32,0.4); }
.sm-modal-cancel {
    display: inline-flex; align-items: center; gap: 8px;
    background: #f1f5f9; color: #475569; padding: 12px 22px;
    border-radius: 10px; font-size: 0.9rem; font-weight: 600;
    border: 1.5px solid #e2e8f0; cursor: pointer; transition: all 0.2s;
}
.sm-modal-cancel:hover { background: #e2e8f0; }
@media (max-width: 640px) {
    .sm-stats-row { grid-template-columns: 1fr 1fr; }
    .sm-hero { padding: 22px; }
    .sm-hero-text h1 { font-size: 1.4rem; }
}
</style>

<!-- Hero -->
<div class="sm-hero">
    <div class="sm-hero-text">
        <h1><i data-lucide="warehouse" style="width:26px;height:26px;vertical-align:middle;margin-right:10px;opacity:0.85;"></i>Stores & Raw Materials</h1>
        <p>Track every kilogram of feed ingredients, drugs, and packaging — with full movement history.</p>
    </div>
    <div class="sm-hero-actions">
        <a href="/Backend/api/export.php?module=raw_materials" class="sm-btn-white">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <button onclick="openMaterialModal()" class="sm-btn-white">
            <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Add Material
        </button>
        <button onclick="openMovementModal()" class="sm-btn-green">
            <i data-lucide="arrow-down-circle" style="width:16px;height:16px;"></i> Record Movement
        </button>
    </div>
</div>

<!-- Stats (populated by JS) -->
<div class="sm-stats-row" id="sm-stats-row">
    <div class="sm-stat-card">
        <div class="sm-stat-icon" style="background: linear-gradient(135deg, #dbeafe, #eff6ff);">
            <i data-lucide="layers" style="width:22px;height:22px;color:#2563eb;"></i>
        </div>
        <div class="sm-stat-info"><small>Total Materials</small><strong id="stat-total">-</strong></div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-icon" style="background: linear-gradient(135deg, #fee2e2, #fef2f2);">
            <i data-lucide="alert-triangle" style="width:22px;height:22px;color:#dc2626;"></i>
        </div>
        <div class="sm-stat-info"><small>Low Stock</small><strong id="stat-low">-</strong></div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-icon" style="background: linear-gradient(135deg, #dcfce7, #f0fdf4);">
            <i data-lucide="trending-up" style="width:22px;height:22px;color:#15803d;"></i>
        </div>
        <div class="sm-stat-info"><small>Total Stock Value</small><strong id="stat-value" style="font-size:1.1rem;">-</strong></div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-icon" style="background: linear-gradient(135deg, #fef9c3, #fffde7);">
            <i data-lucide="shield" style="width:22px;height:22px;color:#b45309;"></i>
        </div>
        <div class="sm-stat-info"><small>Reserved (Prod.)</small><strong id="stat-reserved">-</strong></div>
    </div>
</div>

<!-- Tab Bar -->
<div class="sm-tab-bar">
    <a href="?tab=ingredients" class="sm-tab <?= $tab==='ingredients'?'active':'' ?>">
        <i data-lucide="wheat" style="width:15px;height:15px;"></i> Feed Ingredients
    </a>
    <a href="?tab=drugs" class="sm-tab <?= $tab==='drugs'?'active':'' ?>">
        <i data-lucide="pill" style="width:15px;height:15px;"></i> Drugs & Other
    </a>
    <a href="?tab=movements" class="sm-tab <?= $tab==='movements'?'active':'' ?>">
        <i data-lucide="arrow-left-right" style="width:15px;height:15px;"></i> Movements
    </a>
    <a href="?tab=suppliers" class="sm-tab <?= $tab==='suppliers'?'active':'' ?>">
        <i data-lucide="truck" style="width:15px;height:15px;"></i> Suppliers
    </a>
</div>

<?php if ($tab === 'ingredients' || $tab === 'drugs'): ?>
<div class="sm-card">
    <div class="sm-card-header">
        <h3 class="sm-card-title">
            <i data-lucide="<?= $tab==='ingredients'?'wheat':'pill' ?>" style="width:18px;height:18px;vertical-align:middle;margin-right:8px;color:#1B5E20;"></i>
            <?= $tab==='ingredients'?'Feed Ingredients':'Drugs & Other Items' ?>
        </h3>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead class="sm-thead"><tr>
                <th>Material</th><th>Code</th><th>Category</th>
                <th>Opening</th><th>Current Stock</th><th>Reserved</th>
                <th>Min Level</th><th>Unit Price</th><th>Stock Value</th><th>Status</th><th style="text-align:right;">Actions</th>
            </tr></thead>
            <tbody class="sm-tbody" id="materials-body">
                <tr><td colspan="11" style="text-align:center;padding:40px;color:#94a3b8;">
                    <i data-lucide="loader-2" style="width:24px;height:24px;animation:spin 1s linear infinite;display:inline-block;margin-bottom:8px;"></i><br>Loading materials...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'movements'): ?>
<div class="sm-card">
    <div class="sm-card-header">
        <h3 class="sm-card-title">
            <i data-lucide="arrow-left-right" style="width:18px;height:18px;vertical-align:middle;margin-right:8px;color:#1B5E20;"></i>
            Stock Movement History
        </h3>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <select class="sm-form-control" id="mv-filter-mat" style="max-width:240px;padding:9px 12px;" onchange="loadMovements()">
                <option value="">All materials</option>
            </select>
            <input class="sm-form-control" type="date" id="mv-from" onchange="loadMovements()" style="max-width:155px;padding:9px 12px;">
            <input class="sm-form-control" type="date" id="mv-to" onchange="loadMovements()" style="max-width:155px;padding:9px 12px;">
            <a href="/Backend/api/export.php?module=stores_movements" class="sm-btn-white" style="padding:9px 16px;font-size:0.82rem;">
                <i data-lucide="download" style="width:14px;height:14px;"></i> Export
            </a>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead class="sm-thead"><tr>
                <th>Date</th><th>Material</th><th>Type</th>
                <th>Quantity</th><th>Balance After</th><th>Unit Cost</th><th>Total</th><th>Description</th>
            </tr></thead>
            <tbody class="sm-tbody" id="movements-body">
                <tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">
                    <i data-lucide="loader-2" style="width:24px;height:24px;animation:spin 1s linear infinite;display:inline-block;margin-bottom:8px;"></i><br>Loading movements...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php else: /* suppliers */ ?>
<div class="sm-card">
    <div class="sm-card-header">
        <h3 class="sm-card-title">
            <i data-lucide="truck" style="width:18px;height:18px;vertical-align:middle;margin-right:8px;color:#1B5E20;"></i>
            Suppliers
        </h3>
    </div>
    <div style="text-align:center;padding:60px 24px;">
        <i data-lucide="truck" style="width:48px;height:48px;color:#cbd5e1;margin-bottom:16px;"></i>
        <p style="font-weight:600;color:#475569;margin:0;">Supplier management coming soon.</p>
        <p style="color:#94a3b8;font-size:0.85rem;margin:8px 0 0;">Track vendors of maize, soya, drugs, packaging, etc.</p>
    </div>
</div>
<?php endif; ?>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• MATERIAL MODAL â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div id="material-modal" class="sm-modal-overlay" onclick="if(event.target===this)closeMaterialModal()">
    <div class="sm-modal">
        <div class="sm-modal-header blue-header">
            <h3 id="material-modal-title"><i data-lucide="package" style="width:20px;height:20px;"></i> Add Material</h3>
            <button class="sm-modal-close" onclick="closeMaterialModal()">âœ•</button>
        </div>
        <form id="material-form">
            <div class="sm-modal-body">
                <input type="hidden" id="m-id">
                <div class="sm-form-group">
                    <label class="sm-form-label">Material Name *</label>
                    <input class="sm-form-control" id="m-name" required placeholder="e.g. Maize, Soya Cake, Amin Vit">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="sm-form-group">
                        <label class="sm-form-label">Code</label>
                        <input class="sm-form-control" id="m-code" placeholder="e.g. MAIZE-01">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Unit of Measure</label>
                        <select class="sm-form-control" id="m-unit">
                            <option value="kg">kg</option>
                            <option value="g">g</option>
                            <option value="litre">litre</option>
                            <option value="piece">piece</option>
                            <option value="bag">bag</option>
                            <option value="crate">crate</option>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Category</label>
                        <select class="sm-form-control" id="m-cat">
                            <option value="feed_ingredient">Feed Ingredient</option>
                            <option value="drug">Drug</option>
                            <option value="vaccine">Vaccine</option>
                            <option value="packaging">Packaging</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Opening Balance</label>
                        <input class="sm-form-control" type="number" step="0.001" id="m-open" value="0" min="0">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Min Stock Level</label>
                        <input class="sm-form-control" type="number" step="0.001" id="m-min" value="1" min="0">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Price per Unit (KES)</label>
                        <input class="sm-form-control" type="number" step="0.01" id="m-price" value="0" min="0">
                    </div>
                </div>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="sm-modal-cancel" onclick="closeMaterialModal()">Cancel</button>
                <button type="submit" class="sm-modal-submit">
                    <i data-lucide="save" style="width:16px;height:16px;"></i> Save Material
                </button>
            </div>
        </form>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• MOVEMENT MODAL â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div id="movement-modal" class="sm-modal-overlay" onclick="if(event.target===this)closeMovementModal()">
    <div class="sm-modal">
        <div class="sm-modal-header green-header">
            <h3><i data-lucide="arrow-down-circle" style="width:20px;height:20px;"></i> Record Stock Movement</h3>
            <button class="sm-modal-close" onclick="closeMovementModal()">âœ•</button>
        </div>
        <form id="movement-form">
            <div class="sm-modal-body">
                <div class="sm-form-group">
                    <label class="sm-form-label">Material *</label>
                    <select class="sm-form-control" id="mv-material" required>
                        <option value="">Choose material...</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="sm-form-group">
                        <label class="sm-form-label">Date *</label>
                        <input class="sm-form-control" type="date" id="mv-date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Movement Type *</label>
                        <select class="sm-form-control" id="mv-type" required>
                            <option value="received">Received (in)</option>
                            <option value="used_production">Used in Production (out)</option>
                            <option value="used_treatment">Used in Treatment (out)</option>
                            <option value="sold">Sold (out)</option>
                            <option value="transfer_out">Transfer Out</option>
                            <option value="transfer_in">Transfer In</option>
                            <option value="adjustment_add">Adjustment Add</option>
                            <option value="adjustment_remove">Adjustment Remove</option>
                            <option value="staff_use">Staff Use</option>
                            <option value="wastage">Wastage/Spoilage</option>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Quantity *</label>
                        <input class="sm-form-control" type="number" step="0.001" id="mv-qty" required min="0.001" placeholder="e.g. 500">
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-form-label">Unit Cost (KES)</label>
                        <input class="sm-form-control" type="number" step="0.01" id="mv-cost" value="0" min="0">
                    </div>
                    <div class="sm-form-group" style="grid-column:span 2;">
                        <label class="sm-form-label">Reference (e.g. Invoice No.)</label>
                        <input class="sm-form-control" id="mv-ref" placeholder="e.g. Supplier Invoice #1234">
                    </div>
                    <div class="sm-form-group" style="grid-column:span 2;">
                        <label class="sm-form-label">Description / Notes</label>
                        <input class="sm-form-control" id="mv-desc" placeholder="e.g. Received from Fred, Used for Layers Batch 17">
                    </div>
                </div>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="sm-modal-cancel" onclick="closeMovementModal()">Cancel</button>
                <button type="submit" class="sm-modal-submit">
                    <i data-lucide="check-circle" style="width:16px;height:16px;"></i> Save Movement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
@keyframes spin{to{transform:rotate(360deg)}}
</script>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
const CSRF = window.kindadmin?.csrfToken || '';
const currentTab = '<?= $tab ?>';
let allMaterials = [];

function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

async function loadMaterials() {
    const tbody = document.getElementById('materials-body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:40px;color:#94a3b8;"><i data-lucide="loader-2" style="width:24px;height:24px;animation:spin 1s linear infinite;display:inline-block;margin-bottom:8px;"></i><br>Loading...</td></tr>';
    try {
        const res = await fetch('/Backend/api/admin_poultry_v2.php?action=get_materials');
        const r = await res.json();
        if (!r.success) { tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;color:#dc2626;padding:30px;">${r.message||'Failed to load'}</td></tr>`; return; }
        allMaterials = r.data || [];
        // Populate material selects
        ['mv-filter-mat'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value="">All materials</option>' + allMaterials.map(m => `<option value="${m.id}">${escapeHtml(m.material_name)}</option>`).join('');
        });
        const mvMat = document.getElementById('mv-material');
        if (mvMat) mvMat.innerHTML = '<option value="">Choose material...</option>' + allMaterials.map(m => `<option value="${m.id}">${escapeHtml(m.material_name)} (${m.current_stock} ${m.unit})</option>`).join('');
        // Update stats
        const lowCount = allMaterials.filter(m => parseFloat(m.current_stock) <= parseFloat(m.min_stock_level)).length;
        const totalValue = allMaterials.reduce((s,m) => s + parseFloat(m.current_stock)*parseFloat(m.current_price_per_unit), 0);
        const totalReserved = allMaterials.reduce((s,m) => s + parseFloat(m.reserved_production_kg||0), 0);
        const statTotal = document.getElementById('stat-total');
        const statLow = document.getElementById('stat-low');
        const statValue = document.getElementById('stat-value');
        const statReserved = document.getElementById('stat-reserved');
        if (statTotal) statTotal.textContent = allMaterials.length;
        if (statLow) statLow.textContent = lowCount;
        if (statValue) statValue.textContent = 'KES ' + Math.round(totalValue/1000) + 'K';
        if (statReserved) statReserved.textContent = Math.round(totalReserved) + ' kg';
        // Filter by tab category
        const cats = currentTab === 'ingredients' ? ['feed_ingredient'] : ['drug','other','vaccine','packaging'];
        const filtered = allMaterials.filter(m => cats.includes(m.category));
        if (!filtered.length) {
            tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:50px;">
                <i data-lucide="package-open" style="width:40px;height:40px;color:#cbd5e1;margin-bottom:10px;"></i>
                <p style="margin:0;color:#64748b;font-weight:600;">No materials yet in this category.</p>
            </td></tr>`;
            if(typeof lucide!=='undefined') lucide.createIcons();
            return;
        }
        tbody.innerHTML = filtered.map(m => {
            const stock = parseFloat(m.current_stock);
            const min = parseFloat(m.min_stock_level);
            const lowStock = stock <= min;
            const value = stock * parseFloat(m.current_price_per_unit);
            const catColor = {'feed_ingredient':'#0369a1','drug':'#7c3aed','vaccine':'#db2777','packaging':'#d97706','other':'#64748b'}[m.category]||'#64748b';
            return `<tr>
                <td><strong style="color:#0f172a;">${escapeHtml(m.material_name)}</strong></td>
                <td><span class="sm-code">${escapeHtml(m.material_code||'â€”')}</span></td>
                <td><span class="sm-badge-category" style="background:${catColor}20;color:${catColor};border-color:${catColor}40;">${m.category}</span></td>
                <td style="color:#64748b;">${parseFloat(m.opening_balance).toFixed(2)} ${m.unit}</td>
                <td><strong style="color:${lowStock?'#dc2626':'#0f172a'};">${stock.toFixed(2)} ${m.unit}</strong></td>
                <td style="color:#475569;">${parseFloat(m.reserved_production_kg||0).toFixed(2)} ${m.unit}</td>
                <td style="color:#64748b;">${min.toFixed(2)} ${m.unit}</td>
                <td style="font-weight:600;color:#1B5E20;">KES ${parseFloat(m.current_price_per_unit).toLocaleString('en-KE',{minimumFractionDigits:2})}</td>
                <td style="font-weight:700;">KES ${value.toLocaleString('en-KE',{minimumFractionDigits:2})}</td>
                <td>${lowStock ? '<span class="sm-badge sm-badge-red">âš  LOW</span>' : '<span class="sm-badge sm-badge-green">âœ“ OK</span>'}</td>
                <td>
                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                        <button class="sm-action-btn sm-action-edit" title="Edit Material" onclick='openMaterialModal(${JSON.stringify(m)})'>
                            <i data-lucide="pencil" style="width:14px;height:14px;"></i>
                        </button>
                        <button class="sm-action-btn sm-action-add" title="Record Movement" onclick='openMovementModalFor(${m.id})'>
                            <i data-lucide="plus" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#dc2626;padding:30px;">Network error — could not load materials.</td></tr>';
    }
}

async function loadMovements() {
    const tbody = document.getElementById('movements-body');
    if (!tbody) return;
    const mat = document.getElementById('mv-filter-mat')?.value || '';
    const from = document.getElementById('mv-from')?.value || '';
    const to = document.getElementById('mv-to')?.value || '';
    let url = '/Backend/api/admin_poultry_v2.php?action=get_movements';
    if (mat) url += '&material_id=' + mat;
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;"><i data-lucide="loader-2" style="width:24px;height:24px;animation:spin 1s linear infinite;display:inline-block;margin-bottom:8px;"></i><br>Loading movements...</td></tr>';
    try {
        const res = await fetch(url);
        const r = await res.json();
        if (!r.success) { tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#dc2626;padding:30px;">${r.message||'Failed'}</td></tr>`; return; }
        let data = r.data || [];
        if (from) data = data.filter(m => m.movement_date >= from);
        if (to) data = data.filter(m => m.movement_date <= to);
        if (!data.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:50px;">
                <i data-lucide="inbox" style="width:40px;height:40px;color:#cbd5e1;margin-bottom:10px;"></i>
                <p style="margin:0;color:#64748b;font-weight:600;">No movements found.</p>
            </td></tr>`;
            if(typeof lucide!=='undefined') lucide.createIcons();
            return;
        }
        tbody.innerHTML = data.map(m => {
            const isIn = m.movement_type.includes('in') || m.movement_type==='received' || m.movement_type==='opening_balance' || m.movement_type==='adjustment_add' || m.movement_type==='transfer_in';
            const qty = parseFloat(m.quantity_kg);
            const mtLabel = m.movement_type.replace(/_/g,' ');
            return `<tr>
                <td style="color:#64748b;font-size:0.82rem;">${m.movement_date}</td>
                <td><strong style="color:#0f172a;">${escapeHtml(m.material_name||'—')}</strong></td>
                <td><span class="sm-badge ${isIn?'sm-badge-green':'sm-badge-yellow'}">${mtLabel}</span></td>
                <td style="font-weight:700;color:${isIn?'#15803d':'#dc2626'};">${isIn?'+':'−'}${qty.toFixed(2)} ${m.unit||''}</td>
                <td><strong>${parseFloat(m.balance_after).toFixed(2)}</strong></td>
                <td style="color:#64748b;">KES ${parseFloat(m.unit_cost).toFixed(2)}</td>
                <td style="font-weight:600;">KES ${parseFloat(m.total_cost).toFixed(2)}</td>
                <td style="color:#64748b;font-size:0.82rem;">${escapeHtml(m.description||'')}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#dc2626;padding:30px;">Network error</td></tr>';
    }
}

function openMaterialModal(d) {
    const titleEl = document.getElementById('material-modal-title');
    if (titleEl) titleEl.innerHTML = `<i data-lucide="package" style="width:20px;height:20px;"></i> ${d?.id ? 'Edit Material' : 'Add Material'}`;
    document.getElementById('m-id').value   = d?.id || '';
    document.getElementById('m-name').value = d?.material_name || '';
    document.getElementById('m-code').value = d?.material_code || '';
    document.getElementById('m-unit').value = d?.unit || 'kg';
    document.getElementById('m-cat').value  = d?.category || 'feed_ingredient';
    document.getElementById('m-open').value = d?.opening_balance || 0;
    document.getElementById('m-min').value  = d?.min_stock_level || 1;
    document.getElementById('m-price').value= d?.current_price_per_unit || 0;
    document.getElementById('material-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function closeMaterialModal() {
    document.getElementById('material-modal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('material-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id', document.getElementById('m-id').value);
    fd.append('material_name', document.getElementById('m-name').value);
    fd.append('material_code', document.getElementById('m-code').value);
    fd.append('unit', document.getElementById('m-unit').value);
    fd.append('category', document.getElementById('m-cat').value);
    fd.append('opening_balance', document.getElementById('m-open').value);
    fd.append('min_stock_level', document.getElementById('m-min').value);
    fd.append('current_price_per_unit', document.getElementById('m-price').value);
    const btn = e.target.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'Saving...';
    const res = await fetch('/Backend/api/admin_poultry_v2.php?action=save_material', {method:'POST', body:fd});
    const r = await res.json();
    btn.disabled = false; btn.innerHTML = '<i data-lucide="save" style="width:16px;height:16px;"></i> Save Material';
    if (typeof lucide !== 'undefined') lucide.createIcons();
    if (r.success) { closeMaterialModal(); loadMaterials(); }
    else alert('Error: ' + r.message);
});

function openMovementModal() { openMovementModalFor(null); }
function openMovementModalFor(materialId) {
    if (materialId) document.getElementById('mv-material').value = materialId;
    document.getElementById('movement-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function closeMovementModal() {
    document.getElementById('movement-modal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('movement-form').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('material_id', document.getElementById('mv-material').value);
    fd.append('movement_date', document.getElementById('mv-date').value);
    fd.append('movement_type', document.getElementById('mv-type').value);
    fd.append('quantity_kg', document.getElementById('mv-qty').value);
    fd.append('unit_cost', document.getElementById('mv-cost').value);
    fd.append('reference_no', document.getElementById('mv-ref').value);
    fd.append('description', document.getElementById('mv-desc').value);
    const btn = e.target.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'Saving...';
    const res = await fetch('/Backend/api/admin_poultry_v2.php?action=save_movement', {method:'POST', body:fd});
    const r = await res.json();
    btn.disabled = false; btn.innerHTML = '<i data-lucide="check-circle" style="width:16px;height:16px;"></i> Save Movement';
    if (typeof lucide !== 'undefined') lucide.createIcons();
    if (r.success) {
        closeMovementModal();
        if (currentTab === 'movements') loadMovements(); else loadMaterials();
    } else alert('Error: ' + r.message);
});

// ESC to close modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeMaterialModal(); closeMovementModal(); }
});

document.addEventListener('DOMContentLoaded', () => {
    if (currentTab === 'movements') { loadMovements(); loadMaterials(); }
    else { loadMaterials(); }
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
