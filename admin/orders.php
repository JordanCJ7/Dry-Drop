<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle order status update
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        $success_message = "Order status updated successfully!";
    } else {
        $error_message = "Failed to update order status: " . $conn->error;
    }
    $stmt->close();
}

// Handle order deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $order_id = $_GET['id'];
    
    // First delete order items
    $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
    
    // Then delete the order
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    
    if ($stmt->execute()) {
        $success_message = "Order deleted successfully!";
    } else {
        $error_message = "Failed to delete order: " . $conn->error;
    }
    $stmt->close();
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search_param = "%$search%";
    $search_condition = "WHERE o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR o.status LIKE ?";
}

// Filter by status
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
if (!empty($status_filter)) {
    $search_condition = empty($search_condition) ? "WHERE o.status = ?" : $search_condition . " AND o.status = ?";
}

// Count total orders for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o 
              JOIN users u ON o.user_id = u.id ";
              
if (!empty($search_condition)) {
    $count_sql .= $search_condition;
    $count_stmt = $conn->prepare($count_sql);
    
    if (!empty($search) && !empty($status_filter)) {
        $count_stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $status_filter);
    } elseif (!empty($search)) {
        $count_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    } else {
        $count_stmt->bind_param("s", $status_filter);
    }
} else {
    $count_stmt = $conn->prepare($count_sql);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Get orders with pagination
$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email,
        CASE 
            WHEN o.package_id IS NOT NULL THEN o.total_amount
            ELSE (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id)
        END as total_amount
        FROM orders o
        JOIN users u ON o.user_id = u.id ";

if (!empty($search_condition)) {
    $sql .= $search_condition;
}

$sql .= " ORDER BY o.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if (!empty($search) && !empty($status_filter)) {
    $stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $status_filter, $offset, $limit);
} elseif (!empty($search)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $search_param, $offset, $limit);
} elseif (!empty($status_filter)) {
    $stmt->bind_param("sii", $status_filter, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Manage Orders";
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Manage Orders</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Order List</h6>
            <div class="d-flex">
                <form class="form-inline mr-2" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status_filter" class="form-control ml-2">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
                <a href="order_details.php?new=1" class="btn btn-success">
                    <i class="fas fa-plus"></i> New Order
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $order['status'] === 'completed' ? 'success' : 
                                                ($order['status'] === 'processing' ? 'primary' : 
                                                ($order['status'] === 'cancelled' ? 'danger' : 'warning')); 
                                            ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" 
                                                    data-target="#statusModal<?php echo $order['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="orders.php?delete=1&id=<?php echo $order['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this order?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                        
                                        <!-- Status Update Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Order Status</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <div class="form-group">
                                                                <label>Order Status</label>
                                                                <select name="status" class="form-control">
                                                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                                    <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status_filter=<?php echo urlencode($status_filter); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status_filter=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status_filter=<?php echo urlencode($status_filter); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
