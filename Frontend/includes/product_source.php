<?php
declare(strict_types=1);

/**
 * Centralized product source used by both shop and homepage.
 * Products are loaded from the database only — no hardcoded fallback.
 * Admins add/edit/remove products via the admin Products Catalog.
 */
function loadDisplayProducts(?PDO $pdo = null): array
{
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return !empty($products) ? $products : [];
    } catch (Exception $e) {
        @error_log("Failed to load products from database: " . $e->getMessage());
        return [];
    }
}

?>
