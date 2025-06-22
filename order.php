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
    // Package (pre-selected services)
    $package = $_GET['package'];
    
    if ($package == 'weekly') {
        $sql = "SELECT * FROM services WHERE active = 1 LIMIT 3";    } elseif ($package == 'monthly') {
        $sql = "SELECT * FROM services WHERE active = 1";
    } elseif ($package == 'family') {
        $sql = "SELECT * FROM services WHERE active = 1 LIMIT 4";
    } else {
        $sql = "SELECT * FROM services WHERE active = 1";
    }
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
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
    $total_amount = sanitize_input($_POST['total_amount']);
    
    // Validate input
    if (empty($pickup_date) || empty($pickup_time) || empty($pickup_address)) {
        $error = "Please fill in all required fields";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create order
            $order_sql = "INSERT INTO orders (user_id, total_amount, status, payment_status, payment_method, pickup_date, pickup_time, pickup_address) 
                        VALUES (?, ?, 'pending', 'pending', ?, ?, ?, ?)";
            $order_stmt = $conn->prepare($order_sql);
            $order_stmt->bind_param("idssss", $user_id, $total_amount, $payment_method, $pickup_date, $pickup_time, $pickup_address);
            $order_stmt->execute();
            
            $order_id = $conn->insert_id;
            
            // Add order items
            foreach ($_POST['quantity'] as $service_id => $quantity) {
                if ($quantity > 0) {
                    $price_sql = "SELECT price FROM services WHERE id = ?";
                    $price_stmt = $conn->prepare($price_sql);
                    $price_stmt->bind_param("i", $service_id);
                    $price_stmt->execute();
                    $price_result = $price_stmt->get_result();
                    $service_price = $price_result->fetch_assoc()['price'];
                    
                    $item_sql = "INSERT INTO order_items (order_id, service_id, quantity, price) VALUES (?, ?, ?, ?)";
                    $item_stmt = $conn->prepare($item_sql);
                    $item_stmt->bind_param("iiid", $order_id, $service_id, $quantity, $service_price);
                    $item_stmt->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['message'] = "Your order has been placed successfully. Order ID: " . $order_id;
            $_SESSION['message_type'] = "success";
            
            // Redirect to order details
            header("Location: customer/order_details.php?id=" . $order_id);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "An error occurred while placing your order. Please try again.";
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
                                                <td class="service-price" data-price="<?php echo $service['price']; ?>">$<?php echo number_format($service['price'], 2); ?></td>                                                <td>
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
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span>Subtotal:</span>
                                <span id="orderTotal">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Delivery Fee:</span>
                                <span>$0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3 fw-bold">
                                <span>Total:</span>
                                <span id="orderTotal2">$0.00</span>
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
    let total = 0;
    
    document.querySelectorAll('.service-item').forEach(item => {
        const price = parseFloat(item.querySelector('.service-price').dataset.price);
        const input = item.querySelector('input[type="number"]');
        const quantity = parseInt(input.value) || 0;
        const itemTotal = price * quantity;
        
        item.querySelector('.item-total').textContent = '$' + itemTotal.toFixed(2);
        total += itemTotal;
    });
    
    document.getElementById('orderTotal').textContent = '$' + total.toFixed(2);
    document.getElementById('orderTotal2').textContent = '$' + total.toFixed(2);
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
