<?php
/**
 * Auto-Migration — ensures all module tables exist on every connection.
 *
 * Safe to call repeatedly: CREATE TABLE IF NOT EXISTS makes re-runs no-ops,
 * and individual statement errors are skipped. The completeness guard below
 * re-runs a migration file whenever any of its tables is missing, so tables
 * added to the migration files later are created automatically.
 */
declare(strict_types=1);

/**
 * Split a migration .sql file into individual executable statements.
 *
 * Comment banner lines ("-- ...") are stripped BEFORE splitting. The old
 * approach split on ";" and then dropped chunks that STARTED with a comment —
 * which silently discarded every CREATE TABLE that followed a banner, leaving
 * tables like broiler_weighings / egg_losses permanently missing.
 */
function splitMigrationSql(string $sql): array
{
    // Remove full-line SQL comments (these files use "-- ..." banners)
    $lines = preg_split('/\R/', $sql);
    if ($lines !== false) {
        $lines = array_filter($lines, function (string $line): bool {
            $trimmed = ltrim($line);
            return $trimmed !== '' && !str_starts_with($trimmed, '--');
        });
        $sql = implode("\n", $lines);
    }

    // Strip any USE statement (we are already connected to the right DB)
    $sql = preg_replace('/USE\s+`?\w+`?\s*;/i', '', $sql);

    // Strip any CREATE DATABASE statement (the schema file targets the
    // "kind_commodities_db" dev name; the live DB may be named differently
    // e.g. qymwtpra_kind_commodities, so never create a stray database).
    $sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);

    $statements = [];
    foreach (array_map('trim', explode(';', $sql)) as $stmt) {
        if ($stmt !== '') {
            $statements[] = $stmt;
        }
    }
    return $statements;
}

/**
 * Execute a migration file, skipping statements that fail (idempotent).
 */
function runMigrationFile(PDO $pdo, string $file): void
{
    $sql = @file_get_contents($file);
    if ($sql === false) {
        return;
    }
    foreach (splitMigrationSql($sql) as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (Exception $e) {
            // Ignore — already applied, or a transient FK ordering issue.
            // The completeness guard below re-runs on the next request.
        }
    }
}

/**
 * Table names created by a migration file, derived from the file itself.
 */
function migrationTableNames(string $file): array
{
    $sql = @file_get_contents($file);
    if ($sql === false) {
        return [];
    }
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $sql, $m);
    return array_values(array_unique($m[1] ?? []));
}

/**
 * Reconcile legacy column shapes with the schema the code expects.
 *
 * The raw_materials / suppliers tables in older databases use an older shape
 * (name, stock_tons, current_price_per_ton, feed_type) while the migration
 * files and every current module read (material_name, current_stock,
 * current_price_per_unit, unit, category) and (supplier_name). Adding columns
 * and back-filling from the legacy ones lets both old and new code read the
 * table, without touching or dropping any existing data. Idempotent.
 */
