<?php
/**
 * Smart Environment Configuration for Dry-Drop
 * Automatically detects environment and uses appropriate database settings
 */

// Detect environment
function getEnvironment() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return 'local';
    } elseif (strpos($host, 'infinityfree') !== false || strpos($host, '.epizy.com') !== false) {
        return 'infinityfree';
    } else {
        return 'production';
    }
}

// Environment-specific configurations
$environment = getEnvironment();

switch ($environment) {
    case 'local':
        // XAMPP Local Development
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'drydrop');
        define('ENVIRONMENT', 'development');
        break;
          case 'infinityfree':
        // InfinityFree Hosting - UPDATE THESE WITH YOUR ACTUAL DETAILS FROM INFINITYFREE
        define('DB_HOST', 'sql102.infinityfree.com'); // Replace with YOUR actual host
        define('DB_USER', 'if0_39315078');              // Replace with YOUR actual username
        define('DB_PASS', 'jordanCJ7');           // Replace with YOUR actual password
        define('DB_NAME', 'if0_39315078_drydrop');      // Replace with YOUR actual database name
        define('ENVIRONMENT', 'production');
        break;
        
    default:
        // Other hosting providers
        define('DB_HOST', 'localhost');
        define('DB_USER', 'your_username');
        define('DB_PASS', 'your_password');
        define('DB_NAME', 'your_database');
        define('ENVIRONMENT', 'production');
        break;
}

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist (for local development)
if ($environment === 'local') {
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) !== TRUE) {
        die("Error creating database: " . $conn->error);
    }
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
if ($environment === 'local') {
    // Local development with foreign keys
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
} else {
    // Production (InfinityFree) without foreign keys
    $sql = "CREATE TABLE IF NOT EXISTS inventory_log (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        inventory_id INT(11) NOT NULL,
        adjustment INT NOT NULL,
        reason VARCHAR(100),
        admin_id INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
}
$conn->query($sql);

// Create packages table
$sql = "CREATE TABLE IF NOT EXISTS packages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    max_garments INT NOT NULL,
    num_deliveries INT NOT NULL,
    includes_services TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create orders table
if ($environment === 'local') {
    // Local development with foreign keys
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
        package_id INT(11) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
    )";
} else {
    // Production (InfinityFree) without foreign keys
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
        package_id INT(11) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
}
$conn->query($sql);

// Create order_items table
if ($environment === 'local') {
    // Local development with foreign keys
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
} else {
    // Production (InfinityFree) without foreign keys
    $sql = "CREATE TABLE IF NOT EXISTS order_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        order_id INT(11) NOT NULL,
        service_id INT(11) NOT NULL,
        quantity INT(11) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
}
$conn->query($sql);

// Create feedbacks table
if ($environment === 'local') {
    // Local development with foreign keys
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
} else {
    // Production (InfinityFree) without foreign keys
    $sql = "CREATE TABLE IF NOT EXISTS feedbacks (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        order_id INT(11) NOT NULL,
        rating INT(1) NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
}
$conn->query($sql);

// Check if package_id column exists in orders table, add it if not (without foreign key on InfinityFree)
$checkPackageIdColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'package_id'");
if ($checkPackageIdColumn->num_rows == 0) {
    if ($environment === 'local') {
        // Local development with foreign key
        $conn->query("ALTER TABLE orders ADD COLUMN package_id INT(11) NULL AFTER special_instructions, ADD FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL");
    } else {
        // Production (InfinityFree) without foreign key
        $conn->query("ALTER TABLE orders ADD COLUMN package_id INT(11) NULL AFTER special_instructions");
    }
}

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

// Insert default packages
$packagesCheck = $conn->query("SELECT COUNT(*) as count FROM packages");
$packagesCount = $packagesCheck->fetch_assoc()['count'];

if ($packagesCount == 0) {
    $packages = [
        ['Weekly Package', 'weekly', 'Up to 20 garments with free pickup and delivery, includes washing and ironing with 1 regular delivery per week', 89.99, 20, 1, 'Washing,Ironing'],
        ['Monthly Package', 'monthly', 'Up to 80 garments with free priority pickup and delivery, includes all services with 4 deliveries per month', 299.99, 80, 4, 'Washing,Dry Cleaning,Ironing,Folding,Express Service'],
        ['Family Package', 'family', 'Up to 50 garments with free pickup and delivery, includes washing, dry cleaning and ironing with 2 deliveries per month', 199.99, 50, 2, 'Washing,Dry Cleaning,Ironing']
    ];
    
    $stmt = $conn->prepare("INSERT INTO packages (name, code, description, price, max_garments, num_deliveries, includes_services) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($packages as $package) {
        $stmt->bind_param("sssdiis", $package[0], $package[1], $package[2], $package[3], $package[4], $package[5], $package[6]);
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
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Environment-specific settings
if (ENVIRONMENT === 'development') {
    // Development settings
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production settings
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
