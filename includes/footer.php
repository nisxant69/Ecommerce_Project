    </div><!-- /.container -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/ecomfinal/about.php">About Us</a></li>
                        <li><a href="/ecomfinal/contact.php">Contact</a></li>
                        <li><a href="/ecomfinal/terms.php">Terms & Conditions</a></li>
                        <li><a href="/ecomfinal/privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Categories</h5>
                    <ul class="list-unstyled">
                        <?php
                        // Fetch top categories
                        try {
                            $stmt = $pdo->query("SELECT * FROM categories LIMIT 5");
                            while ($category = $stmt->fetch()) {
                                echo '<li><a href="/ecomfinal/products.php?category=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                            }
                        } catch (PDOException $e) {
                            // Silently fail in footer
                        }
                        ?>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Connect With Us</h5>
                    <div class="social-links">
                        <a href="#" class="me-2"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5>Newsletter</h5>
                    <form action="#" method="POST" class="newsletter-form">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Your email">
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> E-Commerce Store. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- jQuery first, then Bootstrap Bundle with Popper -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/ecomfinal/assets/js/script.js"></script>
    <script>
    // Initialize all dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        var dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(function(dropdown) {
            new bootstrap.Dropdown(dropdown);
        });
    });
    </script>
</body>
</html>
