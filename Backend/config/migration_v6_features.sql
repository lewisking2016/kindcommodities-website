-- ══════════════════════════════════════════════════════════════
-- Kind Commodities Ltd — Feature Migration v6
-- Adds: weight-based stock, quality factors, suppliers, contracts,
--       multi-location inventory, barcodes, email alerts
-- ══════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────
-- 0. FIX PRODUCT_TYPE ENUM — pivot from poultry to commodities
--    The original ENUM only had poultry types. We need grain, legume,
--    raw_material for the commodity business.
-- ─────────────────────────────────────────────────────────────
ALTER TABLE products MODIFY COLUMN product_type VARCHAR(30) NOT NULL DEFAULT 'grain';

-- Also fix categories.category_type ENUM
ALTER TABLE categories MODIFY COLUMN category_type VARCHAR(30) NOT NULL DEFAULT 'feed';

-- ─────────────────────────────────────────────────────────────
-- 1. PRODUCTS TABLE — add weight, quality, barcode columns
-- ─────────────────────────────────────────────────────────────
-- Weight-based stock
ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_weight_kg DECIMAL(12,3) DEFAULT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS unit_weight_kg DECIMAL(8,2) DEFAULT NULL COMMENT 'Weight per unit (e.g. 90 for a 90kg bag)';
ALTER TABLE products ADD COLUMN IF NOT EXISTS price_per_kg DECIMAL(10,2) DEFAULT NULL;

-- Quality factors
ALTER TABLE products ADD COLUMN IF NOT EXISTS moisture_pct DECIMAL(5,2) DEFAULT NULL COMMENT 'Moisture content %';
ALTER TABLE products ADD COLUMN IF NOT EXISTS grade VARCHAR(20) DEFAULT NULL COMMENT 'e.g. Grade 1, Grade 2, Premium';
ALTER TABLE products ADD COLUMN IF NOT EXISTS foreign_material_pct DECIMAL(5,2) DEFAULT NULL COMMENT 'Foreign material %';
ALTER TABLE products ADD COLUMN IF NOT EXISTS test_weight VARCHAR(20) DEFAULT NULL COMMENT 'Test weight kg/hl';
ALTER TABLE products ADD COLUMN IF NOT EXISTS origin VARCHAR(100) DEFAULT NULL COMMENT 'e.g. Nakuru, Eldoret';

-- Barcode / QR
ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode VARCHAR(50) DEFAULT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(50) DEFAULT NULL;

-- Supplier link
ALTER TABLE products ADD COLUMN IF NOT EXISTS default_supplier_id INT DEFAULT NULL;

-- Low stock alert threshold
ALTER TABLE products ADD COLUMN IF NOT EXISTS low_stock_threshold INT DEFAULT 10;

-- Create indexes for new columns (ignore if exists)
CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
CREATE INDEX IF NOT EXISTS idx_products_supplier ON products(default_supplier_id);

