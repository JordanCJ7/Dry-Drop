<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'drydrop');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db(DB_NAME);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create services table
$sql = "CREATE TABLE IF NOT EXISTS services (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create inventory table
$sql = "CREATE TABLE IF NOT EXISTS inventory (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    current_stock INT NOT NULL DEFAULT 0,
    min_stock_level INT NOT NULL DEFAULT 10,
    unit VARCHAR(20) NOT NULL,
    supplier VARCHAR(100),
    cost_per_unit DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create inventory_log table
$sql = "CREATE TABLE IF NOT EXISTS inventory_log (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT(11) NOT NULL,
    adjustment INT NOT NULL,
    reason VARCHAR(100),
    admin_id INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
)";
$conn->query($sql);

// Create orders table
$sql = "CREATE TABLE IF NOT EXISTS orders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'online') DEFAULT 'cash',
    pickup_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    pickup_address TEXT NOT NULL,
    delivery_date DATETIME,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($sql);

// Create order_items table
$sql = "CREATE TABLE IF NOT EXISTS order_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    service_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
)";
$conn->query($sql);

// Create feedbacks table
$sql = "CREATE TABLE IF NOT EXISTS feedbacks (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    order_id INT(11) NOT NULL,
    rating INT(1) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";
$conn->query($sql);

// Insert default services
$servicesCheck = $conn->query("SELECT COUNT(*) as count FROM services");
$serviceCount = $servicesCheck->fetch_assoc()['count'];

if ($serviceCount == 0) {
    $services = [
        ['Washing', 'Regular washing service for clothes', 15.00, 'washing.jpg'],
        ['Dry Cleaning', 'Dry cleaning service for delicate fabrics', 25.00, 'dry-cleaning.jpg'],
        ['Ironing', 'Ironing service for your clothes', 10.00, 'ironing.jpg'],
        ['Folding', 'Folding service for your laundry', 5.00, 'folding.jpg'],
        ['Express Service', 'Get your laundry done within 24 hours', 35.00, 'express.jpg']
    ];
    
    $stmt = $conn->prepare("INSERT INTO services (name, description, price, image) VALUES (?, ?, ?, ?)");
    
    foreach ($services as $service) {
        $stmt->bind_param("ssds", $service[0], $service[1], $service[2], $service[3]);
        $stmt->execute();
    }
}

// Insert default admin user if none exists
$adminCheck = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_role = 'admin'");
$adminCount = $adminCheck->fetch_assoc()['count'];

if ($adminCount == 0) {
    $password = password_hash("admin123", PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (name, email, password, phone, user_role) VALUES ('Admin User', 'admin@drydrop.com', '$password', '1234567890', 'admin')";
    $conn->query($sql);
}

// Insert default inventory items if none exist
$inventoryCheck = $conn->query("SELECT COUNT(*) as count FROM inventory");
$inventoryCount = $inventoryCheck->fetch_assoc()['count'];

if ($inventoryCount == 0) {
    $inventoryItems = [
        ['Laundry Detergent', 'Regular detergent for washing machines', 100, 20, 'bottles', 'CleanSupplies Inc.', 3.50],
        ['Fabric Softener', 'Softener for all types of fabrics', 80, 15, 'bottles', 'CleanSupplies Inc.', 2.75],
        ['Bleach', 'For white clothes and stain removal', 50, 10, 'bottles', 'CleanSupplies Inc.', 2.25],
        ['Stain Remover', 'For tough stains on all fabric types', 40, 8, 'bottles', 'StainMaster Co.', 4.50],
        ['Ironing Starch', 'For crisp ironing results', 30, 5, 'bottles', 'IronWell Ltd.', 3.00],
        ['Laundry Bags', 'For sorting and storing laundry', 200, 50, 'items', 'BagIt Inc.', 0.75],
        ['Hangers', 'Plastic hangers for clothes', 500, 100, 'items', 'HangIt Ltd.', 0.30],
        ['Washing Machine Cleaner', 'For maintenance of washing machines', 20, 5, 'packets', 'CleanMachine Co.', 5.50],
        ['Dryer Sheets', 'Anti-static sheets for dryers', 150, 30, 'boxes', 'DryWell Inc.', 4.25],
        ['Lint Rollers', 'For removing lint from clothes', 35, 10, 'items', 'LintOff Co.', 1.75]
    ];
    
    $stmt = $conn->prepare("INSERT INTO inventory (name, description, current_stock, min_stock_level, unit, supplier, cost_per_unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($inventoryItems as $item) {
        $stmt->bind_param("ssiissd", $item[0], $item[1], $item[2], $item[3], $item[4], $item[5], $item[6]);
        $stmt->execute();
    }
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
