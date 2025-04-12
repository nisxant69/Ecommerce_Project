<?php
require_once 'env.php';

// Khalti Configuration
// Khalti Test Environment Configuration
define('KHALTI_PUBLIC_KEY', getenv('KHALTI_PUBLIC_KEY') ?: '');
define('KHALTI_SECRET_KEY', getenv('KHALTI_SECRET_KEY') ?: '');
define('KHALTI_VERIFY_URL', getenv('KHALTI_VERIFY_URL') ?: 'https://a.khalti.com/api/v2/epayment/initiate/');
?>
