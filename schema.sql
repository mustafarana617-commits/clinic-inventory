CREATE DATABASE IF NOT EXISTS clinic_inventory;

USE clinic_inventory;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'inventory_manager', 'staff') NOT NULL DEFAULT 'staff',
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);


CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);


CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(100) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    minimum_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);


CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    status ENUM('ordered', 'partially_received', 'received', 'cancelled') NOT NULL DEFAULT 'ordered',
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);


CREATE TABLE purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_ordered DECIMAL(12,3) NOT NULL,
    quantity_received DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);


CREATE TABLE batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    expiry_date DATE NULL,
    quantity_remaining DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    CHECK (quantity_remaining >= 0),
    FOREIGN KEY (product_id) REFERENCES products(id)
);


CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    type ENUM('receipt', 'issue', 'adjustment') NOT NULL,
    quantity_change DECIMAL(12,3) NOT NULL,
    note VARCHAR(200) NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);


CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
