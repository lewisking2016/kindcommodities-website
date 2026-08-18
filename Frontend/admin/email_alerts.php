<?php
/**
 * Admin — Email Alerts Configuration & Log
 */
declare(strict_types=1);
$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();
$page_title = 'Email Alerts - Admin';
include __DIR__ . '/includes/admin_header.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin','farm_manager'], true)) {
    header('Location: /kindadmin'); exit;
}

$pdo = getDB();
$success = '';
$error = '';

// Handle POST — save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired.';
    } else {
        $settings = ['alert_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name', 'smtp_encryption', 'low_stock_alert_enabled', 'order_notification_enabled', 'weekly_report_enabled', 'contract_expiry_alert_days'];
        foreach ($settings as $key) {
            if (array_key_exists($key, $_POST)) {
                updateSetting($key, trim((string)$_POST[$key]));
            } elseif (isset($_POST[$key . '_toggle'])) {
                updateSetting($key, isset($_POST[$key]) ? '1' : '0');
            }
        }
        $success = 'Email settings saved.';
    }
    
    if (isset($_POST['test_email'])) {
        require_once __DIR__ . '/../../Backend/api/email_notifications.php';
        $alertEmail = getSetting('alert_email', 'accounts@kindcommoditiesltd.com');
        $sent = sendEmail($alertEmail, 'Test Email — Kind Commodities', '<h2>✅ Email System Working</h2><p>This is a test email from Kind Commodities Ltd admin system.</p><p>Sent at: ' . date('d M Y H:i:s') . '</p>');
        $success = $sent ? 'Test email sent to ' . $alertEmail . '!' : 'Failed to send test email. Check SMTP settings.';
    }
}

