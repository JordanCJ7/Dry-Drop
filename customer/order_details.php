<?php
$page_title = "Order Details";
include_once 'includes/header.php';

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid order ID";
    $_SESSION['message_type'] = "danger";
    header("Location: orders.php");
    exit;
}

$order_id = intval($_GET['id']);

// Get order details
$order_sql = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows != 1) {
    $_SESSION['message'] = "Order not found";
    $_SESSION['message_type'] = "danger";
    header("Location: orders.php");
    exit;
}

$order = $order_result->fetch_assoc();

// Get order items
$items_sql = "SELECT oi.*, s.name, s.image 
             FROM order_items oi 
             JOIN services s ON oi.service_id = s.id 
             WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = [];

while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Check if the order is delivered and feedback not submitted
$can_submit_feedback = false;
$has_feedback = false;
$feedback = null;

if ($order['status'] == 'delivered') {
    // Check if feedback exists
    $feedback_sql = "SELECT * FROM feedbacks WHERE order_id = ? AND user_id = ?";
    $feedback_stmt = $conn->prepare($feedback_sql);
    $feedback_stmt->bind_param("ii", $order_id, $user_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    
    if ($feedback_result->num_rows > 0) {
        $has_feedback = true;
        $feedback = $feedback_result->fetch_assoc();
    } else {
        $can_submit_feedback = true;
    }
}

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $rating = intval($_POST['rating']);
    $comment = sanitize_input($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please provide a valid rating";
    } else {
        $feedback_sql = "INSERT INTO feedbacks (user_id, order_id, rating, comment) VALUES (?, ?, ?, ?)";
        $feedback_stmt = $conn->prepare($feedback_sql);
        $feedback_stmt->bind_param("iiis", $user_id, $order_id, $rating, $comment);
        
        if ($feedback_stmt->execute()) {
            $_SESSION['message'] = "Thank you for your feedback!";
            $_SESSION['message_type'] = "success";
            header("Location: order_details.php?id=" . $order_id);
            exit;
        } else {
            $error = "Failed to submit feedback. Please try again.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Order #<?php echo $order['id']; ?></h1>
    <a href="orders.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i> Back to Orders
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Order Status Timeline -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Order Status</h5>
            </div>
            <div class="card-body">
                <div class="order-timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker <?php echo in_array($order['status'], ['pending', 'picked_up', 'in_washing', 'ready', 'delivered']) ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>Order Placed</h5>
                            <p>Your order has been received and is being processed.</p>
                            <div class="timeline-date">
                                <i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker <?php echo in_array($order['status'], ['picked_up', 'in_washing', 'ready', 'delivered']) ? 'active' : ''; ?>">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>Picked Up</h5>
                            <p>Your laundry has been picked up from your location.</p>
                            <?php if (in_array($order['status'], ['picked_up', 'in_washing', 'ready', 'delivered'])): ?>
                                <div class="timeline-date">
                                    <i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($order['pickup_date'])); ?> 
                                    <i class="far fa-clock ms-2 me-1"></i> <?php echo date('h:i A', strtotime($order['pickup_time'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker <?php echo in_array($order['status'], ['in_washing', 'ready', 'delivered']) ? 'active' : ''; ?>">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>In Process</h5>
                            <p>Your laundry is currently being processed.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker <?php echo in_array($order['status'], ['ready', 'delivered']) ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>Ready for Delivery</h5>
                            <p>Your laundry is cleaned and ready for delivery.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker <?php echo $order['status'] == 'delivered' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>Delivered</h5>
                            <p>Your clean laundry has been delivered to your location.</p>
                            <?php if ($order['status'] == 'delivered' && $order['delivery_date']): ?>
                                <div class="timeline-date">
                                    <i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?> 
                                    <i class="far fa-clock ms-2 me-1"></i> <?php echo date('h:i A', strtotime($order['delivery_time'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Current Status:</strong>
                        <?php
                        switch ($order['status']) {
                            case 'pending':
                                echo '<span class="status-badge status-pending">Pending</span>';
                                break;
                            case 'picked_up':
                                echo '<span class="status-badge status-picked-up">Picked Up</span>';
                                break;
                            case 'in_washing':
                                echo '<span class="status-badge status-in-washing">In Washing</span>';
                                break;
                            case 'ready':
                                echo '<span class="status-badge status-ready">Ready</span>';
                                break;
                            case 'delivered':
                                echo '<span class="status-badge status-delivered">Delivered</span>';
                                break;
                        }
                        ?>
                    </div>
                    <div>
                        <strong>Payment Status:</strong>
                        <?php
                        if ($order['payment_status'] == 'paid') {
                            echo '<span class="badge bg-success">Paid</span>';
                        } else {
                            echo '<span class="badge bg-warning text-dark">Pending</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <img src="../assets/images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $item['name']; ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th>$<?php echo number_format($order['total_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Feedback Section -->
        <?php if ($can_submit_feedback): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Rate Your Experience</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label class="form-label">Your Rating</label>
                            <div class="star-rating mb-3">
                                <i class="far fa-star rating-star" data-value="1"></i>
                                <i class="far fa-star rating-star" data-value="2"></i>
                                <i class="far fa-star rating-star" data-value="3"></i>
                                <i class="far fa-star rating-star" data-value="4"></i>
                                <i class="far fa-star rating-star" data-value="5"></i>
                                <input type="hidden" name="rating" id="rating" value="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Your Comment</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Share your experience with our service..."></textarea>
                        </div>
                        
                        <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                    </form>
                </div>
            </div>
        <?php elseif ($has_feedback): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Your Feedback</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Your Rating</label>
                        <div class="star-rating mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?php echo $i <= $feedback['rating'] ? 'fas' : 'far'; ?> fa-star text-warning"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Comment</label>
                        <p><?php echo $feedback['comment'] ? htmlspecialchars($feedback['comment']) : 'No comment provided.'; ?></p>
                    </div>
                    
                    <div class="text-muted">
                        <small>Submitted on <?php echo date('M d, Y h:i A', strtotime($feedback['created_at'])); ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Order Summary -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Order Summary</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Order ID:</span>
                        <span>#<?php echo $order['id']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Order Date:</span>
                        <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Total Items:</span>
                        <span><?php echo count($items); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Payment Method:</span>
                        <span><?php echo ucfirst($order['payment_method']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Total Amount:</span>
                        <span class="fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Pickup/Delivery Details -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Pickup Details</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <span class="fw-bold">Date & Time:</span><br>
                        <?php echo date('M d, Y', strtotime($order['pickup_date'])); ?> at 
                        <?php echo date('h:i A', strtotime($order['pickup_time'])); ?>
                    </li>
                    <li class="list-group-item">
                        <span class="fw-bold">Address:</span><br>
                        <?php echo nl2br(htmlspecialchars($order['pickup_address'])); ?>
                    </li>
                </ul>
            </div>
        </div>
        
        <?php if ($order['status'] == 'delivered' && $order['delivery_date']): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Delivery Details</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <span class="fw-bold">Date & Time:</span><br>
                            <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?> at 
                            <?php echo date('h:i A', strtotime($order['delivery_time'])); ?>
                        </li>
                        <li class="list-group-item">
                            <span class="fw-bold">Address:</span><br>
                            <?php echo nl2br(htmlspecialchars($order['delivery_address'] ?: $order['pickup_address'])); ?>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="d-grid gap-2">
            <?php if ($order['status'] == 'pending'): ?>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                    <i class="fas fa-times-circle me-2"></i> Cancel Order
                </button>
            <?php endif; ?>
            
            <a href="place_order.php" class="btn btn-primary">
                <i class="fas fa-redo me-2"></i> Place Similar Order
            </a>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<?php if ($order['status'] == 'pending'): ?>
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="cancel_order.php?id=<?php echo $order['id']; ?>" class="btn btn-danger">Cancel Order</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Star rating functionality
    const ratingStars = document.querySelectorAll('.rating-star');
    const ratingInput = document.getElementById('rating');
    
    if (ratingStars && ratingInput) {
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                ratingInput.value = value;
                
                // Update star appearance
                ratingStars.forEach(s => {
                    if (s.dataset.value <= value) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
            
            // Hover effect
            star.addEventListener('mouseover', function() {
                const value = this.dataset.value;
                
                ratingStars.forEach(s => {
                    if (s.dataset.value <= value) {
                        s.classList.add('text-warning');
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                ratingStars.forEach(s => {
                    if (s.classList.contains('fas') === false) {
                        s.classList.remove('text-warning');
                    }
                });
            });
        });
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>
