<?php
require_once 'includes/db_connect.php';

$required_tables = [
    'users',
    'products',
    'orders',
    'order_items',
    'categories',
    'reviews',
    'settings'
];

$missing_tables = [];
$existing_tables = [];

try {
    // Get list of existing tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_tables as $table) {
        if (!in_array($table, $tables)) {
            $missing_tables[] = $table;
        } else {
            $existing_tables[] = $table;
        }
    }
    
    echo "<h2>Database Verification Report</h2>";
    
    if (empty($missing_tables)) {
        echo "<p style='color: green;'>✓ All required tables exist!</p>";
    } else {
        echo "<p style='color: red;'>✗ Missing tables found:</p>";
        echo "<ul>";
        foreach ($missing_tables as $table) {
            echo "<li>{$table}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>Existing Tables:</h3>";
    echo "<ul>";
    foreach ($existing_tables as $table) {
        echo "<li>{$table}</li>";
    }
    echo "</ul>";
    
    // Check if tables have data
    echo "<h3>Table Data Count:</h3>";
    echo "<ul>";
    foreach ($existing_tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "<li>{$table}: {$count} records</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking database structure: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 