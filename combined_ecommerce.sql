-- Create database
USE ecommerce_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    address TEXT,
    phone VARCHAR(20),
    is_banned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Brands table
CREATE TABLE brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    logo_url VARCHAR(255),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    brand_id INT,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255),
    avg_rating DECIMAL(3,2) DEFAULT 0,
    total_sales INT DEFAULT 0,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Wishlist table
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY unique_wishlist (user_id, product_id)
);

-- Reviews table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY unique_review (user_id, product_id)
);

-- Coupons table
CREATE TABLE coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount DECIMAL(5,2) NOT NULL,
    valid_until DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact messages table
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_reviews_product ON reviews(product_id);
CREATE INDEX idx_wishlist_user ON wishlist(user_id);

-- Insert sample data

-- Admin user (password: admin123)
INSERT INTO users (name, email, password_hash, role) VALUES 
('Admin', 'admin@example.com', '$2y$10$8tHxL.q9BzwDhRXwwwR1COYz6TtxMQJqhN9V3UF9T3HJGQZsuHhJi', 'admin');

-- Insert tech store categories
INSERT INTO categories (name) VALUES 
('Gaming Laptops'),
('Business Laptops'),
('Monitors'),
('PC Components'),
('Gaming Accessories'),
('Networking'),
('Storage Devices');

-- Insert brands
INSERT INTO brands (name, description, website) VALUES
('Apple', 'Premium laptops and technology products', 'https://www.apple.com'),
('Samsung', 'Monitors, storage, and computer components', 'https://www.samsung.com'),
('LG', 'High-end monitors and displays', 'https://www.lg.com'),
('NVIDIA', 'Graphics cards and gaming technology', NULL),
('AMD', 'Processors and graphics solutions', NULL),
('Logitech', 'Premium gaming peripherals', NULL),
('Razer', 'Gaming accessories and laptops', NULL),
('ASUS', 'Networking and gaming products', 'https://www.asus.com'),
('Western Digital', 'Storage solutions', NULL),
('Seagate', 'Storage devices and solutions', NULL),
('Acer', 'Leading manufacturer of gaming and business laptops', NULL),
('HP', 'Premium computer hardware and accessories', 'https://www.hp.com'),
('Lenovo', 'Innovative laptops and gaming machines', 'https://www.lenovo.com'),
('Dell', 'Computer technology company', 'https://www.dell.com'),
('Sony', 'Multinational technology and entertainment company', 'https://www.sony.com');

-- Insert products
INSERT INTO products (name, description, price, category_id, stock, image_url) VALUES
-- Gaming Laptops
('Acer Nitro V 15', 'Gaming Laptop with Intel Core i5 13420H, 16GB RAM, 512GB SSD, NVIDIA RTX 4050 6GB, 15.6" FHD 144Hz', 1299.99, 1, 15, 'acer-nitro.jpg'),
('HP Victus 15', '2024 AI-Integrated Gaming Laptop, Ryzen 5 8645HS, 16GB RAM, RTX 4050, 15.6" 144Hz', 1199.99, 1, 10, 'hp-victus.jpg'),
('Lenovo Legion Slim 5', 'AMD Ryzen 7 8845HS, 16GB RAM, 1TB SSD, RTX 4070 8GB, 16" WQXGA 165Hz', 1799.99, 1, 8, 'legion-slim.jpg'),

-- Business Laptops
('MacBook Air M1', '13.3" Retina Display, 8GB RAM, 256GB SSD, Apple M1 Chip', 999.99, 2, 20, 'macbook-air.jpg'),
('Acer Swift Go 14', 'OLED, Intel i5 13500H, 16GB RAM, 512GB SSD, 2.8K 90Hz Display', 899.99, 2, 12, 'swift-go.jpg'),
('Lenovo ThinkPad X1', 'Carbon Gen 11, Intel i7, 32GB RAM, 1TB SSD, 14" 2.8K OLED', 1699.99, 2, 5, 'thinkpad-x1.jpg'),

-- Monitors
('LG 27GL850', '27" Ultragear Gaming Monitor, 1440p, 144Hz, 1ms, HDR', 449.99, 3, 25, 'lg-monitor.jpg'),
('Samsung Odyssey G7', '32" Curved Gaming Monitor, 240Hz, 1ms, QHD', 699.99, 3, 15, 'odyssey-g7.jpg'),
('Dell U2723QE', '27" 4K USB-C Hub Monitor', 649.99, 3, 10, 'dell-monitor.jpg'),

-- PC Components
('NVIDIA RTX 4070', '12GB GDDR6X Graphics Card', 599.99, 4, 8, 'rtx-4070.jpg'),
('AMD Ryzen 7 7800X3D', '8-Core Processor with 3D V-Cache', 449.99, 4, 12, 'ryzen-7.jpg'),
('Samsung 990 PRO', '2TB NVMe M.2 SSD', 199.99, 4, 30, 'samsung-ssd.jpg'),

-- Gaming Accessories
('Logitech G Pro X', 'Wireless Gaming Headset with Blue VO!CE', 199.99, 5, 40, 'gpro-headset.jpg'),
('Razer Huntsman V2', 'Analog Optical Gaming Keyboard', 249.99, 5, 25, 'huntsman-v2.jpg'),
('Glorious Model O', 'Wireless Gaming Mouse, 19,000 DPI', 79.99, 5, 50, 'model-o.jpg'),

-- Networking
('ASUS RT-AX86U', 'Wi-Fi 6 Gaming Router', 249.99, 6, 15, 'asus-router.jpg'),
('TP-Link Archer AX90', 'Tri-Band Wi-Fi 6 Router', 299.99, 6, 10, 'tplink-router.jpg'),
('Netgear Nighthawk XR1000', 'Pro Gaming Router', 349.99, 6, 8, 'nighthawk.jpg'),

-- Storage
('WD Black P50', '2TB External Gaming SSD', 299.99, 7, 20, 'wd-black.jpg'),
('Seagate FireCuda', '4TB External Gaming Drive', 149.99, 7, 25, 'firecuda.jpg'),
('Samsung T7 Shield', '1TB Rugged Portable SSD', 119.99, 7, 30, 't7-shield.jpg');

-- Update product-brand relationships
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Acer') WHERE name LIKE 'Acer%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'HP') WHERE name LIKE 'HP%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Lenovo') WHERE name LIKE 'Lenovo%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Apple') WHERE name LIKE 'MacBook%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Samsung') WHERE name LIKE 'Samsung%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'LG') WHERE name LIKE 'LG%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'NVIDIA') WHERE name LIKE 'NVIDIA%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'AMD') WHERE name LIKE 'AMD%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Logitech') WHERE name LIKE 'Logitech%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Razer') WHERE name LIKE 'Razer%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Western Digital') WHERE name LIKE 'WD%';
UPDATE products SET brand_id = (SELECT id FROM brands WHERE name = 'Seagate') WHERE name LIKE 'Seagate%';
