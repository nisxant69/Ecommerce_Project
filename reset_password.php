<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;

if ($token) {
    try {
        // Check if token exists and is valid
        $stmt = $pdo->prepare("
            SELECT user_id, created_at 
            FROM password_resets 
            WHERE token = ? 
            AND expires_at > NOW() 
            AND used = 0
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if ($result) {
            $valid_token = true;
            $user_id = $result['user_id'];
        }
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
    }
}

if (!$valid_token) {
    set_flash_message('danger', 'Invalid or expired password reset link.');
    header('Location: forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
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
            // Start transaction
            $pdo->beginTransaction();
            
            // Update password with rate limiting check
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?,
                    updated_at = NOW()
                WHERE id = ?
                AND (SELECT COUNT(*) FROM password_resets 
                     WHERE user_id = ? 
                     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) <= 3
            ");
            $stmt->execute([
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                $user_id,
                $user_id
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Too many password reset attempts. Please try again later.');
            }
            
            // Mark token as used
            $stmt = $pdo->prepare("
                UPDATE password_resets 
                SET used = 1 
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            // Commit transaction
            $pdo->commit();
            
            set_flash_message('success', 'Your password has been reset successfully. Please login with your new password.');
            header('Location: login.php');
            exit();
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $errors[] = 'Error resetting password. Please try again later.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4 text-center">Reset Password</h1>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="resetPasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
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
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
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
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
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
const form = document.getElementById('resetPasswordForm');
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
