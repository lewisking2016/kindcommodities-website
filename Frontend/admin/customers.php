<?php
/**
 * Admin — Customer Dashboard & Directory
 * Comprehensive view of all customers (Online Web Users + Walk-in & Wholesale)
 * Manage customer accounts, order history, credit status, and contact info.
 */
declare(strict_types=1);

$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) {
    session_save_path($temp_dir);
}
session_start();

$page_title = 'Customers Dashboard - Admin';
include __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../../Backend/api/dropdowns.php';

// Check admin access
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin', 'farm_manager', 'stock_manager', 'sales_staff'], true)) {
    header("Location: /kindadmin");
    exit;
}

$pdo = getDB();
$message = '';
$error_message = '';

// POST Actions Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    // Action 1: Add new customer
    if ($action === 'add_customer') {
        $customer_type = $_POST['customer_type'] ?? 'walk_in'; // 'online' or 'walk_in'
        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $category = $_POST['category'] ?? 'retail'; // retail, wholesale, institution, agent

        if (empty($name)) {
            $error_message = 'Customer name is required.';
        } else {
            try {
                if ($customer_type === 'online') {
                    // Create web user account
                    $username = trim($_POST['username'] ?? '') ?: strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . rand(10, 99);
                    $password = $_POST['password'] ?? 'Kind@' . rand(1000, 9999);
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Split name
                    $parts = explode(' ', $name, 2);
                    $first_name = $parts[0] ?? '';
                    $last_name = $parts[1] ?? '';

                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $error_message = 'A user account with this username or email already exists.';
                    } else {
                        // Dynamically detect phone columns
                        $cols = [];
                        try {
                            $colStmt = $pdo->query("SHOW COLUMNS FROM users");
                            $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                        } catch (Exception $e) {}

                        $hasPhone = in_array('phone', $cols, true);
                        $hasPhoneNumber = in_array('phone_number', $cols, true);

                        if ($hasPhone && $hasPhoneNumber) {
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, phone_number, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')");
                            $stmt->execute([$username, $email, $hash, $first_name, $last_name, $phone, $phone]);
                        } elseif ($hasPhoneNumber) {
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone_number, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
                            $stmt->execute([$username, $email, $hash, $first_name, $last_name, $phone]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'customer')");
                            $stmt->execute([$username, $email, $hash, $first_name, $last_name]);
                        }
                        $message = "Online customer account created. Username: <strong>{$username}</strong>, Initial Password: <strong>{$password}</strong>";
                    }
                } else {
                    // Walk-in / Wholesale customer
                    if (tableExists($pdo, 'walk_in_customers')) {
                        $stmt = $pdo->prepare("INSERT INTO walk_in_customers (customer_name, phone, customer_type, address, notes) VALUES (?, ?, ?, ?, ?)");
                        $notes = trim($_POST['notes'] ?? '');
                        $stmt->execute([$name, $phone, $category, $address, $notes]);
                        $message = "Walk-in/Wholesale customer '{$name}' created successfully.";
                    } else {
                        $error_message = "walk_in_customers table is not available.";
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Failed to create customer: ' . $e->getMessage();
            }
        }
    }

    // Action 2: Edit customer
    if ($action === 'edit_customer') {
        $source = $_POST['source'] ?? 'walk_in';
        $id = (int)($_POST['customer_id'] ?? 0);
        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($id <= 0 || empty($name)) {
            $error_message = 'Valid customer ID and name are required.';
        } else {
            try {
                if ($source === 'online') {
                    $parts = explode(' ', $name, 2);
                    $first_name = $parts[0] ?? '';
                    $last_name = $parts[1] ?? '';
                    
                    $cols = [];
                    try {
                        $colStmt = $pdo->query("SHOW COLUMNS FROM users");
                        $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                    } catch (Exception $e) {}

                    $hasPhone = in_array('phone', $cols, true);
                    $hasPhoneNumber = in_array('phone_number', $cols, true);

                    if ($hasPhone && $hasPhoneNumber) {
                        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, phone_number=? WHERE id=?");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $phone, $id]);
                    } elseif ($hasPhoneNumber) {
                        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone_number=? WHERE id=?");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
                        $stmt->execute([$first_name, $last_name, $email, $id]);
                    }
                    $message = 'Online customer profile updated.';
                } else {
                    $cat = $_POST['category'] ?? 'retail';
                    $stmt = $pdo->prepare("UPDATE walk_in_customers SET customer_name=?, phone=?, customer_type=?, address=? WHERE id=?");
                    $stmt->execute([$name, $phone, $cat, $address, $id]);
                    $message = 'Customer details updated successfully.';
                }
            } catch (Exception $e) {
                $error_message = 'Failed to update customer: ' . $e->getMessage();
            }
        }
    }

    // Action 3: Reset password for online customer
    if ($action === 'reset_password') {
        $target_user_id = (int)($_POST['target_user_id'] ?? 0);
        $new_pass = $_POST['new_password'] ?? '';
        if ($target_user_id > 0 && !empty($new_pass)) {
            try {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $target_user_id]);
                $message = "Password reset successfully for customer ID #{$target_user_id}.";
            } catch (Exception $e) {
                $error_message = 'Failed to reset password: ' . $e->getMessage();
            }
        }
    }

    // Action 4: Delete customer
    if ($action === 'delete_customer') {
        $source = $_POST['source'] ?? 'walk_in';
        $id = (int)($_POST['customer_id'] ?? 0);
        if ($id > 0) {
            try {
                if ($source === 'online') {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
                    $stmt->execute([$id]);
                    $message = "Online customer account #{$id} deleted.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM walk_in_customers WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Customer #{$id} deleted.";
                }
            } catch (Exception $e) {
                $error_message = 'Failed to delete customer: ' . $e->getMessage();
            }
        }
    }
}

