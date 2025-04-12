// Khalti Payment Integration
let config = {
    // Public key from .env file
    "publicKey": "d8f7258e9c984d48bb8ac083dbee18da",
    "productIdentity": "1234567890",
    "productName": "E-commerce Checkout",
    "productUrl": "http://localhost/ecomfinal/",
    "eventHandler": {
        onSuccess(payload) {
            // hit merchant api for initiating verification
            console.log(payload);
            $.ajax({
                url: "verify-payment.php",
                type: 'POST',
                data: {
                    token: payload.token,
                    amount: payload.amount
                },
                success: function(response) {
                    console.log(response);
                    let res = JSON.parse(response);
                    if (res.success) {
                        // Payment successful
                        alert("Payment Successful!");
                        // Save order details and redirect to success page
                        saveOrder(payload);
                    } else {
                        // Payment verification failed
                        alert("Payment verification failed!");
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                    alert("Error occurred while verifying payment!");
                }
            });
        },
        onError(error) {
            console.log(error);
            alert("Error occurred during payment!");
        },
        onClose() {
            console.log('Payment widget closed');
        }
    }
};

// Initialize Khalti payment
function initiateKhaltiPayment(amount) {
    config.amount = amount * 100; // Convert to paisa
    let checkout = new KhaltiCheckout(config);
    checkout.show({
        amount: config.amount
    });
}

// Function to save order details after successful payment
function saveOrder(payload) {
    $.ajax({
        url: "save-order.php",
        type: 'POST',
        data: {
            payment_token: payload.token,
            payment_amount: payload.amount,
            payment_method: 'khalti'
        },
        success: function(response) {
            console.log(response);
            let res = JSON.parse(response);
            if (res.success) {
                // Redirect to success page
                window.location.href = "order-success.php?order_id=" + res.order_id;
            } else {
                alert("Error saving order details!");
            }
        },
        error: function(xhr, status, error) {
            console.error(error);
            alert("Error occurred while saving order!");
        }
    });
}

// Form validation
$(document).ready(function() {
    $('#checkout-form').on('submit', function(e) {
        e.preventDefault();
        
        // Basic form validation
        let fullName = $('#full_name').val();
        let email = $('#email').val();
        let phone = $('#phone').val();
        let address = $('#address').val();
        
        if (!fullName || !email || !phone || !address) {
            alert("Please fill in all required fields!");
            return false;
        }
        
        // Email validation
        let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert("Please enter a valid email address!");
            return false;
        }
        
        // Phone validation (assuming Nepal phone number format)
        let phoneRegex = /^(\+977|0)?[9][6-9]\d{8}$/;
        if (!phoneRegex.test(phone)) {
            alert("Please enter a valid phone number!");
            return false;
        }
        
        // If validation passes, get the total amount and initiate payment
        let amount = parseFloat($('#total-amount').text());
        if (amount > 0) {
            initiateKhaltiPayment(amount);
        } else {
            alert("Invalid amount!");
        }
    });
}); 