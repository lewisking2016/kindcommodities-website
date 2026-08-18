<?php
/**
 * Email Notification System — Kind Commodities Ltd
 * Sends low-stock alerts, order notifications, and contract alerts
 * via PHPMailer with SMTP (kindcommoditiesltd.com:465 SSL).
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $temp_dir = sys_get_temp_dir();
    if (is_writable($temp_dir)) session_save_path($temp_dir);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auto_migrate.php';

// Load PHPMailer (bundled in vendor)
$mailerPath = dirname(__DIR__, 2) . '/Frontend/assets/vendor/phpmailer/PHPMailer.php';
if (!file_exists($mailerPath)) {
    // Fallback: use PHP native mail() if PHPMailer not installed
    $useNativeMail = true;
} else {
    $useNativeMail = false;
    require_once $mailerPath;
    require_once dirname(__DIR__, 2) . '/Frontend/assets/vendor/phpmailer/SMTP.php';
    require_once dirname(__DIR__, 2) . '/Frontend/assets/vendor/phpmailer/Exception.php';
}

/**
 * Get SMTP configuration from site_settings
 */
function getSmtpConfig(): array {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    $keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 
             'smtp_from_email', 'smtp_from_name', 'smtp_encryption', 'alert_email'];
    $config = [];
    foreach ($keys as $key) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            $config[$key] = $val !== false ? $val : '';
        } catch (Exception $e) {
            $config[$key] = '';
        }
    }
    return $config;
}

/**
 * Send an email using SMTP (PHPMailer) or native mail()
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $config = getSmtpConfig();
    
    if (empty($config['smtp_host']) || empty($config['smtp_username'])) {
        error_log('Email notification: SMTP not configured');
        return false;
    }
    
    global $useNativeMail;
    
    if (!$useNativeMail && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
            $mail->SMTPSecure = $config['smtp_encryption'] === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)$config['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            $fromEmail = $config['smtp_from_email'] ?: $config['smtp_username'];
            $fromName = $config['smtp_from_name'] ?: 'Kind Commodities Ltd';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Email send failed (PHPMailer): ' . $e->getMessage());
            return false;
        }
    }
    
    // Fallback: native mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $fromEmail = $config['smtp_from_email'] ?: $config['smtp_username'];
    $fromName = $config['smtp_from_name'] ?: 'Kind Commodities Ltd';
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    
    $result = @mail($to, $subject, $htmlBody, $headers);
    if (!$result) {
        error_log('Email send failed (native mail)');
    }
    return $result;
}

/**
 * Log email alert to database
 */
