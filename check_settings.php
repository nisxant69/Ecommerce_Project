<?php
require_once 'includes/db_connect.php';

try {
    $stmt = $pdo->query("SELECT * FROM settings");
    echo "<pre>";
    while ($row = $stmt->fetch()) {
        echo htmlspecialchars($row['setting_key']) . ": " . htmlspecialchars($row['setting_value']) . "\n";
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 