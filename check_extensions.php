<?php
$required_extensions = ['curl', 'dom', 'mysqli'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "Please enable the following PHP extensions in your php.ini file:<br>";
    foreach ($missing_extensions as $ext) {
        echo "- $ext<br>";
    }
    echo "<br>In XAMPP, you can enable these by uncommenting the corresponding lines in php.ini";
    exit;
}

echo "All required extensions are loaded. You can proceed with importing products."; 