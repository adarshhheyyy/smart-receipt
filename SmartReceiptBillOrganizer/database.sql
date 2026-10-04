CREATE DATABASE IF NOT EXISTS smart_receipt_db
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE smart_receipt_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    shop_name VARCHAR(150) NOT NULL,
    purchase_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_receipts_user_date (user_id, purchase_date),
    INDEX idx_receipts_category (category)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS warranties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT UNSIGNED NOT NULL UNIQUE,
    product_name VARCHAR(150) NOT NULL,
    warranty_start DATE NOT NULL,
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_warranties_receipt
        FOREIGN KEY (receipt_id) REFERENCES receipts(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_warranty_expiry (expiry_date)
) ENGINE=InnoDB;
