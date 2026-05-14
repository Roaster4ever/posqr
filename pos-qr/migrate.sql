-- Run this in phpMyAdmin → pos_db → SQL tab
USE pos_db;

-- Add MFG and EXP columns to products
ALTER TABLE products
  ADD COLUMN mfg_date DATE NULL AFTER low_stock_alert,
  ADD COLUMN exp_date DATE NULL AFTER mfg_date;

-- Inventory stock log table
CREATE TABLE IF NOT EXISTS inventory_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    qty_added   INT NOT NULL DEFAULT 0,
    mfg_date    DATE NULL,
    exp_date    DATE NULL,
    note        VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Assign random MFG/EXP to existing products that have none
UPDATE products SET
  mfg_date = DATE_SUB(CURDATE(), INTERVAL FLOOR(30 + RAND()*180) DAY),
  exp_date  = DATE_ADD(CURDATE(),  INTERVAL FLOOR(30 + RAND()*365) DAY)
WHERE mfg_date IS NULL;