// Filters
$active_tab = $_GET['tab'] ?? 'all'; // all, online, walk_in, credit
$search = trim($_GET['search'] ?? '');

// Fetch Online Customers with stats
$online_customers = [];
$total_online_count = 0;
$total_online_spent = 0;

if ($pdo && tableExists($pdo, 'users')) {
    try {
        $query = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                         COALESCE(u.phone_number, '') as phone, u.created_at,
                         COUNT(o.id) as orders_count,
                         COALESCE(SUM(o.total_amount), 0) as total_spent
                  FROM users u
                  LEFT JOIN orders o ON o.user_id = u.id
                  WHERE u.role = 'customer'";
        $params = [];
        if (!empty($search)) {
            $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone_number LIKE ?)";
            $sp = "%$search%";
            array_push($params, $sp, $sp, $sp, $sp, $sp);
        }
        $query .= " GROUP BY u.id ORDER BY u.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $online_customers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total_online_count = count($online_customers);
        foreach ($online_customers as $oc) {
            $total_online_spent += (float)$oc['total_spent'];
        }
    } catch (Exception $e) {
        error_log("Online customers fetch: " . $e->getMessage());
    }
}

// Fetch Walk-in / Wholesale Customers with stats
$walk_in_customers = [];
$total_walkin_count = 0;
$total_walkin_spent = 0;
$total_credit_owed = 0;
$active_credit_count = 0;

if ($pdo && tableExists($pdo, 'walk_in_customers')) {
    try {
        $query = "SELECT w.*, 
                         (SELECT COUNT(bs.id) FROM bulk_sales bs WHERE bs.customer_id = w.id OR bs.customer_name = w.customer_name) as sales_count,
                         (SELECT COALESCE(SUM(bs.total_amount), 0) FROM bulk_sales bs WHERE bs.customer_id = w.id OR bs.customer_name = w.customer_name) as total_bought,
                         (SELECT COALESCE(SUM(cc.balance), 0) FROM customer_credits cc WHERE (cc.customer_id = w.id OR cc.customer_name = w.customer_name) AND cc.status != 'paid') as credit_balance
                  FROM walk_in_customers w";
        $params = [];
        if (!empty($search)) {
            $query .= " WHERE (w.customer_name LIKE ? OR w.phone LIKE ? OR w.address LIKE ?)";
            $sp = "%$search%";
            array_push($params, $sp, $sp, $sp);
        }
        $query .= " ORDER BY w.customer_name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $walk_in_customers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total_walkin_count = count($walk_in_customers);
        foreach ($walk_in_customers as $wc) {
            $total_walkin_spent += (float)$wc['total_bought'];
            $bal = (float)$wc['credit_balance'];
            if ($bal > 0) {
                $total_credit_owed += $bal;
                $active_credit_count++;
            }
        }
    } catch (Exception $e) {
        error_log("Walk-in customers fetch: " . $e->getMessage());
    }
}

