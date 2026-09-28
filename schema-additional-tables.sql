-- =====================================================================
-- Sports CMS — additional tables
-- =====================================================================
-- database.sql (the one that shipped with this project) only creates
-- `admins` and `settings`. Every other table the admin panel actually
-- reads/writes (products, categories, orders, etc.) was never included,
-- so a fresh database has nothing for the app to work against.
--
-- This file reconstructs those tables, with columns matched exactly to
-- the INSERT/UPDATE/SELECT statements found in admin/*.php. Run this
-- AFTER database.sql (or paste both into the same phpMyAdmin "Import").
-- =====================================================================

USE sports_cms;

-- ---------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- Sub-categories
-- ---------------------------------------------------------------------
CREATE TABLE sub_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Collections
-- ---------------------------------------------------------------------
CREATE TABLE collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- Products
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    sku VARCHAR(100) NULL,
    category_id INT UNSIGNED NULL,
    sub_category_id INT UNSIGNED NULL,
    collection_id INT UNSIGNED NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(10,2) NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (sub_category_id) REFERENCES sub_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- Product images
-- ---------------------------------------------------------------------
CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Product variants (size / color / etc.)
-- ---------------------------------------------------------------------
CREATE TABLE product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    variant_name VARCHAR(150) NULL,
    sku VARCHAR(100) NULL,
    price DECIMAL(10,2) NULL,
    sale_price DECIMAL(10,2) NULL,
    stock INT NOT NULL DEFAULT 0,
    size VARCHAR(50) NULL,
    color VARCHAR(50) NULL,
    hand VARCHAR(50) NULL,
    type VARCHAR(50) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Reviews
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    review_text TEXT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    admin_reply TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Orders
-- (there is no separate `customers` table — the admin panel derives
-- its "customers" list by grouping this table by customer_email)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(30) NULL,
    shipping_address VARCHAR(255) NULL,
    shipping_city VARCHAR(100) NULL,
    shipping_postal_code VARCHAR(20) NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NULL,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    order_status ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- Order items
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    product_name VARCHAR(200) NOT NULL,
    sku VARCHAR(100) NULL,
    variant_details VARCHAR(255) NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- Order status history
-- ---------------------------------------------------------------------
CREATE TABLE order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Returns / exchanges
-- ---------------------------------------------------------------------
CREATE TABLE returns_exchanges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NULL,
    variant_name VARCHAR(150) NULL,
    quantity INT NOT NULL DEFAULT 1,
    customer_name VARCHAR(150) NULL,
    customer_email VARCHAR(150) NULL,
    customer_phone VARCHAR(30) NULL,
    request_type VARCHAR(30) NOT NULL DEFAULT 'Return',
    reason TEXT NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Received', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    refund_status ENUM('Not Applicable', 'Pending', 'Processing', 'Refunded') NOT NULL DEFAULT 'Not Applicable',
    refund_amount DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- =====================================================================
-- Sample data so the storefront/API actually has something to show
-- =====================================================================

INSERT INTO categories (name, slug, description, status, sort_order) VALUES
('Bats', 'bats', 'Cricket bats for every format.', 1, 1),
('Balls', 'balls', 'Match and practice balls.', 1, 2),
('Protective Gear', 'protective-gear', 'Pads, gloves, and helmets.', 1, 3);

INSERT INTO collections (name, slug, description, status, sort_order) VALUES
('New Arrivals', 'new-arrivals', 'Freshly added gear.', 1, 1),
('Best Sellers', 'best-sellers', 'Our most popular picks.', 1, 2);

INSERT INTO products (name, slug, sku, category_id, sub_category_id, collection_id, description, price, sale_price, stock_quantity, status, featured) VALUES
('Pro English Willow Bat', 'pro-english-willow-bat', 'BAT-001', 1, NULL, 1, 'Grade 1 English willow, hand-crafted for power hitters.', 24500, 19999, 15, 'active', 1),
('Tournament Leather Ball', 'tournament-leather-ball', 'BALL-001', 2, NULL, 2, 'Premium four-piece leather ball for competitive matches.', 2500, NULL, 60, 'active', 1),
('Elite Batting Pads', 'elite-batting-pads', 'PAD-001', 3, NULL, NULL, 'Lightweight, high-density foam protection.', 8500, 6999, 25, 'active', 0);
