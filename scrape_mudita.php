<?php
/**
 * Mudita.com.np Tech Product Scraper
 * 
 * This script scrapes tech product data from Mudita.com.np and inserts it into our ecommerce database
 */

// Set execution time to unlimited for large scraping tasks
set_time_limit(0);

// Include database connection and functions
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Configuration
$base_url = 'https://mudita.com.np';
$categories_to_scrape = [
    '/shop/' => 'All Products'  // Changed to main shop page since category URLs might be different
];

// Initialize counters
$products_added = 0;
$products_failed = 0;

// Prepare statements for inserting brands, categories, and products
$check_brand = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
$insert_brand = $pdo->prepare("INSERT INTO brands (name, description, website) VALUES (?, ?, ?)");

$check_category = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
$insert_category = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");

$check_product = $pdo->prepare("SELECT id FROM products WHERE name = ? AND brand_id = ?");
$insert_product = $pdo->prepare("
    INSERT INTO products (
        name, 
        slug, 
        description, 
        short_description, 
        price, 
        category_id, 
        brand_id, 
        stock, 
        is_featured,
        is_new
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");

// Output log function
function log_message($message) {
    echo date('[Y-m-d H:i:s]') . " $message" . PHP_EOL;
}

// Generate slug from string
function create_slug($string) {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $string), '-'));
    return $slug;
}

// Get HTML content with cURL
function get_html_content($url) {
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    // Set headers
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Execute request
    $html = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        log_message("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    // Get HTTP response code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        log_message("HTTP Error: Received status code $http_code for URL: $url");
        return false;
    }
    
    return $html;
}

// Insert or get category
function get_or_create_category($name, $pdo, $check_category, $insert_category) {
    $slug = create_slug($name);
    $check_category->execute([$name]);
    if ($category = $check_category->fetch()) {
        return $category['id'];
    }
    
    $insert_category->execute([$name, $slug]);
    return $pdo->lastInsertId();
}

// Insert or get brand
function get_or_create_brand($name, $pdo, $check_brand, $insert_brand) {
    $check_brand->execute([$name]);
    if ($brand = $check_brand->fetch()) {
        return $brand['id'];
    }
    
    // Default description and website for tech brands
    $description = 'Quality tech products from ' . $name;
    $website = 'https://example.com/' . strtolower(str_replace(' ', '-', $name));
    
    $insert_brand->execute([$name, $description, $website]);
    return $pdo->lastInsertId();
}

// Main scraping function for a category page
function scrape_category_page($url, $category_name, $pdo, $base_url, $prepared_statements) {
    log_message("Scraping category: $category_name from $url");
    
    $html = get_html_content($base_url . $url);
    if (!$html) {
        log_message("Failed to load category page: $url");
        return [0, 0]; // No products added or failed
    }
    
    // Extract product links
    $product_links = [];
    preg_match_all('/<a\s+href="([^"]+)"\s+class="[^"]*product-link[^"]*"/i', $html, $matches);
    
    if (isset($matches[1]) && is_array($matches[1])) {
        $product_links = array_unique($matches[1]);
    }
    
    // If no product links found, try alternative pattern
    if (empty($product_links)) {
        preg_match_all('/<a\s+href="([^"]+)"\s+class="[^"]*woocommerce-LoopProduct-link[^"]*"/i', $html, $matches);
        if (isset($matches[1]) && is_array($matches[1])) {
            $product_links = array_unique($matches[1]);
        }
    }
    
    log_message("Found " . count($product_links) . " products to scrape");
    
    // Get or create the category
    $category_id = get_or_create_category(
        $category_name, 
        $pdo, 
        $prepared_statements['check_category'], 
        $prepared_statements['insert_category']
    );
    
    // Track success counts
    $products_added = 0;
    $products_failed = 0;
    
    // Process each product link
    foreach ($product_links as $link) {
        // Normalize and check link
        $link = str_replace($base_url, '', $link);
        if (!$link || strpos($link, '/product/') === false) {
            continue;
        }
        
        [$added, $failed] = scrape_product(
            $link, 
            $category_id, 
            $pdo, 
            $base_url, 
            $prepared_statements
        );
        
        $products_added += $added;
        $products_failed += $failed;
        
        // Add a small delay between requests
        usleep(500000); // 0.5 second delay
    }
    
    return [$products_added, $products_failed];
}