// Combine for 'all' tab
$all_list = [];
foreach ($online_customers as $oc) {
    $all_list[] = [
        'id' => $oc['id'],
        'source' => 'online',
        'type_label' => 'Online Customer',
        'badge_class' => 'badge-pill-success',
        'name' => trim(($oc['first_name'] ?? '') . ' ' . ($oc['last_name'] ?? '')) ?: $oc['username'],
        'username' => $oc['username'],
        'email' => $oc['email'],
        'phone' => $oc['phone'],
        'address' => 'Web Registered',
        'orders_count' => (int)$oc['orders_count'],
        'total_spent' => (float)$oc['total_spent'],
        'credit_balance' => 0,
        'category' => 'online',
        'created_at' => $oc['created_at'],
        'raw' => $oc
    ];
}
foreach ($walk_in_customers as $wc) {
    $all_list[] = [
        'id' => $wc['id'],
        'source' => 'walk_in',
        'type_label' => ucfirst((string)($wc['customer_type'] ?? 'Walk-in')),
        'badge_class' => 'badge-pill-info',
        'name' => $wc['customer_name'],
        'username' => '',
        'email' => $wc['email'] ?? '—',
        'phone' => $wc['phone'] ?? '—',
        'address' => $wc['address'] ?? 'Farm Gate / Wholesale',
        'orders_count' => (int)$wc['sales_count'],
        'total_spent' => (float)$wc['total_bought'],
        'credit_balance' => (float)$wc['credit_balance'],
        'category' => $wc['customer_type'] ?? 'retail',
        'created_at' => $wc['created_at'],
        'raw' => $wc
    ];
}

// Filter according to active tab
$display_list = $all_list;
if ($active_tab === 'online') {
    $display_list = array_filter($all_list, fn($c) => $c['source'] === 'online');
} elseif ($active_tab === 'walk_in') {
    $display_list = array_filter($all_list, fn($c) => $c['source'] === 'walk_in');
} elseif ($active_tab === 'credit') {
    $display_list = array_filter($all_list, fn($c) => $c['credit_balance'] > 0);
}

$total_customers_count = $total_online_count + $total_walkin_count;
$grand_total_spent = $total_online_spent + $total_walkin_spent;
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin:0; font-family:'Outfit',sans-serif; font-size:1.65rem; color:var(--admin-text-heading); font-weight:800;">Customer Dashboard &amp; Directory</h1>
        <p style="margin:4px 0 0; color:#64748b; font-size:0.9rem;">View, track and manage online buyers, walk-in clients, wholesale buyers and credit accounts.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="/Backend/api/export.php?module=customers" class="btn btn-outline" style="border-radius:6px; display:inline-flex; align-items:center; gap:6px;">
            <i data-lucide="download" style="width:15px; height:15px;"></i> Export CSV
        </a>
        <button class="btn btn-primary" onclick="openAddCustomerModal()" style="border-radius:6px; display:inline-flex; align-items:center; gap:6px;">
            <i data-lucide="user-plus" style="width:16px; height:16px;"></i> Add Customer
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding:13px 18px; background:#D3E8B8; border:1px solid #B3D98C; border-radius:8px; color:#1B4A24; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
        <i data-lucide="check-circle-2" style="width:18px; height:18px;"></i>
        <span><?= $message ?></span>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div style="padding:13px 18px; background:#fee2e2; border:1px solid #fecaca; border-radius:8px; color:#b91c1c; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
        <i data-lucide="alert-circle" style="width:18px; height:18px;"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
    </div>
