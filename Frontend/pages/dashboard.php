<?php
/**
 * Customer Dashboard
 * Clean SaaS-style account overview & order tracking
 */
declare(strict_types=1);

$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) {
    session_save_path($temp_dir);
}
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: /Frontend/pages/login.php');
    exit;
}

$path_prefix = '../';
$page_title = 'My Account - Kind Commodities Ltd';

include '../includes/header.php';

$pdo = getDB();
$user_id = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile' && $pdo) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    try {
        // Detect phone columns dynamically
        $cols = [];
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM users");
            $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Exception $e) {}

        $hasPhone = in_array('phone', $cols, true);
        $hasPhoneNumber = in_array('phone_number', $cols, true);

        if (!empty($new_password)) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            if ($hasPhone && $hasPhoneNumber) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, phone_number = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $phone, $hash, $user_id]);
            } elseif ($hasPhoneNumber) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone_number = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $hash, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $hash, $user_id]);
            }
        } else {
            if ($hasPhone && $hasPhoneNumber) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, phone_number = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $phone, $user_id]);
            } elseif ($hasPhoneNumber) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone_number = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $user_id]);
            }
        }
        $_SESSION['first_name'] = $first_name;
        $success_msg = 'Your profile details have been successfully updated.';
    } catch (Exception $e) {
        $error_msg = 'Failed to update profile: ' . $e->getMessage();
    }
}

// Fetch user profile
$user = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Dashboard user fetch: " . $e->getMessage());
    }
}

// Fetch order stats
$total_orders = 0;
$total_spent = 0;
$pending_orders = 0;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total FROM orders WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_orders = (int)($row['cnt'] ?? 0);
        $total_spent = (float)($row['total'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending', 'processing', 'picking', 'packing', 'production', 'dispatch')");
        $stmt->execute([$user_id]);
        $pending_orders = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Dashboard stats: " . $e->getMessage());
    }
}

// Fetch recent orders with full items breakdown
$recent_orders = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
        $stmt->execute([$user_id]);
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!empty($recent_orders)) {
            $order_ids = array_column($recent_orders, 'id');
            $in_clause = implode(',', array_fill(0, count($order_ids), '?'));
            $item_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.image_url 
                                        FROM order_items oi 
                                        LEFT JOIN products p ON oi.product_id = p.id 
                                        WHERE oi.order_id IN ($in_clause)");
            $item_stmt->execute($order_ids);
            $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            $items_by_order = [];
            foreach ($items as $it) {
                $items_by_order[$it['order_id']][] = $it;
            }
            
            foreach ($recent_orders as &$ord) {
                $ord_items = $items_by_order[$ord['id']] ?? [];
                $ord['items'] = $ord_items;
                $names = array_map(function($i) { 
                    return ($i['product_name'] ?? 'Commodity') . ' (' . $i['quantity'] . ')'; 
                }, $ord_items);
                $ord['product_names'] = !empty($names) ? implode(', ', $names) : 'Agricultural Commodities';
            }
            unset($ord);
        }
    } catch (Exception $e) {
        error_log("Dashboard orders: " . $e->getMessage());
    }
}

$userPhone = $user['phone_number'] ?? $user['phone'] ?? '';
?>

