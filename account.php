<?php
// // Start the session (ensure this is done before any output)
// session_start();

// Include the database connection
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // For is_logged_in(), set_flash_message(), etc.
require_once 'includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('danger', 'Please log in to access your account.');
    header('Location: login.php');
    exit();
}

// Get user information
$user = null;
$orders = [];
$error = null;

try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // User not found (shouldn't happen if is_logged_in() is working correctly)
        set_flash_message('danger', 'User not found.');
        header('Location: logout.php');
        exit();
    }

    // Get user's orders
    $stmt = $pdo->prepare("
        SELECT o.*, os.name as status_name 
        FROM orders o 
        LEFT JOIN order_status os ON o.status_id = os.id 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user data in account.php: " . $e->getMessage());
    $error = "Error fetching user data. Please try again later.";
}
?>

<div class="container my-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
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
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form action="update_profile.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Update Profile</button>
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