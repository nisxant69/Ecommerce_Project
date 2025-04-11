-- Update categories for tech store
TRUNCATE TABLE categories;
INSERT INTO categories (name) VALUES 
('Gaming Laptops'),
('Business Laptops'),
('Monitors'),
('PC Components'),
('Gaming Accessories'),
('Networking'),
('Storage Devices');

-- Update products with tech focus
TRUNCATE TABLE products;
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

-- Add sample brands
INSERT INTO brands (name, description) VALUES
('Acer', 'Leading manufacturer of gaming and business laptops'),
('HP', 'Premium computer hardware and accessories'),
('Lenovo', 'Innovative laptops and gaming machines'),
('Apple', 'Premium laptops and technology products'),
('Samsung', 'Monitors, storage, and computer components'),
('LG', 'High-end monitors and displays'),
('NVIDIA', 'Graphics cards and gaming technology'),
('AMD', 'Processors and graphics solutions'),
('Logitech', 'Premium gaming peripherals'),
('Razer', 'Gaming accessories and laptops'),
('ASUS', 'Networking and gaming products'),
('Western Digital', 'Storage solutions'),
('Seagate', 'Storage devices and solutions');

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