<style>
    .user-dash-container {
        max-width: 1140px;
        margin: 0 auto;
        padding: 50px 20px 80px;
    }

    .user-dash-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .user-dash-header h1 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--dark, #0f172a);
        margin: 0;
    }

    .user-dash-header p {
        margin: 4px 0 0 0;
        font-size: 0.95rem;
        color: var(--gray-500, #64748b);
    }

    .user-kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }

    .user-kpi-card {
        background: #ffffff;
        border: 1px solid var(--gray-200, #e2e8f0);
        border-radius: 12px;
        padding: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .user-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(0,0,0,0.05);
    }

    .user-kpi-card small {
        display: block;
        color: var(--gray-500, #64748b);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.75rem;
    }

    .user-kpi-card strong {
        display: block;
        margin-top: 8px;
        font-size: 1.75rem;
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        color: var(--dark, #0f172a);
    }

    .user-kpi-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-main-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 32px;
    }

    .user-card {
        background: #ffffff;
        border: 1px solid var(--gray-200, #e2e8f0);
        border-radius: 12px;
        padding: 24px;
    }

    .user-card h3 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--dark, #0f172a);
        margin: 0 0 18px 0;
    }

    .user-order-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid var(--gray-100, #f1f5f9);
        gap: 12px;
    }

    .user-order-row:last-child {
        border-bottom: none;
    }

    .user-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .user-badge-pending { background: #FEF3C7; color: #92400E; }
    .user-badge-completed { background: #D3E8B8; color: #2C6B31; }
    .user-badge-cancelled { background: #FEE2E2; color: #B91C1C; }
    .user-badge-processing { background: #DBEAFE; color: #1E40AF; }
    .user-badge-shipped { background: #E0E7FF; color: #3730A3; }
    .user-badge-paid { background: #E9F2DC; color: #2C6B31; }

    .user-profile-field {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--gray-100, #f1f5f9);
        font-size: 0.9rem;
    }

    .user-profile-field:last-child {
        border-bottom: none;
    }

    .user-profile-field .label {
        color: var(--gray-500, #64748b);
        font-weight: 500;
    }

    .user-profile-field .value {
        color: var(--dark, #0f172a);
        font-weight: 600;
    }

    .user-quick-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 16px;
    }

    .user-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 14px;
        border: 1px solid var(--gray-200, #e2e8f0);
        border-radius: 8px;
        background: #ffffff;
        color: var(--dark, #0f172a);
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .user-action-btn:hover {
        border-color: var(--primary, #396285);
        color: var(--primary, #396285);
        background: #f8fafc;
    }

    @media (max-width: 860px) {
        .user-kpi-grid { grid-template-columns: 1fr; }
        .user-main-grid { grid-template-columns: 1fr; }
        .user-quick-actions { grid-template-columns: 1fr; }
    }
</style>

<div class="user-dash-container">

    <?php if ($success_msg): ?>
        <div style="padding: 14px 20px; background: #D3E8B8; border: 1px solid #B3D98C; border-radius: 8px; color: #1B4A24; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
            <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div style="padding: 14px 20px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
            <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="user-dash-header">
        <div>
            <h1>Welcome back, <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Customer')); ?></h1>
            <p>Kind Commodities Account Overview &bull; Track orders, delivery updates and account details</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="document.getElementById('edit-profile-modal').style.display='flex'" class="btn btn-outline" style="border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;">
                <i data-lucide="user-check" style="width: 17px; height: 17px;"></i>
                <span>Edit Profile</span>
            </button>
            <a href="/Frontend/pages/shop.php" class="btn btn-primary" style="border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
                <i data-lucide="shopping-bag" style="width: 17px; height: 17px;"></i>
                <span>Order Commodities</span>
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="user-kpi-grid">
        <div class="user-kpi-card">
            <div>
                <small>Total Orders Placed</small>
                <strong><?php echo $total_orders; ?></strong>
            </div>
            <div class="user-kpi-icon" style="background: rgba(59, 130, 246, 0.1); color: #2563eb;">
                <i data-lucide="shopping-bag" style="width: 24px; height: 24px;"></i>
            </div>
        </div>
        <div class="user-kpi-card">
            <div>
                <small>Total Spent</small>
                <strong>KES <?php echo number_format($total_spent); ?></strong>
            </div>
            <div class="user-kpi-icon" style="background: rgba(44, 107, 49, 0.1); color: #2C6B31;">
                <i data-lucide="wallet" style="width: 24px; height: 24px;"></i>
            </div>
        </div>
        <div class="user-kpi-card">
            <div>
                <small>Active / Pending Orders</small>
                <strong><?php echo $pending_orders; ?></strong>
            </div>
            <div class="user-kpi-icon" style="background: rgba(217, 119, 6, 0.1); color: #b45309;">
                <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="user-main-grid">
        <!-- Recent Orders -->
        <div class="user-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--gray-100, #f1f5f9); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">My Orders &amp; Purchase History</h3>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;"><?php echo count($recent_orders); ?> recent</span>
            </div>
            <div style="padding: 0 24px;">
                <?php if (!empty($recent_orders)): ?>
                    <?php foreach ($recent_orders as $order): ?>
                    <?php 
                        $badge = 'user-badge-pending';
                        $s = strtolower((string)$order['status']);
                        if ($s === 'completed' || $s === 'delivered') $badge = 'user-badge-completed';
                        elseif ($s === 'cancelled') $badge = 'user-badge-cancelled';
                        elseif ($s === 'picking' || $s === 'packing' || $s === 'production' || $s === 'dispatch' || $s === 'processing') $badge = 'user-badge-processing';
                        elseif ($s === 'shipped') $badge = 'user-badge-shipped';
                        elseif ($s === 'paid') $badge = 'user-badge-paid';
                    ?>
                    <div class="user-order-row">
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 700; color: var(--dark, #0f172a); font-size: 0.95rem;">
                                    Order #<?php echo htmlspecialchars((string)$order['order_number']); ?>
                                </span>
                                <span class="user-badge <?php echo $badge; ?>">
                                    <?php echo ucfirst((string)$order['status']); ?>
                                </span>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--gray-500, #64748b); margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($order['product_names'] ?? 'Commodity items'); ?>
                            </div>
                            <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 3px;">
                                Placed on <?php echo date('M d, Y &bull; h:i A', strtotime((string)$order['created_at'])); ?>
                            </div>
                        </div>
                        <div style="text-align: right; flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
                            <div style="font-weight: 700; color: var(--dark, #0f172a); font-size: 1rem;">
                                KES <?php echo number_format((float)$order['total_amount']); ?>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick='viewOrderModal(<?php echo json_encode($order, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 4px 10px; font-size: 0.78rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i> Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 50px 20px; text-align: center; color: var(--gray-400, #94a3b8);">
                        <i data-lucide="package" style="width: 48px; height: 48px; margin-bottom: 14px; opacity: 0.4;"></i>
                        <h4 style="color: var(--dark, #0f172a); margin-bottom: 6px;">No orders found yet</h4>
                        <p style="margin: 0; font-size: 0.92rem;">Explore our high-quality grains, pulses, and feed raw materials.</p>
                        <a href="/Frontend/pages/shop.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; border-radius: 8px; padding: 10px 20px;">
                            <i data-lucide="shopping-cart" style="width: 16px; height: 16px;"></i> Browse Store
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Profile & Quick Support -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Profile Summary -->
            <div class="user-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <h3 style="margin: 0;">Account Profile</h3>
                    <button onclick="document.getElementById('edit-profile-modal').style.display='flex'" style="background: none; border: none; color: var(--primary, #396285); cursor: pointer; font-size: 0.85rem; font-weight: 600;">Edit</button>
                </div>
                <div class="user-profile-field">
                    <span class="label">Full Name</span>
                    <span class="value"><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Not set'); ?></span>
                </div>
                <div class="user-profile-field">
                    <span class="label">Username</span>
                    <span class="value">@<?php echo htmlspecialchars((string)($user['username'] ?? '—')); ?></span>
                </div>
                <div class="user-profile-field">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars((string)($user['email'] ?? '—')); ?></span>
                </div>
                <div class="user-profile-field">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($userPhone ?: 'Not set'); ?></span>
                </div>
                <div class="user-profile-field">
                    <span class="label">Account Role</span>
                    <span class="value"><span class="user-badge user-badge-paid"><?php echo ucfirst((string)($user['role'] ?? 'customer')); ?></span></span>
                </div>
                <div class="user-profile-field">
                    <span class="label">Member Since</span>
                    <span class="value"><?php echo isset($user['created_at']) ? date('M Y', strtotime((string)$user['created_at'])) : '—'; ?></span>
                </div>
            </div>

            <!-- Regional Contact Support -->
            <div class="user-card">
                <h3>Direct Regional Contacts</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin-top: -8px; margin-bottom: 14px;">Reach our sales desk in your region:</p>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.88rem;">
                    <div style="padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #2C6B31; text-transform: uppercase;">Kenya (HQ)</span>
                        <div style="display: flex; flex-direction: column; margin-top: 2px;">
                            <a href="tel:+254726012845" style="font-weight: 700; color: #0f172a; text-decoration: none;">+254 726 012 845</a>
                            <a href="tel:+254777012845" style="font-weight: 700; color: #0f172a; text-decoration: none;">+254 777 012 845</a>
                        </div>
                    </div>
                    <div style="padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #2C6B31; text-transform: uppercase;">Rwanda Office</span>
                        <div style="margin-top: 2px;">
                            <a href="tel:+250782273747" style="font-weight: 700; color: #0f172a; text-decoration: none;">+250 782 273 747</a>
                        </div>
                    </div>
                    <div style="padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #2C6B31; text-transform: uppercase;">Burundi Office</span>
                        <div style="margin-top: 2px;">
                            <a href="tel:+25762903055" style="font-weight: 700; color: #0f172a; text-decoration: none;">+257 62 903 055</a>
                        </div>
                    </div>
                </div>

                <a href="https://wa.me/254726012845" target="_blank" rel="noopener" class="user-action-btn" style="margin-top: 14px; background: #25D366; color: #ffffff; border-color: #25D366;">
                    <i data-lucide="message-circle" style="width: 17px; height: 17px;"></i>
                    <span>Chat on WhatsApp</span>
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="user-card">
                <h3>Quick Shortcuts</h3>
                <div class="user-quick-actions">
                    <a href="/Frontend/pages/shop.php" class="user-action-btn">
                        <i data-lucide="shopping-cart" style="width: 16px; height: 16px;"></i>
                        Shop
                    </a>
                    <a href="/Frontend/pages/cart.php" class="user-action-btn">
                        <i data-lucide="package" style="width: 16px; height: 16px;"></i>
                        Cart
                    </a>
                    <a href="/Frontend/pages/contact.php" class="user-action-btn">
                        <i data-lucide="headphones" style="width: 16px; height: 16px;"></i>
                        Support
                    </a>
                    <a href="/Frontend/pages/logout.php" class="user-action-btn" style="color: #dc2626;">
                        <i data-lucide="log-out" style="width: 16px; height: 16px;"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== EDIT PROFILE MODAL ========== -->
<div id="edit-profile-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; width:100%; max-width:480px; padding:32px; box-shadow:0 24px 48px rgba(0,0,0,0.2); position:relative; max-height:92vh; overflow-y:auto;">
        <button onclick="document.getElementById('edit-profile-modal').style.display='none'" style="position:absolute; top:18px; right:18px; background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1.3rem;">✕</button>
        <h3 style="margin:0 0 6px 0; font-family:'Outfit',sans-serif; font-size:1.3rem; color:#0f172a;">Edit My Profile</h3>
        <p style="margin:0 0 20px 0; font-size:0.88rem; color:#64748b;">Update your personal details and password.</p>

        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars((string)($user['first_name'] ?? '')); ?>" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars((string)($user['last_name'] ?? '')); ?>" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($userPhone); ?>" placeholder="+254 7..." required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" placeholder="••••••••" minlength="6" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('edit-profile-modal').style.display='none'" class="btn btn-outline" style="border-radius:6px; padding:9px 18px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="border-radius:6px; padding:9px 20px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== ORDER DETAILS MODAL ========== -->
<div id="order-details-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#ffffff; border-radius:12px; width:100%; max-width:620px; padding:32px; box-shadow:0 24px 48px rgba(0,0,0,0.2); position:relative; max-height:92vh; overflow-y:auto;">
        <button onclick="document.getElementById('order-details-modal').style.display='none'" style="position:absolute; top:18px; right:18px; background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1.3rem;">✕</button>
        
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid #e2e8f0;">
            <div>
                <h3 id="od-title" style="margin:0 0 4px 0; font-family:'Outfit',sans-serif; font-size:1.3rem; color:#0f172a;">Order Details</h3>
                <div id="od-date" style="font-size:0.85rem; color:#64748b;">Placed on Date</div>
            </div>
            <div id="od-status"></div>
        </div>

        <div style="margin-bottom:20px;">
            <h4 style="font-size:0.95rem; margin:0 0 10px 0; color:#0f172a;">Ordered Items</h4>
            <div id="od-items" style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;"></div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; font-size:0.88rem; background:#f8fafc; padding:16px; border-radius:8px; border:1px solid #f1f5f9;">
            <div>
                <strong style="display:block; color:#64748b; font-size:0.75rem; text-transform:uppercase;">Delivery Address</strong>
                <span id="od-address" style="color:#0f172a; font-weight:600; display:block; margin-top:2px;">—</span>
            </div>
            <div>
                <strong style="display:block; color:#64748b; font-size:0.75rem; text-transform:uppercase;">Contact Phone</strong>
                <span id="od-phone" style="color:#0f172a; font-weight:600; display:block; margin-top:2px;">—</span>
            </div>
            <div>
                <strong style="display:block; color:#64748b; font-size:0.75rem; text-transform:uppercase;">Payment Method</strong>
                <span id="od-payment" style="color:#0f172a; font-weight:600; display:block; margin-top:2px;">—</span>
            </div>
            <div>
                <strong style="display:block; color:#64748b; font-size:0.75rem; text-transform:uppercase;">Total Amount</strong>
                <span id="od-total" style="color:#2C6B31; font-weight:800; font-size:1.1rem; display:block; margin-top:2px;">KES 0</span>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('order-details-modal').style.display='none'" class="btn btn-outline" style="border-radius:6px; padding:8px 20px;">Close</button>
        </div>
    </div>
</div>

<script>
function viewOrderModal(order) {
    if (!order) return;
    document.getElementById('od-title').textContent = 'Order #' + (order.order_number || order.id);
    document.getElementById('od-date').textContent = 'Placed on ' + (order.created_at || 'Recently');
    
    // Status badge
    const s = String(order.status || 'pending').toLowerCase();
    let badgeClass = 'user-badge-pending';
    if (s === 'completed' || s === 'delivered') badgeClass = 'user-badge-completed';
    else if (s === 'cancelled') badgeClass = 'user-badge-cancelled';
    else if (s === 'processing' || s === 'picking' || s === 'packing') badgeClass = 'user-badge-processing';
    else if (s === 'shipped') badgeClass = 'user-badge-shipped';
    else if (s === 'paid') badgeClass = 'user-badge-paid';
    
    document.getElementById('od-status').innerHTML = `<span class="user-badge ${badgeClass}">${order.status || 'Pending'}</span>`;
    
    document.getElementById('od-address').textContent = order.shipping_address || 'Not specified';
    document.getElementById('od-phone').textContent = order.phone_contact || '<?php echo htmlspecialchars($userPhone); ?>' || 'Not specified';
    document.getElementById('od-payment').textContent = String(order.payment_method || 'Standard').toUpperCase();
    document.getElementById('od-total').textContent = 'KES ' + Number(order.total_amount || 0).toLocaleString();
    
    // Items
    const itemsContainer = document.getElementById('od-items');
    if (order.items && order.items.length) {
        itemsContainer.innerHTML = order.items.map(it => {
            const price = Number(it.price_at_purchase || it.price || 0);
            const qty = Number(it.quantity || 1);
            const lineTotal = price * qty;
            return `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:0.92rem;">${escapeHtml(it.product_name || 'Commodity Item')}</div>
                        <div style="font-size:0.8rem; color:#64748b;">KES ${price.toLocaleString()} &times; ${qty}</div>
                    </div>
                    <div style="font-weight:700; color:#0f172a;">KES ${lineTotal.toLocaleString()}</div>
                </div>
            `;
        }).join('');
    } else {
        itemsContainer.innerHTML = `<div style="padding:16px; text-align:center; color:#64748b; font-size:0.88rem;">${escapeHtml(order.product_names || 'Items on this order')}</div>`;
    }

    document.getElementById('order-details-modal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
}

document.addEventListener('click', e => {
    const m1 = document.getElementById('edit-profile-modal');
    if (m1 && e.target === m1) m1.style.display = 'none';
    const m2 = document.getElementById('order-details-modal');
    if (m2 && e.target === m2) m2.style.display = 'none';
});
</script>

<?php include '../includes/footer.php'; ?>
