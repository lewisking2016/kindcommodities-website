<?php
/**
 * Admin - Product Management (Full CRUD)
 * Clean SaaS Minimalist Design
 */
declare(strict_types=1);

$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) {
    session_save_path($temp_dir);
}
session_start();

$path_prefix = '../../';
$page_title = 'Manage Products - Admin';

include __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../../Backend/api/dropdowns.php';

// Check admin access
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin','farm_manager','sales_staff'], true)) {
    echo "<script>window.location.href = '/kindadmin';</script>";
    exit;
}

$pdo = getDB();
$success_message = '';
$error_message = '';
$csrf_token = function_exists('generateCSRFToken') ? generateCSRFToken() : ($_SESSION['csrf_token'] ?? '');

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Security token expired. Please refresh and try again.';
    } else {
    $action = $_POST['action'] ?? '';

    // Image Upload Helper
    $handleImageUpload = function($file) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Image must be 5MB or smaller.');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']) ?: '';
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('Only JPG, PNG, WEBP, and GIF images are allowed.');
        }

        $target_dir = __DIR__ . '/../images/products/';
        if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext === '') {
            throw new RuntimeException('Uploaded image must have a file extension.');
        }

        $new_filename = uniqid('prod_', true) . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return '/Frontend/images/products/' . $new_filename;
        }
        throw new RuntimeException('Unable to save uploaded image.');
    };

    if ($action === 'add_product') {
        try {
            $image_url = $handleImageUpload($_FILES['product_image'] ?? null);
            $raw_material_id = !empty($_POST['raw_material_id']) ? (int)$_POST['raw_material_id'] : null;
            $reserved_production_kg = (float)($_POST['reserved_production_kg'] ?? 0);

            $stmt = $pdo->prepare("INSERT INTO products (category_id, raw_material_id, name, slug, product_type, price, stock_quantity, description, image_url, is_active, stock_weight_kg, unit_weight_kg, price_per_kg, moisture_pct, grade, foreign_material_pct, origin, low_stock_threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)");
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['name'])));
            $stmt->execute([
                (int)$_POST['category_id'],
                $raw_material_id,
                trim($_POST['name']),
                $slug,
                $_POST['product_type'],
                (float)$_POST['price'],
                (int)$_POST['stock_quantity'],
                trim($_POST['description'] ?? ''),
                $image_url,
                !empty($_POST['stock_weight_kg']) ? (float)$_POST['stock_weight_kg'] : null,
                !empty($_POST['unit_weight_kg']) ? (float)$_POST['unit_weight_kg'] : null,
                !empty($_POST['price_per_kg']) ? (float)$_POST['price_per_kg'] : null,
                !empty($_POST['moisture_pct']) ? (float)$_POST['moisture_pct'] : null,
                trim($_POST['grade'] ?? '') ?: null,
                !empty($_POST['foreign_material_pct']) ? (float)$_POST['foreign_material_pct'] : null,
                trim($_POST['origin'] ?? '') ?: null,
                (int)($_POST['low_stock_threshold'] ?? 10)
            ]);

            if ($raw_material_id) {
                execute($pdo, "UPDATE raw_materials SET reserved_production_kg = ? WHERE id = ?", [$reserved_production_kg, $raw_material_id]);
            }

            $success_message = 'Product added successfully.';
            logActivity($pdo, 'add', 'products', "Added product: " . trim($_POST['name'] ?? ''), (int)$pdo->lastInsertId(), 'product');
        } catch (Exception $e) {
            $error_message = 'Failed to add product: ' . $e->getMessage();
        }
    }

    if ($action === 'edit_product') {
        try {
            $image_url = $handleImageUpload($_FILES['product_image'] ?? null);
            $raw_material_id = !empty($_POST['raw_material_id']) ? (int)$_POST['raw_material_id'] : null;
            $reserved_production_kg = (float)($_POST['reserved_production_kg'] ?? 0);

            $sql = "UPDATE products SET name = ?, product_type = ?, price = ?, stock_quantity = ?, description = ?, category_id = ?, raw_material_id = ?, stock_weight_kg = ?, unit_weight_kg = ?, price_per_kg = ?, moisture_pct = ?, grade = ?, foreign_material_pct = ?, origin = ?, low_stock_threshold = ?";
            $params = [
                trim($_POST['name']),
                $_POST['product_type'],
                (float)$_POST['price'],
                (int)$_POST['stock_quantity'],
                trim($_POST['description'] ?? ''),
                (int)$_POST['category_id'],
                $raw_material_id,
                !empty($_POST['stock_weight_kg']) ? (float)$_POST['stock_weight_kg'] : null,
                !empty($_POST['unit_weight_kg']) ? (float)$_POST['unit_weight_kg'] : null,
                !empty($_POST['price_per_kg']) ? (float)$_POST['price_per_kg'] : null,
                !empty($_POST['moisture_pct']) ? (float)$_POST['moisture_pct'] : null,
                trim($_POST['grade'] ?? '') ?: null,
                !empty($_POST['foreign_material_pct']) ? (float)$_POST['foreign_material_pct'] : null,
                trim($_POST['origin'] ?? '') ?: null,
                (int)($_POST['low_stock_threshold'] ?? 10)
            ];
            
            if ($image_url) {
                $sql .= ", image_url = ?";
                $params[] = $image_url;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = (int)$_POST['product_id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($raw_material_id) {
                execute($pdo, "UPDATE raw_materials SET reserved_production_kg = ? WHERE id = ?", [$reserved_production_kg, $raw_material_id]);
            }

            $success_message = 'Product updated successfully.';
            logActivity($pdo, 'update', 'products', "Updated product: " . trim($_POST['name'] ?? ''), (int)($_POST['product_id'] ?? 0), 'product');
        } catch (Exception $e) {
            $error_message = 'Failed to update product: ' . $e->getMessage();
        }
    }

    if ($action === 'delete_product') {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([(int)$_POST['product_id']]);
            $success_message = 'Product deleted successfully.';
            logActivity($pdo, 'delete', 'products', "Deleted product #" . (int)$_POST['product_id'], (int)$_POST['product_id'], 'product');
        } catch (Exception $e) {
            $error_message = 'Failed to delete product: ' . $e->getMessage();
        }
    }

    if ($action === 'toggle_status') {
        try {
            $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([(int)$_POST['product_id']]);
            $success_message = 'Product status toggled.';
            logActivity($pdo, 'update', 'products', "Toggled visibility of product #" . (int)$_POST['product_id'], (int)$_POST['product_id'], 'product');
        } catch (Exception $e) {
            $error_message = 'Failed to toggle status.';
        }
    }
    }
}

