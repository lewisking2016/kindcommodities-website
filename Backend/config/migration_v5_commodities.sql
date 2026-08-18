-- ══════════════════════════════════════════════════════════════
-- Kind Commodities Ltd — Commodities Pivot Migration
-- Pivot from poultry to grains & raw materials trading.
-- Seeds categories, product types, and client-requested products.
-- ══════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────
-- 1. CATEGORIES — seed the commodity categories
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO categories (name, slug, category_type, description) VALUES
('Grains & Cereals', 'cereals', 'feed', 'Maize, wheat, barley, rice and other cereal grains'),
('Pulses & Legumes', 'pulses', 'feed', 'Common beans, soya beans, green grams and other pulses'),
('Feed Raw Materials', 'feed_ingredients', 'feed', 'Maize bran, wheat bran, rice polish, oil cakes and other feed inputs');

-- ─────────────────────────────────────────────────────────────
-- 2. SYSTEM DROPDOWNS — ensure commodity taxonomy exists
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO system_dropdowns (group_key, group_label, option_value, option_label, sort_order, is_active, is_system) VALUES
('product_types', 'Product Types', 'grain', 'Grains & Cereals', 1, 1, 1),
('product_types', 'Product Types', 'legume', 'Pulses & Legumes', 2, 1, 1),
('product_types', 'Product Types', 'raw_material', 'Feed Raw Materials', 3, 1, 1),
('product_categories', 'Product Categories', 'cereals', 'Grains & Cereals', 1, 1, 1),
('product_categories', 'Product Categories', 'pulses', 'Pulses & Legumes', 2, 1, 1),
('product_categories', 'Product Categories', 'feed_ingredients', 'Feed Raw Materials', 3, 1, 1);

-- ─────────────────────────────────────────────────────────────
-- 3. PRODUCTS — seed the client-requested commodity products
--    These are the real products the business trades.
--    Prices are indicative; admin can edit them.
-- ─────────────────────────────────────────────────────────────

-- Get category IDs (subquery pattern for portability)
SET @cat_cereals = (SELECT id FROM categories WHERE slug = 'cereals' LIMIT 1);
SET @cat_pulses  = (SELECT id FROM categories WHERE slug = 'pulses' LIMIT 1);
SET @cat_feed    = (SELECT id FROM categories WHERE slug = 'feed_ingredients' LIMIT 1);

-- ── GRAINS & PULSES ──
INSERT IGNORE INTO products (category_id, name, slug, description, product_type, price, stock_quantity, image_url, is_active, is_featured) VALUES
(@cat_cereals, 'Maize (90kg Bag)', 'maize-90kg', 'Premium quality maize, machine-sorted and dried to safe moisture levels. Ideal for milling, feed and human consumption.', 'grain', 4500, 200, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_cereals, 'Wheat (90kg Bag)', 'wheat-90kg', 'Clean, well-dried wheat grain suitable for milling, baking and feed industries.', 'grain', 5200, 150, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_cereals, 'Barley (90kg Bag)', 'barley-90kg', 'Quality barley grain for malting, brewing, animal feed and food processing.', 'grain', 4800, 100, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_cereals, 'Rice (90kg Bag)', 'rice-90kg', 'Well-dried paddy/rough rice for milling — clean, uniform and ready for processing.', 'grain', 6200, 80, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_pulses, 'Common Beans (90kg Bag)', 'common-beans-90kg', 'Uniform, well-sorted common beans with excellent cooking quality. A staple pulse for homes and exporters.', 'legume', 7500, 120, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_pulses, 'Soya Beans (50kg Bag)', 'soya-beans-50kg', 'High-protein soya beans, ideal for crushing, feed formulation and food processing.', 'legume', 5500, 150, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_pulses, 'Green Grams (90kg Bag)', 'green-grams-90kg', 'Premium green grams, clean and carefully graded — a high-demand export-quality pulse.', 'legume', 9500, 70, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_pulses, 'Pigeon Peas (90kg Bag)', 'pigeon-peas-90kg', 'Quality pigeon peas, well-dried and sorted for local and export markets.', 'legume', 8000, 60, '/Frontend/images/product-placeholder.svg', 1, 0),
(@cat_pulses, 'Cowpeas (90kg Bag)', 'cowpeas-90kg', 'Clean cowpeas suitable for food processing and direct consumption.', 'legume', 7000, 80, '/Frontend/images/product-placeholder.svg', 1, 0);

-- ── FEED RAW MATERIALS ──
INSERT IGNORE INTO products (category_id, name, slug, description, product_type, price, stock_quantity, image_url, is_active, is_featured) VALUES
(@cat_feed, 'Maize Bran / Germ (50kg Bag)', 'maize-bran-50kg', 'Fresh maize bran and germ, a staple raw material for animal feed formulation. Rich in fibre and energy.', 'raw_material', 1800, 250, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_feed, 'Wheat Bran / Pollard (50kg Bag)', 'wheat-bran-50kg', 'Clean wheat bran and pollard with consistent quality — essential for animal feed rations.', 'raw_material', 1500, 220, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_feed, 'Rice Polish / Bran (50kg Bag)', 'rice-bran-50kg', 'Nutritious rice polish and bran, an excellent energy source for livestock feed.', 'raw_material', 1600, 180, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_feed, 'Cotton Cake (50kg Bag)', 'cotton-cake-50kg', 'Protein-rich cotton seed cake for ruminant and poultry feed formulations.', 'raw_material', 3200, 100, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_feed, 'Sunflower Cake (50kg Bag)', 'sunflower-cake-50kg', 'Protein-rich sunflower oilcake, an economical protein source for feed manufacturers.', 'raw_material', 3800, 110, '/Frontend/images/product-placeholder.svg', 1, 1),
(@cat_feed, 'Soya Cake (50kg Bag)', 'soya-cake-50kg', 'High-protein soya meal/cake, essential for premium feed formulations.', 'raw_material', 4200, 90, '/Frontend/images/product-placeholder.svg', 1, 1);

-- ─────────────────────────────────────────────────────────────
-- 4. SITE SETTINGS — seed default logo paths
-- ─────────────────────────────────────────────────────────────
INSERT INTO site_settings (setting_key, setting_value) VALUES
('header_logo', '/Frontend/images/header logo.jpeg'),
('footer_logo', '/Frontend/images/Kind Commodities Ltd Logo_Final_FOOTER.png'),
('favicon', '/Frontend/images/Kind Commodities Ltd Logo_Final_favicon.png')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
