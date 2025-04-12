<?php
require_once '../includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_admin_login();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: settings.php');
        exit();
    }
    
    $settings = [
        'site_name' => trim($_POST['site_name']),
        'site_description' => trim($_POST['site_description']),
        'currency' => trim($_POST['currency']),
        'currency_symbol' => trim($_POST['currency_symbol']),
        'tax_rate' => (float)$_POST['tax_rate'],
        'shipping_fee' => (float)$_POST['shipping_fee'],
        'min_order_amount' => (float)$_POST['min_order_amount'],
        'contact_email' => trim($_POST['contact_email']),
        'contact_phone' => trim($_POST['contact_phone']),
        'address' => trim($_POST['address']),
        'facebook_url' => trim($_POST['facebook_url']),
        'instagram_url' => trim($_POST['instagram_url']),
        'twitter_url' => trim($_POST['twitter_url'])
    ];
    
    $errors = [];
    
    // Validate input
    if (empty($settings['site_name'])) $errors[] = 'Site name is required.';
    if (empty($settings['currency'])) $errors[] = 'Currency is required.';
    if (empty($settings['currency_symbol'])) $errors[] = 'Currency symbol is required.';
    if ($settings['tax_rate'] < 0) $errors[] = 'Tax rate cannot be negative.';
    if ($settings['shipping_fee'] < 0) $errors[] = 'Shipping fee cannot be negative.';
    if ($settings['min_order_amount'] < 0) $errors[] = 'Minimum order amount cannot be negative.';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $pdo->commit();
            set_flash_message('success', 'Settings updated successfully.');
            
            header('Location: settings.php');
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Settings update error: " . $e->getMessage());
            $errors[] = 'Error updating settings. Please try again later.';
        }
    }
}

// Get current settings
try {
    $stmt = $pdo->query("SELECT * FROM settings");
    $current_settings = [];
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $current_settings = [];
}

// Set default values if not set
$settings = array_merge([
    'site_name' => 'E-Commerce Store',
    'site_description' => 'Your one-stop shop for all your needs',
    'currency' => 'NPR',
    'currency_symbol' => 'रु',
    'tax_rate' => 13,
    'shipping_fee' => 100,
    'min_order_amount' => 0,
    'contact_email' => '',
    'contact_phone' => '',
    'address' => '',
    'facebook_url' => '',
    'instagram_url' => '',
    'twitter_url' => ''
], $current_settings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
        }
        .sidebar .nav-link:hover {
            color: rgba(255,255,255,.95);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="mb-4 px-3">
                    <span class="fs-5 text-white">E-Commerce Admin</span>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box me-2"></i>
                            Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>
                            Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags me-2"></i>
                            Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reviews.php">
                            <i class="fas fa-star me-2"></i>
                            Reviews
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog me-2"></i>
                            Settings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-store me-2"></i>
                            View Store
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2">Site Settings</h1>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="mb-3">General Settings</h4>
                                
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" required
                                           value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Site Description</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php 
                                        echo htmlspecialchars($settings['site_description']); 
                                    ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Currency</label>
                                    <input type="text" class="form-control" id="currency" name="currency" required
                                           value="<?php echo htmlspecialchars($settings['currency']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" required
                                           value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h4 class="mb-3">Financial Settings</h4>
                                
                                <div class="mb-3">
                                    <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" step="0.01" min="0" required
                                           value="<?php echo htmlspecialchars($settings['tax_rate']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="shipping_fee" class="form-label">Shipping Fee (<?php echo htmlspecialchars($settings['currency_symbol']); ?>)</label>
                                    <input type="number" class="form-control" id="shipping_fee" name="shipping_fee" step="0.01" min="0" required
                                           value="<?php echo htmlspecialchars($settings['shipping_fee']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="min_order_amount" class="form-label">Minimum Order Amount (<?php echo htmlspecialchars($settings['currency_symbol']); ?>)</label>
                                    <input type="number" class="form-control" id="min_order_amount" name="min_order_amount" step="0.01" min="0" required
                                           value="<?php echo htmlspecialchars($settings['min_order_amount']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h4 class="mb-3">Contact Information</h4>
                                
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email"
                                           value="<?php echo htmlspecialchars($settings['contact_email']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                                           value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php 
                                        echo htmlspecialchars($settings['address']); 
                                    ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h4 class="mb-3">Social Media</h4>
                                
                                <div class="mb-3">
                                    <label for="facebook_url" class="form-label">Facebook URL</label>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url"
                                           value="<?php echo htmlspecialchars($settings['facebook_url']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instagram_url" class="form-label">Instagram URL</label>
                                    <input type="url" class="form-control" id="instagram_url" name="instagram_url"
                                           value="<?php echo htmlspecialchars($settings['instagram_url']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="twitter_url" class="form-label">Twitter URL</label>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url"
                                           value="<?php echo htmlspecialchars($settings['twitter_url']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 