function logEmailAlert(string $type, string $recipient, string $subject, string $body, string $status = 'sent', ?string $relatedType = null, ?int $relatedId = null): void {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return;
        $stmt = $pdo->prepare("INSERT INTO email_alerts_log (alert_type, recipient_email, subject, body, status, related_type, related_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$type, $recipient, $subject, $body, $status, $relatedType, $relatedId]);
    } catch (Exception $e) {
        error_log('Failed to log email alert: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────
// LOW STOCK ALERT
// ─────────────────────────────────────────────────────────────
function checkAndSendLowStockAlerts(): array {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    $config = getSmtpConfig();
    $alertEmail = $config['alert_email'] ?: 'accounts@kindcommoditiesltd.com';
    
    if (($config['low_stock_alert_enabled'] ?? '1') !== '1') return [];
    
    $sent = [];
    try {
        $lowStock = $pdo->query("
            SELECT p.id, p.name, p.stock_quantity, p.low_stock_threshold, p.product_type,
                   c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1 
            AND p.stock_quantity <= p.low_stock_threshold
            AND p.stock_quantity >= 0
            ORDER BY p.stock_quantity ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($lowStock)) return [];
        
        $rows = '';
        foreach ($lowStock as $item) {
            $severity = $item['stock_quantity'] <= 5 ? '🔴 CRITICAL' : ($item['stock_quantity'] <= 10 ? '🟡 LOW' : '🟠 WARNING');
            $rows .= "<tr>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>{$severity}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;'>{$item['name']}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>{$item['category_name']}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:center;font-weight:700;color:" . ($item['stock_quantity'] <= 5 ? '#dc2626' : '#f59e0b') . ";'>{$item['stock_quantity']}</td>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:center;'>{$item['low_stock_threshold']}</td>
            </tr>";
        }
        
        $count = count($lowStock);
        $subject = "⚠️ Low Stock Alert — {$count} product(s) below threshold";
        
        $html = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;'>";
        $html .= "<div style='background:linear-gradient(135deg,#0B2310,#396285);padding:24px;border-radius:8px 8px 0 0;'>";
        $html .= "<h1 style='color:#fff;margin:0;font-size:1.3rem;'>⚠️ Low Stock Alert</h1>";
        $html .= "<p style='color:rgba(255,255,255,0.8);margin:4px 0 0;font-size:0.9rem;'>Kind Commodities Ltd — Inventory Warning</p></div>";
        $html .= "<div style='padding:24px;background:#fff;border:1px solid #e2e8f0;border-top:none;'>";
        $html .= "<p>The following <strong>{$count} product(s)</strong> are running low on stock:</p>";
        $html .= "<table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:0.85rem;'>";
        $html .= "<thead><tr style='background:#f8fafc;'>";
        $html .= "<th style='padding:8px 12px;text-align:left;border-bottom:2px solid #e2e8f0;'>Status</th>";
        $html .= "<th style='padding:8px 12px;text-align:left;border-bottom:2px solid #e2e8f0;'>Product</th>";
        $html .= "<th style='padding:8px 12px;text-align:left;border-bottom:2px solid #e2e8f0;'>Category</th>";
        $html .= "<th style='padding:8px 12px;text-align:center;border-bottom:2px solid #e2e8f0;'>In Stock</th>";
        $html .= "<th style='padding:8px 12px;text-align:center;border-bottom:2px solid #e2e8f0;'>Threshold</th>";
        $html .= "</tr></thead><tbody>{$rows}</tbody></table>";
        $html .= "<p style='font-size:0.85rem;color:#64748b;'>Please restock these items as soon as possible to avoid order delays.</p>";
        $html .= "<p style='font-size:0.85rem;color:#64748b;'>— Kind Commodities Admin System</p>";
        $html .= "</div></body></html>";
        
        $textBody = "Low Stock Alert — {$count} product(s) below threshold.\n\n";
        foreach ($lowStock as $item) {
            $textBody .= "- {$item['name']}: {$item['stock_quantity']} in stock (threshold: {$item['low_stock_threshold']})\n";
        }
        
        $sent[] = sendEmail($alertEmail, $subject, $html, $textBody);
        logEmailAlert('low_stock', $alertEmail, $subject, strip_tags($html), 'sent', 'product', null);
    } catch (Exception $e) {
        error_log('Low stock alert error: ' . $e->getMessage());
    }
    
    return $sent;
}

// ─────────────────────────────────────────────────────────────
// ORDER NOTIFICATION
// ─────────────────────────────────────────────────────────────
function sendOrderNotification(int $orderId, string $status = 'placed'): bool {
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    $config = getSmtpConfig();
    $alertEmail = $config['alert_email'] ?: 'accounts@kindcommoditiesltd.com';
    
    if (($config['order_notification_enabled'] ?? '1') !== '1') return false;
    
    try {
        $order = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email, u.phone_number
            FROM orders o LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $order->execute([$orderId]);
        $order = $order->fetch(PDO::FETCH_ASSOC);
        if (!$order) return false;
        
        $items = $pdo->prepare("
            SELECT oi.*, p.name as product_name
            FROM order_items oi JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items->execute([$orderId]);
        $items = $items->fetchAll(PDO::FETCH_ASSOC);
        
        $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: 'Guest';
        $itemRows = '';
        foreach ($items as $item) {
            $itemRows .= "<tr>
                <td style='padding:6px 10px;border-bottom:1px solid #e2e8f0;'>{$item['product_name']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;'>{$item['quantity']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;'>KES " . number_format((float)$item['price_at_purchase']) . "</td>
            </tr>";
        }
        
        $subject = "📦 Order #{$order['order_number']} — {$status}";
        $statusColor = $status === 'completed' ? '#16a34a' : ($status === 'cancelled' ? '#dc2626' : '#2563eb');
        
        $html = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;'>";
        $html .= "<div style='background:linear-gradient(135deg,#0B2310,#396285);padding:24px;border-radius:8px 8px 0 0;'>";
        $html .= "<h1 style='color:#fff;margin:0;font-size:1.3rem;'>📦 Order {$status}</h1>";
        $html .= "<p style='color:rgba(255,255,255,0.8);margin:4px 0 0;'>Order #{$order['order_number']}</p></div>";
        $html .= "<div style='padding:24px;background:#fff;border:1px solid #e2e8f0;border-top:none;'>";
        $html .= "<div style='display:flex;gap:16px;margin-bottom:16px;'>";
        $html .= "<div style='flex:1;'><strong>Customer:</strong> {$customerName}<br>";
        $html .= "<strong>Phone:</strong> {$order['phone_contact']}<br>";
        $html .= "<strong>Status:</strong> <span style='color:{$statusColor};font-weight:700;text-transform:uppercase;'>{$status}</span></div>";
        $html .= "<div style='flex:1;text-align:right;'><strong>Total:</strong> <span style='font-size:1.2rem;color:#16a34a;'>KES " . number_format((float)$order['total_amount']) . "</span><br>";
        $html .= "<strong>Payment:</strong> " . ucfirst(str_replace('_', ' ', $order['payment_method'])) . "</div></div>";
        $html .= "<table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:0.85rem;'>";
        $html .= "<thead><tr style='background:#f8fafc;'>";
        $html .= "<th style='padding:6px 10px;text-align:left;'>Product</th>";
        $html .= "<th style='padding:6px 10px;text-align:center;'>Qty</th>";
        $html .= "<th style='padding:6px 10px;text-align:right;'>Price</th></tr></thead>";
        $html .= "<tbody>{$itemRows}</tbody></table>";
        $html .= "<p style='font-size:0.85rem;color:#64748b;'>Order placed: " . date('d M Y H:i', strtotime($order['created_at'])) . "</p>";
        $html .= "</div></body></html>";
        
        $result = sendEmail($alertEmail, $subject, $html);
        logEmailAlert('order_' . $status, $alertEmail, $subject, strip_tags($html), 'sent', 'order', $orderId);
        
        // Also check for low stock after order
        checkAndSendLowStockAlerts();
        
        return $result;
    } catch (Exception $e) {
        error_log('Order notification error: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────
// CONTRACT DELIVERY NOTIFICATION
// ─────────────────────────────────────────────────────────────
function sendContractDeliveryNotification(int $contractId, int $deliveryId): bool {
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    $config = getSmtpConfig();
    $alertEmail = $config['alert_email'] ?: 'accounts@kindcommoditiesltd.com';
    
    try {
        $contract = $pdo->prepare("SELECT * FROM contracts WHERE id = ?");
        $contract->execute([$contractId]);
        $contract = $contract->fetch(PDO::FETCH_ASSOC);
        if (!$contract) return false;
        
        $delivery = $pdo->prepare("SELECT * FROM contract_deliveries WHERE id = ?");
        $delivery->execute([$deliveryId]);
        $delivery = $delivery->fetch(PDO::FETCH_ASSOC);
        if (!$delivery) return false;
        
        $remaining = max(0, (float)$contract['quantity_kg'] - (float)$contract['delivered_kg']);
        $pct = $contract['quantity_kg'] > 0 ? round(($contract['delivered_kg'] / $contract['quantity_kg']) * 100) : 0;
        
        $subject = "📋 Contract Delivery — {$contract['contract_number']} ({$pct}% fulfilled)";
        
        $html = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;'>";
        $html .= "<div style='background:linear-gradient(135deg,#0B2310,#396285);padding:24px;border-radius:8px 8px 0 0;'>";
        $html .= "<h1 style='color:#fff;margin:0;font-size:1.3rem;'>📋 Contract Delivery Recorded</h1>";
        $html .= "<p style='color:rgba(255,255,255,0.8);margin:4px 0 0;'>{$contract['contract_number']} — {$contract['commodity_name']}</p></div>";
        $html .= "<div style='padding:24px;background:#fff;border:1px solid #e2e8f0;border-top:none;'>";
        $html .= "<p><strong>Party:</strong> {$contract['party_name']} ({$contract['party_type']})</p>";
        $html .= "<p><strong>Delivery:</strong> {$delivery['quantity_kg']} kg on " . date('d M Y', strtotime($delivery['delivery_date'])) . "</p>";
        $html .= "<div style='background:#f8fafc;padding:12px;border-radius:6px;margin:12px 0;'>";
        $html .= "<p style='margin:0;'>Total Contracted: <strong>" . number_format((float)$contract['quantity_kg']) . " kg</strong></p>";
        $html .= "<p style='margin:4px 0 0;'>Delivered: <strong>" . number_format((float)$contract['delivered_kg']) . " kg ({$pct}%)</strong></p>";
        $html .= "<p style='margin:4px 0 0;'>Remaining: <strong>" . number_format($remaining) . " kg</strong></p></div>";
        $html .= "</div></body></html>";
        
        $result = sendEmail($alertEmail, $subject, $html);
        logEmailAlert('contract_delivery', $alertEmail, $subject, strip_tags($html), 'sent', 'contract', $contractId);
        return $result;
    } catch (Exception $e) {
        error_log('Contract delivery notification error: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────
// API ENDPOINT — handle HTTP requests
// ─────────────────────────────────────────────────────────────
if (isset($_GET['action']) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_low_stock':
            $results = checkAndSendLowStockAlerts();
            echo json_encode(['success' => true, 'alerts_sent' => count($results)]);
            break;
            
        case 'send_order_notification':
            $orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
            $status = $_POST['status'] ?? 'placed';
            if ($orderId > 0) {
                $sent = sendOrderNotification($orderId, $status);
                echo json_encode(['success' => $sent]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            }
            break;
            
        case 'send_contract_notification':
            $contractId = (int)($_POST['contract_id'] ?? $_GET['contract_id'] ?? 0);
            $deliveryId = (int)($_POST['delivery_id'] ?? $_GET['delivery_id'] ?? 0);
            if ($contractId > 0 && $deliveryId > 0) {
                $sent = sendContractDeliveryNotification($contractId, $deliveryId);
                echo json_encode(['success' => $sent]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
            }
            break;
            
        case 'test_email':
            $alertEmail = getSmtpConfig()['alert_email'] ?? 'accounts@kindcommoditiesltd.com';
            $sent = sendEmail($alertEmail, 'Test Email — Kind Commodities', '<h2>✅ Email System Working</h2><p>This is a test email from Kind Commodities Ltd admin system.</p><p>Sent at: ' . date('d M Y H:i:s') . '</p>');
            logEmailAlert('test', $alertEmail, 'Test Email', 'Test email sent', $sent ? 'sent' : 'failed');
            echo json_encode(['success' => $sent]);
            break;
            
        case 'alert_log':
            $pdo = getDatabaseConnection();
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $logs = [];
            if ($pdo) {
                try {
                    $logs = $pdo->query("SELECT * FROM email_alerts_log ORDER BY created_at DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
            }
            echo json_encode(['success' => true, 'data' => $logs]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}
