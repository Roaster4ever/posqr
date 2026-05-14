-- POS Suite Database
CREATE DATABASE IF NOT EXISTS pos_db;
USE pos_db;

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    barcode VARCHAR(100),
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    low_stock_alert INT DEFAULT 5,
    mfg_date DATE NULL,
    exp_date DATE NULL,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(200),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    change_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','card','mobile') DEFAULT 'cash',
    status ENUM('completed','refunded') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Sale Items
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','cashier') DEFAULT 'cashier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Expenses
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO categories (name) VALUES ('Beverages'),('Snacks'),('Electronics'),('Clothing'),('Food');

INSERT INTO products (category_id,name,barcode,price,cost,stock,mfg_date,exp_date) VALUES
(1,'Coca Cola 500ml','111001',1.50,0.80,100,'2024-10-01','2025-10-01'),
(1,'Mineral Water','111002',0.75,0.30,200,'2024-11-01','2026-11-01'),
(1,'Orange Juice','111003',2.00,1.00,80,'2024-12-01','2025-12-01'),
(2,'Chips (Lays)','222001',1.25,0.60,150,'2024-09-01','2025-09-01'),
(2,'Chocolate Bar','222002',1.75,0.90,120,'2024-08-01','2025-08-01'),
(3,'USB Cable','333001',5.99,2.50,50,'2023-01-01','2028-01-01'),
(3,'Phone Charger','333002',12.99,6.00,30,'2023-06-01','2028-06-01'),
(4,'T-Shirt','444001',15.99,8.00,60,'2024-01-01','2030-01-01'),
(5,'Bread Loaf','555001',2.50,1.20,40,'2025-05-01','2025-05-15'),
(5,'Butter 200g','555002',3.25,1.80,35,'2025-04-01','2025-06-01');

INSERT INTO customers (name,phone,email) VALUES
('Walk-in Customer','',''),
('Ahmed Khan','0300-1234567','ahmed@email.com'),
('Sara Ali','0321-9876543','sara@email.com');

-- Default admin user (password: admin123)
INSERT INTO users (name,username,password,role) VALUES
('Administrator','admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
('Cashier One','cashier','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cashier');

-- ─────────────────────────────────────────────────────────────
--  Sessions table (required for Vercel stateless deployment)
--  PHP sessions are stored here instead of on the filesystem.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
    id         VARCHAR(128)  NOT NULL,
    data       MEDIUMTEXT    NOT NULL,
    expires_at DATETIME      NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
