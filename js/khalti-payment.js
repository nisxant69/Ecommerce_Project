// Initialize Khalti configuration
const config = {
    publicKey: "YOUR_PUBLIC_KEY_HERE",
    productIdentity: "1234567890",
    productName: "E-commerce Order",
    productUrl: window.location.origin,
    eventHandler: {
        onSuccess(payload) {
            // Handle successful payment
            verifyPayment(payload);
        },
        onError(error) {
            // Handle errors
            console.log(error);
            alert("Payment failed. Please try again.");
        },
        onClose() {
            console.log("Payment widget closed");
        }
    }
};

// Create a new Khalti checkout instance
let checkout = new KhaltiCheckout(config);

// Function to initiate payment
function initiateKhaltiPayment(amount) {
    // Amount should be in paisa (100 paisa = 1 NPR)
    checkout.show({ amount: amount * 100 });
}

// Function to verify payment with our server
async function verifyPayment(payload) {
    try {
        const response = await fetch("process_payment.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                token: payload.token,
                amount: payload.amount,
                shipping_name: document.getElementById("shipping_name").value,
                shipping_email: document.getElementById("shipping_email").value,
                shipping_phone: document.getElementById("shipping_phone").value,
                shipping_address: document.getElementById("shipping_address").value
            }),
        });

        const data = await response.json();
        
        if (data.success) {
            alert("Payment successful! Your order has been placed.");
            window.location.href = "order_confirmation.php?order_id=" + data.order_id;
        } else {
            alert("Payment verification failed. Please contact support.");
        }
    } catch (error) {
        console.error("Error:", error);
        alert("An error occurred while processing your payment. Please try again.");
    }
} 