function reconcileLegacySchema(PDO $pdo): void
{
    // Fast exit if already reconciled this request
    static $reconciled = false;
    if ($reconciled) return;
    $reconciled = true;

    // ── raw_materials ──
    if (tableExists($pdo, 'raw_materials')) {
        $add = [];
        if (!columnExists($pdo, 'raw_materials', 'material_name'))   $add[] = 'ADD COLUMN material_name VARCHAR(100) NULL AFTER id';
        if (!columnExists($pdo, 'raw_materials', 'material_code'))   $add[] = 'ADD COLUMN material_code VARCHAR(50) NULL';
        if (!columnExists($pdo, 'raw_materials', 'unit'))            $add[] = "ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'kg'";
        if (!columnExists($pdo, 'raw_materials', 'opening_balance')) $add[] = 'ADD COLUMN opening_balance DECIMAL(12,3) NOT NULL DEFAULT 0';
        if (!columnExists($pdo, 'raw_materials', 'current_stock'))   $add[] = 'ADD COLUMN current_stock DECIMAL(12,3) NOT NULL DEFAULT 0';
        if (!columnExists($pdo, 'raw_materials', 'current_price_per_unit')) $add[] = 'ADD COLUMN current_price_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0';
        if (!columnExists($pdo, 'raw_materials', 'category'))        $add[] = "ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'feed_ingredient'";
        if (!columnExists($pdo, 'raw_materials', 'supplier_id'))     $add[] = 'ADD COLUMN supplier_id INT NULL';
        if (!columnExists($pdo, 'raw_materials', 'notes'))           $add[] = 'ADD COLUMN notes TEXT NULL';
        if ($add) {
            $pdo->exec('ALTER TABLE raw_materials ' . implode(', ', $add));
        }

        // Back-fill from the legacy columns when they exist. The legacy stock
        // is stored in TONS (old code converts kg -> tons), the current schema
        // is in KG — so convert 1:1000 and price per ton -> per kg.
        if (columnExists($pdo, 'raw_materials', 'name')) {
            $pdo->exec("UPDATE raw_materials SET material_name = name WHERE material_name IS NULL OR material_name = ''");
            if (columnExists($pdo, 'raw_materials', 'stock_tons')) {
                $pdo->exec('UPDATE raw_materials SET current_stock = stock_tons * 1000, opening_balance = stock_tons * 1000 WHERE stock_tons IS NOT NULL AND (current_stock = 0 OR current_stock IS NULL)');
            }
            if (columnExists($pdo, 'raw_materials', 'current_price_per_ton')) {
                $pdo->exec('UPDATE raw_materials SET current_price_per_unit = current_price_per_ton / 1000 WHERE current_price_per_ton IS NOT NULL AND (current_price_per_unit = 0 OR current_price_per_unit IS NULL)');
            }
        }
    }

    // ── suppliers ──
    if (tableExists($pdo, 'suppliers')
        && !columnExists($pdo, 'suppliers', 'supplier_name')
        && columnExists($pdo, 'suppliers', 'name')) {
        $pdo->exec('ALTER TABLE suppliers ADD COLUMN supplier_name VARCHAR(150) NULL AFTER id');
        $pdo->exec("UPDATE suppliers SET supplier_name = name WHERE supplier_name IS NULL OR supplier_name = ''");
    }

    // ── financial_records (expenses) ──
    if (tableExists($pdo, 'financial_records')) {
        $add = [];
        if (!columnExists($pdo, 'financial_records', 'payment_method')) {
            $add[] = "ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cash'";
        }
        if (!columnExists($pdo, 'financial_records', 'payment_status')) {
            $add[] = "ADD COLUMN payment_status ENUM('Pending','Approved','Failed','Completed') DEFAULT 'Completed'";
        }
        if ($add) {
            $pdo->exec('ALTER TABLE financial_records ' . implode(', ', $add));
        }
    }

    // ── egg_losses — add a "stage" column so broken eggs can be attributed
    //    to where they were damaged: during collection or on route (transport).
    if (tableExists($pdo, 'egg_losses') && !columnExists($pdo, 'egg_losses', 'stage')) {
        $pdo->exec("ALTER TABLE egg_losses ADD COLUMN stage ENUM('collection','transport','storage','other') NOT NULL DEFAULT 'collection' AFTER loss_type");
    }

    // ── users.role — older databases have an ENUM without 'sales_staff',
    //    so assigning that role silently stores an empty string. Widen it.
    if (tableExists($pdo, 'users')) {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        if ($col && str_contains($col['Type'] ?? '', 'enum') && !str_contains($col['Type'], 'sales_staff')) {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin','farm_manager','stock_manager','sales_staff','customer') NULL DEFAULT 'customer'");
        }
    }

    // ── Legacy mirror columns: fresh installs only have the current shapes
    //    (material_name / current_stock / current_price_per_unit), but several
    //    stock modules still read the legacy columns (name / stock_tons /
    //    current_price_per_ton). Mirror them so both generations of code work.
    if (tableExists($pdo, 'raw_materials')) {
        $add = [];
        if (!columnExists($pdo, 'raw_materials', 'name')) {
            $add[] = 'ADD COLUMN name VARCHAR(100) NULL AFTER id';
        }
        if (!columnExists($pdo, 'raw_materials', 'stock_tons')) {
            $add[] = 'ADD COLUMN stock_tons DECIMAL(12,3) NOT NULL DEFAULT 0';
        }
        if (!columnExists($pdo, 'raw_materials', 'current_price_per_ton')) {
            $add[] = 'ADD COLUMN current_price_per_ton DECIMAL(10,2) NOT NULL DEFAULT 0';
        }
        if ($add) {
            $pdo->exec('ALTER TABLE raw_materials ' . implode(', ', $add));
        }
        // Back-fill the mirror columns from the current ones (kg -> tons 1:1000).
        $pdo->exec("UPDATE raw_materials SET name = material_name WHERE (name IS NULL OR name = '') AND material_name IS NOT NULL");
        $pdo->exec('UPDATE raw_materials SET stock_tons = current_stock / 1000 WHERE current_stock IS NOT NULL AND stock_tons = 0');
        $pdo->exec('UPDATE raw_materials SET current_price_per_ton = current_price_per_unit * 1000 WHERE current_price_per_unit IS NOT NULL AND current_price_per_ton = 0');
    }

    // ── suppliers: legacy code reads suppliers.name (e.g. incoming stock).
    if (tableExists($pdo, 'suppliers') && !columnExists($pdo, 'suppliers', 'name')) {
        $pdo->exec('ALTER TABLE suppliers ADD COLUMN name VARCHAR(150) NULL AFTER id');
        $pdo->exec("UPDATE suppliers SET name = supplier_name WHERE name IS NULL OR name = ''");
    }

    // ── products: legacy raw_material_id link (feed products) used by the
    //    admin products module (INSERT/UPDATE/JOIN) but not in schema.sql.
    if (tableExists($pdo, 'products') && !columnExists($pdo, 'products', 'raw_material_id')) {
        $pdo->exec('ALTER TABLE products ADD COLUMN raw_material_id INT NULL AFTER category_id');
        try {
            $pdo->exec('ALTER TABLE products ADD INDEX idx_products_raw_material (raw_material_id)');
        } catch (Exception $e) {
            // Index may already exist — ignore
        }
    }

    // ── Legacy tables still used by modules but missing from the current
    //    migration files — ensure they exist on fresh installs too.
    $legacyTables = [
        "CREATE TABLE IF NOT EXISTS recipe_ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipe_id INT NOT NULL,
            raw_material_id INT NOT NULL,
            amount_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
            INDEX idx_ri_recipe (recipe_id),
            INDEX idx_ri_material (raw_material_id)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS production_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_number VARCHAR(50),
            recipe_id INT,
            bag_size_kg DECIMAL(10,2) DEFAULT 0,
            quantity_bags INT DEFAULT 0,
            total_cost DECIMAL(12,2) DEFAULT 0,
            notes TEXT,
            produced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS stock_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alert_type VARCHAR(50) NOT NULL,
            message TEXT,
            related_id INT,
            is_resolved TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            level VARCHAR(20) DEFAULT 'info',
            message TEXT,
            context TEXT
        ) ENGINE=InnoDB",
    ];
    foreach ($legacyTables as $legacySql) {
        try {
            $pdo->exec($legacySql);
        } catch (Exception $e) {
            // Ignore — retried on the next request
        }
    }
}

