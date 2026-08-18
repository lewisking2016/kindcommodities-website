<?php
/**
 * One-time fix: add missing columns to suppliers table + seed data
 * DELETE after running: rm Backend/fix_suppliers.php
 */
declare(strict_types=1);
$key = $_GET['key'] ?? '';
if ($key !== 'fixnow2026') { http_response_code(403); echo "Access denied"; exit; }

require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();
if (!$pdo) { echo "DB connection failed"; exit; }

echo "<h2>Fixing suppliers table...</h2>";

$fixes = [
    "ALTER TABLE suppliers ADD COLUMN contact_person VARCHAR(100) NULL AFTER supplier_name",
    "ALTER TABLE suppliers ADD COLUMN phone VARCHAR(20) NULL AFTER contact_person",
    "ALTER TABLE suppliers ADD COLUMN email VARCHAR(100) NULL AFTER phone",
    "ALTER TABLE suppliers ADD COLUMN address TEXT NULL AFTER email",
    "ALTER TABLE suppliers ADD COLUMN location VARCHAR(100) NULL AFTER address",
    "ALTER TABLE suppliers ADD COLUMN payment_terms VARCHAR(100) DEFAULT 'Cash on Delivery' AFTER location",
    "ALTER TABLE suppliers ADD COLUMN rating TINYINT DEFAULT 5 AFTER payment_terms",
    "ALTER TABLE suppliers ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER rating",
    "ALTER TABLE suppliers ADD COLUMN notes TEXT NULL AFTER is_active",
];

foreach ($fixes as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div style='background:#D3E8B8;padding:4px 8px;margin:2px 0;'>✅ " . substr($sql, 0, 60) . "...</div>";
    } catch (Exception $e) {
        echo "<div style='background:#fef3c7;padding:4px 8px;margin:2px 0;'>⚠️ " . substr($e->getMessage(), 0, 80) . "</div>";
    }
}

echo "<h2>Seeding suppliers...</h2>";
$count = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
if ($count === 0) {
    try {
        $pdo->exec("INSERT INTO suppliers (supplier_name, contact_person, phone, location, payment_terms, rating, is_active) VALUES 
            ('Nakuru Grain Merchants', 'John Kamau', '+254 722 000 001', 'Nakuru', '30 days credit', 5, 1),
            ('Eldoret Wheat Farmers Co-op', 'Mary Chebet', '+254 733 000 002', 'Eldoret', 'Cash on Delivery', 4, 1),
            ('Bungoma Beans Supply', 'Peter Wanjala', '+254 712 000 003', 'Bungoma', '14 days credit', 4, 1),
            ('Kisumu Oil Mills', 'Alice Atieno', '+254 700 000 004', 'Kisumu', 'Cash on Delivery', 5, 1)");
        echo "<div style='background:#D3E8B8;padding:8px;margin:4px 0;'>✅ Inserted 4 suppliers</div>";
    } catch (Exception $e) {
        echo "<div style='background:#fee2e2;padding:8px;margin:4px 0;color:#b91c1c;'>❌ " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div style='background:#D3E8B8;padding:8px;margin:4px 0;'>✅ Suppliers already exist ({$count} rows)</div>";
}

echo "<h2>Result</h2>";
$count = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
echo "<p>Suppliers: <strong>{$count}</strong></p>";
echo "<p><a href='/Frontend/admin/suppliers.php'>→ Go to Suppliers</a></p>";
echo "<p style='color:#64748b;font-size:0.8rem;'>Delete this file: <code>rm Backend/fix_suppliers.php</code></p>";
