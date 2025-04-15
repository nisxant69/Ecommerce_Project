# E-Commerce Website

A full-featured e-commerce website built with PHP, MySQL, and Bootstrap. This project includes both user-facing features and an admin panel for managing the store.

## Features

### User Features
- Product browsing with categories and search
- Shopping cart and wishlist
- User registration and authentication
- Order placement and tracking
- Product reviews and ratings
- Responsive design for mobile devices

### Admin Features
- Product management (CRUD operations)
- Order management
- User management
- Category management
- Basic analytics dashboard
- Site settings (currency, shipping, etc.)

## Requirements

- XAMPP (PHP 7.4+ and MySQL 5.7+) with Apache and MySQL running.
- Web browser
- Internet connection (for CDN resources & payment gateways)
- Git (optional, for cloning)

## Installation

1.  **Install XAMPP:** Download and install from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2.  **Clone or Download:** Get the project files into your XAMPP's `htdocs` folder (e.g., `C:\xampp\htdocs\Ecommerce_Project`).
    ```bash
    # Option 1: Clone using Git
    git clone <repository_url> C:\xampp\htdocs\Ecommerce_Project
    cd C:\xampp\htdocs\Ecommerce_Project

    # Option 2: Download and extract the ZIP file to C:\xampp\htdocs\Ecommerce_Project
    ```
3.  **Start Apache & MySQL:** Use the XAMPP Control Panel to start both services.
4.  **Configure Environment:**
    *   Copy the `.env.example` file to a new file named `.env`.
    *   Open the `.env` file and configure your database credentials (usually `DB_USER=root` and `DB_PASS=` are correct for default XAMPP).
    *   Add your Khalti and/or eSewa API keys (Test/Sandbox keys for development). **Make sure to use the correct Khalti Verification URL.**
5.  **Setup Database:**
    *   Ensure your database user (`DB_USER` in `.env`) has privileges to create databases.
    *   Open a terminal or command prompt in the project directory (`C:\xampp\htdocs\Ecommerce_Project`).
    *   Run the database setup script using the full path to your XAMPP PHP executable:
        ```bash
        C:\xampp\php\php.exe setup_database.php
        ```
        This will create the database (`ecommerce_db` by default) and necessary tables based on `setup_tables.sql`.
6.  **Access the Website:**
    Open your browser and navigate to:
    ```
    http://localhost/Ecommerce_Project/
    ```

## Admin Accounts

You can use either of the following default admin accounts:

**Account 1:**
*   Email: `admin@example.com`
*   Password: `admin123`

**Account 2 (created via script):**
*   Email: `admin@admin.com`
*   Password: `Admin@123`

The admin panel can be accessed at `http://localhost/Ecommerce_Project/admin/`.

## Project Structure

```
Ecommerce_Project/
├── admin/          # Admin panel files
├── api/            # API endpoints (cart, wishlist, etc.)
├── assets/         # Static files (CSS, JS, images)
├── config/         # Configuration files (mainly for DB setup)
├── includes/       # Core PHP includes (functions, db connect, header/footer)
├── js/             # Specific JS files (e.g., Khalti checkout helper)
├── .env            # Environment variables (DB, API Keys) - **DO NOT COMMIT**
├── .env.example    # Example environment file
├── setup_database.php # Script to create DB tables
├── setup_tables.sql   # SQL definitions for tables
├── *.php           # Root PHP files (index, login, product, cart, etc.)
└── README.md       # This file
```

## Security Features

- Password hashing using bcrypt
- CSRF protection using tokens
- SQL injection prevention (PDO prepared statements)
- XSS protection (htmlspecialchars)
- Input validation and sanitization

## Contributing

1.  Fork the repository
2.  Create your feature branch
3.  Commit your changes
4.  Push to the branch
5.  Create a new Pull Request

## License

This project is licensed under the MIT License.
