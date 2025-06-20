<?php
$page_title = "Provide Feedback";
include_once 'includes/header.php';

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    echo "<div class='alert alert-danger'>Invalid order selected.</div>";
    include_once 'includes/footer.php';
    exit;
}

$order_id = $_GET['order_id'];

// Check if order exists and belongs to this user
$order_sql = "SELECT o.*, 
                DATE_FORMAT(o.pickup_date, '%M %d, %Y') as formatted_pickup_date,
                DATE_FORMAT(o.delivery_date, '%M %d, %Y') as formatted_delivery_date
              FROM orders o 
              WHERE o.id = ? AND o.user_id = ?";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows == 0) {
    echo "<div class='alert alert-danger'>Order not found or you don't have permission to view it.</div>";
    include_once 'includes/footer.php';
    exit;
}

$order = $order_result->fetch_assoc();

// Check if order is delivered - only delivered orders can receive feedback
if ($order['status'] != 'delivered') {
    echo "<div class='alert alert-warning'>You can only provide feedback for completed orders.</div>";
    include_once 'includes/footer.php';
    exit;
}

// Check if feedback already exists
$feedback_check_sql = "SELECT * FROM feedbacks WHERE order_id = ? AND user_id = ?";
$feedback_check_stmt = $conn->prepare($feedback_check_sql);
$feedback_check_stmt->bind_param("ii", $order_id, $user_id);
$feedback_check_stmt->execute();
$feedback_result = $feedback_check_stmt->get_result();
$existing_feedback = null;

if ($feedback_result->num_rows > 0) {
    $existing_feedback = $feedback_result->fetch_assoc();
}

// Process feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $rating = sanitize_input($_POST['rating']);
    $comment = sanitize_input($_POST['comment']);
    
    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        $error = "Please select a valid rating (1-5 stars).";
    } else {
        if ($existing_feedback) {
            // Update existing feedback
            $sql = "UPDATE feedbacks SET rating = ?, comment = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $rating, $comment, $existing_feedback['id']);
        } else {
            // Insert new feedback
            $sql = "INSERT INTO feedbacks (user_id, order_id, rating, comment) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $user_id, $order_id, $rating, $comment);
        }
        
        if ($stmt->execute()) {
            $success = "Thank you for your feedback!";
            // Refresh the existing feedback data
            $feedback_check_stmt->execute();
            $feedback_result = $feedback_check_stmt->get_result();
            if ($feedback_result->num_rows > 0) {
                $existing_feedback = $feedback_result->fetch_assoc();
            }
        } else {
            $error = "Error saving your feedback. Please try again.";
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1 class="mb-3">Provide Feedback</h1>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">My Orders</a></li>
                    <li class="breadcrumb-item"><a href="order_details.php?id=<?php echo $order_id; ?>">Order #<?php echo $order_id; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Feedback</li>
                </ol>
            </nav>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Order #<?php echo $order_id; ?> Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                    <p><strong>Pickup Date:</strong> <?php echo $order['formatted_pickup_date']; ?></p>
                    <p><strong>Delivery Date:</strong> <?php echo $order['formatted_delivery_date']; ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-success">Delivered</span></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $existing_feedback ? 'Edit Your Feedback' : 'Rate Your Experience'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating</label>
                            <div class="rating-input">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="rating-<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                        <?php echo ($existing_feedback && $existing_feedback['rating'] == $i) ? 'checked' : ''; ?> required>
                                    <label for="rating-<?php echo $i; ?>">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comments</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Share your experience with our service..."><?php echo $existing_feedback ? $existing_feedback['comment'] : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" name="submit" class="btn btn-primary">
                            <?php echo $existing_feedback ? 'Update Feedback' : 'Submit Feedback'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating-input input {
    display: none;
}

.rating-input label {
    cursor: pointer;
    font-size: 1.5rem;
    padding: 0 0.1em;
    color: #ddd;
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffc107;
}
</style>

<?php include_once 'includes/footer.php'; ?>
