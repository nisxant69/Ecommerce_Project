<?php
require_once 'config/database.php';

// Drop tables in correct order (reverse of creation) due to foreign key constraints
$drop_tables_sql = "
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS wishlist;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;
";

if (mysqli_multi_query($conn, $drop_tables_sql)) {
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));
    
    echo "Tables dropped successfully! Now you can run setup_database.php to recreate them.";
} else {
    echo "Error dropping tables: " . mysqli_error($conn);
}

mysqli_close($conn); 