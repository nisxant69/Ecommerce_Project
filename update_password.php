<?php
require_once 'includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Invalid form submission.');
        header('Location: account.php');
        exit();
    }

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    $errors = [];

    // Validate password
    if (strlen($new_password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $new_password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    // Verify passwords match
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match.';
    }

    try {
        if (!empty($errors)) {
            foreach ($errors as $error) {
                set_flash_message('error', $error);
            }
            header('Location: account.php');
            exit();
        }

        // Get current user's password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            set_flash_message('error', 'Current password is incorrect.');
            header('Location: account.php');
            exit();
        }

        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $user_id]);
        
        set_flash_message('success', 'Password updated successfully!');
    } catch (PDOException $e) {
        error_log("Error updating password: " . $e->getMessage());
        set_flash_message('error', 'Error updating password. Please try again.');
    }
}

header('Location: account.php');
exit();