-- ─────────────────────────────────────────────────────────────
-- 2. SUPPLIERS TABLE — full supplier management
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    location VARCHAR(100) COMMENT 'Town/County',
    payment_terms VARCHAR(100) DEFAULT 'Cash on Delivery',
    rating TINYINT DEFAULT 5 COMMENT '1-5 stars',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_active (is_active),
    INDEX idx_suppliers_name (supplier_name)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 3. SUPPLIER DELIVERIES — track what each supplier delivers
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS supplier_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    product_id INT,
    delivery_date DATE NOT NULL,
    quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_sd_supplier (supplier_id),
    INDEX idx_sd_date (delivery_date),
    INDEX idx_sd_product (product_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 4. CONTRACTS TABLE — forward contracts with growers/customers
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(30) NOT NULL UNIQUE,
    contract_type ENUM('purchase','sale') NOT NULL,
    party_name VARCHAR(150) NOT NULL,
    party_phone VARCHAR(20),
    party_email VARCHAR(100),
    party_type ENUM('grower','customer','broker','other') DEFAULT 'customer',
    product_id INT,
    commodity_name VARCHAR(100) NOT NULL,
    quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
    delivered_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_value DECIMAL(14,2) NOT NULL DEFAULT 0,
    currency VARCHAR(5) DEFAULT 'KES',
    contract_date DATE NOT NULL,
    delivery_start DATE,
    delivery_end DATE,
    delivery_location VARCHAR(200),
    payment_terms VARCHAR(200) DEFAULT 'Cash on Delivery',
    quality_specs TEXT COMMENT 'Expected moisture, grade etc.',
    status ENUM('draft','active','fulfilled','cancelled','expired') DEFAULT 'draft',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_contracts_status (status),
    INDEX idx_contracts_type (contract_type),
    INDEX idx_contracts_date (contract_date),
    INDEX idx_contracts_party (party_name)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 5. CONTRACT DELIVERIES — track deliveries against contracts
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contract_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_cd_contract (contract_id),
    INDEX idx_cd_date (delivery_date)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 6. WAREHOUSE LOCATIONS — multi-location inventory
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS warehouse_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(200),
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    capacity_tons DECIMAL(10,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default warehouse
INSERT IGNORE INTO warehouse_locations (id, name, address, is_active) VALUES
(1, 'Main Store', 'Kind Commodities Main Warehouse', 1);

-- ─────────────────────────────────────────────────────────────
-- 7. PRODUCT STOCK BY LOCATION — track stock per warehouse
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_stock_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity_bags INT DEFAULT 0,
    quantity_kg DECIMAL(12,3) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_location (product_id, location_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 8. STOCK MOVEMENTS — full audit trail for all stock changes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT,
    movement_type ENUM('in','out','transfer','adjustment') NOT NULL,
    reason ENUM('purchase','sale','transfer','adjustment','opening','return','other') DEFAULT 'other',
    quantity_bags INT DEFAULT 0,
    quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
    reference_type VARCHAR(50) COMMENT 'order, contract, supplier_delivery, manual',
    reference_id INT,
    notes TEXT,
    recorded_by INT,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(id) ON DELETE SET NULL,
    INDEX idx_sm_product (product_id),
    INDEX idx_sm_type (movement_type),
    INDEX idx_sm_date (movement_date)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 9. EMAIL ALERTS LOG — track sent notifications
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS email_alerts_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL COMMENT 'low_stock, order_placed, order_completed, contract_delivery',
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT,
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    related_type VARCHAR(50),
    related_id INT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_eal_type (alert_type),
    INDEX idx_eal_status (status)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 10. SITE SETTINGS — add email/SMTP config defaults
-- ─────────────────────────────────────────────────────────────
INSERT INTO site_settings (setting_key, setting_value) VALUES
('smtp_host', 'kindcommoditiesltd.com'),
('smtp_port', '465'),
('smtp_username', 'accounts@kindcommoditiesltd.com'),
('smtp_password', ''),
('smtp_from_email', 'accounts@kindcommoditiesltd.com'),
('smtp_from_name', 'Kind Commodities Ltd'),
('smtp_encryption', 'ssl'),
('alert_email', 'accounts@kindcommoditiesltd.com'),
('low_stock_alert_enabled', '1'),
('order_notification_enabled', '1'),
('weekly_report_enabled', '0'),
('contract_expiry_alert_days', '7')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Seed some default suppliers for the commodity business
INSERT INTO suppliers (supplier_name, contact_person, phone, location, payment_terms, rating, is_active) VALUES
('Nakuru Grain Merchants', 'John Kamau', '+254 722 000 001', 'Nakuru', '30 days credit', 5, 1),
('Eldoret Wheat Farmers Co-op', 'Mary Chebet', '+254 733 000 002', 'Eldoret', 'Cash on Delivery', 4, 1),
('Bungoma Beans Supply', 'Peter Wanjala', '+254 712 000 003', 'Bungoma', '14 days credit', 4, 1),
('Kisumu Oil Mills', 'Alice Atieno', '+254 700 000 004', 'Kisumu', 'Cash on Delivery', 5, 1)
ON DUPLICATE KEY UPDATE supplier_name = VALUES(supplier_name);
