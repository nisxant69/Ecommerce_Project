<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Session started here

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start logging
error_log("=== Registration page loaded ===");

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Handle registration form submission
$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== START OF REGISTRATION ATTEMPT ===");
    error_log("Raw POST data: " . print_r($_POST, true));
    
    // Check if form fields are set
    error_log("Checking form fields:");
    error_log("name set: " . (isset($_POST['name']) ? 'yes' : 'no'));
    error_log("email set: " . (isset($_POST['email']) ? 'yes' : 'no'));
    error_log("password set: " . (isset($_POST['password']) ? 'yes' : 'no'));
    error_log("confirm_password set: " . (isset($_POST['confirm_password']) ? 'yes' : 'no'));
    error_log("terms set: " . (isset($_POST['terms']) ? 'yes' : 'no'));
    error_log("csrf_token set: " . (isset($_POST['csrf_token']) ? 'yes' : 'no'));
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        error_log("CSRF token validation failed");
        error_log("Received token: " . ($_POST['csrf_token'] ?? 'none'));
        error_log("Session token: " . ($_SESSION['csrf_token'] ?? 'none'));
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: register.php');
        exit();
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_accepted = isset($_POST['terms']);
    
    error_log("Processed form data - Name: $name, Email: $email, Terms accepted: " . ($terms_accepted ? 'yes' : 'no'));
    
    // Validate name
    if (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Validate password
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }
    
    // Confirm passwords match
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check terms acceptance
    if (!$terms_accepted) {
        $errors[] = 'You must accept the terms and conditions.';
    }
    
    if (!empty($errors)) {
        error_log("Registration validation errors: " . print_r($errors, true));
    }
    
    if (empty($errors)) {
        try {
            error_log("Starting database operations...");
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            error_log("Checking for existing email: $email");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                error_log("Registration failed: Email already exists - $email");
                $errors[] = 'Email address is already registered.';
            } else {
                error_log("Email is available, proceeding with registration");
                
                // Create user account
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')";
                error_log("Preparing insert query: $insert_query");
                
                $stmt = $pdo->prepare($insert_query);
                
                try {
                    error_log("Attempting to insert new user - Name: $name, Email: $email");
                    $result = $stmt->execute([$name, $email, $password_hash]);
                    
                    if ($result) {
                        $user_id = $pdo->lastInsertId();
                        error_log("User registered successfully: ID=$user_id, Email=$email");
                        
                        set_flash_message('success', 'Registration successful! You can now log in.');
                        header('Location: login.php');
                        exit();
                    } else {
                        error_log("Failed to insert new user - PDO error info: " . print_r($stmt->errorInfo(), true));
                        $errors[] = 'Failed to create account. Please try again.';
                    }
                } catch (PDOException $e) {
                    error_log("Database error during user insertion: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
                    $errors[] = 'Error creating account: ' . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            error_log("Database error during email check: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
            $errors[] = 'Error creating account. Please try again later.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4 text-center">Create Account</h1>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($name); ?>" 
                               required minlength="2" maxlength="100">
                        <div class="invalid-feedback">
                            Please enter your full name (2-100 characters).
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required>
                        <div class="invalid-feedback">
                            Please enter a valid email address.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" 
                                   required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Passwords do not match.
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a>
                        </label>
                        <div class="invalid-feedback">
                            You must agree to the terms and conditions.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password visibility toggle
function togglePasswordVisibility(inputId, buttonId) {
    document.getElementById(buttonId).addEventListener('click', function() {
        const input = document.getElementById(inputId);
        const icon = this.querySelector('i');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
}

togglePasswordVisibility('password', 'togglePassword');
togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');

// Form validation
const form = document.getElementById('registerForm');
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');

form.addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
    
    this.classList.add('was-validated');
});

confirmPassword.addEventListener('input', function() {
    if (password.value === this.value) {
        this.setCustomValidity('');
    } else {
        this.setCustomValidity('Passwords do not match');
    }
});

password.addEventListener('input', function() {
    const value = this.value;
    let isValid = true;
    if (value.length < 8) isValid = false;
    if (!/[A-Z]/.test(value)) isValid = false;
    if (!/[a-z]/.test(value)) isValid = false;
    if (!/[0-9]/.test(value)) isValid = false;
    this.setCustomValidity(isValid ? '' : 'Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.');
});
</script>

<?php require_once 'includes/footer.php'; ?>