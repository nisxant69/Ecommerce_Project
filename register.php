<?php
// Start the session (ensure this is done before any output)
session_start();

require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // For set_flash_message, verify_csrf_token, etc.

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
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: register.php');
        exit();
    }
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
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
    
    // Confirm passwords match
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Email address is already registered.';
            } else {
                // Create user account
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role) 
                    VALUES (?, ?, ?, 'user')
                ");
                $stmt->execute([
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);
                
                // Get the new user's ID
                $user_id = $pdo->lastInsertId();
                
                // Log the user in
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = 'user';
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                set_flash_message('success', 'Registration successful! Welcome to our store.');
                header('Location: index.php');
                exit();
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = 'Error creating account. Please try again later.';
        }
    }
}

// Now that all header-modifying logic is done, include the header and output HTML
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
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
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
                        <input type="checkbox" class="form-check-input" id="terms" required>
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
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
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
    
    // Check password match
    if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
    
    this.classList.add('was-validated');
});

// Clear custom validity on input
confirmPassword.addEventListener('input', function() {
    if (password.value === this.value) {
        this.setCustomValidity('');
    } else {
        this.setCustomValidity('Passwords do not match');
    }
});

// Password strength validation
password.addEventListener('input', function() {
    const value = this.value;
    let isValid = true;
    
    if (value.length < 8) isValid = false;
    if (!/[A-Z]/.test(value)) isValid = false;
    if (!/[a-z]/.test(value)) isValid = false;
    if (!/[0-9]/.test(value)) isValid = false;
    
    if (!isValid) {
        this.setCustomValidity('Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>