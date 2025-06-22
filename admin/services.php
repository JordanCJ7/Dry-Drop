<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle service creation
if (isset($_POST['create_service'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $active = isset($_POST['active']) ? 1 : 0;
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/images/services/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_extension, $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = 'assets/images/services/' . $new_filename;
            } else {
                $error_message = "Failed to upload image.";
            }
        } else {
            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    if (!isset($error_message)) {
        $stmt = $conn->prepare("INSERT INTO services (name, description, price, image, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsi", $name, $description, $price, $image, $active);
        
        if ($stmt->execute()) {
            $success_message = "Service created successfully!";
        } else {
            $error_message = "Failed to create service: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle service update
if (isset($_POST['update_service'])) {
    $service_id = $_POST['service_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $active = isset($_POST['active']) ? 1 : 0;
    
    // Get current image
    $stmt = $conn->prepare("SELECT image FROM services WHERE id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $stmt->bind_result($current_image);
    $stmt->fetch();
    $stmt->close();
    
    // Handle image upload
    $image = $current_image;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../assets/images/services/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_extension, $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image if it exists
                if (!empty($current_image) && file_exists("../" . $current_image)) {
                    unlink("../" . $current_image);
                }
                $image = 'assets/images/services/' . $new_filename;
            } else {
                $error_message = "Failed to upload image.";
            }
        } else {
            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    if (!isset($error_message)) {
        $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, price = ?, image = ?, active = ? WHERE id = ?");
        $stmt->bind_param("ssdsii", $name, $description, $price, $image, $active, $service_id);
        
        if ($stmt->execute()) {
            $success_message = "Service updated successfully!";
        } else {
            $error_message = "Failed to update service: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle service deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $service_id = $_GET['id'];
    
    // Check if the service is used in any orders
    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE service_id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    if ($count > 0) {
        $error_message = "Cannot delete this service because it is used in orders. Consider marking it as inactive instead.";
    } else {
        // Get image path to delete file
        $stmt = $conn->prepare("SELECT image FROM services WHERE id = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $stmt->bind_result($image);
        $stmt->fetch();
        $stmt->close();
        
        // Delete the service
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param("i", $service_id);
        
        if ($stmt->execute()) {
            // Delete image file if it exists
            if (!empty($image) && file_exists("../" . $image)) {
                unlink("../" . $image);
            }
            $success_message = "Service deleted successfully!";
        } else {
            $error_message = "Failed to delete service: " . $conn->error;
        }
        $stmt->close();
    }
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
    $search_condition = "WHERE name LIKE ? OR description LIKE ?";
}

// Count total services for pagination
$count_sql = "SELECT COUNT(*) as total FROM services ";
if (!empty($search_condition)) {
    $count_sql .= $search_condition;
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $search_param, $search_param);
} else {
    $count_stmt = $conn->prepare($count_sql);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Get services with pagination
$sql = "SELECT * FROM services ";
if (!empty($search_condition)) {
    $sql .= $search_condition;
}
$sql .= " ORDER BY name ASC LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!empty($search_condition)) {
    $stmt->bind_param("ssii", $search_param, $search_param, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();
$result = $stmt->get_result();
$services = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Manage Services";
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Manage Services</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Service List</h6>
                    <div class="d-flex">
                        <form class="form-inline mr-2" method="GET">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search services..." value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="60">Image</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($services)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No services found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($services as $service): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($service['image']) && file_exists("../" . $service['image'])): ?>
                                                    <img src="../assets/images/services/<?php echo $service['image']; ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" class="img-thumbnail" width="50">
                                                <?php else: ?>
                                                    <img src="../assets/images/no-image.jpg" alt="No Image" class="img-thumbnail" width="50">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($service['name']); ?><br>
                                                <small class="text-muted"><?php echo substr(htmlspecialchars($service['description']), 0, 50); ?><?php echo strlen($service['description']) > 50 ? '...' : ''; ?></small>
                                            </td>
                                            <td>$<?php echo number_format($service['price'], 2); ?></td>
                                            <td>
                                                <?php if ($service['active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-primary edit-service" 
                                                            data-id="<?php echo $service['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                            data-price="<?php echo $service['price']; ?>"
                                                            data-active="<?php echo $service['active']; ?>"
                                                            data-image="<?php echo $service['image']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="services.php?delete=1&id=<?php echo $service['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this service?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary" id="form-title">Add New Service</h6>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" id="serviceForm">
                        <input type="hidden" name="service_id" id="service_id">
                        
                        <div class="form-group">
                            <label>Service Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="description" class="form-control" rows="4" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Service Image</label>
                            <input type="file" name="image" id="image" class="form-control-file">
                            <small class="form-text text-muted">Recommended size: 600x400px, Max: 2MB</small>
                            <div id="current-image-container" class="mt-2" style="display: none;">
                                <label>Current Image:</label>
                                <img id="current-image" src="" alt="Current Image" class="img-thumbnail" width="100">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="active" name="active" checked>
                                <label class="custom-control-label" for="active">Active</label>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="create_service" id="submit-btn" class="btn btn-primary">Add Service</button>
                            <button type="button" id="cancel-btn" class="btn btn-secondary" style="display: none;">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const serviceForm = document.getElementById('serviceForm');
    const formTitle = document.getElementById('form-title');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const currentImageContainer = document.getElementById('current-image-container');
    const currentImage = document.getElementById('current-image');
    
    // Edit service button click
    document.querySelectorAll('.edit-service').forEach(button => {
        button.addEventListener('click', function() {
            const serviceId = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const price = this.getAttribute('data-price');
            const active = this.getAttribute('data-active') === '1';
            const image = this.getAttribute('data-image');
            
            // Fill the form
            document.getElementById('service_id').value = serviceId;
            document.getElementById('name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('price').value = price;
            document.getElementById('active').checked = active;
            
            // Show current image if available
            if (image) {
                currentImage.src = '../' + image;
                currentImageContainer.style.display = 'block';
            } else {
                currentImageContainer.style.display = 'none';
            }
            
            // Change form to update mode
            formTitle.textContent = 'Edit Service';
            submitBtn.textContent = 'Update Service';
            submitBtn.name = 'update_service';
            cancelBtn.style.display = 'inline-block';
            
            // Scroll to form
            serviceForm.scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Cancel button click
    cancelBtn.addEventListener('click', function() {
        resetForm();
    });
    
    function resetForm() {
        serviceForm.reset();
        document.getElementById('service_id').value = '';
        formTitle.textContent = 'Add New Service';
        submitBtn.textContent = 'Add Service';
        submitBtn.name = 'create_service';
        cancelBtn.style.display = 'none';
        currentImageContainer.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