// Fetch recent alerts
$alerts = [];
if ($pdo) {
    try {
        $alerts = $pdo->query("SELECT * FROM email_alerts_log ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $alerts = []; }
}

$csrf = function_exists('generateCSRFToken') ? generateCSRFToken() : ($_SESSION['csrf_token'] ?? '');
?>

<?php if ($success): ?>
<div style="padding:12px 20px;background:#D3E8B8;border:1px solid #B3D98C;border-radius:4px;color:#2C6B31;font-size:0.9rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;"><i data-lucide="check-circle" style="width:16px;height:16px;"></i> <?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div style="padding:12px 20px;background:#fee2e2;border:1px solid #fecaca;border-radius:4px;color:#b91c1c;font-size:0.9rem;margin-bottom:20px;display:flex;align-items:center;gap:8px;"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> <?php echo $error; ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.5rem;color:var(--admin-text-heading);">Email Alerts</h2>
        <p style="margin:4px 0 0;font-size:0.875rem;color:#475569;">Configure SMTP, notification preferences, and view alert history.</p>
    </div>
</div>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

<div style="display:flex;flex-direction:column;gap:24px;">
    <!-- SMTP Settings -->
    <div class="admin-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--admin-border);">
            <i data-lucide="mail" style="width:20px;height:20px;color:var(--admin-primary);"></i>
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.15rem;">SMTP Configuration</h3>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="admin-form-group"><label class="admin-form-label">Alert Recipient Email</label><input name="alert_email" type="email" value="<?php echo htmlspecialchars(getSetting('alert_email', 'accounts@kindcommoditiesltd.com')); ?>" class="admin-form-control"></div>
            <div class="admin-form-group"><label class="admin-form-label">SMTP Host</label><input name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host', 'kindcommoditiesltd.com')); ?>" class="admin-form-control"></div>
            <div class="admin-form-group"><label class="admin-form-label">SMTP Port</label><input name="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port', '465')); ?>" class="admin-form-control"></div>
            <div class="admin-form-group"><label class="admin-form-label">Encryption</label><select name="smtp_encryption" class="admin-form-control"><option value="ssl" <?php echo getSetting('smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>SSL</option><option value="tls" <?php echo getSetting('smtp_encryption') === 'tls' ? 'selected' : ''; ?>>TLS</option></select></div>
            <div class="admin-form-group"><label class="admin-form-label">SMTP Username</label><input name="smtp_username" value="<?php echo htmlspecialchars(getSetting('smtp_username', 'accounts@kindcommoditiesltd.com')); ?>" class="admin-form-control"></div>
            <div class="admin-form-group"><label class="admin-form-label">SMTP Password</label><input name="smtp_password" type="password" value="<?php echo htmlspecialchars(getSetting('smtp_password', '')); ?>" class="admin-form-control" placeholder="Enter SMTP password"></div>
            <div class="admin-form-group"><label class="admin-form-label">From Email</label><input name="smtp_from_email" type="email" value="<?php echo htmlspecialchars(getSetting('smtp_from_email', 'accounts@kindcommoditiesltd.com')); ?>" class="admin-form-control"></div>
            <div class="admin-form-group"><label class="admin-form-label">From Name</label><input name="smtp_from_name" value="<?php echo htmlspecialchars(getSetting('smtp_from_name', 'Kind Commodities Ltd')); ?>" class="admin-form-control"></div>
        </div>
    </div>

    <!-- Notification Preferences -->
    <div class="admin-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--admin-border);">
            <i data-lucide="bell" style="width:20px;height:20px;color:var(--admin-primary);"></i>
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.15rem;">Notification Preferences</h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--admin-border);">
                <div><strong>Low Stock Alerts</strong><br><span style="font-size:0.8rem;color:#64748b;">Email when products fall below threshold</span></div>
                <label style="position:relative;display:inline-block;width:48px;height:26px;"><input type="checkbox" name="low_stock_alert_enabled" value="1" <?php echo getSetting('low_stock_alert_enabled', '1') === '1' ? 'checked' : ''; ?> style="opacity:0;width:0;height:0;"><span style="position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:13px;transition:.4s;"></span></label>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--admin-border);">
                <div><strong>Order Notifications</strong><br><span style="font-size:0.8rem;color:#64748b;">Email on order status changes (paid, shipped, completed)</span></div>
                <label style="position:relative;display:inline-block;width:48px;height:26px;"><input type="checkbox" name="order_notification_enabled" value="1" <?php echo getSetting('order_notification_enabled', '1') === '1' ? 'checked' : ''; ?> style="opacity:0;width:0;height:0;"><span style="position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:13px;transition:.4s;"></span></label>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">
                <div><strong>Contract Delivery Alerts</strong><br><span style="font-size:0.8rem;color:#64748b;">Email when contract deliveries are recorded</span></div>
                <label style="position:relative;display:inline-block;width:48px;height:26px;"><input type="checkbox" name="contract_alert_enabled" value="1" <?php echo getSetting('contract_alert_enabled', '1') === '1' ? 'checked' : ''; ?> style="opacity:0;width:0;height:0;"><span style="position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:13px;transition:.4s;"></span></label>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">
                <div><strong>Contract Expiry Warnings</strong><br><span style="font-size:0.8rem;color:#64748b;">Alert days before contract delivery deadline</span></div>
                <input name="contract_expiry_alert_days" type="number" min="1" max="30" value="<?php echo htmlspecialchars(getSetting('contract_expiry_alert_days', '7')); ?>" class="admin-form-control" style="width:80px;text-align:center;">
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:12px;justify-content:flex-end;">
        <button type="submit" name="test_email" value="1" class="btn btn-outline"><i data-lucide="send" style="width:16px;height:16px;"></i> Send Test Email</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:16px;height:16px;"></i> Save Settings</button>
    </div>
</div>
</form>

<!-- Alert Log -->
<div class="admin-card" style="margin-top:32px;padding:0;overflow:hidden;">
    <div style="padding:20px;border-bottom:1px solid var(--admin-border);">
        <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.15rem;">Recent Alert History</h3>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Type</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Sent</th></tr></thead>
            <tbody>
                <?php if (!empty($alerts)): ?>
                <?php foreach ($alerts as $a): ?>
                <tr>
                    <td><span class="badge-pill <?php echo $a['alert_type'] === 'low_stock' ? 'badge-pill-warning' : ($a['alert_type'] === 'test' ? 'badge-pill-info' : 'badge-pill-success'); ?>"><?php echo htmlspecialchars($a['alert_type']); ?></span></td>
                    <td style="font-size:0.85rem;"><?php echo htmlspecialchars($a['recipient_email']); ?></td>
                    <td style="font-size:0.85rem;"><?php echo htmlspecialchars($a['subject']); ?></td>
                    <td><span class="badge-pill <?php echo $a['status'] === 'sent' ? 'badge-pill-success' : 'badge-pill-danger'; ?>"><?php echo $a['status']; ?></span></td>
                    <td style="font-size:0.8rem;color:#64748b;"><?php echo $a['sent_at'] ? date('d M Y H:i', strtotime($a['sent_at'])) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="5" style="text-align:center;padding:24px;color:#64748b;">No alerts sent yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
