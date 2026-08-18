<?php
/**
 * Quick Setup — Lightweight table creation + data seeding
 * 
 * USAGE: https://yourdomain.com/Backend/quick_setup.php?key=kind2026setup
 * 
 * This does NOT run the full auto_migrate — just creates the essential
 * tables and seeds data. Much lighter on shared hosting.
 * 
 * DELETE THIS FILE after running.
 */
declare(strict_types=1);

$key = $_GET['key'] ?? '';
if ($key !== 'kind2026setup') {
    http_response_code(403);
    echo "Access denied. Use ?key=kind2026setup";
    exit;
}

require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();
if (!$pdo) { echo "DB connection failed"; exit; }

echo "<!DOCTYPE html><html><head><title>Quick Setup</title>";
echo "<style>body{font-family:Arial;max-width:700px;margin:40px auto;padding:20px;}";
echo ".ok{background:#D3E8B8;padding:8px 12px;margin:4px 0;border-radius:4px;}";
echo ".err{background:#fee2e2;padding:8px 12px;margin:4px 0;border-radius:4px;color:#b91c1c;}";
echo "h2{margin-top:24px;} table{width:100%;border-collapse:collapse;margin:12px 0;}";
echo "th,td{padding:8px 12px;border:1px solid #e2e8f0;text-align:left;font-size:0.85rem;}";
echo "th{background:#f8fafc;}</style></head><body>";
echo "<h1>⚡ Quick Setup v2</h1>";

function run($pdo, $label, $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='ok'>✅ {$label}</div>";
        return true;
    } catch (Exception $e) {
        echo "<div class='err'>⚠️ {$label}: " . $e->getMessage() . "</div>";
        return false;
    }
}

function addColumnIfMissing($pdo, $table, $column, $definition) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        if (!$stmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$column} {$definition}");
            echo "<div class='ok'>✅ Added {$table}.{$column}</div>";
            return true;
        }
    } catch (Exception $e) {
        echo "<div class='err'>⚠️ Add {$table}.{$column}: " . $e->getMessage() . "</div>";
    }
    return false;
}

