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

## Requirements

- XAMPP (PHP 7.4+ and MySQL 5.7+)
- Web browser
- Internet connection (for CDN resources)

## Installation

1. Install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

2. Clone or download this repository to your XAMPP's htdocs folder:
   ```
   C:\xampp\htdocs\ecommerce
   ```

3. Start Apache and MySQL from XAMPP Control Panel

4. Create the database and tables:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named 'ecomfinal_db'
   - Import the `combined_ecommerce.sql` file

5. Access the website:
   ```
   http://localhost/ecomfinal
   ```

## Default Admin Account

- Email: admin@example.com
- Password: admin123

## Project Structure

```
ecomfinal/
├── admin/           # Admin panel files
├── api/            # API endpoints
├── assets/         # Static files (CSS, JS, images)
├── includes/       # PHP includes
└── README.md       # This file
```

## Security Features

- Password hashing using bcrypt
- CSRF protection
- SQL injection prevention (PDO prepared statements)
- XSS protection
- Input validation and sanitization

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License.
