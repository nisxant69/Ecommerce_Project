<?php
require_once 'config/database.php';

// Read the SQL file
$sql = file_get_contents('setup_tables.sql');

// Execute multiple SQL statements
if (mysqli_multi_query($conn, $sql)) {
    do {
        // Store first result set
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));
    
    echo "Database tables created successfully!";
} else {
    echo "Error creating database tables: " . mysqli_error($conn);
}

mysqli_close($conn);
?> 