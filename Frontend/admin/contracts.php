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
                            <button onclick='viewContract(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)' class="btn btn-primary btn-sm" title="View / Print Contract Document"><i data-lucide="eye" style="width:14px;height:14px;"></i></button>
                            <button onclick='openDeliveryModal(<?php echo $c["id"]; ?>,"<?php echo htmlspecialchars(addslashes($c["commodity_name"])); ?>")' class="btn btn-info btn-sm" title="Record Delivery"><i data-lucide="truck" style="width:14px;height:14px;"></i></button>
                            <button onclick='openEditContract(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8"); ?>)' class="btn btn-trans btn-sm" title="Edit"><i data-lucide="edit-3" style="width:14px;height:14px;"></i></button>
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
    <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.15);margin:20px;">
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
    <div style="background:#fff;border-radius:8px;width:100%;max-width:520px;padding:32px;box-shadow:0 24px 48px rgba(0,0,0,0.15);">
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

<!-- View Contract Document Modal -->
<div id="view-contract-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;width:100%;max-width:820px;box-shadow:0 20px 40px rgba(0,0,0,0.15);max-height:92vh;overflow-y:auto;margin:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 22px;border-bottom:1px solid var(--admin-border);position:sticky;top:0;background:#fff;z-index:5;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.05rem;">Commercial Contract Document</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="window.print()"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print Contract</button>
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('view-contract-modal').style.display='none'">Close</button>
            </div>
        </div>
        <div id="contract-preview-body" style="padding:28px;"></div>
    </div>
</div>

<script>
function openDeliveryModal(contractId, commodity) {
    document.getElementById('del-contract-id').value = contractId;
    document.getElementById('del-commodity').textContent = commodity;
    document.getElementById('delivery-modal').style.display = 'flex';
}
function openEditContract(c) {
    alert('Edit contract: ' + c.contract_number + '. Please update via the database or contact system admin.');
}

