<?php
/**
 * Admin — Supplier Management
 * Full CRUD for suppliers, delivery tracking, performance ratings
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Supplier Management - Admin';
include __DIR__ . '/includes/admin_header.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin','farm_manager','sales_staff'], true)) {
    header('Location: /kindadmin');
    exit;
}

$pdo = getDB();
$success = '';
$error = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_supplier') {
            try {
                $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, location, payment_terms, rating, notes) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    trim($_POST['name']), trim($_POST['contact_person'] ?? ''),
                    trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''),
                    trim($_POST['address'] ?? ''), trim($_POST['location'] ?? ''),
                    trim($_POST['payment_terms'] ?? 'Cash on Delivery'),
                    (int)($_POST['rating'] ?? 5), trim($_POST['notes'] ?? '')
                ]);
                $success = 'Supplier added successfully.';
                logActivity($pdo, 'add', 'suppliers', 'Added supplier: ' . trim($_POST['name']), (int)$pdo->lastInsertId(), 'supplier');
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
        
        if ($action === 'edit_supplier') {
            try {
                $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?, email=?, address=?, location=?, payment_terms=?, rating=?, notes=? WHERE id=?");
                $stmt->execute([
                    trim($_POST['name']), trim($_POST['contact_person'] ?? ''),
                    trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''),
                    trim($_POST['address'] ?? ''), trim($_POST['location'] ?? ''),
                    trim($_POST['payment_terms'] ?? 'Cash on Delivery'),
                    (int)($_POST['rating'] ?? 5), trim($_POST['notes'] ?? ''),
                    (int)$_POST['supplier_id']
                ]);
                $success = 'Supplier updated.';
                logActivity($pdo, 'update', 'suppliers', 'Updated supplier: ' . trim($_POST['name']), (int)$_POST['supplier_id'], 'supplier');
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
        
        if ($action === 'delete_supplier') {
            try {
                $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([(int)$_POST['supplier_id']]);
                $success = 'Supplier deleted.';
                logActivity($pdo, 'delete', 'suppliers', 'Deleted supplier #' . (int)$_POST['supplier_id'], (int)$_POST['supplier_id'], 'supplier');
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
        
        if ($action === 'toggle_supplier') {
            try {
                $pdo->prepare("UPDATE suppliers SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_POST['supplier_id']]);
                $success = 'Supplier status toggled.';
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
    }
}

// Fetch suppliers
$suppliers = [];
if ($pdo) {
    try {
        // Tables are created by migration — no inline CREATE TABLE needed
        $suppliers = $pdo->query("SELECT s.*, COALESCE(del.cnt,0) as delivery_count, COALESCE(del.tot,0) as total_purchased FROM suppliers s LEFT JOIN (SELECT supplier_id, COUNT(*) as cnt, SUM(total_cost) as tot FROM supplier_deliveries GROUP BY supplier_id) del ON del.supplier_id = s.id ORDER BY s.supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $suppliers = []; }
}
?>

<?php if ($success): ?>
<div style="padding:12px 20px;background:#D3E8B8;border:1px solid #B3D98C;border-radius:4px;color:#2C6B31;font-size:0.9rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
    <i data-lucide="check-circle" style="width:16px;height:16px;"></i> <?php echo $success; ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="padding:12px 20px;background:#fee2e2;border:1px solid #fecaca;border-radius:4px;color:#b91c1c;font-size:0.9rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
    <i data-lucide="alert-circle" style="width:16px;height:16px;"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.5rem;color:var(--admin-text-heading);">Suppliers</h2>
        <p style="margin:4px 0 0;font-size:0.875rem;color:#475569;">Manage vendors, track deliveries, and rate performance.</p>
    </div>
    <button onclick="document.getElementById('add-supplier-modal').style.display='flex'" class="btn btn-primary">
        <i data-lucide="plus" style="width:18px;height:18px;"></i> Add Supplier
    </button>
</div>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card-info">
            <small>Total Suppliers</small>
            <strong><?php echo count($suppliers); ?></strong>
        </div>
        <div class="stat-card-icon"><i data-lucide="truck"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-info">
            <small>Active Suppliers</small>
            <strong><?php echo count(array_filter($suppliers, fn($s) => $s['is_active'])); ?></strong>
        </div>
        <div class="stat-card-icon accent"><i data-lucide="check-circle"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-info">
            <small>Total Purchases</small>
            <strong>KES <?php echo number_format(array_sum(array_column($suppliers, 'total_purchased'))); ?></strong>
        </div>
        <div class="stat-card-icon info"><i data-lucide="banknote"></i></div>
    </div>
</div>

<!-- Suppliers Table -->
<div class="admin-card" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Contact</th>
                    <th>Location</th>
                    <th>Rating</th>
                    <th>Deliveries</th>
                    <th>Total Purchased</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($s['supplier_name']); ?></strong></td>
                    <td>
                        <div style="font-size:0.85rem;">
                            <?php echo htmlspecialchars($s['contact_person'] ?: '—'); ?><br>
                            <span style="color:#64748b;"><?php echo htmlspecialchars($s['phone'] ?: '—'); ?></span>
                        </div>
                    </td>
                    <td style="font-size:0.85rem;"><?php echo htmlspecialchars($s['location'] ?: '—'); ?></td>
                    <td>
                        <span style="color:#f59e0b;font-size:0.9rem;">
                            <?php echo str_repeat('★', (int)$s['rating']) . str_repeat('☆', 5 - (int)$s['rating']); ?>
                        </span>
                    </td>
                    <td><?php echo $s['delivery_count']; ?></td>
                    <td><strong>KES <?php echo number_format((float)$s['total_purchased']); ?></strong></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="toggle_supplier">
                            <input type="hidden" name="supplier_id" value="<?php echo $s['id']; ?>">
                            <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                                <?php if ($s['is_active']): ?>
                                    <span class="badge-pill badge-pill-success">Active</span>
                                <?php else: ?>
                                    <span class="badge-pill badge-pill-danger">Inactive</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <button onclick='openEditSupplier(<?php echo htmlspecialchars(json_encode($s)); ?>)' class="btn btn-trans btn-sm" title="Edit"><i data-lucide="edit-3" style="width:14px;height:14px;"></i></button>
                            <form method="POST" onsubmit="return confirm('Delete this supplier?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="delete_supplier">
                                <input type="hidden" name="supplier_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:#64748b;">No suppliers yet. Click "Add Supplier" to get started.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Supplier Modal -->
<div id="add-supplier-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:4px;width:100%;max-width:560px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.15);position:relative;">
        <button onclick="this.closest('div[style]').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1.2rem;">✕</button>
        <h3 style="margin:0 0 24px;font-family:'Outfit',sans-serif;font-size:1.25rem;">Add New Supplier</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="add_supplier">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Supplier Name *</label><input name="name" required class="admin-form-control" placeholder="e.g. Nakuru Grain Merchants"></div>
                <div class="admin-form-group"><label class="admin-form-label">Contact Person</label><input name="contact_person" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Phone</label><input name="phone" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Email</label><input name="email" type="email" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Location</label><input name="location" class="admin-form-control" placeholder="e.g. Nakuru"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Address</label><input name="address" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Payment Terms</label><input name="payment_terms" class="admin-form-control" value="Cash on Delivery"></div>
                <div class="admin-form-group"><label class="admin-form-label">Rating (1-5)</label><select name="rating" class="admin-form-control"><option value="5">★★★★★</option><option value="4">★★★★☆</option><option value="3">★★★☆☆</option><option value="2">★★☆☆☆</option><option value="1">★☆☆☆☆</option></select></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Notes</label><textarea name="notes" rows="2" class="admin-form-control"></textarea></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="this.closest('div[style]').style.display='none'" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div id="edit-supplier-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:4px;width:100%;max-width:560px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.15);position:relative;">
        <button onclick="document.getElementById('edit-supplier-modal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1.2rem;">✕</button>
        <h3 style="margin:0 0 24px;font-family:'Outfit',sans-serif;font-size:1.25rem;">Edit Supplier</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="edit_supplier">
            <input type="hidden" name="supplier_id" id="edit-sid">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Supplier Name *</label><input name="name" id="edit-sname" required class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Contact Person</label><input name="contact_person" id="edit-scontact" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Phone</label><input name="phone" id="edit-sphone" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Email</label><input name="email" id="edit-semail" type="email" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Location</label><input name="location" id="edit-slocation" class="admin-form-control"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Address</label><input name="address" id="edit-saddress" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Payment Terms</label><input name="payment_terms" id="edit-spayment" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Rating (1-5)</label><select name="rating" id="edit-srating" class="admin-form-control"><option value="5">★★★★★</option><option value="4">★★★★☆</option><option value="3">★★★☆☆</option><option value="2">★★☆☆☆</option><option value="1">★☆☆☆☆</option></select></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Notes</label><textarea name="notes" id="edit-snotes" rows="2" class="admin-form-control"></textarea></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="document.getElementById('edit-supplier-modal').style.display='none'" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSupplier(s) {
    document.getElementById('edit-sid').value = s.id;
    document.getElementById('edit-sname').value = s.supplier_name;
    document.getElementById('edit-scontact').value = s.contact_person || '';
    document.getElementById('edit-sphone').value = s.phone || '';
    document.getElementById('edit-semail').value = s.email || '';
    document.getElementById('edit-slocation').value = s.location || '';
    document.getElementById('edit-saddress').value = s.address || '';
    document.getElementById('edit-spayment').value = s.payment_terms || '';
    document.getElementById('edit-srating').value = s.rating || 5;
    document.getElementById('edit-snotes').value = s.notes || '';
    document.getElementById('edit-supplier-modal').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
