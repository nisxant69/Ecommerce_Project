<?php
require_once 'includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $phone = sanitize_input($_POST['phone']);
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $user_id]);
        
        set_flash_message('success', 'Profile updated successfully!');
    } catch (PDOException $e) {
        error_log("Error updating profile: " . $e->getMessage());
        set_flash_message('error', 'Error updating profile. Please try again.');
    }
}

header('Location: account.php');
exit();
