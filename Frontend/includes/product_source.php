<?php
declare(strict_types=1);

/**
 * Centralized product source used by both shop and homepage
 * Tries DB first, otherwise returns the local sample dataset.
 */
function loadDisplayProducts(?PDO $pdo = null): array
{
    if (!$pdo) {
        return getFallbackProducts();
    }

    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return !empty($products) ? $products : getFallbackProducts();
    } catch (Exception $e) {
        @error_log("Failed to load products from database: " . $e->getMessage());
        return getFallbackProducts();
    }
}

/**
 * Fallback products when database is not available
 */
function getFallbackProducts(): array
{
    return [
        [
            'id' => 1,
            'name' => 'White Maize (90kg Bag)',
            'slug' => 'white-maize',
            'description' => 'Premium-grade white maize, machine-sorted and dried to safe moisture levels. Ideal for milling, feed and human consumption.',
            'product_type' => 'grain',
            'price' => 4500,
            'stock_quantity' => 200,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 2,
            'name' => 'Yellow Maize (90kg Bag)',
            'slug' => 'yellow-maize',
            'description' => 'High-energy yellow maize, excellent for animal feed formulation and processing industries.',
            'product_type' => 'grain',
            'price' => 4300,
            'stock_quantity' => 180,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 3,
            'name' => 'Wheat Grain (90kg Bag)',
            'slug' => 'wheat-grain',
            'description' => 'Clean, well-dried wheat grain suitable for milling, baking and feed industries.',
            'product_type' => 'grain',
            'price' => 5200,
            'stock_quantity' => 120,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 4,
            'name' => 'Red Beans (90kg Bag)',
            'slug' => 'red-beans',
            'description' => 'Uniform, well-sorted red beans with excellent cooking quality. A staple pulse for homes and exporters.',
            'product_type' => 'legume',
            'price' => 7500,
            'stock_quantity' => 90,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 5,
            'name' => 'Green Grams (90kg Bag)',
            'slug' => 'green-grams',
            'description' => 'Premium green grams, clean and carefully graded — a high-demand export-quality pulse.',
            'product_type' => 'legume',
            'price' => 9500,
            'stock_quantity' => 70,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 6,
            'name' => 'Soya Beans (50kg Bag)',
            'slug' => 'soya-beans',
            'description' => 'High-protein soya beans, ideal for crushing, feed formulation and food processing.',
            'product_type' => 'legume',
            'price' => 5500,
            'stock_quantity' => 150,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 7,
            'name' => 'Sunflower Seeds (50kg Bag)',
            'slug' => 'sunflower-seeds',
            'description' => 'Quality oil sunflower seeds with good oil content, suitable for crushing and bird feed.',
            'product_type' => 'oilseed',
            'price' => 4200,
            'stock_quantity' => 100,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 8,
            'name' => 'Sesame Seeds (50kg Bag)',
            'slug' => 'sesame-seeds',
            'description' => 'Clean, high-purity sesame seeds — a prized export commodity for oil and food markets.',
            'product_type' => 'oilseed',
            'price' => 8500,
            'stock_quantity' => 60,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 9,
            'name' => 'Maize Bran (50kg Bag)',
            'slug' => 'maize-bran',
            'description' => 'Fresh maize bran, a staple raw material for animal feed formulation.',
            'product_type' => 'raw_material',
            'price' => 1800,
            'stock_quantity' => 250,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 10,
            'name' => 'Wheat Bran (50kg Bag)',
            'slug' => 'wheat-bran',
            'description' => 'Clean wheat bran with consistent quality — essential for animal feed rations.',
            'product_type' => 'raw_material',
            'price' => 1500,
            'stock_quantity' => 220,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 11,
            'name' => 'Sunflower Cake (50kg Bag)',
            'slug' => 'sunflower-cake',
            'description' => 'Protein-rich sunflower oilcake, an economical protein source for feed manufacturers.',
            'product_type' => 'raw_material',
            'price' => 3800,
            'stock_quantity' => 110,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
        [
            'id' => 12,
            'name' => 'Rice Grain (90kg Bag)',
            'slug' => 'rice-grain',
            'description' => 'Well-dried paddy/rough rice for milling — clean, uniform and ready for processing.',
            'product_type' => 'grain',
            'price' => 6200,
            'stock_quantity' => 80,
            'image_url' => '/Frontend/images/product-placeholder.svg',
            'is_featured' => 1,
        ],
    ];
}

?>
