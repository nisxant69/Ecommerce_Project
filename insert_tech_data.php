<?php
/**
 * Tech Store Data Insertion Script
 * 
 * This script inserts sample tech store data into the ecommerce database
 */

// Include database connection
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Function to create URL-friendly slugs
function create_slug($string) {
    // Convert to lowercase
    $string = strtolower($string);
    
    // Replace spaces with hyphens
    $string = str_replace(' ', '-', $string);
    
    // Remove special characters
    $string = preg_replace('/[^a-z0-9\-]/', '', $string);
    
    // Remove multiple hyphens
    $string = preg_replace('/-+/', '-', $string);
    
    // Trim hyphens from beginning and end
    $string = trim($string, '-');
    
    return $string;
}

// Begin transaction
$pdo->beginTransaction();

try {
    echo "Starting database population...<br>";
    
    // Insert admin user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, email_verified) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            'Admin',
            'admin@example.com',
            '$2y$10$8tHxL.q9BzwDhRXwwwR1COYz6TtxMQJqhN9V3UF9T3HJGQZsuHhJi',
            'admin',
            1
        ]);
        echo "Added admin user<br>";
    } else {
        echo "Admin user already exists<br>";
    }
    
    // Insert categories
    $categories = [
        'Gaming Laptops',
        'Business Laptops',
        'Monitors',
        'PC Components',
        'Gaming Accessories',
        'Networking',
        'Storage Devices'
    ];
    
    $category_ids = [];
    foreach ($categories as $category_name) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$category_name]);
        if ($category = $stmt->fetch()) {
            $category_ids[$category_name] = $category['id'];
            echo "Category already exists: $category_name<br>";
        } else {
            $slug = create_slug($category_name);
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$category_name, $slug, "Category for $category_name", 1]);
            $category_ids[$category_name] = $pdo->lastInsertId();
            echo "Added category: $category_name<br>";
        }
    }
    
    // Insert brands
    $brands = [
        ['Apple', 'Premium laptops and technology products', 'https://www.apple.com'],
        ['Samsung', 'Monitors, storage, and computer components', 'https://www.samsung.com'],
        ['LG', 'High-end monitors and displays', 'https://www.lg.com'],
        ['NVIDIA', 'Graphics cards and gaming technology', NULL],
        ['AMD', 'Processors and graphics solutions', NULL],
        ['Logitech', 'Premium gaming peripherals', NULL],
        ['Razer', 'Gaming accessories and laptops', NULL],
        ['ASUS', 'Networking and gaming products', 'https://www.asus.com'],
        ['Western Digital', 'Storage solutions', NULL],
        ['Seagate', 'Storage devices and solutions', NULL],
        ['Acer', 'Leading manufacturer of gaming and business laptops', NULL],
        ['HP', 'Premium computer hardware and accessories', 'https://www.hp.com'],
        ['Lenovo', 'Innovative laptops and gaming machines', 'https://www.lenovo.com'],
        ['Dell', 'Computer technology company', 'https://www.dell.com'],
        ['Sony', 'Multinational technology and entertainment company', 'https://www.sony.com']
    ];
    
    $brand_ids = [];
    foreach ($brands as $brand) {
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
        $stmt->execute([$brand[0]]);
        if ($existing_brand = $stmt->fetch()) {
            $brand_ids[$brand[0]] = $existing_brand['id'];
            echo "Brand already exists: {$brand[0]}<br>";
        } else {
            $slug = create_slug($brand[0]);
            $stmt = $pdo->prepare("INSERT INTO brands (name, slug, description, website, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$brand[0], $slug, $brand[1], $brand[2], 1]);
            $brand_ids[$brand[0]] = $pdo->lastInsertId();
            echo "Added brand: {$brand[0]}<br>";
        }
    }
    
    // Insert products
    $products = [
        // Gaming Laptops
        ['Acer Nitro V 15', 'Gaming Laptop with Intel Core i5 13420H, 16GB RAM, 512GB SSD, NVIDIA RTX 4050 6GB, 15.6" FHD 144Hz', 1299.99, 'Gaming Laptops', 'Acer', 'acer-nitro.jpg'],
        ['HP Victus 15', '2024 AI-Integrated Gaming Laptop, Ryzen 5 8645HS, 16GB RAM, RTX 4050, 15.6" 144Hz', 1199.99, 'Gaming Laptops', 'HP', 'hp-victus.jpg'],
        ['Lenovo Legion Slim 5', 'AMD Ryzen 7 8845HS, 16GB RAM, 1TB SSD, RTX 4070 8GB, 16" WQXGA 165Hz', 1799.99, 'Gaming Laptops', 'Lenovo', 'legion-slim.jpg'],
        
        // Business Laptops
        ['MacBook Air M1', '13.3" Retina Display, 8GB RAM, 256GB SSD, Apple M1 Chip', 999.99, 'Business Laptops', 'Apple', 'macbook-air.jpg'],
        ['Acer Swift Go 14', 'OLED, Intel i5 13500H, 16GB RAM, 512GB SSD, 2.8K 90Hz Display', 899.99, 'Business Laptops', 'Acer', 'swift-go.jpg'],
        ['Lenovo ThinkPad X1', 'Carbon Gen 11, Intel i7, 32GB RAM, 1TB SSD, 14" 2.8K OLED', 1699.99, 'Business Laptops', 'Lenovo', 'thinkpad-x1.jpg'],
        
        // Monitors
        ['LG 27GL850', '27" Ultragear Gaming Monitor, 1440p, 144Hz, 1ms, HDR', 449.99, 'Monitors', 'LG', 'lg-monitor.jpg'],
        ['Samsung Odyssey G7', '32" Curved Gaming Monitor, 240Hz, 1ms, QHD', 699.99, 'Monitors', 'Samsung', 'odyssey-g7.jpg'],
        ['Dell U2723QE', '27" 4K USB-C Hub Monitor', 649.99, 'Monitors', 'Dell', 'dell-monitor.jpg'],
        
        // PC Components
        ['NVIDIA RTX 4070', '12GB GDDR6X Graphics Card', 599.99, 'PC Components', 'NVIDIA', 'rtx-4070.jpg'],
        ['AMD Ryzen 7 7800X3D', '8-Core Processor with 3D V-Cache', 449.99, 'PC Components', 'AMD', 'ryzen-7.jpg'],
        ['Samsung 990 PRO', '2TB NVMe M.2 SSD', 199.99, 'PC Components', 'Samsung', 'samsung-ssd.jpg'],
        
        // Gaming Accessories
        ['Logitech G Pro X', 'Wireless Gaming Headset with Blue VO!CE', 199.99, 'Gaming Accessories', 'Logitech', 'gpro-headset.jpg'],
        ['Razer Huntsman V2', 'Analog Optical Gaming Keyboard', 249.99, 'Gaming Accessories', 'Razer', 'huntsman-v2.jpg'],
        ['Glorious Model O', 'Wireless Gaming Mouse, 19,000 DPI', 79.99, 'Gaming Accessories', 'Logitech', 'model-o.jpg'],
        
        // Networking
        ['ASUS RT-AX86U', 'Wi-Fi 6 Gaming Router', 249.99, 'Networking', 'ASUS', 'asus-router.jpg'],
        ['TP-Link Archer AX90', 'Tri-Band Wi-Fi 6 Router', 299.99, 'Networking', 'ASUS', 'tplink-router.jpg'],
        ['Netgear Nighthawk XR1000', 'Pro Gaming Router', 349.99, 'Networking', 'ASUS', 'nighthawk.jpg'],
        
        // Storage
        ['WD Black P50', '2TB External Gaming SSD', 299.99, 'Storage Devices', 'Western Digital', 'wd-black.jpg'],
        ['Seagate FireCuda', '4TB External Gaming Drive', 149.99, 'Storage Devices', 'Seagate', 'firecuda.jpg'],
        ['Samsung T7 Shield', '1TB Rugged Portable SSD', 119.99, 'Storage Devices', 'Samsung', 't7-shield.jpg']
    ];
    
    $products_added = 0;
    foreach ($products as $product) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$product[0]]);
        if ($stmt->fetch()) {
            echo "Product already exists: {$product[0]}<br>";
            continue;
        }
        
        $category_id = $category_ids[$product[3]];
        $brand_id = $brand_ids[$product[4]];
        $slug = create_slug($product[0]);
        $short_description = substr(strip_tags($product[1]), 0, 150) . '...';
        
        // Generate SKU
        $sku = strtoupper(substr($product[4], 0, 3)) . '-' . rand(1000, 9999);
        
        $stmt = $pdo->prepare("
            INSERT INTO products (
                name, 
                slug, 
                description, 
                short_description, 
                price, 
                category_id, 
                brand_id, 
                stock, 
                sku,
                image_url,
                is_featured,
                is_new,
                is_on_sale,
                is_deleted
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        
        $stmt->execute([
            $product[0],
            $slug,
            $product[1],
            $short_description,
            $product[2],
            $category_id,
            $brand_id,
            rand(5, 30), // Random stock
            $sku,
            $product[5],
            rand(0, 10) > 8 ? 1 : 0, // 20% chance of being featured
            1, // All items are new
            0, // Not on sale by default
            0  // Not deleted
        ]);
        
        $products_added++;
        echo "Added product: {$product[0]}<br>";
    }
    
    // Commit transaction
    $pdo->commit();
    echo "<h3>Database population completed successfully!</h3>";
    echo "<p>Added $products_added new products</p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo "<h3>Error: " . $e->getMessage() . "</h3>";
}
?> 