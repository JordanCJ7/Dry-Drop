<?php
$page_title = "My Orders";
include_once 'includes/header.php';

// Pagination setup
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Filter by status
$status_filter = '';
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']);
    $status_filter = "AND o.status = '$status'";
}

// Get total orders count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.user_id = ? $status_filter";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get orders with pagination
$orders_sql = "SELECT o.*, 
                SUM(oi.quantity) as total_items 
              FROM orders o 
              LEFT JOIN order_items oi ON o.id = oi.order_id 
              WHERE o.user_id = ? $status_filter
              GROUP BY o.id 
              ORDER BY o.created_at DESC 
              LIMIT ?, ?";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("iii", $user_id, $offset, $records_per_page);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    $orders[] = $order;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>My Orders</h1>
    <a href="place_order.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> Place New Order
    </a>
</div>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Filter by Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Orders</option>
                    <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="picked_up" <?php echo isset($_GET['status']) && $_GET['status'] == 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
                    <option value="in_washing" <?php echo isset($_GET['status']) && $_GET['status'] == 'in_washing' ? 'selected' : ''; ?>>In Washing</option>
                    <option value="ready" <?php echo isset($_GET['status']) && $_GET['status'] == 'ready' ? 'selected' : ''; ?>>Ready</option>
                    <option value="delivered" <?php echo isset($_GET['status']) && $_GET['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <?php if (isset($_GET['status']) && !empty($_GET['status'])): ?>
                    <a href="orders.php" class="btn btn-outline-secondary ms-2">Clear Filter</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Orders List -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Order History</h5>
    </div>
    <div class="card-body">
        <?php if (count($orders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td><?php echo $order['total_items']; ?> items</td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
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
                                </td>
                                <td>
                                    <?php
                                    if ($order['payment_status'] == 'paid') {
                                        echo '<span class="badge bg-success">Paid</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    }
                                    echo ' (' . ucfirst($order['payment_method']) . ')';
                                    ?>
                                </td>
                                <td>
                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Orders pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-basket fa-3x text-muted mb-3"></i>
                <h4>No orders found</h4>
                <p class="text-muted">You haven't placed any orders yet or no orders match your filter.</p>
                <a href="place_order.php" class="btn btn-primary mt-3">Place Your First Order</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
