<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle inventory item creation
if (isset($_POST['create_item'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $current_stock = $_POST['current_stock'];
    $min_stock_level = $_POST['min_stock_level'];
    $unit = $_POST['unit'];
    $supplier = $_POST['supplier'];
    $cost_per_unit = $_POST['cost_per_unit'];
    
    $stmt = $conn->prepare("INSERT INTO inventory (name, description, current_stock, min_stock_level, unit, supplier, cost_per_unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiisd", $name, $description, $current_stock, $min_stock_level, $unit, $supplier, $cost_per_unit);
    
    if ($stmt->execute()) {
        $success_message = "Inventory item created successfully!";
    } else {
        $error_message = "Failed to create inventory item: " . $conn->error;
    }
    $stmt->close();
}

// Handle inventory item update
if (isset($_POST['update_item'])) {
    $item_id = $_POST['item_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $current_stock = $_POST['current_stock'];
    $min_stock_level = $_POST['min_stock_level'];
    $unit = $_POST['unit'];
    $supplier = $_POST['supplier'];
    $cost_per_unit = $_POST['cost_per_unit'];
    
    $stmt = $conn->prepare("UPDATE inventory SET name = ?, description = ?, current_stock = ?, min_stock_level = ?, unit = ?, supplier = ?, cost_per_unit = ? WHERE id = ?");
    $stmt->bind_param("ssiissdi", $name, $description, $current_stock, $min_stock_level, $unit, $supplier, $cost_per_unit, $item_id);
    
    if ($stmt->execute()) {
        $success_message = "Inventory item updated successfully!";
    } else {
        $error_message = "Failed to update inventory item: " . $conn->error;
    }
    $stmt->close();
}

// Handle inventory item deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $item_id = $_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    
    if ($stmt->execute()) {
        $success_message = "Inventory item deleted successfully!";
    } else {
        $error_message = "Failed to delete inventory item: " . $conn->error;
    }
    $stmt->close();
}

// Handle stock adjustment
if (isset($_POST['adjust_stock'])) {
    $item_id = $_POST['item_id'];
    $adjustment = $_POST['adjustment'];
    $reason = $_POST['reason'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update current stock
        $stmt = $conn->prepare("UPDATE inventory SET current_stock = current_stock + ? WHERE id = ?");
        $stmt->bind_param("ii", $adjustment, $item_id);
        $stmt->execute();
        $stmt->close();
        
        // Log the adjustment
        $stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, adjustment, reason, admin_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $item_id, $adjustment, $reason, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $success_message = "Stock adjusted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
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
    $search_condition = "WHERE name LIKE ? OR description LIKE ? OR supplier LIKE ?";
}

// Stock level filter
$stock_filter = isset($_GET['stock_filter']) ? $_GET['stock_filter'] : '';
if (!empty($stock_filter)) {
    if ($stock_filter === 'low') {
        $search_condition = empty($search_condition) ? 
                            "WHERE current_stock <= min_stock_level" : 
                            $search_condition . " AND current_stock <= min_stock_level";
    } elseif ($stock_filter === 'out') {
        $search_condition = empty($search_condition) ? 
                            "WHERE current_stock = 0" : 
                            $search_condition . " AND current_stock = 0";
    }
}

// Count total inventory items for pagination
$count_sql = "SELECT COUNT(*) as total FROM inventory ";
if (!empty($search_condition)) {
    $count_sql .= $search_condition;
    $count_stmt = $conn->prepare($count_sql);
    
    if (strpos($search_condition, "LIKE") !== false) {
        $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
} else {
    $count_stmt = $conn->prepare($count_sql);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Get inventory items with pagination
$sql = "SELECT * FROM inventory ";
if (!empty($search_condition)) {
    $sql .= $search_condition;
}
$sql .= " ORDER BY name ASC LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (strpos($search_condition, "LIKE") !== false) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();
$result = $stmt->get_result();
$inventory_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Inventory Management";
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Inventory Management</h1>
    
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
                    <h6 class="m-0 font-weight-bold text-primary">Inventory Items</h6>
                    <div class="d-flex">
                        <form class="form-inline mr-2" method="GET">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search inventory..." value="<?php echo htmlspecialchars($search); ?>">
                                <select name="stock_filter" class="form-control ml-2">
                                    <option value="">All Stock Levels</option>
                                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
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
                                    <th>Name</th>
                                    <th>Current Stock</th>
                                    <th>Min Level</th>
                                    <th>Unit</th>
                                    <th>Supplier</th>
                                    <th>Cost</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory_items)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No inventory items found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($inventory_items as $item): ?>
                                        <tr class="<?php echo $item['current_stock'] <= $item['min_stock_level'] ? 'table-warning' : ''; ?> <?php echo $item['current_stock'] == 0 ? 'table-danger' : ''; ?>">
                                            <td>
                                                <?php echo htmlspecialchars($item['name']); ?><br>
                                                <small class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?><?php echo strlen($item['description']) > 50 ? '...' : ''; ?></small>
                                            </td>
                                            <td><?php echo $item['current_stock']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                            <td><?php echo $item['min_stock_level']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                            <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                                            <td>$<?php echo number_format($item['cost_per_unit'], 2); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info adjust-stock" 
                                                            data-id="<?php echo $item['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                            data-toggle="modal" data-target="#adjustStockModal">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-primary edit-item" 
                                                            data-id="<?php echo $item['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                            data-current-stock="<?php echo $item['current_stock']; ?>"
                                                            data-min-stock-level="<?php echo $item['min_stock_level']; ?>"
                                                            data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                                            data-supplier="<?php echo htmlspecialchars($item['supplier']); ?>"
                                                            data-cost-per-unit="<?php echo $item['cost_per_unit']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="inventory.php?delete=1&id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this inventory item?');">
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&stock_filter=<?php echo urlencode($stock_filter); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&stock_filter=<?php echo urlencode($stock_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&stock_filter=<?php echo urlencode($stock_filter); ?>">Next</a>
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
                    <h6 class="m-0 font-weight-bold text-primary" id="form-title">Add New Inventory Item</h6>
                </div>
                <div class="card-body">
                    <form method="post" id="inventoryForm">
                        <input type="hidden" name="item_id" id="item_id">
                        
                        <div class="form-group">
                            <label>Item Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Current Stock</label>
                            <input type="number" name="current_stock" id="current_stock" class="form-control" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Minimum Stock Level</label>
                            <input type="number" name="min_stock_level" id="min_stock_level" class="form-control" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Unit</label>
                            <select name="unit" id="unit" class="form-control" required>
                                <option value="items">Items</option>
                                <option value="kg">Kilograms (kg)</option>
                                <option value="l">Liters (l)</option>
                                <option value="bottles">Bottles</option>
                                <option value="boxes">Boxes</option>
                                <option value="packs">Packs</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Supplier</label>
                            <input type="text" name="supplier" id="supplier" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Cost Per Unit ($)</label>
                            <input type="number" name="cost_per_unit" id="cost_per_unit" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="create_item" id="submit-btn" class="btn btn-primary">Add Item</button>
                            <button type="button" id="cancel-btn" class="btn btn-secondary" style="display: none;">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" role="dialog" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustStockModalLabel">Adjust Stock</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="adjust_item_id">
                    
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" id="adjust_item_name" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Adjustment Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">+/-</span>
                            </div>
                            <input type="number" name="adjustment" class="form-control" required>
                        </div>
                        <small class="form-text text-muted">Use positive numbers to add stock, negative to remove.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason for Adjustment</label>
                        <select name="reason" class="form-control" required>
                            <option value="Restock">Restock/Purchase</option>
                            <option value="Used">Used in Service</option>
                            <option value="Damaged">Damaged/Expired</option>
                            <option value="Inventory Count">Inventory Count Correction</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inventoryForm = document.getElementById('inventoryForm');
    const formTitle = document.getElementById('form-title');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    
    // Edit inventory item button click
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const currentStock = this.getAttribute('data-current-stock');
            const minStockLevel = this.getAttribute('data-min-stock-level');
            const unit = this.getAttribute('data-unit');
            const supplier = this.getAttribute('data-supplier');
            const costPerUnit = this.getAttribute('data-cost-per-unit');
            
            // Fill the form
            document.getElementById('item_id').value = itemId;
            document.getElementById('name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('current_stock').value = currentStock;
            document.getElementById('min_stock_level').value = minStockLevel;
            document.getElementById('unit').value = unit;
            document.getElementById('supplier').value = supplier;
            document.getElementById('cost_per_unit').value = costPerUnit;
            
            // Change form to update mode
            formTitle.textContent = 'Edit Inventory Item';
            submitBtn.textContent = 'Update Item';
            submitBtn.name = 'update_item';
            cancelBtn.style.display = 'inline-block';
            
            // Scroll to form
            inventoryForm.scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Adjust stock button click
    document.querySelectorAll('.adjust-stock').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('adjust_item_id').value = itemId;
            document.getElementById('adjust_item_name').value = name;
        });
    });
    
    // Cancel button click
    cancelBtn.addEventListener('click', function() {
        resetForm();
    });
    
    function resetForm() {
        inventoryForm.reset();
        document.getElementById('item_id').value = '';
        formTitle.textContent = 'Add New Inventory Item';
        submitBtn.textContent = 'Add Item';
        submitBtn.name = 'create_item';
        cancelBtn.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
