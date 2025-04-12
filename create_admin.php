<?php
require_once 'includes/db_connect.php';

try {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@ecomfinal.com']);
    $adminExists = $stmt->fetch();

    if (!$adminExists) {
        // Create admin user
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, is_banned, email_verified, created_at) 
            VALUES (?, ?, ?, 'admin', 0, 1, NOW())
        ");
        $stmt->execute(['Admin User', 'admin@ecomfinal.com', $password_hash]);
        echo "Admin user created successfully!\n";
        echo "Email: admin@ecomfinal.com\n";
        echo "Password: admin123\n";
    } else {
        echo "Admin user already exists!\n";
        echo "Email: admin@ecomfinal.com\n";
        echo "Password: admin123\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 