function viewContract(c) {
    const qty = parseFloat(c.quantity_kg || 0);
    const unitPrice = parseFloat(c.unit_price || 0);
    const totalVal = parseFloat(c.total_value || (qty * unitPrice));
    const delivered = parseFloat(c.delivered_kg || 0);
    const remaining = Math.max(0, qty - delivered);
    const isSale = c.contract_type === 'sale';
    const contractTitle = isSale ? 'COMMODITY SALES CONTRACT' : 'COMMODITY PURCHASE AGREEMENT';
    const partyRole = isSale ? 'BUYER / CUSTOMER' : 'SELLER / GROWER';

    document.getElementById('contract-preview-body').innerHTML = `
        <div id="printable-contract" style="font-family:'Inter',sans-serif;color:#0f172a;max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
            <!-- Header Banner -->
            <div style="background:#1E3A5F;padding:22px 28px;color:#ffffff;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #D97706;gap:20px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <?php $contract_logo = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg'; ?>
                    <div style="background:#ffffff;padding:6px 10px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <img src="<?php echo htmlspecialchars($contract_logo, ENT_QUOTES, 'UTF-8'); ?>" style="height:44px;max-width:140px;object-fit:contain;" alt="Logo" onerror="this.parentElement.style.display='none'">
                    </div>
                    <div>
                        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-weight:800;font-size:1.35rem;color:#ffffff;letter-spacing:-0.02em;">Kind Commodities Ltd</h2>
                        <div style="font-size:0.82rem;color:#cbd5e1;margin-top:2px;">Agricultural Commodity Trading &amp; Grain Contracts</div>
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
                    <!-- Left: Party Info -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:#1E3A5F;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">CONTRACTING PARTY (${partyRole})</div>
                            <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;">${escapeHtml(c.party_name)}</div>
                            ${c.party_phone ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Phone:</strong> ' + escapeHtml(c.party_phone) + '</div>' : ''}
                            ${c.party_email ? '<div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Email:</strong> ' + escapeHtml(c.party_email) + '</div>' : ''}
                            <div style="font-size:0.85rem;color:#475569;margin-top:3px;"><strong style="color:#64748b;font-weight:500;">Party Type:</strong> ${escapeHtml(c.party_type || 'Customer')}</div>
                        </div>
                    </div>

                    <!-- Right: Contract Meta -->
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;text-align:right;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                            <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.2rem;color:#1E3A5F;letter-spacing:0.04em;">${contractTitle}</div>
                            <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:2px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">#${escapeHtml(c.contract_number)}</div>
                            
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Contract Date:</span>
                                <strong style="color:#0f172a;">${c.contract_date}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                                <span style="color:#64748b;">Delivery Period:</span>
                                <strong style="color:#0f172a;">${c.delivery_start || 'Immediate'} &ndash; ${c.delivery_end || 'Open'}</strong>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                            <span style="font-size:0.82rem;color:#64748b;">Status:</span>
                            <span class="badge-pill ${c.status==='active'?'badge-pill-success':(c.status==='completed'?'badge-pill-success':'badge-pill-info')}" style="text-transform:uppercase;font-weight:700;font-size:0.75rem;padding:4px 10px;">${escapeHtml(c.status)}</span>
                        </div>
                    </div>
                </div>

                <!-- Commercial Terms Table -->
                <table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:20px;">
                    <thead>
                        <tr style="background:#1E3A5F;color:#ffffff;">
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:left;">Commodity / Product</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:110px;">Contract Qty</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:110px;">Unit Price</th>
                            <th style="padding:10px 12px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-align:right;width:140px;">Total Value (KES)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#ffffff;">
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.95rem;font-weight:600;color:#0f172a;">${escapeHtml(c.commodity_name)}</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.9rem;text-align:right;color:#0f172a;">${qty.toLocaleString(undefined,{minimumFractionDigits:2})} kg</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.9rem;text-align:right;color:#0f172a;">KES ${unitPrice.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                            <td style="padding:12px;border-bottom:1px solid #e2e8f0;font-size:0.95rem;text-align:right;font-weight:800;color:#1E3A5F;">KES ${totalVal.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Specifications & Terms Grid -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;">
                        <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#1E3A5F;margin-bottom:4px;">Delivery Location</div>
                        <div style="font-size:0.85rem;color:#334155;">${escapeHtml(c.delivery_location || 'Designated Kind Commodities Depot / Farm Gate')}</div>
                        
                        <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#1E3A5F;margin-top:10px;margin-bottom:4px;">Quality Specifications</div>
                        <div style="font-size:0.85rem;color:#334155;">${escapeHtml(c.quality_specs || 'Standard commercial export & milling grade (Moisture <= 13%, clean, insect-free)')}</div>
                    </div>
                    
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;">
                        <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#1E3A5F;margin-bottom:4px;">Payment Terms</div>
                        <div style="font-size:0.85rem;color:#334155;">${escapeHtml(c.payment_terms || 'Cash upon inspection and verified delivery weighbridge slip')}</div>

                        <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#1E3A5F;margin-top:10px;margin-bottom:4px;">Delivery Fulfilment</div>
                        <div style="font-size:0.85rem;color:#334155;">Delivered: <strong>${delivered.toLocaleString(undefined,{minimumFractionDigits:2})} kg</strong> &bull; Remaining: <strong>${remaining.toLocaleString(undefined,{minimumFractionDigits:2})} kg</strong></div>
                    </div>
                </div>

                ${c.notes ? `
                <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:14px 16px;font-size:0.85rem;color:#78350F;margin-bottom:20px;">
                    <div style="font-weight:700;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;color:#92400E;margin-bottom:4px;">Special Conditions &amp; Notes</div>
                    <div style="white-space:pre-wrap;line-height:1.45;">${escapeHtml(c.notes)}</div>
                </div>` : ''}

                <!-- Signatures & Authorization -->
                <div style="border-top:1px solid #e2e8f0;padding-top:20px;margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:30px;font-size:0.82rem;color:#475569;">
                    <div>
                        <div style="font-weight:700;color:#0f172a;margin-bottom:24px;">For Kind Commodities Ltd:</div>
                        <div style="border-bottom:1px solid #94a3b8;width:200px;margin-bottom:6px;"></div>
                        <div>Authorized Signature &amp; Stamp</div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:4px;">Date: ________________________</div>
                    </div>
                    <div>
                        <div style="font-weight:700;color:#0f172a;margin-bottom:24px;">For ${escapeHtml(c.party_name)}:</div>
                        <div style="border-bottom:1px solid #94a3b8;width:200px;margin-bottom:6px;"></div>
                        <div>Authorized Party Signature</div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:4px;">Date: ________________________</div>
                    </div>
                </div>
            </div>
        </div>`;
    document.getElementById('view-contract-modal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

document.addEventListener('click', e => {
    const m = document.getElementById('view-contract-modal');
    if (m && e.target === m) m.style.display = 'none';
});

function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
</script>

<style>
@media print {
    body * { visibility: hidden !important; }
    #view-contract-modal, #view-contract-modal > div, #contract-preview-body, #printable-contract, #printable-contract * { visibility: visible !important; }
    #view-contract-modal {
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
    #view-contract-modal > div {
        max-width: 100% !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
    #view-contract-modal > div > div:first-child { display: none !important; }
    #contract-preview-body { padding: 0 !important; }
    #printable-contract {
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