// Scrape a single product page
function scrape_product($url, $category_id, $pdo, $base_url, $prepared_statements) {
    log_message("Scraping product: $url");
    
    $html = get_html_content($base_url . $url);
    if (!$html) {
        log_message("Failed to load product page: $url");
        return [0, 1]; // Failed to add
    }
    
    // Extract product name
    preg_match('/<h1[^>]*class="[^"]*product_title[^"]*"[^>]*>(.*?)<\/h1>/is', $html, $name_match);
    $name = isset($name_match[1]) ? trim(strip_tags($name_match[1])) : '';
    
    if (empty($name)) {
        log_message("Failed to extract product name from: $url");
        return [0, 1]; // Failed to add
    }
    
    // Extract product price
    preg_match('/<span[^>]*class="[^"]*price[^"]*"[^>]*>.*?<bdi>(.*?)<\/bdi>/is', $html, $price_match);
    $price_text = isset($price_match[1]) ? trim(strip_tags($price_match[1])) : '';
    $price = 0;
    
    // Remove currency symbol and commas, then convert to float
    if (!empty($price_text)) {
        $price_text = preg_replace('/[^\d.,]/', '', $price_text); // Remove everything except digits, dots and commas
        $price_text = str_replace(',', '', $price_text); // Remove commas
        $price = (float) $price_text;
    }
    
    if ($price <= 0) {
        // Try alternative price pattern for sale prices
        preg_match('/<span[^>]*class="[^"]*price[^"]*"[^>]*>.*?<ins>.*?<bdi>(.*?)<\/bdi>/is', $html, $sale_price_match);
        $sale_price_text = isset($sale_price_match[1]) ? trim(strip_tags($sale_price_match[1])) : '';
        
        if (!empty($sale_price_text)) {
            $sale_price_text = preg_replace('/[^\d.,]/', '', $sale_price_text);
            $sale_price_text = str_replace(',', '', $sale_price_text);
            $price = (float) $sale_price_text;
        }
    }
    
    if ($price <= 0) {
        log_message("Failed to extract valid price for product: $name");
        $price = 999.99; // Default price if we can't determine it
    }
    
    // Extract description
    preg_match('/<div[^>]*class="[^"]*woocommerce-product-details__short-description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $desc_match);
    $short_description = isset($desc_match[1]) ? trim(strip_tags($desc_match[1])) : '';
    
    preg_match('/<div[^>]*class="[^"]*woocommerce-Tabs-panel--description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $full_desc_match);
    $description = isset($full_desc_match[1]) ? trim($full_desc_match[1]) : '';
    
    if (empty($description)) {
        $description = $short_description;
    }
    
    if (empty($short_description)) {
        $short_description = substr(strip_tags($description), 0, 255);
    }
    
    // Extract brand name from product name or description
    $brand_name = 'Mudita'; // Default brand
    if (preg_match('/(HP|Dell|Lenovo|Asus|Acer|MSI|Samsung|LG|Intel|AMD|NVIDIA|Corsair|Logitech|Razer)/i', $name, $brand_match)) {
        $brand_name = $brand_match[1];
    }
    
    // Get or create brand
    $brand_id = get_or_create_brand(
        $brand_name, 
        $pdo, 
        $prepared_statements['check_brand'], 
        $prepared_statements['insert_brand']
    );
    
    // Check if product already exists
    $prepared_statements['check_product']->execute([$name, $brand_id]);
    if ($prepared_statements['check_product']->fetch()) {
        log_message("Product already exists: $name");
        return [0, 0]; // No success, no failure
    }
    
    // Generate slug
    $slug = create_slug($name);
    
    // Default values
    $stock = rand(5, 30); // Random stock between 5-30
    $is_featured = rand(0, 10) > 8 ? 1 : 0; // Random 20% chance of being featured
    $is_new = 1; // All items are new since we're adding them now
    
    try {
        $prepared_statements['insert_product']->execute([
            $name,
            $slug,
            $description,
            $short_description,
            $price,
            $category_id,
            $brand_id,
            $stock,
            $is_featured,
            $is_new
        ]);
        
        log_message("Added product: $name - $price");
        return [1, 0]; // Success
    } catch (PDOException $e) {
        log_message("Failed to insert product: " . $e->getMessage());
        return [0, 1]; // Failed to add
    }
}

// Main execution block
try {
    // Prepare all statements
    $prepared_statements = [
        'check_brand' => $check_brand,
        'insert_brand' => $insert_brand,
        'check_category' => $check_category,
        'insert_category' => $insert_category,
        'check_product' => $check_product,
        'insert_product' => $insert_product
    ];
    
    log_message("Starting scraping process from $base_url");
    
    // Scrape each category
    foreach ($categories_to_scrape as $category_url => $category_name) {
        list($added, $failed) = scrape_category_page(
            $category_url, 
            $category_name, 
            $pdo, 
            $base_url, 
            $prepared_statements
        );
        
        $products_added += $added;
        $products_failed += $failed;
    }
    
    log_message("Scraping completed. Products added: $products_added, Failed: $products_failed");
    
} catch (Exception $e) {
    log_message("Critical error: " . $e->getMessage());
} 