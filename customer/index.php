<?php
$page_title = "Dashboard";
include_once 'includes/header.php';

// Get order statistics
$stats_sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
            FROM orders WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get recent orders
$recent_orders_sql = "SELECT o.*, 
                        SUM(oi.quantity) as total_items 
                     FROM orders o 
                     LEFT JOIN order_items oi ON o.id = oi.order_id 
                     WHERE o.user_id = ? 
                     GROUP BY o.id 
                     ORDER BY o.created_at DESC 
                     LIMIT 5";
$recent_orders_stmt = $conn->prepare($recent_orders_sql);
$recent_orders_stmt->bind_param("i", $user_id);
$recent_orders_stmt->execute();
$recent_orders_result = $recent_orders_stmt->get_result();
$recent_orders = [];

while ($order = $recent_orders_result->fetch_assoc()) {
    $recent_orders[] = $order;
}
?>

<h1 class="mb-4">Customer Dashboard</h1>

<!-- Welcome Card -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Welcome, <?php echo $user['name']; ?>!</h5>
        <p class="card-text">Here's an overview of your laundry services and orders.</p>
        <a href="place_order.php" class="btn btn-primary">Place New Order</a>
    </div>
</div>

<!-- Statistics Section -->
<h4 class="mb-3">Order Statistics</h4>
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['total_orders'] ?: 0; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-icon bg-white text-primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
      <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['pending_orders'] ?: 0; ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-icon bg-white text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['processing_orders'] ?: 0; ?></div>
                        <div class="stat-label">Processing Orders</div>
                    </div>
                    <div class="stat-icon bg-white text-info">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
      <div class="col-xl-3 col-md-6">
        <div class="card dashboard-stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['completed_orders'] ?: 0; ?></div>
                        <div class="stat-label">Completed Orders</div>
                    </div>
                    <div class="stat-icon bg-white text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Section -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Recent Orders</h5>
    </div>
    <div class="card-body">
        <?php if (count($recent_orders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td><?php echo $order['total_items']; ?> items</td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>                                    <?php
                                    switch ($order['status']) {
                                        case 'pending':
                                            echo '<span class="status-badge status-pending">Pending</span>';
                                            break;
                                        case 'processing':
                                            echo '<span class="status-badge status-processing">Processing</span>';
                                            break;
                                        case 'completed':
                                            echo '<span class="status-badge status-completed">Completed</span>';
                                            break;
                                        case 'cancelled':
                                            echo '<span class="status-badge status-cancelled">Cancelled</span>';
                                            break;
                                        default:
                                            echo '<span class="status-badge">' . ucfirst($order['status']) . '</span>';
                                    }
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
            <div class="text-end">
                <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
            </div>
        <?php else: ?>
            <p class="text-center py-4">You don't have any orders yet.</p>
            <div class="text-center">
                <a href="place_order.php" class="btn btn-primary">Place Your First Order</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Services Section -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Quick Services</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php
            // Fetch services for quick access
            $services_sql = "SELECT * FROM services WHERE active = 1 LIMIT 3";
            $services_result = $conn->query($services_sql);
            
            if ($services_result->num_rows > 0) {
                while ($service = $services_result->fetch_assoc()) {
                    ?>                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <img src="../assets/images/services/<?php echo $service['image']; ?>" class="card-img-top" alt="<?php echo $service['name']; ?>" style="height: 300px; object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $service['name']; ?></h5>
                                <p class="card-text"><?php echo $service['description']; ?></p>
                                <p class="card-text text-primary fw-bold">$<?php echo number_format($service['price'], 2); ?></p>
                                <a href="place_order.php?service_id=<?php echo $service['id']; ?>" class="btn btn-primary">Order Now</a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="col-12 text-center">No services available at the moment.</div>';
            }
            ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
