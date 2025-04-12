<?php
require_once 'includes/db_connect.php';

try {
    // Add missing indexes
    echo "Adding missing indexes...\n";
    
    // Function to check if index exists
    function indexExists($pdo, $table, $index_name) {
        $stmt = $pdo->prepare("SHOW INDEX FROM $table WHERE Key_name = ?");
        $stmt->execute([$index_name]);
        return $stmt->rowCount() > 0;
    }
    
    // Orders indexes
    if (!indexExists($pdo, 'orders', 'idx_orders_user')) {
        $pdo->exec("CREATE INDEX idx_orders_user ON orders(user_id)");
        echo "✅ Added idx_orders_user index\n";
    }
    if (!indexExists($pdo, 'orders', 'idx_orders_status')) {
        $pdo->exec("CREATE INDEX idx_orders_status ON orders(status_id)");
        echo "✅ Added idx_orders_status index\n";
    }
    
    // Reviews index
    if (!indexExists($pdo, 'reviews', 'idx_reviews_product')) {
        $pdo->exec("CREATE INDEX idx_reviews_product ON reviews(product_id)");
        echo "✅ Added idx_reviews_product index\n";
    }
    
    // Wishlist index
    if (!indexExists($pdo, 'wishlist', 'idx_wishlist_user')) {
        $pdo->exec("CREATE INDEX idx_wishlist_user ON wishlist(user_id)");
        echo "✅ Added idx_wishlist_user index\n";
    }
    
    // Login attempts index
    if (!indexExists($pdo, 'login_attempts', 'idx_login_attempts_email')) {
        $pdo->exec("CREATE INDEX idx_login_attempts_email ON login_attempts(email)");
        echo "✅ Added idx_login_attempts_email index\n";
    }
    
    // Add default order statuses
    echo "\nAdding default order statuses...\n";
    
    // First, check if order_status table exists and has data
    $result = $pdo->query("SELECT COUNT(*) FROM order_status");
    $count = $result->fetchColumn();
    
    if ($count < 5) {
        // Temporarily disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Clear existing data if any
        $pdo->exec("TRUNCATE TABLE order_status");
        
        // Add all statuses
        $stmt = $pdo->prepare("INSERT INTO order_status (name, description) VALUES (?, ?)");
        
        $statuses = [
            ['Pending', 'Order has been placed but not yet processed'],
            ['Processing', 'Order is being prepared for shipment'],
            ['Shipped', 'Order has been shipped to the customer'],
            ['Delivered', 'Order has been delivered to the customer'],
            ['Cancelled', 'Order has been cancelled']
        ];
        
        foreach ($statuses as $status) {
            $stmt->execute($status);
        }
        
        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "✅ Added order statuses\n";
    } else {
        echo "✅ Order statuses already exist\n";
    }
    
    // Add admin user
    echo "\nAdding admin user...\n";
    
    // First, check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    $admin_exists = $stmt->fetchColumn() > 0;
    
    if (!$admin_exists) {
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, email_verified) 
            VALUES (?, ?, ?, 'admin', TRUE)
        ");
        
        $admin_data = [
            'Admin',
            'admin@example.com',
            '$2y$10$8tHxL.q9BzwDhRXwwwR1COYz6TtxMQJqhN9V3UF9T3HJGQZsuHhJi' // password: admin123
        ];
        
        $stmt->execute($admin_data);
        echo "✅ Added admin user\n";
    } else {
        echo "✅ Admin user already exists\n";
    }
    
    echo "\nAll fixes completed successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 