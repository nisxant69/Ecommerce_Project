<?php
session_start(); // Start the session
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Clear remember token if exists
if (isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    } catch (PDOException $e) {
        error_log("Error clearing remember token: " . $e->getMessage());
    }
}

// Perform logout
$_SESSION = []; // Clear all session variables
session_destroy(); // Destroy the session

// Redirect to login page
set_flash_message('success', 'You have been logged out successfully.');
header('Location: login.php');
exit();