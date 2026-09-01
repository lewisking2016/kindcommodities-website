<?php
/**
 * Admin - Sales Module
 */
declare(strict_types=1);

$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();

$page_title = 'Sales - Admin';
include __DIR__ . '/includes/admin_header.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin', 'farm_manager', 'sales_staff'], true)) {
    header('Location: /kindadmin');
    exit;
}

$pdo = getDB();
$action = $_GET['action'] ?? 'list';
$message = '';
$error_message = '';

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];

    if (in_array($newStatus, $validStatuses, true)) {
        try {
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $orderId]);
            $message = 'Order status updated successfully.';
        } catch (Exception $e) {
            $error_message = 'Unable to update order status: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Invalid order status selected.';
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$orders = [];
if ($pdo) {
    try {
        $query = 'SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1';
        $params = [];
        if ($search !== '') {
            $query .= ' AND (o.order_number LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if ($statusFilter !== '') {
            $query .= ' AND o.status = ?';
            $params[] = $statusFilter;
        }
        $query .= ' ORDER BY o.created_at DESC';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

$selectedOrder = null;
if ($action === 'view' && $pdo) {
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId > 0) {
        $stmt = $pdo->prepare('SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?');
        $stmt->execute([$orderId]);
        $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($selectedOrder) {
            $stmt = $pdo->prepare('SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?');
            $stmt->execute([$orderId]);
            $selectedOrder['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

function renderInput(string $label, string $name, string $value = '', string $type = 'text'): string {
    return '<div class="admin-form-group"><label class="admin-form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label><input class="admin-form-control" type="' . $type . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"></div>';
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
    <div>
        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.5rem;color:var(--admin-text-heading);">Sales</h2>
        <p style="margin:4px 0 0 0;font-size:0.9rem;color:#64748b;">Manage customer sales and invoices.</p>
    </div>
</div>

<?php if ($message): ?>
<div style="padding:14px 18px;background:#D3E8B8;border:1px solid #B3D98C;border-radius:6px;color:#1B4A24;margin-bottom:20px;">
    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div style="padding:14px 18px;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;color:#b91c1c;margin-bottom:20px;">
    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;color:var(--admin-text-heading);">Sales List</h3>
        <span style="font-size:0.85rem;color:#64748b;">Recent customer sales</span>
    </div>
    <div style="margin-bottom:16px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" style="padding:10px;border:1px solid #cbd5e1;border-radius:6px;min-width:220px;">
            <select name="status" style="padding:10px;border:1px solid #cbd5e1;border-radius:6px;">
                <option value="">All Statuses</option>
                <?php foreach (['pending','paid','processing','shipped','completed','cancelled'] as $statusOption): ?>
                <option value="<?php echo $statusOption; ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>><?php echo ucfirst($statusOption); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline" style="border-radius:4px;">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px; color: #64748b;">No sales orders found.</td></tr>
                <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($order['username'] ?: $order['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>KES <?php echo number_format((float)$order['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($order['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align:right;">
                        <a class="btn btn-outline btn-sm" href="?action=view&id=<?php echo (int)$order['id']; ?>">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($action === 'view'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.5rem;color:var(--admin-text-heading);">Sales Order Invoice</h2>
        <p style="margin:4px 0 0;font-size:0.9rem;color:#64748b;">Review and print customer order invoice / receipt.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/Frontend/admin/sales.php" class="btn btn-outline"><i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Back to Sales</a>
        <button class="btn btn-primary" onclick="window.print()"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print Invoice</button>
    </div>
</div>

<?php if ($selectedOrder): ?>
<?php 
    $statusClass = [
        'completed' => 'badge-pill-success',
        'paid' => 'badge-pill-success',
        'shipped' => 'badge-pill-info',
        'processing' => 'badge-pill-info',
        'pending' => 'badge-pill-warning',
        'cancelled' => 'badge-pill-danger'
    ][$selectedOrder['status']] ?? 'badge-pill-warning';
    $order_logo = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg';
?>
<div id="printable-sales-doc" style="font-family:'Inter',sans-serif;color:#0f172a;max-width:780px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
    <!-- Header Banner -->
    <div style="background:#1E3A5F;padding:22px 28px;color:#ffffff;display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #D97706;gap:20px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:16px;">
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
            <!-- Left: Customer Details -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;display:flex;flex-direction:column;justify-content:space-between;">
                <div>
                    <div style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:#1E3A5F;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">BILL TO / CUSTOMER</div>
                    <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;"><?php echo htmlspecialchars($selectedOrder['username'] ?: 'Guest Customer', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Email:</strong> <?php echo htmlspecialchars($selectedOrder['email'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if (!empty($selectedOrder['phone_contact'])): ?>
                    <div style="font-size:0.85rem;color:#475569;margin-bottom:3px;"><strong style="color:#64748b;font-weight:500;">Phone:</strong> <?php echo htmlspecialchars($selectedOrder['phone_contact'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($selectedOrder['shipping_address'])): ?>
                    <div style="font-size:0.85rem;color:#475569;margin-top:3px;"><strong style="color:#64748b;font-weight:500;">Shipping Address:</strong> <?php echo htmlspecialchars($selectedOrder['shipping_address'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Order Details -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;text-align:right;display:flex;flex-direction:column;justify-content:space-between;">
                <div>
                    <div style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.3rem;color:#1E3A5F;letter-spacing:0.04em;">SALES ORDER RECEIPT</div>
                    <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:2px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;">#<?php echo htmlspecialchars($selectedOrder['order_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                    
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                        <span style="color:#64748b;">Order Date:</span>
                        <strong style="color:#0f172a;"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($selectedOrder['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#475569;margin-bottom:4px;">
                        <span style="color:#64748b;">Payment Method:</span>
                        <strong style="color:#0f172a;"><?php echo htmlspecialchars($selectedOrder['payment_method'] ?: 'Standard', ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                    <span style="font-size:0.82rem;color:#64748b;">Status:</span>
                    <span class="badge-pill <?php echo $statusClass; ?>" style="text-transform:uppercase;font-weight:700;font-size:0.75rem;padding:4px 10px;"><?php echo htmlspecialchars(ucfirst($selectedOrder['status']), ENT_QUOTES, 'UTF-8'); ?></span>
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
            <tbody>
                <?php if (empty($selectedOrder['items'])): ?>
                <tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">No products found for this order.</td></tr>
                <?php else: ?>
                <?php 
                $rIdx = 0;
                foreach ($selectedOrder['items'] as $item): 
                    $rIdx++;
                    $bg = $rIdx % 2 === 1 ? '#ffffff' : '#f8fafc';
                    $lineTotal = (float)$item['quantity'] * (float)$item['price_at_purchase'];
                ?>
                <tr style="background:<?php echo $bg; ?>;">
                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;color:#64748b;"><?php echo $rIdx; ?></td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;font-weight:600;color:#0f172a;"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;color:#0f172a;"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;color:#0f172a;"><?php echo number_format((float)$item['price_at_purchase'], 2); ?></td>
                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:0.88rem;text-align:right;font-weight:700;color:#0f172a;"><?php echo number_format($lineTotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals Block -->
        <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
            <div style="width:280px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:#475569;margin-bottom:8px;">
                    <span>Subtotal:</span>
                    <strong style="color:#0f172a;">KES <?php echo number_format((float)$selectedOrder['total_amount'], 2); ?></strong>
                </div>
                <div style="background:#1E3A5F;color:#ffffff;margin:-4px -16px -16px -16px;padding:12px 16px;border-bottom-left-radius:7px;border-bottom-right-radius:7px;display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                    <span style="font-weight:700;font-size:0.9rem;letter-spacing:0.04em;">TOTAL PAID / DUE</span>
                    <span style="font-family:'Outfit',sans-serif;font-weight:800;font-size:1.25rem;color:#ffffff;">KES <?php echo number_format((float)$selectedOrder['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Footer / Signoff -->
        <div style="border-top:1px solid #e2e8f0;padding-top:18px;margin-top:16px;display:flex;justify-content:space-between;align-items:flex-end;font-size:0.8rem;color:#64748b;">
            <div>
                <div>Thank you for your business!</div>
                <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Kind Commodities Ltd &bull; Tel: +254 700 000 000 &bull; info@kindcommoditiesltd.com</div>
            </div>
            <div style="text-align:right;">
                <div>Kind Commodities Management System</div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<p style="color:#64748b;">Order not found.</p>
<?php endif; ?>

<style>
@media print {
    body * { visibility: hidden !important; }
    #printable-sales-doc, #printable-sales-doc * { visibility: visible !important; }
    #printable-sales-doc {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
