<?php
/**
 * Admin — Contract Management
 * Forward contracts with growers/customers, delivery tracking
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Contract Management - Admin';
include __DIR__ . '/includes/admin_header.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin','farm_manager','sales_staff'], true)) {
    header('Location: /kindadmin'); exit;
}

$pdo = getDB();
$success = '';
$error = '';

// Generate contract number
function nextContractNumber(PDO $pdo): string {
    $year = date('Y');
    $prefix = 'KC-' . $year . '-';
    try {
        $last = $pdo->query("SELECT contract_number FROM contracts WHERE contract_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($last) {
            $num = (int)substr($last, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }
    } catch (Exception $e) { $num = 1; }
    return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_contract') {
            try {
                $qty = (float)$_POST['quantity_kg'];
                $price = (float)$_POST['unit_price'];
                $stmt = $pdo->prepare("INSERT INTO contracts (contract_number, contract_type, party_name, party_phone, party_email, party_type, product_id, commodity_name, quantity_kg, unit_price, total_value, contract_date, delivery_start, delivery_end, delivery_location, payment_terms, quality_specs, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    nextContractNumber($pdo), $_POST['contract_type'], trim($_POST['party_name']),
                    trim($_POST['party_phone'] ?? ''), trim($_POST['party_email'] ?? ''),
                    $_POST['party_type'] ?? 'customer',
                    !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null,
                    trim($_POST['commodity_name']), $qty, $price, $qty * $price,
                    $_POST['contract_date'], $_POST['delivery_start'] ?: null, $_POST['delivery_end'] ?: null,
                    trim($_POST['delivery_location'] ?? ''), trim($_POST['payment_terms'] ?? 'Cash on Delivery'),
                    trim($_POST['quality_specs'] ?? ''), $_POST['status'] ?? 'draft',
                    trim($_POST['notes'] ?? ''), $_SESSION['user_id']
                ]);
                $success = 'Contract created successfully.';
                logActivity($pdo, 'add', 'contracts', 'Created contract for ' . trim($_POST['party_name']), (int)$pdo->lastInsertId(), 'contract');
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
        
        if ($action === 'update_contract') {
            try {
                $qty = (float)$_POST['quantity_kg'];
                $price = (float)$_POST['unit_price'];
                $stmt = $pdo->prepare("UPDATE contracts SET contract_type=?, party_name=?, party_phone=?, party_email=?, party_type=?, product_id=?, commodity_name=?, quantity_kg=?, unit_price=?, total_value=?, contract_date=?, delivery_start=?, delivery_end=?, delivery_location=?, payment_terms=?, quality_specs=?, status=?, notes=? WHERE id=?");
                $stmt->execute([
                    $_POST['contract_type'], trim($_POST['party_name']),
                    trim($_POST['party_phone'] ?? ''), trim($_POST['party_email'] ?? ''),
                    $_POST['party_type'] ?? 'customer',
                    !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null,
                    trim($_POST['commodity_name']), $qty, $price, $qty * $price,
                    $_POST['contract_date'], $_POST['delivery_start'] ?: null, $_POST['delivery_end'] ?: null,
                    trim($_POST['delivery_location'] ?? ''), trim($_POST['payment_terms'] ?? 'Cash on Delivery'),
                    trim($_POST['quality_specs'] ?? ''), $_POST['status'] ?? 'active',
                    trim($_POST['notes'] ?? ''), (int)$_POST['contract_id']
                ]);
                $success = 'Contract updated.';
                logActivity($pdo, 'update', 'contracts', 'Updated contract #' . (int)$_POST['contract_id'], (int)$_POST['contract_id'], 'contract');
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
        
        if ($action === 'add_delivery') {
            try {
                $contractId = (int)$_POST['contract_id'];
                $qty = (float)$_POST['quantity_kg'];
                $stmt = $pdo->prepare("INSERT INTO contract_deliveries (contract_id, delivery_date, quantity_kg, bags_count, moisture_pct, grade, vehicle_plate, driver_name, driver_phone, waybill_number, quality_notes, received_by, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $contractId, $_POST['delivery_date'], $qty,
                    (int)($_POST['bags_count'] ?? 0),
                    !empty($_POST['moisture_pct']) ? (float)$_POST['moisture_pct'] : null,
                    trim($_POST['grade'] ?? '') ?: null,
                    trim($_POST['vehicle_plate'] ?? ''), trim($_POST['driver_name'] ?? ''),
                    trim($_POST['driver_phone'] ?? ''), trim($_POST['waybill_number'] ?? ''),
                    trim($_POST['quality_notes'] ?? ''), trim($_POST['received_by'] ?? ''),
                    $_SESSION['user_id']
                ]);
                // Update delivered quantity
                $pdo->prepare("UPDATE contracts SET delivered_kg = delivered_kg + ? WHERE id = ?")->execute([$qty, $contractId]);
                $success = 'Delivery recorded.';
                logActivity($pdo, 'add', 'contracts', "Delivery of {$qty}kg for contract #{$contractId}", $contractId, 'contract');
                
                // Send email notification
                require_once __DIR__ . '/../../Backend/api/email_notifications.php';
                sendContractDeliveryNotification($contractId, (int)$pdo->lastInsertId());
            } catch (Exception $e) { $error = 'Failed: ' . $e->getMessage(); }
        }
    }
}

// Tables are created by migration — no inline CREATE TABLE needed

// Fetch contracts
$contracts = [];
$products = [];
if ($pdo) {
    try {
        $contracts = $pdo->query("SELECT c.*, p.name as product_name FROM contracts c LEFT JOIN products p ON c.product_id = p.id ORDER BY c.contract_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    try {
        $products = $pdo->query("SELECT id, name FROM products WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Stats
$totalContracts = count($contracts);
$activeContracts = count(array_filter($contracts, fn($c) => $c['status'] === 'active'));
$totalValue = array_sum(array_column($contracts, 'total_value'));
?>

<?php if ($success): ?>
<div style="padding:12px 20px;background:#D3E8B8;border:1px solid #B3D98C;border-radius:4px;color:#2C6B31;font-size:0.9rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;"><i data-lucide="check-circle" style="width:16px;height:16px;"></i> <?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div style="padding:12px 20px;background:#fee2e2;border:1px solid #fecaca;border-radius:4px;color:#b91c1c;font-size:0.9rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> <?php echo $error; ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.5rem;color:var(--admin-text-heading);">Contracts</h2>
        <p style="margin:4px 0 0;font-size:0.875rem;color:#475569;">Manage forward purchase and sale contracts with growers and customers.</p>
    </div>
    <button onclick="document.getElementById('add-contract-modal').style.display='flex'" class="btn btn-primary"><i data-lucide="plus" style="width:18px;height:18px;"></i> New Contract</button>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card"><div class="stat-card-info"><small>Total Contracts</small><strong><?php echo $totalContracts; ?></strong></div><div class="stat-card-icon"><i data-lucide="file-text"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Active Contracts</small><strong><?php echo $activeContracts; ?></strong></div><div class="stat-card-icon accent"><i data-lucide="clock"></i></div></div>
    <div class="stat-card"><div class="stat-card-info"><small>Total Contract Value</small><strong>KES <?php echo number_format($totalValue); ?></strong></div><div class="stat-card-icon info"><i data-lucide="banknote"></i></div></div>
</div>

<!-- Contracts Table -->
<div class="admin-card" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Contract #</th><th>Party</th><th>Type</th><th>Commodity</th><th>Quantity</th><th>Delivered</th><th>Value</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
                <?php if (!empty($contracts)): ?>
                <?php foreach ($contracts as $c): ?>
                <?php
                    $pct = $c['quantity_kg'] > 0 ? round(($c['delivered_kg'] / $c['quantity_kg']) * 100) : 0;
                    $statusColors = ['draft'=>'#64748b','active'=>'#2563eb','fulfilled'=>'#16a34a','cancelled'=>'#dc2626','expired'=>'#f59e0b'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['contract_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['party_name']); ?><br><span style="font-size:0.75rem;color:#64748b;text-transform:capitalize;"><?php echo $c['party_type']; ?></span></td>
                    <td><span class="badge-pill <?php echo $c['contract_type']==='purchase'?'badge-pill-info':'badge-pill-success'; ?>"><?php echo ucfirst($c['contract_type']); ?></span></td>
                    <td><?php echo htmlspecialchars($c['commodity_name']); ?></td>
                    <td><?php echo number_format((float)$c['quantity_kg']); ?> kg</td>
                    <td>
                        <div style="font-size:0.85rem;">
                            <?php echo number_format((float)$c['delivered_kg']); ?> kg<br>
                            <div style="width:80px;height:4px;background:#e2e8f0;border-radius:2px;margin-top:2px;"><div style="width:<?php echo min(100, $pct); ?>%;height:100%;background:<?php echo $pct >= 100 ? '#16a34a' : '#2563eb'; ?>;border-radius:2px;"></div></div>
                        </div>
                    </td>
                    <td><strong>KES <?php echo number_format((float)$c['total_value']); ?></strong></td>
                    <td><span style="color:<?php echo $statusColors[$c['status']] ?? '#64748b'; ?>;font-weight:600;text-transform:capitalize;"><?php echo $c['status']; ?></span></td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <button onclick='openDeliveryModal(<?php echo $c["id"]; ?>,"<?php echo htmlspecialchars(addslashes($c["commodity_name"])); ?>")' class="btn btn-info btn-sm" title="Record Delivery"><i data-lucide="truck" style="width:14px;height:14px;"></i></button>
                            <button onclick='openEditContract(<?php echo htmlspecialchars(json_encode($c)); ?>)' class="btn btn-trans btn-sm" title="Edit"><i data-lucide="edit-3" style="width:14px;height:14px;"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="9" style="text-align:center;padding:30px;color:#64748b;">No contracts yet. Click "New Contract" to create one.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Contract Modal -->
<div id="add-contract-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:4px;width:100%;max-width:640px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.15);margin:20px;">
        <h3 style="margin:0 0 20px;font-family:'Outfit',sans-serif;font-size:1.25rem;">New Contract</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="add_contract">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="admin-form-group"><label class="admin-form-label">Contract Type *</label><select name="contract_type" class="admin-form-control" required><option value="sale">Sale (to customer)</option><option value="purchase">Purchase (from grower)</option></select></div>
                <div class="admin-form-group"><label class="admin-form-label">Status</label><select name="status" class="admin-form-control"><option value="draft">Draft</option><option value="active">Active</option></select></div>
                <div class="admin-form-group"><label class="admin-form-label">Party Name *</label><input name="party_name" required class="admin-form-control" placeholder="e.g. Kariuki Wholesalers"></div>
                <div class="admin-form-group"><label class="admin-form-label">Party Type</label><select name="party_type" class="admin-form-control"><option value="customer">Customer</option><option value="grower">Grower</option><option value="broker">Broker</option><option value="other">Other</option></select></div>
                <div class="admin-form-group"><label class="admin-form-label">Phone</label><input name="party_phone" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Email</label><input name="party_email" type="email" class="admin-form-control"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Commodity Name *</label><input name="commodity_name" required class="admin-form-control" placeholder="e.g. Maize, Wheat"></div>
                <div class="admin-form-group"><label class="admin-form-label">Product (optional)</label><select name="product_id" class="admin-form-control"><option value="">None</option><?php foreach ($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select></div>
                <div class="admin-form-group"><label class="admin-form-label">Quantity (kg) *</label><input name="quantity_kg" type="number" step="0.001" required class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Unit Price (KES) *</label><input name="unit_price" type="number" step="0.01" required class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Contract Date *</label><input name="contract_date" type="date" required class="admin-form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">Delivery Start</label><input name="delivery_start" type="date" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Delivery End</label><input name="delivery_end" type="date" class="admin-form-control"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Delivery Location</label><input name="delivery_location" class="admin-form-control"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Quality Specs</label><input name="quality_specs" class="admin-form-control" placeholder="e.g. Moisture max 13%, Grade 1"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Payment Terms</label><input name="payment_terms" class="admin-form-control" value="Cash on Delivery"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Notes</label><textarea name="notes" rows="2" class="admin-form-control"></textarea></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="this.closest('div[style*=\"position\"]').style.display='none'" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Contract</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Delivery Modal -->
<div id="delivery-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:4px;width:100%;max-width:520px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.15);">
        <h3 style="margin:0 0 20px;font-family:'Outfit',sans-serif;font-size:1.25rem;">Record Delivery — <span id="del-commodity"></span></h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="add_delivery">
            <input type="hidden" name="contract_id" id="del-contract-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="admin-form-group"><label class="admin-form-label">Delivery Date *</label><input name="delivery_date" type="date" required class="admin-form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="admin-form-group"><label class="admin-form-label">Quantity (kg) *</label><input name="quantity_kg" type="number" step="0.001" required class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Bags Count</label><input name="bags_count" type="number" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Moisture %</label><input name="moisture_pct" type="number" step="0.01" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Grade</label><input name="grade" class="admin-form-control" placeholder="e.g. Grade 1"></div>
                <div class="admin-form-group"><label class="admin-form-label">Vehicle Plate</label><input name="vehicle_plate" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Driver Name</label><input name="driver_name" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Driver Phone</label><input name="driver_phone" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Waybill #</label><input name="waybill_number" class="admin-form-control"></div>
                <div class="admin-form-group"><label class="admin-form-label">Received By</label><input name="received_by" class="admin-form-control"></div>
                <div class="admin-form-group" style="grid-column:span 2;"><label class="admin-form-label">Quality Notes</label><textarea name="quality_notes" rows="2" class="admin-form-control"></textarea></div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="document.getElementById('delivery-modal').style.display='none'" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Delivery</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeliveryModal(contractId, commodity) {
    document.getElementById('del-contract-id').value = contractId;
    document.getElementById('del-commodity').textContent = commodity;
    document.getElementById('delivery-modal').style.display = 'flex';
}
function openEditContract(c) {
    // For now, redirect to add modal pre-filled — can be expanded
    alert('Edit contract: ' + c.contract_number + '. Full edit coming soon.');
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
