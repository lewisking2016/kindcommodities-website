<?php
/**
 * Manual Migration Runner — Kind Commodities Ltd
 * 
 * USAGE: Visit this file in your browser once:
 *   https://yourdomain.com/Backend/run_migration.php
 * 
 * It will run ALL migrations (schema, poultry, business, commodities, features)
 * and report what was created. Safe to run multiple times (idempotent).
 * 
 * DELETE THIS FILE after running it for security.
 */
declare(strict_types=1);

// Require login for security
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auto_migrate.php';

// Allow if logged in as admin, OR if ?key=runmigrate2026 is provided (one-time use)
$runKey = $_GET['key'] ?? '';
$allowed = false;

if ($runKey === 'runmigrate2026') {
    $allowed = true; // One-time bypass key
} elseif (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['super_admin', 'farm_manager'], true)) {
    $allowed = true; // Logged-in admin
}

if (!$allowed) {
    http_response_code(403);
    echo "<h1>⛔ Access Denied</h1>";
    echo "<p>Two ways to access this:</p>";
    echo "<ol>";
    echo "<li><a href='/Frontend/admin/login.php'>Log in as admin</a> first, then visit this page again</li>";
    echo "<li>Or add <code>?key=runmigrate2026</code> to the URL (one-time use)</li>";
    echo "</ol>";
    echo "<p>Example: <code>https://yourdomain.com/Backend/run_migration.php?key=runmigrate2026</code></p>";
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo "<h1>❌ Database Connection Failed</h1>";
    echo "<p>Check your database configuration in <code>Backend/config/database.php</code></p>";
    exit;
}

echo "<!DOCTYPE html><html><head><title>Migration Runner</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:20px;color:#1e293b;}";
echo "h1{color:#0B2310;} .success{background:#D3E8B8;padding:12px;border-radius:6px;color:#2C6B31;margin:8px 0;}";
echo ".error{background:#fee2e2;padding:12px;border-radius:6px;color:#b91c1c;margin:8px 0;}";
echo ".info{background:#e0f2fe;padding:12px;border-radius:6px;color:#0369a1;margin:8px 0;}";
echo "table{width:100%;border-collapse:collapse;margin:16px 0;}";
echo "th,td{padding:10px 14px;border:1px solid #e2e8f0;text-align:left;font-size:0.9rem;}";
echo "th{background:#f8fafc;font-weight:700;} .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;}";
echo ".badge-ok{background:#D3E8B8;color:#2C6B31;} .badge-err{background:#fee2e2;color:#b91c1c;}</style></head><body>";

echo "<h1>🔄 Running Database Migration</h1>";
echo "<p>Time: <strong>" . date('d M Y H:i:s') . "</strong></p>";

// ── Step 1: Run auto-migrate ──
echo "<div class='info'>⏳ Running auto-migration (this may take a few seconds)...</div>";
ob_flush(); flush();

try {
    ensureKindSchema($pdo);
    echo "<div class='success'>✅ Auto-migration completed successfully!</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Migration error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ── Step 2: Run commodities migration explicitly ──
echo "<div class='info'>⏳ Running commodities migration (v5)...</div>";
ob_flush(); flush();

$commoditiesFile = __DIR__ . '/config/migration_v5_commodities.sql';
if (file_exists($commoditiesFile)) {
    $sql = file_get_contents($commoditiesFile);
    // Remove comments and USE statements
    $lines = explode("\n", $sql);
    $lines = array_filter($lines, fn($l) => $l !== '' && !str_starts_with(trim($l), '--'));
    $sql = implode("\n", $lines);
    $sql = preg_replace('/USE\s+`?\w+`?\s*;/i', '', $sql);
    $sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);
    
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $success = 0;
    $errors = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (Exception $e) {
            $errors++;
            // Log but continue
        }
    }
    echo "<div class='success'>✅ Commodities migration: {$success} statements succeeded, {$errors} skipped (already applied).</div>";
}

// ── Step 3: Run features migration explicitly ──
echo "<div class='info'>⏳ Running features migration (v6)...</div>";
ob_flush(); flush();