/**
 * List of every admin module key (used by role permissions and the sidebar).
 * Keep in sync with admin_sidebar.php sub-module keys.
 */
function kindModuleKeys(): array
{
    return [
        'dashboard',
        // Inventory & Products
        'products', 'stores', 'suppliers', 'contracts',
        // Sales & Finance
        'hub_finance', 'profit', 'cashbook', 'credit', 'purchase_orders', 'daily_sales', 'bulk_sales', 'lpo',
        // Reports & Tools
        'analytics', 'bulk_import_export',
        // Team & Messages
        'staff', 'users', 'tasks', 'messages',
        // Settings
        'calendar', 'dropdowns', 'settings', 'logs', 'permissions', 'email_alerts',
    ];
}

/**
 * Map an admin script name to its module key ('' when unknown).
 */
function kindModuleKeyForScript(string $script): string
{
    $map = [
        'dashboard.php' => 'dashboard',
        'hub_inventory.php' => 'products', 'products.php' => 'products',
        'stores.php' => 'stores', 'suppliers.php' => 'suppliers', 'contracts.php' => 'contracts',
        'hub_finance.php' => 'hub_finance', 'profit.php' => 'profit', 'cashbook.php' => 'cashbook',
        'credit.php' => 'credit', 'purchase_orders.php' => 'purchase_orders',
        'daily_sales.php' => 'daily_sales', 'bulk_sales.php' => 'bulk_sales', 'lpo.php' => 'lpo',
        'analytics.php' => 'analytics', 'bulk_import_export.php' => 'bulk_import_export',
        'hub_people.php' => 'staff', 'staff.php' => 'staff', 'users.php' => 'users',
        'tasks.php' => 'tasks', 'messages.php' => 'messages',
        'hub_settings.php' => 'settings', 'calendar.php' => 'calendar', 'dropdowns.php' => 'dropdowns',
        'settings.php' => 'settings', 'logs.php' => 'logs', 'permissions.php' => 'permissions', 'email_alerts.php' => 'email_alerts',
        'orders.php' => 'hub_finance', 'sales.php' => 'hub_finance', 'payments.php' => 'hub_finance',
        'expenses.php' => 'hub_finance', 'reports.php' => 'analytics', 'operations.php' => 'flocks',
    ];
    return $map[$script] ?? '';
}

