<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle package creation
if (isset($_POST['create_package'])) {
    $name = $_POST['name'];
    $code = $_POST['code'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $max_garments = $_POST['max_garments'];
    $num_deliveries = $_POST['num_deliveries'];
    $includes_services = implode(',', $_POST['includes_services']);
    $active = isset($_POST['active']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO packages (name, code, description, price, max_garments, num_deliveries, includes_services, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdiisi", $name, $code, $description, $price, $max_garments, $num_deliveries, $includes_services, $active);
    
    if ($stmt->execute()) {
        $success_message = "Package created successfully!";
    } else {
        $error_message = "Error creating package: " . $stmt->error;
    }
}

// Handle package update
if (isset($_POST['update_package'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $code = $_POST['code'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $max_garments = $_POST['max_garments'];
    $num_deliveries = $_POST['num_deliveries'];
    $includes_services = implode(',', $_POST['includes_services']);
    $active = isset($_POST['active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE packages SET name = ?, code = ?, description = ?, price = ?, max_garments = ?, num_deliveries = ?, includes_services = ?, active = ? WHERE id = ?");
    $stmt->bind_param("sssdiisii", $name, $code, $description, $price, $max_garments, $num_deliveries, $includes_services, $active, $id);
    
    if ($stmt->execute()) {
        $success_message = "Package updated successfully!";
    } else {
        $error_message = "Error updating package: " . $stmt->error;
    }
}

// Handle package deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if any orders use this package
    $check_sql = "SELECT COUNT(*) as count FROM orders WHERE package_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $order_count = $check_result->fetch_assoc()['count'];
    
    if ($order_count > 0) {
        $error_message = "Cannot delete this package as it is associated with $order_count orders. You can deactivate it instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM packages WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success_message = "Package deleted successfully!";
        } else {
            $error_message = "Error deleting package: " . $stmt->error;
        }
    }
}

// Get all packages
$sql = "SELECT * FROM packages ORDER BY active DESC, name ASC";
$result = $conn->query($sql);
$packages = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
}

// Get all services for dropdown
$services_sql = "SELECT name FROM services WHERE active = 1 ORDER BY name ASC";
$services_result = $conn->query($services_sql);
$available_services = [];

if ($services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $available_services[] = $row['name'];
    }
}

$page_title = "Manage Packages";
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">Manage Packages</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Create Package Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Create New Package</h5>
        </div>
        <div class="card-body">
            <form method="post" action="" class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Package Name*</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-6">
                    <label for="code" class="form-label">Package Code*</label>
                    <input type="text" class="form-control" id="code" name="code" required>
                    <div class="form-text">Unique code for this package (e.g., weekly, monthly)</div>
                </div>
                <div class="col-md-12">
                    <label for="description" class="form-label">Description*</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                </div>
                <div class="col-md-4">
                    <label for="price" class="form-label">Price*</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="max_garments" class="form-label">Max Garments*</label>
                    <input type="number" class="form-control" id="max_garments" name="max_garments" min="1" required>
                </div>
                <div class="col-md-4">
                    <label for="num_deliveries" class="form-label">Number of Deliveries*</label>
                    <input type="number" class="form-control" id="num_deliveries" name="num_deliveries" min="1" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Included Services*</label>
                    <div class="row">
                        <?php foreach($available_services as $service): ?>
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="includes_services[]" value="<?php echo $service; ?>" id="service_<?php echo md5($service); ?>">
                                    <label class="form-check-label" for="service_<?php echo md5($service); ?>">
                                        <?php echo $service; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <button type="submit" name="create_package" class="btn btn-primary">Create Package</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Packages List -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Existing Packages</h5>
        </div>
        <div class="card-body">
            <?php if (count($packages) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="packagesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Price</th>
                                <th>Max Garments</th>
                                <th>Deliveries</th>
                                <th>Included Services</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $package): ?>
                                <tr class="<?php echo $package['active'] ? '' : 'table-secondary'; ?>">
                                    <td><?php echo $package['id']; ?></td>
                                    <td><?php echo $package['name']; ?></td>
                                    <td><?php echo $package['code']; ?></td>
                                    <td>$<?php echo number_format($package['price'], 2); ?></td>
                                    <td><?php echo $package['max_garments']; ?></td>
                                    <td><?php echo $package['num_deliveries']; ?></td>
                                    <td><?php echo $package['includes_services']; ?></td>
                                    <td>
                                        <?php if ($package['active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-package" 
                                                data-id="<?php echo $package['id']; ?>"
                                                data-name="<?php echo $package['name']; ?>"
                                                data-code="<?php echo $package['code']; ?>"
                                                data-description="<?php echo htmlspecialchars($package['description']); ?>"
                                                data-price="<?php echo $package['price']; ?>"
                                                data-max-garments="<?php echo $package['max_garments']; ?>"
                                                data-num-deliveries="<?php echo $package['num_deliveries']; ?>"
                                                data-includes-services="<?php echo $package['includes_services']; ?>"
                                                data-active="<?php echo $package['active']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editPackageModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="packages.php?delete=<?php echo $package['id']; ?>" class="btn btn-sm btn-danger delete-package" onclick="return confirm('Are you sure you want to delete this package?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">No packages found. Create your first package using the form above.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Package Modal -->
<div class="modal fade" id="editPackageModal" tabindex="-1" aria-labelledby="editPackageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editPackageModalLabel">Edit Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="" id="editPackageForm" class="row g-3">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="col-md-6">
                        <label for="edit_name" class="form-label">Package Name*</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_code" class="form-label">Package Code*</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    <div class="col-md-12">
                        <label for="edit_description" class="form-label">Description*</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_price" class="form-label">Price*</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="edit_price" name="price" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_max_garments" class="form-label">Max Garments*</label>
                        <input type="number" class="form-control" id="edit_max_garments" name="max_garments" min="1" required>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_num_deliveries" class="form-label">Number of Deliveries*</label>
                        <input type="number" class="form-control" id="edit_num_deliveries" name="num_deliveries" min="1" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Included Services*</label>
                        <div class="row" id="edit_services_container">
                            <?php foreach($available_services as $service): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input edit-service-check" type="checkbox" name="includes_services[]" value="<?php echo $service; ?>" id="edit_service_<?php echo md5($service); ?>">
                                        <label class="form-check-label" for="edit_service_<?php echo md5($service); ?>">
                                            <?php echo $service; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_active" name="active">
                            <label class="form-check-label" for="edit_active">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editPackageForm" name="update_package" class="btn btn-primary">Update Package</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if (document.getElementById('packagesTable')) {
        $('#packagesTable').DataTable({
            order: [[0, 'desc']]
        });
    }
    
    // Edit Package Modal
    document.querySelectorAll('.edit-package').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var code = this.getAttribute('data-code');
            var description = this.getAttribute('data-description');
            var price = this.getAttribute('data-price');
            var maxGarments = this.getAttribute('data-max-garments');
            var numDeliveries = this.getAttribute('data-num-deliveries');
            var includesServices = this.getAttribute('data-includes-services').split(',');
            var active = this.getAttribute('data-active') === '1';
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_code').value = code;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_max_garments').value = maxGarments;
            document.getElementById('edit_num_deliveries').value = numDeliveries;
            document.getElementById('edit_active').checked = active;
            
            // Reset all checkboxes
            document.querySelectorAll('.edit-service-check').forEach(function(checkbox) {
                checkbox.checked = false;
            });
            
            // Check the appropriate services
            includesServices.forEach(function(service) {
                var serviceCheckbox = document.querySelector('.edit-service-check[value="' + service.trim() + '"]');
                if (serviceCheckbox) {
                    serviceCheckbox.checked = true;
                }
            });
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
