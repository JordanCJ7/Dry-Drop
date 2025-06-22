<?php
$page_title = "Admin Dashboard";
include_once 'includes/header.php';

// Get statistics
$stats = [];

// Total users
$user_sql = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN user_role = 'customer' THEN 1 ELSE 0 END) as total_customers,
                SUM(CASE WHEN user_role = 'admin' THEN 1 ELSE 0 END) as total_admins
            FROM users";
$user_result = $conn->query($user_sql);
$user_stats = $user_result->fetch_assoc();
$stats['users'] = $user_stats;

// Total orders
$order_sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'picked_up' OR status = 'in_washing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(total_amount) as total_revenue
            FROM orders";
$order_result = $conn->query($order_sql);
$order_stats = $order_result->fetch_assoc();
$stats['orders'] = $order_stats;

// Check if inventory table exists before querying it
$check_table_sql = "SHOW TABLES LIKE 'inventory'";
$table_exists = $conn->query($check_table_sql)->num_rows > 0;

// Set default values if table doesn't exist
$inventory_stats = ['low_stock' => 0];

if ($table_exists) {
    // Check if the required columns exist in the inventory table
    $check_columns_sql = "SHOW COLUMNS FROM inventory LIKE 'quantity'";
    $quantity_column_exists = $conn->query($check_columns_sql)->num_rows > 0;
    
    $check_threshold_sql = "SHOW COLUMNS FROM inventory LIKE 'threshold'";
    $threshold_column_exists = $conn->query($check_threshold_sql)->num_rows > 0;
    
    // Only query if both columns exist
    if ($quantity_column_exists && $threshold_column_exists) {
        // Inventory alerts - only run if table and columns exist
        $inventory_sql = "SELECT COUNT(*) as low_stock FROM inventory WHERE quantity <= threshold";
        $inventory_result = $conn->query($inventory_sql);
        $inventory_stats = $inventory_result->fetch_assoc();
    }
}
$stats['inventory'] = $inventory_stats;

// Get recent orders
$recent_orders_sql = "SELECT o.*, u.name, u.email
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id
                     ORDER BY o.created_at DESC 
                     LIMIT 5";
$recent_orders_result = $conn->query($recent_orders_sql);
$recent_orders = [];

while ($order = $recent_orders_result->fetch_assoc()) {
    $recent_orders[] = $order;
}

// Get recent users
$recent_users_sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_sql);
$recent_users = [];

while ($user_row = $recent_users_result->fetch_assoc()) {
    $recent_users[] = $user_row;
}
?>

<h1 class="mb-4">Admin Dashboard</h1>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-stat-card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['orders']['total_orders']; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-icon bg-white text-primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-stat-card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count">$<?php echo number_format($stats['orders']['total_revenue'], 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-icon bg-white text-success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-stat-card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['users']['total_customers']; ?></div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                    <div class="stat-icon bg-white text-info">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-stat-card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-count"><?php echo $stats['orders']['pending_orders']; ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-icon bg-white text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Status Overview -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0">Order Status Overview</h5>
            </div>
            <div class="card-body">
                <canvas id="orderStatusChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="orders.php" class="btn btn-primary w-100 d-flex align-items-center justify-content-center py-3">
                            <i class="fas fa-list-alt me-2"></i> Manage Orders
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="services.php" class="btn btn-success w-100 d-flex align-items-center justify-content-center py-3">
                            <i class="fas fa-plus-circle me-2"></i> Add Service
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="users.php" class="btn btn-info w-100 d-flex align-items-center justify-content-center py-3 text-white">
                            <i class="fas fa-user-plus me-2"></i> Add User
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="reports.php" class="btn btn-secondary w-100 d-flex align-items-center justify-content-center py-3">
                            <i class="fas fa-chart-line me-2"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders & Users -->
<div class="row">
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="orders.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo $order['name']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
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
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Users</h5>
                <a href="users.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($recent_users as $user_item): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">                                <div>
                                    <h6 class="mb-0"><?php echo $user_item['name']; ?></h6>
                                    <small class="text-muted"><?php echo $user_item['email']; ?></small>
                                </div>
                                <span class="badge bg-<?php echo $user_item['user_role'] == 'admin' ? 'danger' : 'success'; ?>">
                                    <?php echo ucfirst($user_item['user_role']); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Inventory Alerts</h5>
            </div>            <div class="card-body">
                <?php
                // Check if inventory table exists
                $check_table_sql = "SHOW TABLES LIKE 'inventory'";
                $table_exists = $conn->query($check_table_sql)->num_rows > 0;
                
                if ($table_exists) {
                    // Check if the required columns exist
                    $check_columns_sql = "SHOW COLUMNS FROM inventory LIKE 'quantity'";
                    $quantity_column_exists = $conn->query($check_columns_sql)->num_rows > 0;
                    
                    $check_threshold_sql = "SHOW COLUMNS FROM inventory LIKE 'threshold'";
                    $threshold_column_exists = $conn->query($check_threshold_sql)->num_rows > 0;
                    
                    if ($quantity_column_exists && $threshold_column_exists) {
                        $low_stock_sql = "SELECT * FROM inventory WHERE quantity <= threshold ORDER BY quantity ASC LIMIT 5";
                        $low_stock_result = $conn->query($low_stock_sql);
                        
                        if ($low_stock_result->num_rows > 0) {
                            echo '<ul class="list-group list-group-flush">';
                            while ($item = $low_stock_result->fetch_assoc()) {
                                $stock_percentage = ($item['quantity'] / $item['threshold']) * 100;
                                $alert_class = $stock_percentage <= 30 ? 'danger' : ($stock_percentage <= 70 ? 'warning' : 'success');
                                
                                echo '<li class="list-group-item">';
                                echo '<div class="d-flex justify-content-between align-items-center mb-2">';
                                echo '<div><h6 class="mb-0">' . $item['name'] . '</h6></div>';
                                echo '<span class="badge bg-' . $alert_class . '">' . $item['quantity'] . ' ' . $item['unit'] . '</span>';
                                echo '</div>';
                                echo '<div class="progress" style="height: 5px;">';
                                echo '<div class="progress-bar bg-' . $alert_class . '" role="progressbar" style="width: ' . $stock_percentage . '%"></div>';
                                echo '</div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                            echo '<div class="text-center mt-3">';
                            echo '<a href="inventory.php" class="btn btn-sm btn-outline-primary">View All Inventory</a>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-center py-3 mb-0">No low stock items found.</p>';
                        }
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<p class="mb-0">Inventory tracking structure needs to be set up.</p>';
                        echo '<a href="inventory.php" class="btn btn-sm btn-primary mt-2">Set Up Inventory</a>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-info">';
                    echo '<p class="mb-0">Inventory tracking is not set up yet.</p>';
                    echo '<a href="inventory.php" class="btn btn-sm btn-primary mt-2">Set Up Inventory</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Order Status Chart
    var ctx = document.getElementById('orderStatusChart').getContext('2d');
    var orderStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Processing', 'Ready', 'Delivered'],
            datasets: [{
                data: [
                    <?php echo $stats['orders']['pending_orders']; ?>,
                    <?php echo $stats['orders']['processing_orders']; ?>,
                    <?php echo $stats['orders']['ready_orders']; ?>,
                    <?php echo $stats['orders']['delivered_orders']; ?>
                ],
                backgroundColor: [
                    '#ffc107',
                    '#0d6efd',
                    '#198754',
                    '#212529'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