<?php endif; ?>

<!-- KPI Cards -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card-info">
            <small>Total Customers</small>
            <strong style="color:var(--admin-primary);"><?= $total_customers_count ?></strong>
            <span style="font-size:0.75rem; color:#64748b; margin-top:2px;">Online &amp; Walk-in Combined</span>
        </div>
        <div class="stat-card-icon" style="background:rgba(57,98,133,0.1); color:#396285;">
            <i data-lucide="users" style="width:22px; height:22px;"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-info">
            <small>Online Web Accounts</small>
            <strong style="color:#2C6B31;"><?= $total_online_count ?></strong>
            <span style="font-size:0.75rem; color:#64748b; margin-top:2px;">KES <?= number_format($total_online_spent) ?> Ordered</span>
        </div>
        <div class="stat-card-icon" style="background:#E9F2DC; color:#2C6B31;">
            <i data-lucide="globe" style="width:22px; height:22px;"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-info">
            <small>Walk-in &amp; Wholesale</small>
            <strong style="color:#2563eb;"><?= $total_walkin_count ?></strong>
            <span style="font-size:0.75rem; color:#64748b; margin-top:2px;">KES <?= number_format($total_walkin_spent) ?> Purchased</span>
        </div>
        <div class="stat-card-icon" style="background:rgba(59,130,246,0.1); color:#2563eb;">
            <i data-lucide="store" style="width:22px; height:22px;"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-info">
            <small>Active Credit Accounts</small>
            <strong style="color:<?= $total_credit_owed > 0 ? '#dc2626' : '#2C6B31' ?>;">KES <?= number_format($total_credit_owed) ?></strong>
            <span style="font-size:0.75rem; color:#64748b; margin-top:2px;"><?= $active_credit_count ?> Customer(s) with Balance</span>
        </div>
        <div class="stat-card-icon" style="background:<?= $total_credit_owed > 0 ? '#fee2e2' : '#E9F2DC' ?>; color:<?= $total_credit_owed > 0 ? '#dc2626' : '#2C6B31' ?>;">
            <i data-lucide="hand-coins" style="width:22px; height:22px;"></i>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="admin-card" style="padding:0; overflow:hidden;">
    
    <!-- Tab navigation & Search -->
    <div style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid var(--admin-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
        
        <!-- Tabs -->
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <a href="?tab=all<?= !empty($search)?'&search='.urlencode($search):'' ?>" style="padding:7px 14px; border-radius:6px; font-size:0.85rem; font-weight:600; text-decoration:none; <?= $active_tab==='all'?'background:#ffffff; color:var(--admin-primary); box-shadow:0 1px 4px rgba(0,0,0,0.08);':'color:#64748b;' ?>">
                All Customers (<?= $total_customers_count ?>)
            </a>
            <a href="?tab=online<?= !empty($search)?'&search='.urlencode($search):'' ?>" style="padding:7px 14px; border-radius:6px; font-size:0.85rem; font-weight:600; text-decoration:none; <?= $active_tab==='online'?'background:#ffffff; color:var(--admin-primary); box-shadow:0 1px 4px rgba(0,0,0,0.08);':'color:#64748b;' ?>">
                Online Accounts (<?= $total_online_count ?>)
            </a>
            <a href="?tab=walk_in<?= !empty($search)?'&search='.urlencode($search):'' ?>" style="padding:7px 14px; border-radius:6px; font-size:0.85rem; font-weight:600; text-decoration:none; <?= $active_tab==='walk_in'?'background:#ffffff; color:var(--admin-primary); box-shadow:0 1px 4px rgba(0,0,0,0.08);':'color:#64748b;' ?>">
                Walk-in &amp; Wholesale (<?= $total_walkin_count ?>)
            </a>
            <a href="?tab=credit<?= !empty($search)?'&search='.urlencode($search):'' ?>" style="padding:7px 14px; border-radius:6px; font-size:0.85rem; font-weight:600; text-decoration:none; <?= $active_tab==='credit'?'background:#ffffff; color:#dc2626; box-shadow:0 1px 4px rgba(0,0,0,0.08);':'color:#64748b;' ?>">
                Credit Accounts (<?= $active_credit_count ?>)
            </a>
        </div>

        <!-- Search Form -->
        <form method="GET" style="display:flex; gap:8px; align-items:center; min-width:240px; margin:0;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <div style="display:flex; align-items:center; gap:8px; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:6px 12px; width:100%;">
                <i data-lucide="search" style="width:16px; height:16px; color:#94a3b8;"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, phone, email..." style="border:none; outline:none; font-size:0.85rem; width:100%;">
                <?php if (!empty($search)): ?>
                    <a href="?tab=<?= htmlspecialchars($active_tab) ?>" style="color:#94a3b8; text-decoration:none; font-size:0.9rem;">✕</a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-outline" style="border-radius:6px; padding:6px 12px; font-size:0.82rem;">Search</button>
        </form>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Type</th>
                    <th>Contact Phone</th>
                    <th>Email Address</th>
                    <th style="text-align:center;">Orders / Sales</th>
                    <th style="text-align:right;">Total Spend</th>
                    <th style="text-align:right;">Credit Balance</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($display_list)): ?>
                    <?php foreach ($display_list as $cust): ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color:var(--admin-text-heading); font-size:0.92rem;">
                                <?= htmlspecialchars((string)$cust['name']) ?>
                            </div>
                            <?php if (!empty($cust['username'])): ?>
                                <div style="font-size:0.78rem; color:#64748b;">@<?= htmlspecialchars((string)$cust['username']) ?> &bull; ID #<?= $cust['id'] ?></div>
                            <?php else: ?>
                                <div style="font-size:0.78rem; color:#64748b;"><?= htmlspecialchars((string)$cust['address']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-pill <?= $cust['badge_class'] ?>">
                                <?= htmlspecialchars((string)$cust['type_label']) ?>
                            </span>
                        </td>
                        <td style="font-family:monospace; font-size:0.88rem; font-weight:600;">
                            <?php if (!empty($cust['phone']) && $cust['phone'] !== '—'): ?>
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', (string)$cust['phone']) ?>" style="color:var(--admin-primary); text-decoration:none;">
                                    <?= htmlspecialchars((string)$cust['phone']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem; color:#475569;">
                            <?php if (!empty($cust['email']) && $cust['email'] !== '—'): ?>
                                <a href="mailto:<?= htmlspecialchars((string)$cust['email']) ?>" style="color:#475569; text-decoration:none;">
                                    <?= htmlspecialchars((string)$cust['email']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span style="font-weight:700; font-size:0.9rem; color:#0f172a;">
                                <?= $cust['orders_count'] ?>
                            </span>
                        </td>
                        <td style="text-align:right; font-weight:700; color:#2C6B31; font-size:0.92rem;">
                            KES <?= number_format($cust['total_spent']) ?>
                        </td>
                        <td style="text-align:right;">
                            <?php if ($cust['credit_balance'] > 0): ?>
                                <span style="font-weight:800; color:#dc2626; font-size:0.92rem;">
                                    KES <?= number_format($cust['credit_balance']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:0.82rem;">KSh 0</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                
                                <!-- Edit Button -->
                                <button title="Edit Customer" onclick='openEditCustomerModal(<?= json_encode($cust, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="btn btn-trans btn-sm" style="padding:4px 8px;">
                                    <i data-lucide="pencil" style="width:14px; height:14px;"></i>
                                </button>

                                <?php if ($cust['source'] === 'online'): ?>
                                    <!-- Reset Password for online user -->
                                    <button title="Reset Account Password" onclick="openResetModal(<?= $cust['id'] ?>, '<?= htmlspecialchars((string)$cust['name']) ?>')" class="btn btn-trans btn-sm" style="padding:4px 8px;">
                                        <i data-lucide="key-round" style="width:14px; height:14px;"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this customer record?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="source" value="<?= $cust['source'] ?>">
                                    <input type="hidden" name="customer_id" value="<?= $cust['id'] ?>">
                                    <button type="submit" title="Delete Customer" class="btn btn-trans btn-sm" style="padding:4px 8px; color:#dc2626;">
                                        <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px 20px; color:#94a3b8;">
                            <i data-lucide="users" style="width:36px; height:36px; margin-bottom:8px; opacity:0.4;"></i>
                            <p style="margin:0; font-size:0.95rem;">No customer records matching your filter.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== ADD CUSTOMER MODAL ========== -->
<div id="add-customer-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:540px; padding:32px; box-shadow:0 24px 48px rgba(0,0,0,0.2); position:relative; max-height:92vh; overflow-y:auto;">
        <button onclick="document.getElementById('add-customer-modal').style.display='none'" style="position:absolute; top:18px; right:18px; background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1.3rem;">✕</button>
        <h3 style="margin:0 0 6px 0; font-family:'Outfit',sans-serif; font-size:1.3rem; color:var(--admin-text-heading);">Add New Customer</h3>
        <p style="margin:0 0 20px 0; font-size:0.85rem; color:#64748b;">Create either a Walk-in / Wholesale buyer or an Online Web customer account.</p>

        <form method="POST">
            <input type="hidden" name="action" value="add_customer">

            <div class="admin-form-group" style="margin-bottom:14px;">
                <label class="admin-form-label">Customer Channel Type *</label>
                <select name="customer_type" id="add-channel-type" class="admin-form-control" onchange="toggleCustomerTypeFields()" required>
                    <option value="walk_in">Walk-in / Farm-Gate / Wholesale Buyer</option>
                    <option value="online">Online Web Customer (Portal Login)</option>
                </select>
            </div>

            <div class="admin-form-group" style="margin-bottom:14px;">
                <label class="admin-form-label">Full Name / Business Name *</label>
                <input type="text" name="customer_name" required placeholder="e.g. John Mwangi or Western Feed Millers" class="admin-form-control">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="admin-form-group">
                    <label class="admin-form-label">Phone Number *</label>
                    <input type="text" name="phone" required placeholder="+254 7..." class="admin-form-control">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Email Address</label>
                    <input type="email" name="email" placeholder="customer@example.com" class="admin-form-control">
                </div>
            </div>

            <div id="walkin-fields">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Buyer Category</label>
                        <select name="category" class="admin-form-control">
                            <option value="retail">Retail Buyer</option>
                            <option value="wholesale">Wholesale Buyer</option>
                            <option value="institution">Institution / School / Mill</option>
                            <option value="agent">Broker / Agent</option>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Location / Address</label>
                        <input type="text" name="address" placeholder="e.g., Kakamega Town" class="admin-form-control">
                    </div>
                </div>
                <div class="admin-form-group" style="margin-bottom:14px;">
                    <label class="admin-form-label">Notes</label>
                    <textarea name="notes" rows="2" class="admin-form-control" placeholder="Special pricing, credit history, etc."></textarea>
                </div>
            </div>

            <div id="online-fields" style="display:none; background:#f8fafc; padding:14px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:16px;">
                <div style="font-weight:700; font-size:0.85rem; color:var(--admin-primary); margin-bottom:10px;">Portal Login Credentials</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Username</label>
                        <input type="text" name="username" placeholder="Leave empty to auto-generate" class="admin-form-control">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Initial Password</label>
                        <input type="text" name="password" placeholder="Defaults to Kind@1234" class="admin-form-control">
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" onclick="document.getElementById('add-customer-modal').style.display='none'" class="btn btn-outline" style="border-radius:6px; padding:9px 18px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="border-radius:6px; padding:9px 22px;">Create Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== EDIT CUSTOMER MODAL ========== -->
<div id="edit-customer-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; padding:32px; box-shadow:0 24px 48px rgba(0,0,0,0.2); position:relative; max-height:92vh; overflow-y:auto;">
        <button onclick="document.getElementById('edit-customer-modal').style.display='none'" style="position:absolute; top:18px; right:18px; background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1.3rem;">✕</button>
        <h3 style="margin:0 0 6px 0; font-family:'Outfit',sans-serif; font-size:1.3rem; color:var(--admin-text-heading);">Edit Customer</h3>
        <p style="margin:0 0 20px 0; font-size:0.85rem; color:#64748b;">Modify contact information and details.</p>

        <form method="POST">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="source" id="edit-cust-source">
            <input type="hidden" name="customer_id" id="edit-cust-id">

            <div class="admin-form-group" style="margin-bottom:14px;">
                <label class="admin-form-label">Customer Name *</label>
                <input type="text" name="customer_name" id="edit-cust-name" required class="admin-form-control">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="admin-form-group">
                    <label class="admin-form-label">Phone Number</label>
                    <input type="text" name="phone" id="edit-cust-phone" class="admin-form-control">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Email Address</label>
                    <input type="email" name="email" id="edit-cust-email" class="admin-form-control">
                </div>
            </div>

            <div class="admin-form-group" style="margin-bottom:14px;">
                <label class="admin-form-label">Location / Address</label>
                <input type="text" name="address" id="edit-cust-address" class="admin-form-control">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" onclick="document.getElementById('edit-customer-modal').style.display='none'" class="btn btn-outline" style="border-radius:6px; padding:9px 18px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="border-radius:6px; padding:9px 22px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== RESET PASSWORD MODAL ========== -->
<div id="reset-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:400px; padding:32px; box-shadow:0 24px 48px rgba(0,0,0,0.2); position:relative;">
        <button onclick="document.getElementById('reset-modal').style.display='none'" style="position:absolute; top:18px; right:18px; background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1.3rem;">✕</button>
        <h3 style="margin:0 0 6px 0; font-family:'Outfit',sans-serif; font-size:1.25rem; color:var(--admin-text-heading);">Reset Password</h3>
        <p style="margin:0 0 18px 0; font-size:0.85rem; color:#64748b;" id="reset-modal-subtitle">Set a new password for user.</p>

        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_user_id" id="reset-target-id">

            <div class="admin-form-group" style="margin-bottom:20px;">
                <label class="admin-form-label">New Password *</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Enter new password" class="admin-form-control">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('reset-modal').style.display='none'" class="btn btn-outline" style="border-radius:6px; padding:8px 16px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="border-radius:6px; padding:8px 18px;">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCustomerModal() {
    document.getElementById('add-customer-modal').style.display = 'flex';
    toggleCustomerTypeFields();
}

function toggleCustomerTypeFields() {
    const val = document.getElementById('add-channel-type').value;
    const walkin = document.getElementById('walkin-fields');
    const online = document.getElementById('online-fields');
    if (val === 'online') {
        walkin.style.display = 'none';
        online.style.display = 'block';
    } else {
        walkin.style.display = 'block';
        online.style.display = 'none';
    }
}

function openEditCustomerModal(cust) {
    if (!cust) return;
    document.getElementById('edit-cust-source').value = cust.source || 'walk_in';
    document.getElementById('edit-cust-id').value = cust.id || '';
    document.getElementById('edit-cust-name').value = cust.name || '';
    document.getElementById('edit-cust-phone').value = (cust.phone && cust.phone !== '—') ? cust.phone : '';
    document.getElementById('edit-cust-email').value = (cust.email && cust.email !== '—') ? cust.email : '';
    document.getElementById('edit-cust-address').value = (cust.address && cust.address !== '—') ? cust.address : '';
    document.getElementById('edit-customer-modal').style.display = 'flex';
}

function openResetModal(userId, name) {
    document.getElementById('reset-target-id').value = userId;
    document.getElementById('reset-modal-subtitle').textContent = "Set a new password for " + name;
    document.getElementById('reset-modal').style.display = 'flex';
}

document.addEventListener('click', e => {
    ['add-customer-modal', 'edit-customer-modal', 'reset-modal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && e.target === el) el.style.display = 'none';
    });
});
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
?>

