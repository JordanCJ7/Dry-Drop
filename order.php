<?php
$page_title = "Place an Order";
include_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please login to place an order";
    $_SESSION['message_type'] = "warning";
    header("Location: login.php");
    exit;
}

// Fetch user address
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT address, phone FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$error = '';
$success = '';

// Get services based on selection
$services = [];
$package = null;

if (isset($_GET['service_id'])) {
    // Single service
    $service_id = $_GET['service_id'];
    $sql = "SELECT * FROM services WHERE id = ? AND active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $services[] = $result->fetch_assoc();
    }
} elseif (isset($_GET['package'])) {
    // Package (pre-selected services with special pricing)
    $package_code = $_GET['package'];
    
    // Get package details
    $package_sql = "SELECT * FROM packages WHERE code = ? AND active = 1";
    $package_stmt = $conn->prepare($package_sql);
    $package_stmt->bind_param("s", $package_code);
    $package_stmt->execute();
    $package_result = $package_stmt->get_result();
    
    if ($package_result->num_rows > 0) {
        $package = $package_result->fetch_assoc();
        
        // Get services included in the package
        $included_services = explode(',', $package['includes_services']);
        
        // Fetch services that are included in the package
        $services_sql = "SELECT * FROM services WHERE name IN (";
        $services_sql .= str_repeat('?,', count($included_services) - 1) . "?";
        $services_sql .= ") AND active = 1";
        
        $services_stmt = $conn->prepare($services_sql);
        $services_stmt->bind_param(str_repeat('s', count($included_services)), ...$included_services);
        $services_stmt->execute();
        $services_result = $services_stmt->get_result();
        
        if ($services_result->num_rows > 0) {
            while ($row = $services_result->fetch_assoc()) {
                $services[] = $row;
            }
        }
    } else {
        // Fallback to all services if package not found
        $sql = "SELECT * FROM services WHERE active = 1";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $services[] = $row;
            }
        }
    }
} else {
    // All services
    $sql = "SELECT * FROM services WHERE active = 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
    }
}

