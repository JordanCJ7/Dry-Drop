<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$order = null;
$order_items = [];
$services = [];
$users = [];
$is_new = isset($_GET['new']) && $_GET['new'] == 1;

// Get all services for the dropdown
$service_query = "SELECT * FROM services WHERE active = 1";
$service_result = $conn->query($service_query);
while ($row = $service_result->fetch_assoc()) {
    $services[] = $row;
}

// Get all users for the dropdown
$user_query = "SELECT id, name, email FROM users WHERE user_role = 'customer'";
$user_result = $conn->query($user_query);
while ($row = $user_result->fetch_assoc()) {
    $users[] = $row;
}

// Handle form submission for new order or updating existing order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_order']) || isset($_POST['update_order'])) {
        $user_id = $_POST['user_id'];
        $pickup_date = $_POST['pickup_date'];
        $delivery_date = $_POST['delivery_date'];
        $status = $_POST['status'];
        $payment_method = $_POST['payment_method'];
        $payment_status = $_POST['payment_status'];
        $special_instructions = $_POST['special_instructions'];
        
        $conn->begin_transaction();
        
        try {
            if (isset($_POST['create_order'])) {
                // Insert new order
                $stmt = $conn->prepare("INSERT INTO orders (user_id, pickup_date, delivery_date, status, payment_method, payment_status, special_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", $user_id, $pickup_date, $delivery_date, $status, $payment_method, $payment_status, $special_instructions);
                $stmt->execute();
                $order_id = $conn->insert_id;
                $stmt->close();
            } else {
                // Update existing order
                $order_id = $_POST['order_id'];
                $stmt = $conn->prepare("UPDATE orders SET user_id = ?, pickup_date = ?, delivery_date = ?, status = ?, payment_method = ?, payment_status = ?, special_instructions = ? WHERE id = ?");
                $stmt->bind_param("issssssi", $user_id, $pickup_date, $delivery_date, $status, $payment_method, $payment_status, $special_instructions, $order_id);
                $stmt->execute();
                $stmt->close();
                
                // Delete existing order items to replace with new ones
                $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Insert order items
            $service_ids = $_POST['service_id'];
            $quantities = $_POST['quantity'];
            $prices = $_POST['price'];
            
            for ($i = 0; $i < count($service_ids); $i++) {
                if (!empty($service_ids[$i]) && !empty($quantities[$i]) && !empty($prices[$i])) {
                    $stmt = $conn->prepare("INSERT INTO order_items (order_id, service_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $order_id, $service_ids[$i], $quantities[$i], $prices[$i]);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conn->commit();
            $success_message = isset($_POST['create_order']) ? "Order created successfully!" : "Order updated successfully!";
            
            // Redirect to the orders list or refresh the current page
            if (isset($_POST['create_order'])) {
                header("Location: order_details.php?id=$order_id&success=1");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get order details if editing an existing order
if (isset($_GET['id']) && !$is_new) {
    $order_id = $_GET['id'];
    
    // Get order information
    $stmt = $conn->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email, 
                           p.name as package_name, p.price as package_price, p.description as package_description
                           FROM orders o 
                           JOIN users u ON o.user_id = u.id 
                           LEFT JOIN packages p ON o.package_id = p.id
                           WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        $error_message = "Order not found!";
    } else {
        // Get order items
        $stmt = $conn->prepare("SELECT oi.*, s.name as service_name 
                               FROM order_items oi 
                               JOIN services s ON oi.service_id = s.id 
                               WHERE oi.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        while ($item = $items_result->fetch_assoc()) {
            $order_items[] = $item;
        }
        $stmt->close();
    }
}

$page_title = $is_new ? "Create New Order" : "Order Details #" . ($order ? $order['id'] : '');
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo $page_title; ?></h1>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="alert alert-success">Order created successfully!</div>
    <?php endif; ?>
    
    <?php if ($is_new || $order): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo $is_new ? "New Order Form" : "Order #" . $order['id'] . " Details"; ?></h6>
                <a href="orders.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
            <div class="card-body">
                <form method="post" id="orderForm">
                    <?php if (!$is_new): ?>
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer</label>
                                <select name="user_id" class="form-control" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo (!$is_new && $order['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Pickup Date</label>
                                <input type="datetime-local" name="pickup_date" class="form-control" 
                                       value="<?php echo !$is_new ? date('Y-m-d\TH:i', strtotime($order['pickup_date'])) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Delivery Date</label>
                                <input type="datetime-local" name="delivery_date" class="form-control" 
                                       value="<?php echo !$is_new ? date('Y-m-d\TH:i', strtotime($order['delivery_date'])) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="pending" <?php echo (!$is_new && $order['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo (!$is_new && $order['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                    <option value="completed" <?php echo (!$is_new && $order['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo (!$is_new && $order['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="cash" <?php echo (!$is_new && $order['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                    <option value="credit_card" <?php echo (!$is_new && $order['payment_method'] == 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                                    <option value="paypal" <?php echo (!$is_new && $order['payment_method'] == 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Status</label>
                                <select name="payment_status" class="form-control" required>
                                    <option value="pending" <?php echo (!$is_new && $order['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo (!$is_new && $order['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="refunded" <?php echo (!$is_new && $order['payment_status'] == 'refunded') ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-12">                            <div class="form-group">
                                <label>Special Instructions</label>
                                <textarea name="special_instructions" class="form-control" rows="3"><?php echo !$is_new ? htmlspecialchars($order['special_instructions']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$is_new && !empty($order['package_id'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Package Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Package Name:</strong> <?php echo htmlspecialchars($order['package_name']); ?></p>
                                    <p><strong>Package Price:</strong> $<?php echo number_format($order['package_price'], 2); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($order['package_description']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <h4 class="mt-4 mb-3">Order Items</h4>
                    
                    <div id="order-items-container">
                        <?php if (!$is_new && !empty($order_items)): ?>
                            <?php foreach ($order_items as $index => $item): ?>
                                <div class="row order-item mb-2">
                                    <div class="col-md-5">
                                        <select name="service_id[]" class="form-control service-select" required>
                                            <option value="">Select Service</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?php echo $service['id']; ?>" 
                                                        data-price="<?php echo $service['price']; ?>"
                                                        <?php echo $item['service_id'] == $service['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="quantity[]" min="1" class="form-control quantity-input" placeholder="Qty" 
                                               value="<?php echo $item['quantity']; ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" name="price[]" step="0.01" min="0" class="form-control price-input" 
                                                   value="<?php echo $item['price']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger remove-item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="row order-item mb-2">
                                <div class="col-md-5">
                                    <select name="service_id[]" class="form-control service-select" required>
                                        <option value="">Select Service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                                                <?php echo htmlspecialchars($service['name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="quantity[]" min="1" class="form-control quantity-input" placeholder="Qty" value="1" required>
                                </div>
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" name="price[]" step="0.01" min="0" class="form-control price-input" value="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-danger remove-item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <button type="button" id="add-item" class="btn btn-info">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6 offset-md-6">
                            <table class="table">
                                <tr>
                                    <th>Total:</th>
                                    <td class="text-right">$<span id="order-total">0.00</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <button type="submit" name="<?php echo $is_new ? 'create_order' : 'update_order'; ?>" class="btn btn-primary btn-lg">
                            <?php echo $is_new ? 'Create Order' : 'Update Order'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Order not found!</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate initial total
    calculateTotal();
    
    // Add new item row
    document.getElementById('add-item').addEventListener('click', function() {
        const container = document.getElementById('order-items-container');
        const template = document.querySelector('.order-item').cloneNode(true);
        
        // Reset values in the cloned template
        template.querySelector('.service-select').value = '';
        template.querySelector('.quantity-input').value = '1';
        template.querySelector('.price-input').value = '0.00';
        
        // Add event listeners to the new row
        addItemEventListeners(template);
        
        container.appendChild(template);
        calculateTotal();
    });
    
    // Add event listeners to existing item rows
    document.querySelectorAll('.order-item').forEach(item => {
        addItemEventListeners(item);
    });
    
    function addItemEventListeners(item) {
        // Service selection changes price
        item.querySelector('.service-select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                item.querySelector('.price-input').value = price;
                calculateTotal();
            }
        });
        
        // Quantity changes affect total
        item.querySelector('.quantity-input').addEventListener('input', function() {
            calculateTotal();
        });
        
        // Price changes affect total
        item.querySelector('.price-input').addEventListener('input', function() {
            calculateTotal();
        });
        
        // Remove item
        item.querySelector('.remove-item').addEventListener('click', function() {
            const items = document.querySelectorAll('.order-item');
            if (items.length > 1) {
                item.remove();
                calculateTotal();
            } else {
                alert('At least one item is required.');
            }
        });
    }
      // Calculate order total
    function calculateTotal() {
        let total = 0;
        
        // Add package price if it exists
        <?php if (!$is_new && !empty($order['package_id'])): ?>
        total += <?php echo (float)$order['package_price']; ?>;
        <?php endif; ?>
        
        // Add individual items
        document.querySelectorAll('.order-item').forEach(item => {
            const quantity = parseFloat(item.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(item.querySelector('.price-input').value) || 0;
            total += quantity * price;
        });
        
        document.getElementById('order-total').textContent = total.toFixed(2);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
