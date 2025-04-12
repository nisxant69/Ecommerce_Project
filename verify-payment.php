<?php
session_start();
require_once 'config/database.php';

// Khalti API settings from .env file
define('KHALTI_SECRET_KEY', 'efb56023d3da49ee8c29e89b189bdb7c');
define('KHALTI_VERIFY_URL', 'https://a.khalti.com/api/v2/epayment/initiate/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $amount = $_POST['amount'];

    // Prepare the request data
    $args = http_build_query(array(
        'token' => $token,
        'amount'  => $amount
    ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, KHALTI_VERIFY_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Key ' . KHALTI_SECRET_KEY
    ));

    // Execute the request
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status_code == 200) {
        $response_data = json_decode($response, true);
        
        // Check if the amount matches
        if ($response_data['amount'] == $amount) {
            echo json_encode(array(
                'success' => true,
                'message' => 'Payment verified successfully'
            ));
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => 'Amount mismatch'
            ));
        }
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'Payment verification failed'
        ));
    }
} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid request method'
    ));
} 