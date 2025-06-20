<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle user creation
if (isset($_POST['create_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_role = $_POST['user_role'];
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $error_message = "Email already exists. Please use a different email.";
    } else {
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, address, password, user_role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $phone, $address, $password, $user_role);
        
        if ($stmt->execute()) {
            $success_message = "User created successfully!";
        } else {
            $error_message = "Failed to create user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle user update
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $user_role = $_POST['user_role'];
    
    // Check if email already exists for other users
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $error_message = "Email already exists. Please use a different email.";
    } else {
        $stmt->close();
        
        // If password is provided, update it too
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, password = ?, user_role = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $name, $email, $phone, $address, $password, $user_role, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, user_role = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $email, $phone, $address, $user_role, $user_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "User updated successfully!";
        } else {
            $error_message = "Failed to update user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle user deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Check if user has orders
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($order_count);
    $stmt->fetch();
    $stmt->close();
    
    if ($order_count > 0) {
        $error_message = "Cannot delete this user because they have orders in the system.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Failed to delete user: " . $conn->error;
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
    $search_condition = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?";
}

// Role filter
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
if (!empty($role_filter)) {
    $search_condition = empty($search_condition) ? "WHERE user_role = ?" : $search_condition . " AND user_role = ?";
}

// Count total users for pagination
$count_sql = "SELECT COUNT(*) as total FROM users ";
if (!empty($search_condition)) {
    $count_sql .= $search_condition;
    $count_stmt = $conn->prepare($count_sql);
    
    if (!empty($search) && !empty($role_filter)) {
        $count_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $role_filter);
    } elseif (!empty($search)) {
        $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    } else {
        $count_stmt->bind_param("s", $role_filter);
    }
} else {
    $count_stmt = $conn->prepare($count_sql);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Get users with pagination
$sql = "SELECT * FROM users ";
if (!empty($search_condition)) {
    $sql .= $search_condition;
}
$sql .= " ORDER BY name ASC LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!empty($search) && !empty($role_filter)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $role_filter, $offset, $limit);
} elseif (!empty($search)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $offset, $limit);
} elseif (!empty($role_filter)) {
    $stmt->bind_param("sii", $role_filter, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Manage Users";
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Manage Users</h1>
    
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
                    <h6 class="m-0 font-weight-bold text-primary">User List</h6>
                    <div class="d-flex">
                        <form class="form-inline mr-2" method="GET">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                <select name="role" class="form-control ml-2">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
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
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No users found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['user_role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst($user['user_role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-primary edit-user" 
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                                                            data-address="<?php echo htmlspecialchars($user['address']); ?>"
                                                            data-role="<?php echo $user['user_role']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="users.php?delete=1&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this user?');">
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">Next</a>
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
                    <h6 class="m-0 font-weight-bold text-primary" id="form-title">Add New User</h6>
                </div>
                <div class="card-body">
                    <form method="post" id="userForm">
                        <input type="hidden" name="user_id" id="user_id">
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" id="phone" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" id="address" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" id="password" class="form-control">
                            <small class="form-text text-muted password-hint">At least 8 characters required.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>User Role</label>
                            <select name="user_role" id="user_role" class="form-control" required>
                                <option value="customer">Customer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="create_user" id="submit-btn" class="btn btn-primary">Add User</button>
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
    const userForm = document.getElementById('userForm');
    const formTitle = document.getElementById('form-title');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const passwordField = document.getElementById('password');
    const passwordHint = document.querySelector('.password-hint');
    
    // Edit user button click
    document.querySelectorAll('.edit-user').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const address = this.getAttribute('data-address');
            const role = this.getAttribute('data-role');
            
            // Fill the form
            document.getElementById('user_id').value = userId;
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('phone').value = phone;
            document.getElementById('address').value = address;
            document.getElementById('user_role').value = role;
            
            // Clear password field and update hint for edit mode
            passwordField.value = '';
            passwordField.required = false;
            passwordHint.textContent = 'Leave blank to keep current password.';
            
            // Change form to update mode
            formTitle.textContent = 'Edit User';
            submitBtn.textContent = 'Update User';
            submitBtn.name = 'update_user';
            cancelBtn.style.display = 'inline-block';
            
            // Scroll to form
            userForm.scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Cancel button click
    cancelBtn.addEventListener('click', function() {
        resetForm();
    });
    
    function resetForm() {
        userForm.reset();
        document.getElementById('user_id').value = '';
        passwordField.required = true;
        passwordHint.textContent = 'At least 8 characters required.';
        formTitle.textContent = 'Add New User';
        submitBtn.textContent = 'Add User';
        submitBtn.name = 'create_user';
        cancelBtn.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