// --- Fetch products with search/filter ---
$products = [];
$categories = [];
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

$raw_materials_list = [];
if ($pdo) {
    try {
        // Sync system_dropdowns product_categories into categories table
        $missingCats = safeQueryAll($pdo,
            "SELECT sd.option_value AS slug, sd.option_label AS name
            FROM system_dropdowns sd
            LEFT JOIN categories c ON c.slug COLLATE utf8mb4_unicode_ci = sd.option_value COLLATE utf8mb4_unicode_ci
            WHERE sd.group_key = 'product_categories' AND c.id IS NULL"
        );
        if (!empty($missingCats)) {
            $insertCat = $pdo->prepare("INSERT INTO categories (name, slug, category_type, description) VALUES (?, ?, 'chicken', '')");
            foreach ($missingCats as $mc) {
                $insertCat->execute([$mc['name'], $mc['slug']]);
            }
        }
    } catch (Exception $e) {
        error_log("Admin products category sync error: " . $e->getMessage());
    }

    try {
        // Fetch categories for dropdowns
        $categories = safeQueryAll($pdo,
            "SELECT c.id, sd.option_label AS name, sd.option_value AS slug 
            FROM system_dropdowns sd
            JOIN categories c ON c.slug COLLATE utf8mb4_unicode_ci = sd.option_value COLLATE utf8mb4_unicode_ci
            WHERE sd.group_key = 'product_categories' AND sd.is_active = 1
            ORDER BY sd.sort_order ASC, sd.option_label ASC"
        );
    } catch (Exception $e) {
        error_log('Admin products categories load error: ' . $e->getMessage());
        $categories = [];
    }

    try {
        $raw_materials_list = safeQueryAll($pdo, "SELECT id, name, reserved_production_kg, stock_tons FROM raw_materials ORDER BY name");
    } catch (Exception $e) {
        error_log('Admin products raw materials load error: ' . $e->getMessage());
        $raw_materials_list = [];
    }

    try {
        // Build product query
        $query = "SELECT p.*, c.name as category_name, rm.name as linked_raw_material_name, rm.reserved_production_kg, rm.stock_tons as raw_material_stock
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  LEFT JOIN raw_materials rm ON p.raw_material_id = rm.id
                  WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $query .= " AND p.name LIKE ?";
            $params[] = "%$search%";
        }
        if (!empty($type_filter)) {
            $query .= " AND p.product_type = ?";
            $params[] = $type_filter;
        }

        $query .= " ORDER BY p.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Admin products query error: " . $e->getMessage());
        $error_message = "Database query error: " . $e->getMessage();
        $products = [];
    }
}
?>

