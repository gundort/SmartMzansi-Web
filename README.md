Smart Mzansi
A South African multi-vendor e-commerce platform connecting local sellers with buyers. Built with PHP, MySQL, HTML, CSS, and JavaScript.
Overview
Smart Mzansi is a complete e-commerce solution that empowers local South African sellers and gives shoppers more ways to connect with proudly South African products. The platform features role-based access for buyers, sellers, and administrators.
Features
For Buyers
* Browse and search products by category
* Add products to shopping cart
* Create and manage wishlists
* Place orders with shipping and payment simulation
* View order history and status
* Send messages to sellers about orders
* Profile management
For Sellers
* Product management (add, edit, delete)
* Order management and status tracking
* Message buyers directly
* View product reviews and ratings
* Set monthly sales goals and track progress
* Low-stock alerts
* Seller profile management
For Administrators
* Product approval workflow
* User management (view, delete)
* Order overview
* View all message threads between buyers and sellers
* Category management
Technology Stack
* PHP 7.4+
* MySQL 5.7+
* HTML5, CSS3, JavaScript
* Font Awesome 6.5.0
* Google Fonts (Georgia, Poppins)
Database Structure
The application uses a MySQL database named user_auth with the following key tables:
* users - User accounts (email, password, role, full_name)
* products - Product listings (seller_id, name, description, price, quantity, image, approved)
* categories - Product categories
* orders - Order records (buyer_id, seller_id, product_id, quantity, total_price, status)
* order_items - Individual items within an order
* cart_items - Shopping cart contents
* wishlists - User wishlist items
* messages - Communication between buyers and sellers
* reviews - Product reviews and ratings
* seller_goals - Monthly sales goals for sellers
* notifications - System notifications for users
Installation
Prerequisites
* Web server (Apache/Nginx) with PHP support
* MySQL database server
* PHP 7.4 or higher
Steps
1. Clone the repository to your web server root directory:
text
git clone https://github.com/yourusername/smart-mzansi.git
1. Create a MySQL database named user_auth:
sql
CREATE DATABASE user_auth;
1. Import the database schema. The database structure is defined in the db.php file and tables are created during registration. For a fresh installation, you can create the tables manually:
sql
-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller', 'admin') NOT NULL,
    full_name VARCHAR(100),
    id_number VARCHAR(13),
    proof_of_id VARCHAR(255),
    proof_of_residence VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    approved TINYINT DEFAULT 0,
    rejected_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Additional tables for cart, wishlist, messages, reviews, seller_goals, notifications, order_items
-- These are created during first time use
1. Update the database connection settings in Database/db.php:
php
$host = "localhost";
$username = "your_db_username";
$password = "your_db_password";
$database = "user_auth";
1. Create an admin user by running createadmin.php from the browser or command line:
text
http://localhost/smart-mzansi/createadmin.php
Default admin credentials:
* Email: admin@admin.com
* Password: Admin123
6. Make sure the following directories are writable by the web server:
    * Dashboards/uploads/ - For product images
    * uploads/ - For seller ID documents
File Structure
text
smart-mzansi/
├── index.html                 # Landing page
├── auth.php                   # Login/Register page
├── auth.js                    # Authentication UI logic
├── styles.css                 # Main styles
├── script.js                  # Main JavaScript
├── Database/
│   ├── db.php                 # Database connection
│   ├── login.php              # Login handler
│   ├── register.php           # Registration handler
│   └── createadmin.php        # Admin creation script
├── Dashboards/
│   ├── buyerdashboard.php     # Buyer dashboard
│   ├── sellerdashboard.php    # Seller dashboard
│   ├── admindashboard.php     # Admin dashboard
│   ├── checkout.php           # Checkout page
│   ├── product.php            # Product detail page
│   └── uploads/               # Product images directory
├── completeprofile.php        # Seller profile completion
├── updateprofile.php          # Profile update handler
├── uploads/                   # ID documents directory
└── README.md                  # This file
Usage
Registration
1. Navigate to auth.php
2. Click the "Register" button
3. Fill in email, password, confirm password, and select role (Buyer/Seller)
4. Submit the form
Seller Profile Completion
After registration, sellers are redirected to completeprofile.php to:
* Provide full name and gender
* Enter South African ID number (auto-fills date of birth)
* Upload ID document
Admin Login
* Email: admin@admin.com
* Password: Admin123
Security Features
* Password hashing using password_hash() with PASSWORD_DEFAULT
* Prepared statements for SQL queries to prevent SQL injection
* Session-based authentication
* Role-based access control
* Input validation and sanitization
Development Notes
Adding Categories
Categories can be added directly to the categories table. The initial categories are:
* Electronics
* Fashion
* Home & Living
* Beauty
* Sports
* Grocery
Product Approval Workflow

1. Seller adds a product (approved = 0)
2. Admin reviews pending products
3. Admin approves (approved = 1) or rejects (approved = -1) with reason
4. Approved products appear in buyer shop

File Uploads

* Product images are stored in Dashboards/uploads/
* ID documents are stored in uploads/
* File size limits and validation should be configured on the server

Browser Support
* Chrome (latest)
* Firefox (latest)
* Edge (latest)
* Safari (latest)
* Mobile responsive design

License
This project is proprietary and confidential. All rights reserved.

Contributing
This is a private project. For internal use only.

Support
For support, contact the development team.
