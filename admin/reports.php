<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Default date range is current month
$default_start_date = date('Y-m-01');
$default_end_date = date('Y-m-t');

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Function to get sales data
function getSalesData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                DATE(o.created_at) as order_date,
                COUNT(DISTINCT o.id) as order_count,
                SUM(CASE 
                    WHEN o.package_id IS NOT NULL THEN o.total_amount
                    ELSE (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id)
                END) as total_sales
            FROM 
                orders o
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                DATE(o.created_at)
            ORDER BY 
                order_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

// Function to get service popularity data
function getServicePopularityData($conn, $start_date, $end_date) {
    // First, get regular service order data
    $sql = "SELECT 
                s.name as service_name,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.price) as total_revenue
            FROM 
                services s
            JOIN 
                order_items oi ON s.id = oi.service_id
            JOIN 
                orders o ON oi.order_id = o.id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
                AND o.package_id IS NULL
            GROUP BY 
                s.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $services_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Then, get package-based order data with included services
    $sql = "SELECT 
                s.name as service_name,
                COUNT(o.id) as order_count,
                0 as total_quantity,
                SUM(o.total_amount) as package_revenue
            FROM 
                services s
            JOIN 
                packages p ON FIND_IN_SET(s.id, p.includes_services)
            JOIN 
                orders o ON p.id = o.package_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                s.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $package_services_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Merge the data
    $combined_data = [];
    foreach ($services_data as $service) {
        $combined_data[$service['service_name']] = [
            'service_name' => $service['service_name'],
            'order_count' => $service['order_count'],
            'total_quantity' => $service['total_quantity'],
            'total_revenue' => $service['total_revenue']
        ];
    }
    
    foreach ($package_services_data as $service) {
        if (isset($combined_data[$service['service_name']])) {
            $combined_data[$service['service_name']]['order_count'] += $service['order_count'];
            $combined_data[$service['service_name']]['total_revenue'] += $service['package_revenue'] / count($package_services_data); // Distribute package revenue evenly
        } else {
            $combined_data[$service['service_name']] = [
                'service_name' => $service['service_name'],
                'order_count' => $service['order_count'],
                'total_quantity' => 0, // No specific quantity for packages
                'total_revenue' => $service['package_revenue'] / count($package_services_data) // Distribute package revenue evenly
            ];
        }
    }
    
    // Sort by total revenue
    usort($combined_data, function($a, $b) {
        return $b['total_revenue'] <=> $a['total_revenue'];
    });
    
    return array_values($combined_data);
}

// Function to get customer data
function getCustomerData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                u.name as customer_name,
                u.email as customer_email,
                COUNT(o.id) as order_count,
                SUM(CASE 
                    WHEN o.package_id IS NOT NULL THEN o.total_amount
                    ELSE (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id)
                END) as total_spent
            FROM 
                users u
            JOIN 
                orders o ON u.id = o.user_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                u.id
            ORDER BY 
                total_spent DESC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

// Function to get inventory usage data
function getInventoryUsageData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                i.name as item_name,
                i.unit,
                SUM(CASE WHEN il.adjustment < 0 THEN ABS(il.adjustment) ELSE 0 END) as used_quantity,
                SUM(CASE WHEN il.adjustment > 0 THEN il.adjustment ELSE 0 END) as added_quantity,
                i.current_stock as current_level,
                i.min_stock_level as min_level
            FROM 
                inventory i
            LEFT JOIN 
                inventory_log il ON i.id = il.inventory_id
            WHERE 
                (il.created_at BETWEEN ? AND ? OR il.created_at IS NULL)
            GROUP BY 
                i.id
            ORDER BY 
                used_quantity DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

// Function to get package popularity data
function getPackagePopularityData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                p.name as package_name,
                p.code as package_code,
                p.price as package_price,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_revenue
            FROM 
                packages p
            JOIN 
                orders o ON p.id = o.package_id
            WHERE 
                o.created_at BETWEEN ? AND ?
                AND o.status != 'cancelled'
            GROUP BY 
                p.id
            ORDER BY 
                order_count DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

