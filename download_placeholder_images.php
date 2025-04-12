<?php
/**
 * Placeholder Image Downloader
 * 
 * This script downloads placeholder jewelry images from placeholder services
 * to use for our product catalog.
 */

// Directory to save images
$save_dir = __DIR__ . '/assets/images/products/';

// Create directory if it doesn't exist
if (!file_exists($save_dir)) {
    mkdir($save_dir, 0755, true);
    echo "Created directory: $save_dir<br>";
}

// List of placeholder image URLs
$placeholder_urls = [
    // Jewelry placeholder images from placeholder services
    'https://source.unsplash.com/random/600x600/?jewelry,necklace',
    'https://source.unsplash.com/random/600x600/?jewelry,ring',
    'https://source.unsplash.com/random/600x600/?jewelry,earrings',
    'https://source.unsplash.com/random/600x600/?jewelry,bracelet',
    'https://source.unsplash.com/random/600x600/?jewelry,pendant',
    'https://source.unsplash.com/random/600x600/?gold,necklace',
    'https://source.unsplash.com/random/600x600/?silver,ring',
    'https://source.unsplash.com/random/600x600/?diamond,jewelry',
    'https://source.unsplash.com/random/600x600/?gemstone,jewelry',
    'https://source.unsplash.com/random/600x600/?crystal,jewelry',
    'https://source.unsplash.com/random/600x600/?anklet,jewelry',
    'https://source.unsplash.com/random/600x600/?silver,jewelry',
    'https://source.unsplash.com/random/600x600/?gold,jewelry',
    'https://source.unsplash.com/random/600x600/?handmade,jewelry',
    'https://source.unsplash.com/random/600x600/?ethnic,jewelry',
    'https://source.unsplash.com/random/600x600/?bohemian,jewelry',
    'https://source.unsplash.com/random/600x600/?elegant,jewelry',
    'https://source.unsplash.com/random/600x600/?modern,jewelry',
    'https://source.unsplash.com/random/600x600/?traditional,jewelry',
    'https://source.unsplash.com/random/600x600/?luxury,jewelry',
];

$downloaded_images = [];
$failed_images = [];

// Download images
foreach ($placeholder_urls as $index => $url) {
    // Create a unique filename
    $filename = 'jewelry_' . ($index + 1) . '.jpg';
    $save_path = $save_dir . $filename;
    
    echo "Downloading image $filename...<br>";
    
    // Check if file already exists
    if (file_exists($save_path)) {
        echo "File already exists: $filename. Skipping.<br>";
        $downloaded_images[] = $filename;
        continue;
    }
    
    // Try to download the image
    $image_data = @file_get_contents($url);
    
    // If download fails, try again with a slight delay
    if ($image_data === false) {
        echo "First attempt failed, retrying...<br>";
        sleep(1);
        $image_data = @file_get_contents($url);
    }
    
    if ($image_data === false) {
        echo "Failed to download image from $url<br>";
        $failed_images[] = $url;
        continue;
    }
    
    // Save the image
    if (file_put_contents($save_path, $image_data)) {
        echo "Successfully downloaded and saved $filename<br>";
        $downloaded_images[] = $filename;
    } else {
        echo "Failed to save image to $save_path<br>";
        $failed_images[] = $url;
    }
    
    // Add a small delay to prevent rate limiting
    usleep(500000); // 0.5 seconds
}

// Summary
echo "<h3>Download Summary</h3>";
echo "<p>Successfully downloaded " . count($downloaded_images) . " images</p>";
echo "<p>Failed to download " . count($failed_images) . " images</p>";

if (count($downloaded_images) > 0) {
    echo "<h4>Downloaded Images:</h4>";
    echo "<ul>";
    foreach ($downloaded_images as $image) {
        echo "<li>$image</li>";
    }
    echo "</ul>";
}

if (count($failed_images) > 0) {
    echo "<h4>Failed URLs:</h4>";
    echo "<ul>";
    foreach ($failed_images as $url) {
        echo "<li>$url</li>";
    }
    echo "</ul>";
}
?> 