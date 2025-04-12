<?php
require_once 'includes/db_connect.php';

try {
    // Delete existing admin user
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    $stmt->execute(['admin@ecomfinal.com']);
    echo "Existing admin user deleted (if any existed).\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 