// Get summary data
function getSummaryData($conn, $start_date, $end_date) {
    // Total orders
    $sql = "SELECT COUNT(*) as total_orders FROM orders WHERE created_at BETWEEN ? AND ? AND status != 'cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['total_orders'] = $result->fetch_assoc()['total_orders'];
    $stmt->close();
    
    // Total sales (including package orders)
    $sql = "SELECT 
            SUM(CASE 
                WHEN o.package_id IS NOT NULL THEN o.total_amount
                ELSE (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id)
            END) as total_sales 
            FROM orders o 
            WHERE o.created_at BETWEEN ? AND ? 
            AND o.status != 'cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['total_sales'] = $result->fetch_assoc()['total_sales'];
    $stmt->close();
    
    // Average order value
    $summary['avg_order_value'] = $summary['total_orders'] > 0 ? $summary['total_sales'] / $summary['total_orders'] : 0;
    
    // Number of package orders
    $sql = "SELECT COUNT(*) as package_orders FROM orders WHERE package_id IS NOT NULL AND created_at BETWEEN ? AND ? AND status != 'cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['package_orders'] = $result->fetch_assoc()['package_orders'];
    $stmt->close();
    
    // Package orders revenue
    $sql = "SELECT SUM(total_amount) as package_revenue FROM orders WHERE package_id IS NOT NULL AND created_at BETWEEN ? AND ? AND status != 'cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['package_revenue'] = $result->fetch_assoc()['package_revenue'];
    $stmt->close();
    
    // New customers
    $sql = "SELECT COUNT(*) as new_customers FROM users WHERE created_at BETWEEN ? AND ? AND user_role = 'customer'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['new_customers'] = $result->fetch_assoc()['new_customers'];
    $stmt->close();
    
    return $summary;
}

// Get data based on report type
$data = [];
$summary = [];

if ($report_type === 'sales') {
    $data = getSalesData($conn, $start_date, $end_date);
} elseif ($report_type === 'services') {
    $data = getServicePopularityData($conn, $start_date, $end_date);
} elseif ($report_type === 'customers') {
    $data = getCustomerData($conn, $start_date, $end_date);
} elseif ($report_type === 'inventory') {
    $data = getInventoryUsageData($conn, $start_date, $end_date);
} elseif ($report_type === 'packages') {
    $data = getPackagePopularityData($conn, $start_date, $end_date);
}

// Get summary data for all report types
$summary = getSummaryData($conn, $start_date, $end_date);

