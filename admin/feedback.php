<?php
$page_title = "Customer Feedback";
include_once 'includes/header.php';

// Pagination
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query
$sql_count = "SELECT COUNT(*) as total FROM feedbacks f JOIN users u ON f.user_id = u.id JOIN orders o ON f.order_id = o.id WHERE 1=1";
$sql = "SELECT f.*, u.name as customer_name, u.email, o.id as order_id, o.total_amount, DATE_FORMAT(f.created_at, '%M %d, %Y') as formatted_date
        FROM feedbacks f 
        JOIN users u ON f.user_id = u.id 
        JOIN orders o ON f.order_id = o.id 
        WHERE 1=1";

// Add filters
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR f.comment LIKE ?)";
    $sql_count .= " AND (u.name LIKE ? OR u.email LIKE ? OR f.comment LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= "sss";
}

if ($rating_filter > 0) {
    $sql .= " AND f.rating = ?";
    $sql_count .= " AND f.rating = ?";
    array_push($params, $rating_filter);
    $types .= "i";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(f.created_at) >= ?";
    $sql_count .= " AND DATE(f.created_at) >= ?";
    array_push($params, $date_from);
    $types .= "s";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(f.created_at) <= ?";
    $sql_count .= " AND DATE(f.created_at) <= ?";
    array_push($params, $date_to);
    $types .= "s";
}

// Add sorting and pagination
$sql .= " ORDER BY f.created_at DESC LIMIT ?, ?";
array_push($params, $offset, $records_per_page);
$types .= "ii";

// Prepare and execute count query
$count_stmt = $conn->prepare($sql_count);
if (!empty($types) && count($params) > 2) {
    $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Prepare and execute main query
$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$feedbacks = [];

while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
}

// Calculate average rating
$avg_rating_sql = "SELECT AVG(rating) as avg_rating FROM feedbacks";
$avg_rating_result = $conn->query($avg_rating_sql);
$avg_rating = $avg_rating_result->fetch_assoc()['avg_rating'];

// Get rating distribution
$rating_dist_sql = "SELECT rating, COUNT(*) as count FROM feedbacks GROUP BY rating ORDER BY rating DESC";
$rating_dist_result = $conn->query($rating_dist_sql);
$rating_distribution = [];

while ($row = $rating_dist_result->fetch_assoc()) {
    $rating_distribution[$row['rating']] = $row['count'];
}

// Fill in missing ratings with zero
for ($i = 5; $i >= 1; $i--) {
    if (!isset($rating_distribution[$i])) {
        $rating_distribution[$i] = 0;
    }
}
ksort($rating_distribution);
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800">Customer Feedback</h1>
    <p class="mb-4">View and manage all customer feedback and ratings.</p>

    <!-- Overview Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Feedback</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Average Rating</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($avg_rating, 1); ?> <i class="fas fa-star text-warning"></i>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                5-Star Ratings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo isset($rating_distribution[5]) ? $rating_distribution[5] : 0; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-award fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Recent Feedback (Last 7 Days)</div>
                            <?php
                            // Count feedbacks from last 7 days
                            $recent_sql = "SELECT COUNT(*) as count FROM feedbacks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                            $recent_result = $conn->query($recent_sql);
                            $recent_count = $recent_result->fetch_assoc()['count'];
                            ?>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $recent_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rating Distribution Chart -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Rating Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar">
                        <canvas id="ratingDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filter Feedback</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer name, email, or comment">
                        </div>
                        
                        <div class="mb-3">
                            <label for="rating" class="form-label">Filter by Rating</label>
                            <select class="form-control" id="rating" name="rating">
                                <option value="0" <?php echo $rating_filter == 0 ? 'selected' : ''; ?>>All Ratings</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $rating_filter == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?> Only
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="feedback.php" class="btn btn-secondary">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Customer Feedback</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Order #</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($feedbacks) > 0): ?>
                            <?php foreach ($feedbacks as $feedback): ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($feedback['customer_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($feedback['email']); ?></div>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $feedback['order_id']; ?>">#<?php echo $feedback['order_id']; ?></a>
                                        <div class="small">$<?php echo number_format($feedback['total_amount'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= $feedback['rating'] ? 'fas' : 'far'; ?> fa-star text-warning"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($feedback['comment']); ?></td>
                                    <td><?php echo $feedback['formatted_date']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger delete-feedback" data-id="<?php echo $feedback['id']; ?>" data-bs-toggle="modal" data-bs-target="#deleteFeedbackModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No feedback found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Feedback pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $rating_filter > 0 ? '&rating=' . $rating_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $rating_filter > 0 ? '&rating=' . $rating_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $rating_filter > 0 ? '&rating=' . $rating_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Feedback Modal -->
<div class="modal fade" id="deleteFeedbackModal" tabindex="-1" aria-labelledby="deleteFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteFeedbackModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this feedback? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteFeedbackForm" method="post" action="delete_feedback.php">
                    <input type="hidden" id="feedback_id" name="feedback_id" value="">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Rating distribution chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('ratingDistributionChart').getContext('2d');
    
    const distributionData = <?php echo json_encode(array_values($rating_distribution)); ?>;
    const labels = ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'];
    
    const myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Ratings',
                data: distributionData,
                backgroundColor: [
                    '#f8d7da', // 1 star - light red
                    '#fff3cd', // 2 stars - light yellow
                    '#d1e7dd', // 3 stars - light green
                    '#cfe2ff', // 4 stars - light blue
                    '#e8cfff'  // 5 stars - light purple
                ],
                borderColor: [
                    '#dc3545', // 1 star - red
                    '#ffc107', // 2 stars - yellow
                    '#198754', // 3 stars - green
                    '#0d6efd', // 4 stars - blue
                    '#6f42c1'  // 5 stars - purple
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Set feedback ID in delete modal
    document.querySelectorAll('.delete-feedback').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('feedback_id').value = this.getAttribute('data-id');
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