/**
 * Default permission grants per role. super_admin and farm_manager get full
 * access; limited roles get their own module sets. 'customer' gets nothing.
 */
function kindDefaultRolePermissions(): array
{
    $all = kindModuleKeys();
    $perms = [];
    foreach ($all as $m) {
        $perms['super_admin'][$m] = ['view' => 1, 'edit' => 1];
        $perms['farm_manager'][$m] = ['view' => 1, 'edit' => 1];
    }

    $stock = ['products', 'stores'];
    $sales = ['hub_finance', 'profit', 'cashbook', 'credit', 'daily_sales', 'bulk_sales', 'lpo', 'purchase_orders'];
    foreach ($all as $m) {
        $perms['stock_manager'][$m] = in_array($m, $stock, true) ? ['view' => 1, 'edit' => 1] : ['view' => 0, 'edit' => 0];
        $perms['sales_staff'][$m] = in_array($m, $sales, true) ? ['view' => 1, 'edit' => 1] : ['view' => 0, 'edit' => 0];
    }
    // Everyone can always open the dashboard.
    $perms['stock_manager']['dashboard'] = ['view' => 1, 'edit' => 1];
    $perms['sales_staff']['dashboard'] = ['view' => 1, 'edit' => 1];
    foreach ($all as $m) {
        $perms['customer'][$m] = ['view' => 0, 'edit' => 0];
    }
    return $perms;
}

/**
 * Load the role_permissions matrix for every role.
 * Returns ['role' => ['module' => ['view'=>bool,'edit'=>bool]]]
 */
function kindRolePermissions(?PDO $pdo = null): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    if ($pdo === null) $pdo = getDatabaseConnection();
    $cache = kindDefaultRolePermissions();
    if (!$pdo) return $cache;
    try {
        if (!tableExists($pdo, 'role_permissions')) return $cache;
        $rows = $pdo->query('SELECT role, module_key, can_view, can_edit FROM role_permissions')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (!isset($cache[$r['role']])) continue;
            if (!isset($cache[$r['role']][$r['module_key']])) continue;
            $cache[$r['role']][$r['module_key']] = ['view' => (int)$r['can_view'], 'edit' => (int)$r['can_edit']];
        }
    } catch (Exception $e) {
        // Table missing — fall back to defaults
    }
    return $cache;
}

/**
 * Idempotent master-data seeding: commodity taxonomy, user-role dropdowns,
 * and the role_permissions matrix. Runs only once per migration cycle.
 */
