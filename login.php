<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Get redirect URL if set
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: login.php');
        exit();
    }
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        // Get user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_banned = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Sync cart if guest cart exists
            if (!empty($_SESSION['cart'])) {
                // Cart will be maintained in session
                set_flash_message('info', 'Your cart has been saved to your account.');
            }
            
            set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
            header('Location: ' . $redirect);
            exit();
        } else {
            set_flash_message('danger', 'Invalid email or password.');
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        set_flash_message('danger', 'Error during login. Please try again later.');
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4 text-center">Login</h1>
                
                <form method="POST" action="login.php?redirect=<?php echo urlencode($redirect); ?>" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
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
// Password visibility toggle
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

<?php require_once 'includes/footer.php'; ?>