echo "<h2>0. Create site_settings table</h2>";
run($pdo, "site_settings table", "CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'text',
    description VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "<h2>1. Fix product_type column</h2>";
run($pdo, "ALTER product_type to VARCHAR", "ALTER TABLE products MODIFY COLUMN product_type VARCHAR(30) NOT NULL DEFAULT 'grain'");
run($pdo, "ALTER category_type to VARCHAR", "ALTER TABLE categories MODIFY COLUMN category_type VARCHAR(30) NOT NULL DEFAULT 'feed'");

echo "<h2>2. Create suppliers tables</h2>";
run($pdo, "suppliers table", "CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    location VARCHAR(100),
    payment_terms VARCHAR(100) DEFAULT 'Cash on Delivery',
    rating TINYINT DEFAULT 5,
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
// Add missing columns to existing suppliers table
addColumnIfMissing($pdo, 'suppliers', 'contact_person', "VARCHAR(100) NULL");
addColumnIfMissing($pdo, 'suppliers', 'address', "TEXT NULL");
addColumnIfMissing($pdo, 'suppliers', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

run($pdo, "supplier_deliveries table", "CREATE TABLE IF NOT EXISTS supplier_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    product_id INT,
    delivery_date DATE NOT NULL,
    quantity_kg DECIMAL(12,3) DEFAULT 0,
    bags_count INT DEFAULT 0,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    total_cost DECIMAL(12,2) DEFAULT 0,
    moisture_pct DECIMAL(5,2) DEFAULT NULL,
    grade VARCHAR(20) DEFAULT NULL,
    quality_notes TEXT,
    invoice_number VARCHAR(50),
    payment_status ENUM('pending','partial','paid') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT 'cash',
    recorded_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "<h2>3. Create contracts tables</h2>";
run($pdo, "contracts table", "CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(30) NOT NULL UNIQUE,
    contract_type ENUM('purchase','sale') NOT NULL,
    party_name VARCHAR(150) NOT NULL,
    party_phone VARCHAR(20),
    party_email VARCHAR(100),
    party_type ENUM('grower','customer','broker','other') DEFAULT 'customer',
    product_id INT,
    commodity_name VARCHAR(100) NOT NULL,
    quantity_kg DECIMAL(12,3) DEFAULT 0,
    delivered_kg DECIMAL(12,3) DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0,
    total_value DECIMAL(14,2) DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'KES',
    contract_date DATE NOT NULL,
    delivery_start DATE,
    delivery_end DATE,
    delivery_location VARCHAR(200),
    payment_terms VARCHAR(200) DEFAULT 'Cash on Delivery',
    quality_specs TEXT,
    status ENUM('draft','active','fulfilled','cancelled','expired') DEFAULT 'draft',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
addColumnIfMissing($pdo, 'contracts', 'party_email', "VARCHAR(100) NULL");
addColumnIfMissing($pdo, 'contracts', 'currency', "VARCHAR(5) DEFAULT 'KES'");

run($pdo, "contract_deliveries table", "CREATE TABLE IF NOT EXISTS contract_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    quantity_kg DECIMAL(12,3) DEFAULT 0,
    bags_count INT DEFAULT 0,
    moisture_pct DECIMAL(5,2) DEFAULT NULL,
    grade VARCHAR(20) DEFAULT NULL,
    vehicle_plate VARCHAR(20),
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    waybill_number VARCHAR(50),
    quality_notes TEXT,
    received_by VARCHAR(100),
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "<h2>4. Create email alerts table</h2>";
run($pdo, "email_alerts_log table", "CREATE TABLE IF NOT EXISTS email_alerts_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT,
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    related_type VARCHAR(50),
    related_id INT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "<h2>5. Create warehouse tables</h2>";
run($pdo, "warehouse_locations table", "CREATE TABLE IF NOT EXISTS warehouse_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(200),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
run($pdo, "product_stock_locations table", "CREATE TABLE IF NOT EXISTS product_stock_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity_bags INT DEFAULT 0,
    quantity_kg DECIMAL(12,3) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_location (product_id, location_id)
) ENGINE=InnoDB");
run($pdo, "stock_movements table", "CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT,
    movement_type ENUM('in','out','transfer','adjustment') NOT NULL,
    quantity_kg DECIMAL(12,3) DEFAULT 0,
    notes TEXT,
    recorded_by INT,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

echo "<h2>6. Seed suppliers</h2>";
$supCount = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
if ($supCount === 0) {
    run($pdo, "Insert 4 suppliers", "INSERT INTO suppliers (supplier_name, contact_person, phone, location, payment_terms, rating, is_active) VALUES 
        ('Nakuru Grain Merchants', 'John Kamau', '+254 722 000 001', 'Nakuru', '30 days credit', 5, 1),
        ('Eldoret Wheat Farmers Co-op', 'Mary Chebet', '+254 733 000 002', 'Eldoret', 'Cash on Delivery', 4, 1),
        ('Bungoma Beans Supply', 'Peter Wanjala', '+254 712 000 003', 'Bungoma', '14 days credit', 4, 1),
        ('Kisumu Oil Mills', 'Alice Atieno', '+254 700 000 004', 'Kisumu', 'Cash on Delivery', 5, 1)");
} else {
    echo "<div class='ok'>✅ Suppliers already exist ({$supCount} rows)</div>";
}

echo "<h2>7. Seed products</h2>";
$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
if ($prodCount === 0) {
    run($pdo, "Insert products", file_get_contents(__DIR__ . '/config/migration_v5_commodities.sql'));
} else {
    echo "<div class='ok'>✅ Products already exist ({$prodCount} rows)</div>";
}

echo "<h2>8. Seed site settings</h2>";
$settings = [
    ['farm_name', 'Kind Commodities Ltd'],
    ['farm_email', 'info@kindcommoditiesltd.com'],
    ['farm_phone', '+254 700 000 000'],
    ['farm_address', 'Kenya'],
    ['currency', 'KES'],
    ['header_logo', '/Frontend/images/header logo.jpeg'],
    ['footer_logo', '/Frontend/images/Kind Commodities Ltd Logo_Final_FOOTER.png'],
    ['favicon', '/Frontend/images/Kind Commodities Ltd Logo_Final_favicon.png'],
    ['smtp_host', 'kindcommoditiesltd.com'],
    ['smtp_port', '465'],
    ['smtp_username', 'accounts@kindcommoditiesltd.com'],
    ['smtp_password', ''],
    ['smtp_from_email', 'accounts@kindcommoditiesltd.com'],
    ['smtp_from_name', 'Kind Commodities Ltd'],
    ['smtp_encryption', 'ssl'],
    ['alert_email', 'accounts@kindcommoditiesltd.com'],
    ['low_stock_alert_enabled', '1'],
    ['order_notification_enabled', '1'],
    ['weekly_report_enabled', '0'],
    ['contract_expiry_alert_days', '7'],
    ['migration_v6_completed', '1'],
];
$ins = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
$seeded = 0;
foreach ($settings as [$key, $value]) {
    try {
        $ins->execute([$key, $value]);
        $seeded++;
    } catch (Exception $e) {
        // Ignore duplicate
    }
}
echo "<div class='ok'>✅ Seeded {$seeded} site settings</div>";

echo "<h2>📊 Final Report</h2>";
$tables = ['products', 'categories', 'suppliers', 'contracts', 'contract_deliveries', 'email_alerts_log', 'warehouse_locations', 'site_settings'];
echo "<table><tr><th>Table</th><th>Rows</th></tr>";
foreach ($tables as $t) {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo "<tr><td>{$t}</td><td>{$count}</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>{$t}</td><td style='color:red;'>ERROR</td></tr>";
    }
}
echo "</table>";

echo "<h2>📦 All Products</h2>";
$products = $pdo->query("SELECT id, name, product_type, price, stock_quantity FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>#</th><th>Name</th><th>Type</th><th>Price</th><th>Stock</th></tr>";
foreach ($products as $p) {
    echo "<tr><td>{$p['id']}</td><td>" . htmlspecialchars($p['name']) . "</td><td>{$p['product_type']}</td><td>KES " . number_format($p['price']) . "</td><td>{$p['stock_quantity']}</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<p>✅ <a href='/Frontend/admin/dashboard.php'>→ Go to Admin Dashboard</a></p>";
echo "<p style='color:#64748b;font-size:0.8rem;'>⚠️ Delete this file after use: <code>rm Backend/quick_setup.php</code></p>";
echo "</body></html>";