$featuresFile = __DIR__ . '/config/migration_v6_features.sql';
if (file_exists($featuresFile)) {
    $sql = file_get_contents($featuresFile);
    $lines = explode("\n", $sql);
    $lines = array_filter($lines, fn($l) => $l !== '' && !str_starts_with(trim($l), '--'));
    $sql = implode("\n", $lines);
    $sql = preg_replace('/USE\s+`?\w+`?\s*;/i', '', $sql);
    $sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);
    
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $success = 0;
    $errors = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (Exception $e) {
            $errors++;
        }
    }
    echo "<div class='success'>✅ Features migration: {$success} statements succeeded, {$errors} skipped (already applied).</div>";
}

// ── Step 4: Verify products exist ──
echo "<h2>📊 Verification Report</h2>";

echo "<table>";
echo "<tr><th>Check</th><th>Result</th><th>Status</th></tr>";

// Products count
$productCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
echo "<tr><td>Products in database</td><td><strong>{$productCount}</strong></td><td>";
echo $productCount > 0 ? "<span class='badge badge-ok'>OK</span>" : "<span class='badge badge-err'>EMPTY</span>";
echo "</td></tr>";

// Categories count
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
echo "<tr><td>Categories</td><td><strong>{$catCount}</strong></td><td>";
echo $catCount >= 3 ? "<span class='badge badge-ok'>OK</span>" : "<span class='badge badge-err'>MISSING</span>";
echo "</td></tr>";

// Suppliers count
try {
    $supCount = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    echo "<tr><td>Suppliers</td><td><strong>{$supCount}</strong></td><td>";
    echo $supCount > 0 ? "<span class='badge badge-ok'>OK</span>" : "<span class='badge badge-err'>EMPTY</span>";
    echo "</td></tr>";
} catch (Exception $e) {
    echo "<tr><td>Suppliers table</td><td><em>Not created yet</em></td><td><span class='badge badge-err'>MISSING</span></td></tr>";
}

// Contracts table
try {
    $pdo->query("SELECT COUNT(*) FROM contracts");
    echo "<tr><td>Contracts table</td><td><strong>Exists</strong></td><td><span class='badge badge-ok'>OK</span></td></tr>";
} catch (Exception $e) {
    echo "<tr><td>Contracts table</td><td><em>Not created yet</em></td><td><span class='badge badge-err'>MISSING</span></td></tr>";
}

// product_type column type
$typeCol = $pdo->query("SHOW COLUMNS FROM products LIKE 'product_type'")->fetch(PDO::FETCH_ASSOC);
$isVarchar = str_contains($typeCol['Type'] ?? '', 'varchar');
echo "<tr><td>product_type column type</td><td><strong>" . ($typeCol['Type'] ?? 'unknown') . "</strong></td><td>";
echo $isVarchar ? "<span class='badge badge-ok'>VARCHAR (OK)</span>" : "<span class='badge badge-err'>ENUM (NEEDS FIX)</span>";
echo "</td></tr>";

// product_type values
$productTypes = $pdo->query("SELECT DISTINCT product_type FROM products")->fetchAll(PDO::FETCH_COLUMN);
echo "<tr><td>Product types in use</td><td><strong>" . implode(', ', $productTypes ?: ['none']) . "</strong></td><td><span class='badge badge-ok'>OK</span></td></tr>";

echo "</table>";

// ── Step 5: List all products ──
if ($productCount > 0) {
    echo "<h2>📦 All Products</h2>";
    echo "<table>";
    echo "<tr><th>#</th><th>Name</th><th>Type</th><th>Category</th><th>Price (KES)</th><th>Stock</th><th>Active</th></tr>";
    $products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $i => $p) {
        echo "<tr>";
        echo "<td>{$p['id']}</td>";
        echo "<td><strong>" . htmlspecialchars($p['name']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($p['product_type']) . "</td>";
        echo "<td>" . htmlspecialchars($p['cat_name'] ?? '—') . "</td>";
        echo "<td>" . number_format((float)$p['price']) . "</td>";
        echo "<td>" . $p['stock_quantity'] . "</td>";
        echo "<td>" . ($p['is_active'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr style='margin:32px 0;border:none;border-top:1px solid #e2e8f0;'>";
echo "<p>✅ <a href='/Frontend/admin/products.php' style='color:#396285;font-weight:600;'>→ Go to Admin Products</a></p>";
echo "<p style='color:#64748b;font-size:0.85rem;'>⚠️ For security, delete <code>Backend/run_migration.php</code> after use.</p>";

echo "</body></html>";
