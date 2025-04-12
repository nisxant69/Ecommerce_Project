<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    set_flash_message('danger', 'Invalid verification link.');
    header('Location: login.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE verification_token = ? AND verification_expiry > NOW() AND email_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Verify the email
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verification_expiry = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        set_flash_message('success', 'Email verified successfully! You can now login.');
        error_log("Email verified for user: ID=" . $user['id'] . ", Email=" . $user['email']);
    } else {
        set_flash_message('danger', 'Invalid or expired verification link.');
    }
} catch (PDOException $e) {
    error_log("Email verification error: " . $e->getMessage());
    set_flash_message('danger', 'Error verifying email. Please try again later.');
}

header('Location: login.php');
exit();
?> 