$page_title = "Reports";
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Reports & Analytics</h1>
    
    <!-- Report Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="form-inline">
                <div class="form-group mr-3">
                    <label class="mr-2">Report Type:</label>                    <select name="report_type" class="form-control">
                        <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                        <option value="services" <?php echo $report_type === 'services' ? 'selected' : ''; ?>>Service Popularity</option>
                        <option value="packages" <?php echo $report_type === 'packages' ? 'selected' : ''; ?>>Package Popularity</option>
                        <option value="customers" <?php echo $report_type === 'customers' ? 'selected' : ''; ?>>Customer Report</option>
                        <option value="inventory" <?php echo $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory Usage</option>
                    </select>
                </div>
                
                <div class="form-group mr-3">
                    <label class="mr-2">Date Range:</label>
                    <div class="input-group">
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        <div class="input-group-prepend input-group-append">
                            <span class="input-group-text">to</span>
                        </div>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Generate Report</button>
                
                <div class="ml-auto">
                    <button type="button" id="exportCsv" class="btn btn-success">
                        <i class="fas fa-file-csv"></i> Export to CSV
                    </button>
                    <button type="button" id="printReport" class="btn btn-info ml-2">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['total_orders']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($summary['total_sales'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average Order Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($summary['avg_order_value'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">New Customers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['new_customers']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>    </div>
    
    <!-- Additional Summary Cards for Package Statistics -->
    <div class="row mb-4">
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-purple shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-purple text-uppercase mb-1">Package Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['package_orders']); ?></div>
                            <div class="text-xs text-gray-600"><?php echo $summary['total_orders'] > 0 ? number_format(($summary['package_orders'] / $summary['total_orders']) * 100, 1) : 0; ?>% of total orders</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Package Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($summary['package_revenue'], 2); ?></div>
                            <div class="text-xs text-gray-600"><?php echo $summary['total_sales'] > 0 ? number_format(($summary['package_revenue'] / $summary['total_sales']) * 100, 1) : 0; ?>% of total revenue</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Content -->
    <div class="row">
        <?php if ($report_type === 'sales'): ?>
            <!-- Sales Report -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Sales Trend</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daily Sales Data</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                                            <td><?php echo $row['order_count']; ?></td>
                                            <td>$<?php echo number_format($row['total_sales'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($data)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No data available for selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type === 'services'): ?>
            <!-- Service Popularity Report -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Service Popularity</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar">
                            <canvas id="servicesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Service Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                            <td><?php echo $row['order_count']; ?></td>
                                            <td>$<?php echo number_format($row['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($data)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No data available for selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type === 'customers'): ?>
            <!-- Customer Report -->
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Top Customers</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_email']); ?></td>
                                            <td><?php echo $row['order_count']; ?></td>
                                            <td>$<?php echo number_format($row['total_spent'], 2); ?></td>
                                            <td>
                                                <a href="users.php?search=<?php echo urlencode($row['customer_email']); ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-user"></i> View Profile
                                                </a>
                                            </td>                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($data)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No data available for selected period</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($report_type === 'packages'): ?>
            <!-- Package Popularity Report -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Package Popularity</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar">
                            <canvas id="packagesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Package Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Package</th>
                                        <th>Code</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['package_code']); ?></td>
                                            <td><?php echo $row['order_count']; ?></td>
                                            <td>$<?php echo number_format($row['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($data)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No data available for selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type === 'inventory'): ?>
            <!-- Inventory Usage Report -->
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Inventory Usage</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Unit</th>
                                        <th>Used Qty</th>
                                        <th>Added Qty</th>
                                        <th>Current Level</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr class="<?php echo $row['current_level'] <= $row['min_level'] ? 'table-warning' : ''; ?> <?php echo $row['current_level'] == 0 ? 'table-danger' : ''; ?>">
                                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                            <td><?php echo $row['used_quantity']; ?></td>
                                            <td><?php echo $row['added_quantity']; ?></td>
                                            <td><?php echo $row['current_level']; ?></td>
                                            <td>
                                                <?php if ($row['current_level'] == 0): ?>
                                                    <span class="badge badge-danger">Out of Stock</span>
                                                <?php elseif ($row['current_level'] <= $row['min_level']): ?>
                                                    <span class="badge badge-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($data)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No data available for selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Print report button
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
    
    // Export to CSV
    document.getElementById('exportCsv').addEventListener('click', function() {
        const table = document.getElementById('reportTable');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Replace HTML entities and remove $ sign for CSV format
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/\s+/g, ' ');
                data = data.replace(/\$/g, '');
                row.push('"' + data + '"');
            }
            csv.push(row.join(','));
        }
        
        const csvString = csv.join('\n');
        const filename = '<?php echo $report_type; ?>_report_<?php echo date('Y-m-d'); ?>.csv';
        const link = document.createElement('a');
        link.style.display = 'none';
        link.setAttribute('target', '_blank');
        link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    <?php if ($report_type === 'sales' && !empty($data)): ?>
        // Sales chart
        const salesCtx = document.getElementById('salesChart');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(', ', array_map(function($row) { return "'" . date('M d', strtotime($row['order_date'])) . "'"; }, $data)); ?>],
                datasets: [{
                    label: 'Daily Sales ($)',
                    lineTension: 0.3,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: [<?php echo implode(', ', array_map(function($row) { return $row['total_sales']; }, $data)); ?>],
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10,
                            callback: function(value, index, values) {
                                return '$' + value;
                            }
                        },
                        gridLines: {
                            color: 'rgb(234, 236, 244)',
                            zeroLineColor: 'rgb(234, 236, 244)',
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }],
                },
                legend: {
                    display: false
                },
                tooltips: {
                    backgroundColor: 'rgb(255,255,255)',
                    bodyFontColor: '#858796',
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10,
                    callbacks: {
                        label: function(tooltipItem, chart) {
                            return 'Revenue: $' + tooltipItem.yLabel.toFixed(2);
                        }
                    }
                }
            }
        });
    <?php endif; ?>
    
    <?php if ($report_type === 'services' && !empty($data)): ?>
        // Services chart
        const servicesCtx = document.getElementById('servicesChart');
        const servicesChart = new Chart(servicesCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(', ', array_map(function($row) { return "'" . addslashes($row['service_name']) . "'"; }, $data)); ?>],
                datasets: [{
                    label: 'Orders',
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderColor: '#4e73df',
                    data: [<?php echo implode(', ', array_map(function($row) { return $row['order_count']; }, $data)); ?>],
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            min: 0,
                            maxTicksLimit: 5,
                            padding: 10,
                        },
                        gridLines: {
                            color: 'rgb(234, 236, 244)',
                            zeroLineColor: 'rgb(234, 236, 244)',
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }],
                },
                legend: {
                    display: false
                },
                tooltips: {
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    backgroundColor: 'rgb(255,255,255)',
                    bodyFontColor: '#858796',
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,                caretPadding: 10,
                }
            }
        });
    <?php endif; ?>
    
    <?php if ($report_type === 'packages' && !empty($data)): ?>
        // Packages chart
        const packagesCtx = document.getElementById('packagesChart');
        const packagesChart = new Chart(packagesCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(', ', array_map(function($row) { return "'" . addslashes($row['package_name']) . "'"; }, $data)); ?>],
                datasets: [{
                    label: 'Orders',
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderColor: '#4e73df',
                    data: [<?php echo implode(', ', array_map(function($row) { return $row['order_count']; }, $data)); ?>],
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            min: 0,
                            maxTicksLimit: 5,
                            padding: 10,
                        },
                        gridLines: {
                            color: 'rgb(234, 236, 244)',
                            zeroLineColor: 'rgb(234, 236, 244)',
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }],
                },
                legend: {
                    display: false
                },
                tooltips: {
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    backgroundColor: 'rgb(255,255,255)',
                    bodyFontColor: '#858796',
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                }
            }
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
