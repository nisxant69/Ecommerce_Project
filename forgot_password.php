<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: forgot_password.php');
        exit();
    }
    
    $email = trim($_POST['email']);
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_banned = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $token, $expires]);
            
            // Send reset email
            $reset_link = "http://{$_SERVER['HTTP_HOST']}/ecomfinal/reset_password.php?token=" . $token;
            $to = $email;
            $subject = "Password Reset Request";
            $message = "
                <html>
                <head>
                    <title>Password Reset Request</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>Dear {$user['name']},</p>
                    <p>We received a request to reset your password. Click the link below to set a new password:</p>
                    <p><a href='{$reset_link}'>{$reset_link}</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <p>Best regards,<br>Your E-Commerce Team</p>
                </body>
                </html>
            ";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: E-Commerce Store <noreply@example.com>\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                set_flash_message('success', 'If an account exists with this email, you will receive password reset instructions.');
                header('Location: login.php');
                exit();
            } else {
                throw new Exception("Error sending email");
            }
        } else {
            // Don't reveal if email exists
            set_flash_message('success', 'If an account exists with this email, you will receive password reset instructions.');
            header('Location: login.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        set_flash_message('danger', 'Error processing your request. Please try again later.');
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4 text-center">Forgot Password</h1>
                
                <p class="text-muted mb-4">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>
                
                <form method="POST" id="forgotPasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">
                            Please enter a valid email address.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

<?php require_once 'includes/footer.php'; ?>
