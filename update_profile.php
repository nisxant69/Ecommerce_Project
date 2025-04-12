<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug logging
error_log("Update profile - Session status: " . session_status());
error_log("Update profile - User ID: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Update profile - POST data: " . print_r($_POST, true));

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('warning', 'Please log in to update your profile.');
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'Invalid request method.');
    header('Location: account.php');
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('danger', 'Invalid security token. Please try again.');
    header('Location: account.php');
    exit();
}

// Validate input
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$errors = [];

// Validate name
if (empty($name)) {
    $errors[] = 'Name is required.';
} elseif (!preg_match('/^[A-Za-z\s]{2,50}$/', $name)) {
    $errors[] = 'Name must be 2-50 characters long and contain only letters and spaces.';
}

// Validate phone (optional)
if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
    $errors[] = 'Phone number must be 10 digits.';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        set_flash_message('danger', $error);
    }
    header('Location: account.php');
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update user profile
    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, 
            phone = ?, 
            updated_at = NOW() 
        WHERE id = ? 
        AND is_banned = 0
    ");
    
    $result = $stmt->execute([
        $name,
        $phone ?: null, // Convert empty string to null
        $_SESSION['user_id']
    ]);
    
    if (!$result || $stmt->rowCount() === 0) {
        throw new Exception('Failed to update profile. Please try again.');
    }
    
    // Update session name
    $_SESSION['user_name'] = $name;
    
    // Commit transaction
    $pdo->commit();
    
    // Log success
    error_log("Profile updated successfully for user ID: " . $_SESSION['user_id']);
    
    set_flash_message('success', 'Profile updated successfully!');
    header('Location: account.php');
    exit();
    
} catch (PDOException $e) {
    // Roll back transaction on database error
    $pdo->rollBack();
    error_log("Database error in update_profile.php: " . $e->getMessage());
    set_flash_message('danger', 'An error occurred while updating your profile. Please try again later.');
    header('Location: account.php');
    exit();
    
} catch (Exception $e) {
    // Roll back transaction on other errors
    $pdo->rollBack();
    error_log("Error in update_profile.php: " . $e->getMessage());
    set_flash_message('danger', $e->getMessage());
    header('Location: account.php');
    exit();
}
