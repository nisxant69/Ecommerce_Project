<?php
require_once 'includes/functions.php';

// Perform logout
logout_user();

// Redirect to login page
set_flash_message('success', 'You have been logged out successfully.');
header('Location: login.php');
exit();
