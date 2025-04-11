<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Require login
require_login();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: profile.php');
        exit();
    }
    
    $name = trim($_POST['name']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate name
    if (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }
    
    try {
        // Get current user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        // Update profile
        if (empty($errors)) {
            // If password change is requested
            if (!empty($current_password)) {
                if (!password_verify($current_password, $user['password_hash'])) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (empty($new_password)) {
                    $errors[] = 'New password is required.';
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = 'New passwords do not match.';
                } elseif (strlen($new_password) < 8) {
                    $errors[] = 'Password must be at least 8 characters long.';
                } else {
                    // Update name and password
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, password_hash = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name,
                        password_hash($new_password, PASSWORD_DEFAULT),
                        $_SESSION['user_id']
                    ]);
                    
                    $_SESSION['user_name'] = $name;
                    set_flash_message('success', 'Profile updated successfully.');
                    header('Location: profile.php');
                    exit();
                }
            } else {
                // Update only name
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $_SESSION['user_id']]);
                
                $_SESSION['user_name'] = $name;
                set_flash_message('success', 'Profile updated successfully.');
                header('Location: profile.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $errors[] = 'Error updating profile. Please try again later.';
    }
}

// Get user data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.id) as total_items 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    set_flash_message('danger', 'Error loading profile data.');
    $user = null;
    $recent_orders = [];
}
?>

<div class="container py-5">
    <div class="row">
        <!-- Profile Summary -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <span class="display-1">
                            <i class="fas fa-user-circle text-primary"></i>
                        </span>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h6>Orders</h6>
                            <p class="text-primary mb-0"><?php echo number_format($user['total_orders']); ?></p>
                        </div>
                        <div class="col-6">
                            <h6>Spent</h6>
                            <p class="text-primary mb-0"><?php echo format_price($user['total_spent']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Edit Profile</h5>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($flash = get_flash_message()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   readonly disabled>
                            <div class="form-text">Email cannot be changed.</div>
                        </div>
                        
                        <hr>
                        
                        <h6>Change Password</h6>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <div class="form-text">Leave blank if you don't want to change your password.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="8">
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <?php if (!empty($recent_orders)): ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Recent Orders</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $order['total_items']; ?> items</td>
                                    <td><?php echo format_price($order['total_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="order.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Password validation
const form = document.querySelector('form');
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const currentPassword = document.getElementById('current_password');

form.addEventListener('submit', function(e) {
    // Reset custom validity
    newPassword.setCustomValidity('');
    confirmPassword.setCustomValidity('');
    
    // If changing password
    if (currentPassword.value) {
        if (newPassword.value.length < 8) {
            newPassword.setCustomValidity('Password must be at least 8 characters long.');
            e.preventDefault();
            return;
        }
        
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match.');
            e.preventDefault();
            return;
        }
    }
});

// Clear custom validity on input
newPassword.addEventListener('input', () => newPassword.setCustomValidity(''));
confirmPassword.addEventListener('input', () => confirmPassword.setCustomValidity(''));
</script>

<?php require_once 'includes/footer.php'; ?>