// Process order form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pickup_date = sanitize_input($_POST['pickup_date']);
    $pickup_time = sanitize_input($_POST['pickup_time']);
    $pickup_address = sanitize_input($_POST['pickup_address']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $total_amount = floatval(sanitize_input($_POST['total_amount']));
    $package_id = isset($_POST['package_id']) ? intval(sanitize_input($_POST['package_id'])) : null;
    
    // Enhanced validation
    $has_items = false;
    
    // Check if we're dealing with a package or individual services
    if ($package_id) {
        $has_items = true; // Packages always have items
    } else {
        foreach ($_POST['quantity'] as $service_id => $quantity) {
            if (intval($quantity) > 0) {
                $has_items = true;
                break;
            }
        }
    }
    
    // Validate input
    if (empty($pickup_date) || empty($pickup_time) || empty($pickup_address)) {
        $error = "Please fill in all required fields";
    } elseif ($total_amount <= 0) {
        $error = "Your order total must be greater than zero";
    } elseif (!$has_items) {
        $error = "Please select at least one service";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create order with package_id if applicable
            if ($package_id) {
                $order_sql = "INSERT INTO orders (user_id, total_amount, status, payment_status, payment_method, pickup_date, pickup_time, pickup_address, package_id) 
                            VALUES (?, ?, 'pending', 'pending', ?, ?, ?, ?, ?)";
                $order_stmt = $conn->prepare($order_sql);
                $order_stmt->bind_param("idssssi", $user_id, $total_amount, $payment_method, $pickup_date, $pickup_time, $pickup_address, $package_id);
            } else {
                $order_sql = "INSERT INTO orders (user_id, total_amount, status, payment_status, payment_method, pickup_date, pickup_time, pickup_address) 
                            VALUES (?, ?, 'pending', 'pending', ?, ?, ?, ?)";
                $order_stmt = $conn->prepare($order_sql);
                $order_stmt->bind_param("idssss", $user_id, $total_amount, $payment_method, $pickup_date, $pickup_time, $pickup_address);
            }
            
            if (!$order_stmt->execute()) {
                throw new Exception("Error creating order: " . $order_stmt->error);
            }
            
            $order_id = $conn->insert_id;
            
            // Check if order_id is valid
            if (!$order_id) {
                throw new Exception("Failed to get order ID");
            }
            
            // Add order items
            $has_valid_items = false;
            foreach ($_POST['quantity'] as $service_id => $quantity) {
                $quantity = intval($quantity); // Ensure it's an integer
                if ($quantity > 0) {
                    $price_sql = "SELECT price FROM services WHERE id = ?";
                    $price_stmt = $conn->prepare($price_sql);
                    $price_stmt->bind_param("i", $service_id);
                    $price_stmt->execute();
                    $price_result = $price_stmt->get_result();
                    
                    if ($price_result->num_rows === 0) {
                        throw new Exception("Service not found: ID " . $service_id);
                    }
                    
                    $service_price = $price_result->fetch_assoc()['price'];
                    
                    $item_sql = "INSERT INTO order_items (order_id, service_id, quantity, price) VALUES (?, ?, ?, ?)";
                    $item_stmt = $conn->prepare($item_sql);
                    $item_stmt->bind_param("iiid", $order_id, $service_id, $quantity, $service_price);
                    
                    if (!$item_stmt->execute()) {
                        throw new Exception("Error adding order item: " . $item_stmt->error);
                    }
                    
                    $has_valid_items = true;
                }
            }
            
            if (!$has_valid_items) {
                throw new Exception("No valid items in order");
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['message'] = "Your order has been placed successfully. Order ID: " . $order_id;
            $_SESSION['message_type'] = "success";
            
            // Redirect to order details
            header("Location: customer/order_details.php?id=" . $order_id);
            exit;
        } catch (Exception $e) {            // Rollback transaction on error
            $conn->rollback();
            // For debugging only - in production you'd want a generic message
            $error = "An error occurred while placing your order: " . $e->getMessage();
            // Log the error (in a production environment, you should use proper logging)
            error_log("Order Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
    }
}
?>

<!-- Order Form Section -->
<div class="container my-5">
    <h1 class="mb-4">Place an Order</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (count($services) > 0): ?>
        <form method="post" action="" id="orderForm">
            <?php if ($package): ?>
                <div class="alert alert-success mb-4">
                    <h5><i class="fas fa-tag me-2"></i> <?php echo $package['name']; ?> Selected</h5>
                    <p class="mb-0"><?php echo $package['description']; ?></p>
                    <p class="mb-0 mt-2"><strong>Package Price:</strong> $<?php echo number_format($package['price'], 2); ?></p>
                    <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                    <input type="hidden" name="package_price" value="<?php echo $package['price']; ?>">
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Select Services</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($services as $service): ?>
                                            <tr class="service-item">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <img src="assets/images/<?php echo $service['image']; ?>" alt="<?php echo $service['name']; ?>" style="width: 60px; height: 60px; object-fit: cover;" class="rounded">
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo $service['name']; ?></h6>
                                                            <small class="text-muted"><?php echo $service['description']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="service-price" data-price="<?php echo $service['price']; ?>">$<?php echo number_format($service['price'], 2); ?></td>
                                                <td>
                                                    <div class="input-group" style="width: 120px;">
                                                        <!-- Use inline JavaScript to guarantee direct execution -->
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                                onclick="this.nextElementSibling.value = Math.max(0, parseInt(this.nextElementSibling.value) - 1); updateTotalsNow();">-</button>
                                                        <input type="number" name="quantity[<?php echo $service['id']; ?>]" 
                                                               class="form-control form-control-sm text-center" 
                                                               value="1" min="0" 
                                                               onchange="updateTotalsNow()">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                                onclick="this.previousElementSibling.value = parseInt(this.previousElementSibling.value) + 1; updateTotalsNow();">+</button>
                                                    </div>
                                                </td>
                                                <td class="item-total">$<?php echo number_format($service['price'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Pickup Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="pickup_date" class="form-label">Pickup Date *</label>
                                    <input type="date" class="form-control" id="pickup_date" name="pickup_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="pickup_time" class="form-label">Pickup Time *</label>
                                    <input type="time" class="form-control" id="pickup_time" name="pickup_time" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="pickup_address" class="form-label">Pickup Address *</label>
                                <textarea class="form-control" id="pickup_address" name="pickup_address" rows="3" required><?php echo $user['address']; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="cash" checked>
                                <label class="form-check-label" for="payment_cash">
                                    <i class="fas fa-money-bill-wave me-2"></i> Cash on Delivery
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_online" value="online">
                                <label class="form-check-label" for="payment_online">
                                    <i class="fas fa-credit-card me-2"></i> Online Payment
                                </label>
                            </div>
                            
                            <div id="onlinePaymentDetails" class="d-none">
                                <div class="alert alert-info">
                                    <p>Online payment integration will be available soon. For now, please select Cash on Delivery.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">                            <div class="d-flex justify-content-between mb-3">
                                <span>Subtotal:</span>
                                <span id="orderSubtotal">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Delivery Fee:</span>
                                <span id="deliveryFee">$0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3 fw-bold">
                                <span>Total:</span>
                                <span id="orderTotal">$0.00</span>
                            </div>
                            <input type="hidden" name="total_amount" id="totalAmount" value="0">
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            <p>No services available at the moment. Please check back later.</p>
            <a href="services.php" class="btn btn-primary mt-3">View All Services</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Global function that is called directly by inline handlers 
function updateTotalsNow() {
    let subtotal = 0;
    
    // Check if a package is selected
    const packagePriceElement = document.querySelector('input[name="package_price"]');
    const isPackage = packagePriceElement !== null;
    
    if (isPackage) {
        // If a package is selected, use the package price as the base
        subtotal = parseFloat(packagePriceElement.value);
        
        // Update service item totals but don't add to subtotal since it's included in package
        document.querySelectorAll('.service-item').forEach(item => {
            const price = parseFloat(item.querySelector('.service-price').dataset.price);
            const input = item.querySelector('input[type="number"]');
            const quantity = parseInt(input.value) || 0;
            const itemTotal = price * quantity;
            
            item.querySelector('.item-total').textContent = '$' + itemTotal.toFixed(2);
        });
    } else {
        // Regular item-by-item pricing
        document.querySelectorAll('.service-item').forEach(item => {
            const price = parseFloat(item.querySelector('.service-price').dataset.price);
            const input = item.querySelector('input[type="number"]');
            const quantity = parseInt(input.value) || 0;
            const itemTotal = price * quantity;
            
            item.querySelector('.item-total').textContent = '$' + itemTotal.toFixed(2);
            subtotal += itemTotal;
        });
    }
    
    // Calculate delivery fee based on subtotal
    let deliveryFee = 0;
    if (subtotal > 0) {
        if (!isPackage) { // Only apply delivery fee if not a package (packages include free delivery)
            if (subtotal < 20) {
                deliveryFee = (subtotal * 0.2); // 20% delivery fee for orders under $20
            } else if (subtotal < 50) {
                deliveryFee = (subtotal * 0.1); // 10% delivery fee for orders between $20 and $50
            } else {
                deliveryFee = (subtotal * 0.05); // 5% delivery for orders over $50
            }
            // Ensure delivery fee is at least $5
            deliveryFee = Math.max(deliveryFee, 5);
        }
    }
    
    // Calculate total (subtotal + delivery fee)
    const total = subtotal + deliveryFee;
    
    // Update display
    document.getElementById('orderSubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('deliveryFee').textContent = '$' + deliveryFee.toFixed(2);
    document.getElementById('orderTotal').textContent = '$' + total.toFixed(2);
    document.getElementById('totalAmount').value = total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    // Update totals on page load
    updateTotalsNow();
    
    // Payment method toggle
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.onchange = function() {
            if (this.value === 'online') {
                document.getElementById('onlinePaymentDetails').classList.remove('d-none');
            } else {
                document.getElementById('onlinePaymentDetails').classList.add('d-none');
            }
        };
    });
    
    // Set min date to today
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    document.getElementById('pickup_date').min = yyyy + '-' + mm + '-' + dd;
});
</script>

<?php include_once 'includes/footer.php'; ?>
