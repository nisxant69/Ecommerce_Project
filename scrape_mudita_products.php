<?php
require_once 'config/database.php';

// Function to safely download and save image
function saveImage($imageUrl, $savePath) {
    $ch = curl_init($imageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $rawData = curl_exec($ch);
    curl_close($ch);

    if (!file_exists(dirname($savePath))) {
        mkdir(dirname($savePath), 0777, true);
    }
    
    return file_put_contents($savePath, $rawData);
}

// Function to clean price string
function cleanPrice($price) {
    return (float) preg_replace('/[^0-9.]/', '', $price);
}

// Create images directory if it doesn't exist
$image_dir = 'images/products';
if (!file_exists($image_dir)) {
    mkdir($image_dir, 0777, true);
}

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// First, ensure we have the "Mudita Products" category
$category_query = "INSERT IGNORE INTO categories (name, description) VALUES (?, ?)";
$cat_stmt = $conn->prepare($category_query);
$cat_name = "Mudita Products";
$cat_desc = "Products from Mudita Nepal";
$cat_stmt->bind_param("ss", $cat_name, $cat_desc);
$cat_stmt->execute();

$category_id = $conn->insert_id ?: mysqli_query($conn, "SELECT id FROM categories WHERE name = 'Mudita Products'")->fetch_assoc()['id'];

// Prepare product insert statement
$product_query = "INSERT INTO products (name, description, price, image, stock, category_id) VALUES (?, ?, ?, ?, ?, ?)";
$prod_stmt = $conn->prepare($product_query);

// URLs to scrape
$urls = [
    'https://www.mudita.com.np/',
    'https://www.mudita.com.np/collections/all'
];

foreach ($urls as $url) {
    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);
    
    if ($html === false) {
        echo "Error fetching URL: " . curl_error($ch) . "<br>";
        continue;
    }

    // Create a DOMDocument instance
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Find product elements
    $products = $xpath->query("//div[contains(@class, 'product')]");

    foreach ($products as $product) {
        // Extract product information
        $name = $xpath->evaluate("string(.//h2[contains(@class, 'product-title')])", $product);
        $price = $xpath->evaluate("string(.//span[contains(@class, 'price')])", $product);
        $image = $xpath->evaluate("string(.//img/@src)", $product);
        
        if (empty($name) || empty($price) || empty($image)) {
            continue;
        }

        // Clean up the data
        $name = trim($name);
        $price = cleanPrice($price);
        $description = "Product from Mudita Nepal - " . $name;
        $stock = 10; // Default stock value

        // Download and save image
        $image_filename = 'images/products/' . basename($image);
        if (saveImage($image, $image_filename)) {
            // Insert product into database
            $prod_stmt->bind_param("ssdsii", 
                $name,
                $description,
                $price,
                $image_filename,
                $stock,
                $category_id
            );

            if ($prod_stmt->execute()) {
                echo "Added product: $name<br>";
            } else {
                echo "Error adding product $name: " . $prod_stmt->error . "<br>";
            }
        }
    }
}

curl_close($ch);
mysqli_close($conn);

echo "Done importing products from Mudita Nepal!"; 