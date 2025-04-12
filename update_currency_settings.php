<?php
require_once 'includes/db_connect.php';

try {
    $pdo->beginTransaction();

    // Update currency settings
    $settings = [
        'currency' => 'NPR',
        'currency_symbol' => 'रु'
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$key, $value, $value]);
    }

    $pdo->commit();
    echo "Currency settings updated successfully!";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error updating currency settings: " . $e->getMessage();
}
?> 