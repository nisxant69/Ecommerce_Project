<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechHub - Premium Tech & Gaming Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.22.0.0.0/khalti-checkout.iffe.js"></script>
    <script>
        var khaltiConfig = {
            "publicKey": "<?php echo KHALTI_PUBLIC_KEY; ?>",
            "env": "test"
        };
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">TechHub Store</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="laptopsDropdown" role="button" data-bs-toggle="dropdown">
                            Laptops
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="products.php?category=1">Gaming Laptops</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="products.php?category=2">Business Laptops</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php?category=3">Monitors</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="componentsDropdown" role="button" data-bs-toggle="dropdown">
                            Components
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="products.php?category=4">PC Components</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="products.php?category=7">Storage</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="products.php?category=6">Networking</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php?category=5">Gaming Accessories</a>
                    </li>
                </ul>
                <form class="d-flex mx-auto" action="search.php" method="GET">
                    <input class="form-control me-2" type="search" name="q" placeholder="Search for laptops, components...">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </form>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item me-2">
                        <?php include 'currency_selector.php'; ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="badge bg-primary cart-count" id="cartCount"><?php echo get_cart_total(); ?></span>
                        </a>
                    </li>
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> Account
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="account.php">My Account</a></li>
                                <li><a class="dropdown-item" href="wishlist.php">Wishlist</a></li>
                                <?php if (is_admin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="admin">Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php 
        $flash_messages = get_flash_messages();
        foreach ($flash_messages as $flash) {
            echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($flash['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        ?>