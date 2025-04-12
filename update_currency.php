<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get currency code from request
$code = sanitize_input($_GET['code'] ?? '');
$redirect = $_GET['redirect'] ?? 'index.php';

// Validate currency exists
try {
    $stmt = $pdo->prepare("SELECT code FROM currencies WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$code]);
    $currency = $stmt->fetch();
    
    if ($currency) {
        // Set the currency in session
        $_SESSION['currency'] = $currency['code'];
    }
} catch (PDOException $e) {
    error_log("Error setting currency: " . $e->getMessage());
}

// Redirect back
header('Location: ' . $redirect);
exit();
?> 