<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
}

// Get redirect URL
$redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_URL) : 'index.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit();
    }
    
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        set_flash_message('danger', 'Please fill in all fields.');
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit();
    }
    
    try {
        // Check for failed login attempts
        $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute([$email]);
        $failed_attempts = $stmt->rowCount();
        
        if ($failed_attempts >= 5) {
            set_flash_message('danger', 'Too many failed attempts. Please try again in 15 minutes.');
            header('Location: login.php?redirect=' . urlencode($redirect));
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_banned = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if email is verified
            if (!$user['email_verified']) {
                set_flash_message('warning', 'Please verify your email address before logging in.');
                header('Location: login.php?redirect=' . urlencode($redirect));
                exit();
            }
            
            // Clear failed attempts
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
            $stmt->execute([$email]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            session_regenerate_id(true);
            
            // Handle remember me
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expiry]);
                
                setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
            }
            
            error_log("User logged in: ID=" . $user['id'] . ", Email=$email, Role=" . $user['role']);
            
            if (!empty($_SESSION['cart'])) {
                set_flash_message('info', 'Your cart has been saved to your account.');
            }
            
            set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
            
            // Redirect based on user role
            if ($user['role'] === 'admin') {
                error_log("Admin user redirecting to dashboard");
                header('Location: admin/dashboard.php');
                exit();
            } else {
                header('Location: ' . $redirect);
                exit();
            }
        } else {
            // Record failed attempt
            $stmt = $pdo->prepare("INSERT INTO login_attempts (email, attempt_time) VALUES (?, NOW())");
            $stmt->execute([$email]);
            
            error_log("Login failed for email: $email - User not found or incorrect password");
            set_flash_message('danger', 'Invalid email or password.');
            header('Location: login.php?redirect=' . urlencode($redirect));
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        set_flash_message('danger', 'Error during login. Please try again later.');
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit();
    }
}

// Now that all header-modifying logic is done, include the header and output HTML
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4 text-center">Login</h1>
                
                <form method="POST" action="login.php?redirect=<?php echo htmlspecialchars(urlencode($redirect)); ?>" id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-decoration-none">Forgot your password?</a>
                    </div>
                </form>
                
                <hr class="my-4">
                <div class="text-center">
                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Register now</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    password.type = password.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

<?php require_once 'includes/footer.php'; ?>