function seedMasterData(PDO $pdo): void
{
    // Fast exit if already seeded — check a lightweight flag
    static $seeded = false;
    if ($seeded) return;
    $seeded = true;

    if (!tableExists($pdo, 'system_dropdowns')) return;

    // ── User roles (only 3 INSERT IGNOREs — cheap) ──
    $stmt = $pdo->prepare('INSERT IGNORE INTO system_dropdowns (group_key, group_label, option_value, option_label, sort_order, is_active, is_system) VALUES (?,?,?,?,?,1,1)');
    $stmt->execute(['user_roles', 'User Roles', 'super_admin', 'Super Admin', 0]);
    $stmt->execute(['user_roles', 'User Roles', 'farm_manager', 'Farm Manager', 1]);
    $stmt->execute(['user_roles', 'User Roles', 'sales_staff', 'Sales Staff', 4]);

    // ── Commodity product types (only INSERT IGNORE — no UPDATEs) ──
    $commodityTypes = [
        ['product_types', 'Product Types', 'grain', 'Grains & Cereals', 1],
        ['product_types', 'Product Types', 'legume', 'Pulses & Legumes', 2],
        ['product_types', 'Product Types', 'raw_material', 'Feed Raw Materials', 3],
    ];
    $commodityCats = [
        ['product_categories', 'Product Categories', 'cereals', 'Grains & Cereals', 1],
        ['product_categories', 'Product Categories', 'pulses', 'Pulses & Legumes', 2],
        ['product_categories', 'Product Categories', 'feed_ingredients', 'Feed Raw Materials', 3],
    ];
    foreach (array_merge($commodityTypes, $commodityCats) as $row) {
        $stmt->execute($row);
    }

    // ── Role permissions matrix (idempotent INSERT IGNORE) ──
    if (tableExists($pdo, 'role_permissions')) {
        $defaults = kindDefaultRolePermissions();
        $permStmt = $pdo->prepare('INSERT IGNORE INTO role_permissions (role, module_key, can_view, can_edit) VALUES (?,?,?,?)');
        foreach ($defaults as $role => $mods) {
            foreach ($mods as $mod => $p) {
                $permStmt->execute([$role, $mod, $p['view'], $p['edit']]);
            }
        }
    }
}

/**
 * Ensure all module tables exist. No-op when everything is present.
 */
function ensureKindSchema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    // CRITICAL: Check migration-complete flag FIRST — before any heavy work.
    // This prevents expensive ALTER TABLE, UPDATE, and INSERT operations
    // from running on every single request on shared hosting.
    try {
        $flag = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'migration_v6_completed'")->fetchColumn();
        if ($flag === '1') return; // Migration already done, skip entirely
    } catch (Exception $e) {
        // site_settings table might not exist yet — continue with migration
    }

    try {
        $configDir = __DIR__;
        $schemaFile = $configDir . '/schema.sql';
        $settingsFile = $configDir . '/settings.sql';
        $commoditiesFile = $configDir . '/migration_v5_commodities.sql';
        $featuresFile = $configDir . '/migration_v6_features.sql';

        // Lightweight migration: only run essential files
        // max 2 passes to keep shared hosting happy
        for ($pass = 0; $pass < 2; $pass++) {
            $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $missingSchema = array_diff(migrationTableNames($schemaFile), $existing);

            if (!$missingSchema && in_array('products', $existing) && in_array('site_settings', $existing)) {
                break; // core tables present
            }

            $tableCountBefore = count($existing);
            if ($missingSchema) runMigrationFile($pdo, $schemaFile);
            runMigrationFile($pdo, $settingsFile);
            runMigrationFile($pdo, $commoditiesFile);
            runMigrationFile($pdo, $featuresFile);

            $after = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if (count($after) <= $tableCountBefore) break;
        }

        // Mark migration as completed to skip on subsequent requests
        try {
            updateSetting('migration_v6_completed', '1');
        } catch (Exception $e) {
            // Ignore — will retry next request
        }
    } catch (Exception $e) {
        // Silent — never break the page
    }
}
