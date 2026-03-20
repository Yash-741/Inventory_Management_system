-- Inventory Management System - Database Schema
-- Create database and tables

-- Drop existing database if it exists
DROP DATABASE IF EXISTS inventory_db;

-- Create database
CREATE DATABASE IF NOT EXISTS inventory_db;
USE inventory_db;

-- ============================================
-- Products Table
-- ============================================
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    unit_price DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_product_name (product_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Inventory Table
-- ============================================
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 0,
    min_stock_level INT NOT NULL DEFAULT 10,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_quantity (quantity),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Inventory History Table (for auditing)
-- ============================================
CREATE TABLE inventory_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    old_quantity INT,
    new_quantity INT NOT NULL,
    change_type ENUM('adjustment', 'sale', 'return', 'initial') DEFAULT 'adjustment',
    changed_by VARCHAR(255) DEFAULT 'system',
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_change_date (change_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Sample Data
-- ============================================

-- Insert sample products
INSERT INTO products (product_name, sku, description, unit_price) VALUES
('Wireless Mouse', 'SKU-WM-001', 'Ergonomic wireless mouse with USB receiver', 25.99),
('USB-C Cable', 'SKU-UC-002', 'High-quality USB-C to USB-A cable', 9.99),
('Mechanical Keyboard', 'SKU-MK-003', 'RGB LED backlit mechanical keyboard', 89.99),
('Monitor Stand', 'SKU-MS-004', 'Adjustable dual monitor stand', 49.99),
('Desk Lamp', 'SKU-DL-005', 'LED desk lamp with USB charging', 35.99),
('External SSD', 'SKU-ES-006', '500GB portable external SSD', 69.99),
('Phone Charger', 'SKU-PC-007', 'Fast USB-C phone charger', 19.99),
('Webcam HD', 'SKU-WC-008', '1080p HD webcam with microphone', 59.99),
('Network Cable', 'SKU-NC-009', 'Cat6 Ethernet network cable', 12.99),
('Power Bank', 'SKU-PB-010', '20000mAh portable power bank', 29.99);

-- Insert initial inventory
INSERT INTO inventory (product_id, quantity, min_stock_level) VALUES
(1, 45, 15),
(2, 120, 30),
(3, 8, 5),
(4, 22, 10),
(5, 35, 10),
(6, 5, 8),
(7, 150, 50),
(8, 12, 5),
(9, 75, 20),
(10, 28, 10);

-- ============================================
-- Views for easier querying
-- ============================================

-- View: Low Stock Items
CREATE VIEW low_stock_items AS
SELECT 
    p.product_id,
    p.product_name,
    p.sku,
    i.quantity,
    i.min_stock_level,
    (i.min_stock_level - i.quantity) AS units_needed
FROM products p
JOIN inventory i ON p.product_id = i.product_id
WHERE i.quantity <= i.min_stock_level;

-- View: Out of Stock Items
CREATE VIEW out_of_stock_items AS
SELECT 
    p.product_id,
    p.product_name,
    p.sku,
    i.quantity,
    i.last_updated
FROM products p
JOIN inventory i ON p.product_id = i.product_id
WHERE i.quantity = 0;

-- View: Inventory Summary
CREATE VIEW inventory_summary AS
SELECT 
    COUNT(DISTINCT p.product_id) as total_products,
    SUM(CASE WHEN i.quantity > i.min_stock_level AND i.quantity > 0 THEN 1 ELSE 0 END) as in_stock_count,
    SUM(CASE WHEN i.quantity <= i.min_stock_level AND i.quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
    SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
    SUM(i.quantity) as total_quantity
FROM products p
LEFT JOIN inventory i ON p.product_id = i.product_id;

-- ============================================
-- Indexes for better performance
-- ============================================
CREATE INDEX idx_product_inventory ON inventory(product_id);
CREATE INDEX idx_inventory_quantity_stock ON inventory(quantity, min_stock_level);

-- ============================================
-- Stored Procedures (Optional)
-- ============================================

-- Procedure: Update inventory quantity with history
DELIMITER $$

CREATE PROCEDURE UpdateInventoryWithHistory(
    IN p_product_id INT,
    IN p_new_quantity INT,
    IN p_change_type VARCHAR(50),
    IN p_notes TEXT
)
BEGIN
    DECLARE v_old_quantity INT;
    
    -- Get current quantity
    SELECT quantity INTO v_old_quantity FROM inventory WHERE product_id = p_product_id;
    
    -- Update inventory
    UPDATE inventory SET 
        quantity = p_new_quantity,
        last_updated = NOW()
    WHERE product_id = p_product_id;
    
    -- Log the change
    INSERT INTO inventory_history (product_id, old_quantity, new_quantity, change_type, notes)
    VALUES (p_product_id, v_old_quantity, p_new_quantity, p_change_type, p_notes);
END$$

DELIMITER ;

-- ============================================
-- Grants and Permissions (if using separate DB user)
-- ============================================
-- Uncomment and modify if using a separate database user
-- GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.* TO 'inventory_user'@'localhost' IDENTIFIED BY 'password';
-- FLUSH PRIVILEGES;

-- ============================================
-- Verification Queries
-- ============================================
-- Run these queries to verify the setup:
-- SELECT * FROM products;
-- SELECT * FROM inventory;
-- SELECT * FROM inventory_summary;
-- SELECT * FROM low_stock_items;
-- SELECT * FROM out_of_stock_items;