<style>
/* â”€â”€â”€ Products Catalog Premium Design â”€â”€â”€ */
.pc-hero {
    background: linear-gradient(135deg, #0B2310 0%, #1B5E20 60%, #2E7D32 100%);
    border-radius: 16px;
    padding: 32px 36px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
}
.pc-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 220px; height: 220px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.pc-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: 40%;
    width: 280px; height: 280px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}
.pc-hero-text h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.9rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    letter-spacing: -0.5px;
}
.pc-hero-text p {
    color: rgba(255,255,255,0.72);
    margin: 0;
    font-size: 0.95rem;
}
.pc-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    position: relative;
    z-index: 1;
}
.pc-btn-white {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: #1B5E20;
    padding: 11px 22px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
    text-decoration: none;
}
.pc-btn-white:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.pc-btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.12);
    color: #fff;
    padding: 11px 22px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.2);
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.pc-btn-ghost:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}
/* Stats row */
.pc-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.pc-stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 12px rgba(15,23,42,0.04);
    transition: all 0.2s ease;
}
.pc-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15,23,42,0.08);
}
.pc-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.pc-stat-info small {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
}
.pc-stat-info strong {
    display: block;
    font-family: 'Outfit', sans-serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    margin-top: 2px;
    line-height: 1;
}
/* Search & Filter */
.pc-filter-bar {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15,23,42,0.03);
}
.pc-search-input {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 180px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
}
.pc-search-input input {
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.9rem;
    width: 100%;
    color: #1e293b;
}
.pc-search-input input::placeholder { color: #94a3b8; }
/* Table card */
.pc-table-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(15,23,42,0.04);
    margin-bottom: 28px;
}
.pc-table-head th {
    padding: 14px 20px;
    background: #f8fafc;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.pc-table-body td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.9rem;
    color: #334155;
}
.pc-table-body tr:last-child td { border-bottom: none; }
.pc-table-body tr:hover td { background: #fafbfc; }
.pc-product-thumb {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, #e8f5e9 0%, #f0fdf4 100%);
    border: 1px solid #d1fae5;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pc-product-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pc-product-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.92rem;
}
.pc-product-cat {
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 2px;
}
.pc-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}
.pc-badge-green { background: #dcfce7; color: #15803d; }
.pc-badge-red { background: #fee2e2; color: #b91c1c; }
.pc-badge-orange { background: #fff7ed; color: #c2410c; }
.pc-badge-blue { background: #dbeafe; color: #1d4ed8; }
.pc-badge-type {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}
.pc-price {
    font-family: 'Outfit', sans-serif;
    font-size: 1rem;
    font-weight: 800;
    color: #1B5E20;
}
.pc-stock-val {
    font-weight: 700;
    font-size: 0.92rem;
}
.pc-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.pc-action-btn:hover { transform: scale(1.1); }
.pc-action-edit {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.pc-action-edit:hover { background: #dcfce7; }
.pc-action-delete {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.pc-action-delete:hover { background: #fee2e2; }
/* Raw Material section */
.pc-rm-section {
    background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 28px;
}
.pc-rm-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}
.pc-rm-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
.pc-rm-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(15,23,42,0.04);
    display: flex;
    flex-direction: column;
    gap: 12px;
}
/* â”€â”€â”€ Premium Modal Design â”€â”€â”€ */
.pc-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 15, 30, 0.65);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 9999;
    overflow-y: auto;
    padding: 32px 16px;
}
.pc-modal {
    background: #fff;
    border-radius: 20px;
    width: 100%;
    max-width: 660px;
    margin: 0 auto;
    box-shadow: 0 32px 64px -12px rgba(0,0,0,0.3);
    overflow: hidden;
}
.pc-modal-header {
    background: linear-gradient(135deg, #0B2310 0%, #1B5E20 100%);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pc-modal-header h3 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.pc-modal-close {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.pc-modal-close:hover { background: rgba(255,255,255,0.25); transform: scale(1.1); }
.pc-modal-body {
    padding: 28px;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}
.pc-modal-body::-webkit-scrollbar { width: 5px; }
.pc-modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.pc-modal-footer {
    padding: 20px 28px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #fafafa;
}
.pc-form-group { margin-bottom: 18px; }
.pc-form-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pc-form-control {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.9rem;
    color: #1e293b;
    background: #f8fafc;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.pc-form-control:focus {
    border-color: #1B5E20;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.12);
}
.pc-form-section-title {
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #1B5E20;
    margin: 20px 0 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid #dcfce7;
    display: flex;
    align-items: center;
    gap: 7px;
}
.pc-image-upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
}
.pc-image-upload-zone:hover { border-color: #1B5E20; background: #f0fdf4; }
.pc-image-preview {
    width: 72px;
    height: 72px;
    border-radius: 10px;
    overflow: hidden;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    border: 2px solid #e2e8f0;
}
.pc-image-preview img { width: 100%; height: 100%; object-fit: cover; }
.pc-modal-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
    color: #fff;
    padding: 12px 28px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(27,94,32,0.3);
    transition: all 0.2s;
}
.pc-modal-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(27,94,32,0.4); }
.pc-modal-cancel {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    color: #475569;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;
}
.pc-modal-cancel:hover { background: #e2e8f0; }
@media (max-width: 640px) {
    .pc-stats-row { grid-template-columns: 1fr 1fr; }
    .pc-hero { padding: 22px; }
    .pc-hero-text h1 { font-size: 1.4rem; }
}
</style>

<?php if ($success_message): ?>
<div style="padding: 14px 20px; background: linear-gradient(135deg, #dcfce7, #f0fdf4); border: 1px solid #86efac; border-radius: 12px; color: #15803d; font-size: 0.9rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
    <i data-lucide="check-circle-2" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div style="padding: 14px 20px; background: linear-gradient(135deg, #fee2e2, #fef2f2); border: 1px solid #fca5a5; border-radius: 12px; color: #dc2626; font-size: 0.9rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600;">
    <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Hero Header -->
<div class="pc-hero">
    <div class="pc-hero-text" style="position: relative; z-index: 1;">
        <h1><i data-lucide="package" style="width:26px;height:26px;vertical-align:middle;margin-right:10px;opacity:0.9;"></i>Product Catalog</h1>
        <p>Manage your full product range â€” grains, raw materials, feed ingredients & more.</p>
    </div>
    <div class="pc-hero-actions">
        <a href="/Backend/api/export.php?module=products" class="pc-btn-ghost">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <button onclick="openModal('add-modal')" class="pc-btn-white">
            <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Add Product
        </button>
    </div>
</div>

<!-- Stats Row -->
<?php
$totalProducts   = count($products);
$activeProducts  = count(array_filter($products, fn($p) => $p['is_active']));
$lowStockCount   = count(array_filter($products, fn($p) => $p['stock_quantity'] < ($p['low_stock_threshold'] ?? 10)));
$totalStockValue = array_sum(array_map(fn($p) => (float)$p['price'] * (int)$p['stock_quantity'], $products));
?>
<div class="pc-stats-row">
    <div class="pc-stat-card">
        <div class="pc-stat-icon" style="background: linear-gradient(135deg, #dbeafe, #eff6ff);">
            <i data-lucide="package" style="width:22px;height:22px;color:#2563eb;"></i>
        </div>
        <div class="pc-stat-info">
            <small>Total Products</small>
            <strong><?= $totalProducts ?></strong>
        </div>
    </div>
    <div class="pc-stat-card">
        <div class="pc-stat-icon" style="background: linear-gradient(135deg, #dcfce7, #f0fdf4);">
            <i data-lucide="check-circle-2" style="width:22px;height:22px;color:#15803d;"></i>
        </div>
        <div class="pc-stat-info">
            <small>Active</small>
            <strong><?= $activeProducts ?></strong>
        </div>
    </div>
    <div class="pc-stat-card">
        <div class="pc-stat-icon" style="background: linear-gradient(135deg, #fef9c3, #fffde7);">
            <i data-lucide="alert-triangle" style="width:22px;height:22px;color:#d97706;"></i>
        </div>
        <div class="pc-stat-info">
            <small>Low Stock</small>
            <strong><?= $lowStockCount ?></strong>
        </div>
    </div>
    <div class="pc-stat-card">
        <div class="pc-stat-icon" style="background: linear-gradient(135deg, #fce7f3, #fdf2f8);">
            <i data-lucide="trending-up" style="width:22px;height:22px;color:#9d174d;"></i>
        </div>
        <div class="pc-stat-info">
            <small>Catalog Value</small>
            <strong style="font-size:1.1rem;">KES <?= number_format($totalStockValue / 1000, 0) ?>K</strong>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<form method="GET" class="pc-filter-bar">
    <div class="pc-search-input">
        <i data-lucide="search" style="width:18px;height:18px;color:#94a3b8;flex-shrink:0;"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products by name...">
    </div>
    <select name="type" class="pc-form-control" style="max-width:180px;padding:10px 14px;border-radius:8px;">
        <?php echo renderDropdownOptions('product_types', $type_filter, 'All Types'); ?>
    </select>
    <button type="submit" class="pc-modal-submit" style="padding:10px 20px;">
        <i data-lucide="filter" style="width:15px;height:15px;"></i> Filter
    </button>
    <?php if ($search || $type_filter): ?>
        <a href="products.php" class="pc-modal-cancel" style="text-decoration:none;">Clear</a>
    <?php endif; ?>
</form>

<!-- Products Table -->
<div class="pc-table-card">
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;">
            <thead class="pc-table-head">
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Quality</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody class="pc-table-body">
                <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="pc-product-thumb">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="">
                                <?php else: ?>
                                    <i data-lucide="package" style="width:20px;height:20px;color:#16a34a;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="pc-product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <?php if ($product['category_name']): ?>
                                    <div class="pc-product-cat"><?= htmlspecialchars($product['category_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="pc-badge-type"><?= ucfirst(str_replace('_', ' ', $product['product_type'] ?? 'General')) ?></span>
                    </td>
                    <td>
                        <div class="pc-price">KES <?= number_format((float)$product['price']) ?></div>
                        <?php if (!empty($product['price_per_kg'])): ?>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:2px;">KES <?= number_format((float)$product['price_per_kg'], 2) ?>/kg</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $isLow = $product['stock_quantity'] < ($product['low_stock_threshold'] ?? 10); ?>
                        <div class="pc-stock-val" style="color:<?= $isLow ? '#dc2626' : '#0f172a' ?>;">
                            <?= number_format($product['stock_quantity']) ?> bags
                        </div>
                        <?php if (!empty($product['stock_weight_kg'])): ?>
                            <div style="font-size:0.75rem;color:#64748b;margin-top:2px;"><?= number_format((float)$product['stock_weight_kg'], 0) ?> kg</div>
                        <?php endif; ?>
                        <?php if ($isLow): ?>
                            <span class="pc-badge pc-badge-orange" style="font-size:0.68rem;margin-top:3px;">Low Stock</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;color:#475569;">
                        <?php if (!empty($product['grade'])): ?><div><b>Grade:</b> <?= htmlspecialchars($product['grade']) ?></div><?php endif; ?>
                        <?php if (!empty($product['moisture_pct'])): ?><div>Moisture: <?= $product['moisture_pct'] ?>%</div><?php endif; ?>
                        <?php if (!empty($product['origin'])): ?><div>Origin: <?= htmlspecialchars($product['origin']) ?></div><?php endif; ?>
                        <?php if (empty($product['grade']) && empty($product['moisture_pct']) && empty($product['origin'])): ?><span style="color:#cbd5e1;">â€”</span><?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                                <?php if ($product['is_active']): ?>
                                    <span class="pc-badge pc-badge-green">â— Active</span>
                                <?php else: ?>
                                    <span class="pc-badge pc-badge-red">â— Inactive</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div style="display:flex;gap:8px;justify-content:flex-end;align-items:center;">
                            <button title="Edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($product)) ?>)" class="pc-action-btn pc-action-edit">
                                <i data-lucide="pencil" style="width:15px;height:15px;"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Permanently delete this product?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" title="Delete" class="pc-action-btn pc-action-delete">
                                    <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:60px 20px;">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:12px;color:#94a3b8;">
                            <i data-lucide="package-open" style="width:48px;height:48px;opacity:0.4;"></i>
                            <p style="margin:0;font-size:1rem;font-weight:600;">No products found</p>
                            <p style="margin:0;font-size:0.85rem;">Try adjusting your search or add your first product.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Raw Material Sales Section -->
<div class="pc-rm-section">
    <div class="pc-rm-header">
        <div class="pc-rm-icon">
            <i data-lucide="percent" style="width:20px;height:20px;"></i>
        </div>
        <div>
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1.1rem;color:#0f172a;font-weight:700;">Raw Material Sales & Protection Control</h3>
            <p style="margin:2px 0 0;font-size:0.82rem;color:#64748b;">Direct retail of ingredients while safeguarding production reserves.</p>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
        <?php 
        $linked_found = false;
        foreach ($products as $p):
            if (!empty($p['raw_material_id'])):
                $linked_found = true;
                $total   = (float)$p['raw_material_stock'];
                $reserve = (float)$p['reserved_production_kg'];
                $sellable = max(0.0, $total - $reserve);
                $fill_pct = $total > 0 ? min(100.0, ($reserve / $total) * 100) : 0;
        ?>
        <div class="pc-rm-card">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:8px;">
                <div>
                    <div style="font-weight:700;font-size:0.95rem;color:#0f172a;"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:0.78rem;color:#64748b;margin-top:2px;">Linked: <?= htmlspecialchars($p['linked_raw_material_name']) ?></div>
                </div>
                <span class="pc-badge pc-badge-green" style="font-size:0.7rem;white-space:nowrap;">Raw Material</span>
            </div>
            <div style="font-size:0.82rem;color:#64748b;">Selling: <strong style="color:#1B5E20;">KES <?= number_format($p['price'], 2) ?>/kg</strong></div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:#475569;margin-bottom:6px;">
                    <span>Reserve: <b><?= number_format($reserve) ?> kg</b></span>
                    <span>Sellable: <b><?= number_format($sellable) ?> kg</b></span>
                </div>
                <div style="width:100%;height:8px;background:#e2e8f0;border-radius:99px;overflow:hidden;display:flex;">
                    <div style="width:<?= $fill_pct ?>%;height:100%;background:linear-gradient(90deg,#6EAF44,#4CAF50);" title="Production Reserve"></div>
                    <div style="width:<?= 100 - $fill_pct ?>%;height:100%;background:linear-gradient(90deg,#1B5E20,#2E7D32);" title="Sellable"></div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f1f5f9;padding-top:12px;font-size:0.78rem;color:#64748b;">
                <span>Total: <b><?= number_format($total) ?> kg</b></span>
                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)" class="pc-btn-white" style="font-size:0.75rem;padding:6px 14px;border-radius:8px;color:#1B5E20;box-shadow:0 2px 8px rgba(27,94,32,0.15);">
                    Adjust Reserve
                </button>
            </div>
        </div>
        <?php
            endif;
        endforeach;
        if (!$linked_found):
        ?>
        <div style="grid-column:1/-1;text-align:center;padding:40px;background:#fff;border:1.5px dashed #cbd5e1;border-radius:12px;">
            <i data-lucide="link-off" style="width:32px;height:32px;color:#cbd5e1;margin-bottom:10px;"></i>
            <p style="margin:0;color:#64748b;font-weight:600;">No Raw Material products configured yet.</p>
            <p style="margin:6px 0 0;color:#94a3b8;font-size:0.85rem;">Edit a product and set "Link Raw Material" to enable direct sales control.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• ADD PRODUCT MODAL â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div id="add-modal" class="pc-modal-overlay" onclick="if(event.target===this)closeModal('add-modal')">
    <div class="pc-modal">
        <div class="pc-modal-header">
            <h3><i data-lucide="plus-circle" style="width:20px;height:20px;"></i> Add New Product</h3>
            <button class="pc-modal-close" onclick="closeModal('add-modal')">âœ•</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="add_product">
            <div class="pc-modal-body">
                <!-- Image Upload -->
                <div class="pc-form-group">
                    <label class="pc-form-label">Product Image</label>
                    <div class="pc-image-upload-zone" onclick="document.getElementById('add-image-input').click()">
                        <div id="add-preview" class="pc-image-preview">
                            <i data-lucide="image" style="width:26px;height:26px;color:#94a3b8;"></i>
                        </div>
                        <input type="file" id="add-image-input" name="product_image" accept="image/*" onchange="previewImage(this,'add-preview')" style="display:none;">
                        <p style="margin:0;font-size:0.82rem;color:#64748b;">Click to upload product image</p>
                        <p style="margin:4px 0 0;font-size:0.75rem;color:#94a3b8;">JPG, PNG, WEBP â€” max 5MB</p>
                    </div>
                </div>

                <!-- Core Info -->
                <div class="pc-form-section-title">
                    <i data-lucide="info" style="width:14px;height:14px;"></i> Core Information
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="pc-form-group" style="grid-column:span 2;">
                        <label class="pc-form-label">Product Name *</label>
                        <input type="text" name="name" required class="pc-form-control" placeholder="e.g. Premium Maize (Grade 1)">
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Category *</label>
                        <select name="category_id" required class="pc-form-control">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Product Type *</label>
                        <select name="product_type" required class="pc-form-control">
                            <?php echo renderDropdownOptions('product_types', null, '-- Select Type --'); ?>
                        </select>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Price (KES) *</label>
                        <input type="number" name="price" required min="0" step="0.01" class="pc-form-control" placeholder="0.00">
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Stock Quantity (bags) *</label>
                        <input type="number" name="stock_quantity" required min="0" class="pc-form-control" placeholder="0">
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Link Raw Material</label>
                        <select name="raw_material_id" class="pc-form-control">
                            <option value="">None</option>
                            <?php foreach ($raw_materials_list as $rm): ?>
                                <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Safety Production Floor (kg)</label>
                        <input type="number" name="reserved_production_kg" class="pc-form-control" min="0" value="0" step="0.01" placeholder="0">
                    </div>
                </div>
                <div class="pc-form-group">
                    <label class="pc-form-label">Description</label>
                    <textarea name="description" rows="3" class="pc-form-control" style="resize:vertical;" placeholder="Short description of the product..."></textarea>
                </div>

                <!-- Quality & Weight -->
                <div class="pc-form-section-title">
                    <i data-lucide="scale" style="width:14px;height:14px;"></i> Quality & Weight Details
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="pc-form-group"><label class="pc-form-label">Unit Weight (kg)</label><input type="number" name="unit_weight_kg" step="0.01" class="pc-form-control" placeholder="e.g. 90"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Stock Weight (kg)</label><input type="number" name="stock_weight_kg" step="0.001" class="pc-form-control" placeholder="Total kg in stock"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Price per kg (KES)</label><input type="number" name="price_per_kg" step="0.01" class="pc-form-control" placeholder="Auto-calculated"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Low Stock Threshold</label><input type="number" name="low_stock_threshold" value="10" min="0" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Moisture %</label><input type="number" name="moisture_pct" step="0.01" min="0" max="100" class="pc-form-control" placeholder="e.g. 13.5"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Grade</label><input name="grade" class="pc-form-control" placeholder="e.g. Grade 1, Premium"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Foreign Material %</label><input type="number" name="foreign_material_pct" step="0.01" min="0" max="100" class="pc-form-control" placeholder="e.g. 1.2"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Origin</label><input name="origin" class="pc-form-control" placeholder="e.g. Nakuru"></div>
                </div>
            </div>
            <div class="pc-modal-footer">
                <button type="button" class="pc-modal-cancel" onclick="closeModal('add-modal')">Cancel</button>
                <button type="submit" class="pc-modal-submit">
                    <i data-lucide="check-circle" style="width:16px;height:16px;"></i> Add Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• EDIT PRODUCT MODAL â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div id="edit-modal" class="pc-modal-overlay" onclick="if(event.target===this)closeModal('edit-modal')">
    <div class="pc-modal">
        <div class="pc-modal-header">
            <h3><i data-lucide="pencil" style="width:20px;height:20px;"></i> Edit Product</h3>
            <button class="pc-modal-close" onclick="closeModal('edit-modal')">âœ•</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="product_id" id="edit-id">
            <div class="pc-modal-body">
                <!-- Image -->
                <div class="pc-form-group">
                    <label class="pc-form-label">Update Product Image</label>
                    <div class="pc-image-upload-zone" onclick="document.getElementById('edit-image-input').click()">
                        <div id="edit-preview" class="pc-image-preview">
                            <i data-lucide="image" style="width:26px;height:26px;color:#94a3b8;"></i>
                        </div>
                        <input type="file" id="edit-image-input" name="product_image" accept="image/*" onchange="previewImage(this,'edit-preview')" style="display:none;">
                        <p style="margin:0;font-size:0.82rem;color:#64748b;">Click to change image</p>
                        <p style="margin:4px 0 0;font-size:0.75rem;color:#94a3b8;">Leave empty to keep current image</p>
                    </div>
                </div>

                <div class="pc-form-section-title"><i data-lucide="info" style="width:14px;height:14px;"></i> Core Information</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="pc-form-group" style="grid-column:span 2;">
                        <label class="pc-form-label">Product Name *</label>
                        <input type="text" name="name" id="edit-name" required class="pc-form-control">
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Category *</label>
                        <select name="category_id" id="edit-category" required class="pc-form-control">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Product Type *</label>
                        <select name="product_type" id="edit-type" required class="pc-form-control">
                            <?php echo renderDropdownOptions('product_types', null, '-- Select Type --'); ?>
                        </select>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Price (KES) *</label>
                        <input type="number" name="price" id="edit-price" required min="0" step="0.01" class="pc-form-control">
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Stock Quantity (bags) *</label>
                        <input type="number" name="stock_quantity" id="edit-stock" required min="0" class="pc-form-control">
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Link Raw Material</label>
                        <select name="raw_material_id" id="edit-raw-material-id" class="pc-form-control">
                            <option value="">None</option>
                            <?php foreach ($raw_materials_list as $rm): ?>
                                <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pc-form-group">
                        <label class="pc-form-label">Safety Production Floor (kg)</label>
                        <input type="number" name="reserved_production_kg" id="edit-reserved-production-kg" class="pc-form-control" min="0" step="0.01">
                    </div>
                </div>
                <div class="pc-form-group">
                    <label class="pc-form-label">Description</label>
                    <textarea name="description" id="edit-desc" rows="3" class="pc-form-control" style="resize:vertical;"></textarea>
                </div>

                <div class="pc-form-section-title"><i data-lucide="scale" style="width:14px;height:14px;"></i> Quality & Weight Details</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="pc-form-group"><label class="pc-form-label">Unit Weight (kg)</label><input type="number" name="unit_weight_kg" id="edit-unit-weight" step="0.01" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Stock Weight (kg)</label><input type="number" name="stock_weight_kg" id="edit-stock-weight" step="0.001" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Price per kg (KES)</label><input type="number" name="price_per_kg" id="edit-price-kg" step="0.01" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Low Stock Threshold</label><input type="number" name="low_stock_threshold" id="edit-low-stock" min="0" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Moisture %</label><input type="number" name="moisture_pct" id="edit-moisture" step="0.01" min="0" max="100" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Grade</label><input name="grade" id="edit-grade" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Foreign Material %</label><input type="number" name="foreign_material_pct" id="edit-foreign" step="0.01" min="0" max="100" class="pc-form-control"></div>
                    <div class="pc-form-group"><label class="pc-form-label">Origin</label><input name="origin" id="edit-origin" class="pc-form-control"></div>
                </div>
            </div>
            <div class="pc-modal-footer">
                <button type="button" class="pc-modal-cancel" onclick="closeModal('edit-modal')">Cancel</button>
                <button type="submit" class="pc-modal-submit">
                    <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'block';
    document.body.style.overflow = 'hidden';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = '';
}
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function openEditModal(product) {
    document.getElementById('edit-id').value             = product.id;
    document.getElementById('edit-name').value           = product.name;
    document.getElementById('edit-category').value       = product.category_id;
    document.getElementById('edit-type').value           = product.product_type;
    document.getElementById('edit-price').value          = product.price;
    document.getElementById('edit-stock').value          = product.stock_quantity;
    document.getElementById('edit-desc').value           = product.description || '';
    document.getElementById('edit-unit-weight').value    = product.unit_weight_kg || '';
    document.getElementById('edit-stock-weight').value   = product.stock_weight_kg || '';
    document.getElementById('edit-price-kg').value       = product.price_per_kg || '';
    document.getElementById('edit-low-stock').value      = product.low_stock_threshold || 10;
    document.getElementById('edit-moisture').value       = product.moisture_pct || '';
    document.getElementById('edit-grade').value          = product.grade || '';
    document.getElementById('edit-foreign').value        = product.foreign_material_pct || '';
    document.getElementById('edit-origin').value         = product.origin || '';
    document.getElementById('edit-raw-material-id').value            = product.raw_material_id || '';
    document.getElementById('edit-reserved-production-kg').value     = product.reserved_production_kg || 0;
    const preview = document.getElementById('edit-preview');
    if (product.image_url) {
        preview.innerHTML = `<img src="${product.image_url}" style="width:100%;height:100%;object-fit:cover;">`;
    } else {
        preview.innerHTML = `<i data-lucide="image" style="width:26px;height:26px;color:#94a3b8;"></i>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    openModal('edit-modal');
}
// Escape key to close modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('add-modal');
        closeModal('edit-modal');
    }
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
