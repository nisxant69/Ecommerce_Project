<?php
// // Start the session (ensure this is done before any output)
// session_start();

// Include the database connection
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // For is_logged_in(), set_flash_message(), etc.
require_once 'includes/header.php';

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug logging
error_log("Account page - Session status: " . session_status());
error_log("Account page - User ID: " . ($_SESSION['user_id'] ?? 'not set'));

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('warning', 'Please log in to access your account.');
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Initialize variables
$user = null;
$orders = [];
$error = null;

try {
    // Begin transaction for consistent data
    $pdo->beginTransaction();
    
    // Fetch user details with error handling
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, email_verified, created_at 
        FROM users 
        WHERE id = ? AND is_banned = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User account not found or has been deactivated.');
    }

    // Get user's recent orders with status
    $stmt = $pdo->prepare("
        SELECT o.*, os.name as status_name, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o 
        LEFT JOIN order_status os ON o.status_id = os.id 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
} catch (PDOException $e) {
    // Roll back transaction on database error
    $pdo->rollBack();
    error_log("Database error in account.php: " . $e->getMessage());
    $error = "We're experiencing technical difficulties. Please try again later.";
} catch (Exception $e) {
    // Roll back transaction on other errors
    $pdo->rollBack();
    error_log("Error in account.php: " . $e->getMessage());
    set_flash_message('danger', $e->getMessage());
    header('Location: logout.php');
    exit();
}
?>

<div class="container my-5">
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Account Menu</h5>
                    <div class="list-group">
                        <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="tab">Profile</a>
                        <a href="#orders" class="list-group-item list-group-item-action" data-bs-toggle="tab">Orders</a>
                        <a href="#settings" class="list-group-item list-group-item-action" data-bs-toggle="tab">Settings</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="tab-content">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Profile Information</h5>
                            <?php if (!$user['email_verified']): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Your email address is not verified. Please check your email for verification instructions.
                                <a href="resend_verification.php" class="alert-link">Resend verification email</a>
                            </div>
                            <?php endif; ?>
                            
                            <form action="update_profile.php" method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" 
                                           required pattern="[A-Za-z\s]{2,50}">
                                    <div class="invalid-feedback">
                                        Please enter a valid name (2-50 characters, letters and spaces only).
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                                    <small class="text-muted">Email cannot be changed for security reasons.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                           pattern="[0-9]{10}">
                                    <div class="invalid-feedback">
                                        Please enter a valid 10-digit phone number.
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div class="tab-pane fade" id="orders">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Order History</h5>
                            <?php if (!empty($orders)): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                                <tr>
                                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['created_at']))); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $order['status_id'] == 1 ? 'success' : 'warning'; ?>">
                                                            <?php echo htmlspecialchars($order['status_name'] ?? 'Unknown'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo format_price($order['total_amount']); ?></td>
                                                    <td>
                                                        <a href="order_details.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No orders found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div class="tab-pane fade" id="settings">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Account Settings</h5>
                            <form action="update_password.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>