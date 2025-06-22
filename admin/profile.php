<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get admin information
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_role = 'admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error_message = "Email is already in use by another account.";
    } else {
        // Update profile information
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $address, $admin_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            
            // Update the session information
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            // Refresh admin information
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
        } else {
            $error_message = "Failed to update profile: " . $conn->error;
        }
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $admin['password'])) {
        $password_error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $password_error = "New password must be at least 6 characters long.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $password_success = "Password changed successfully!";
        } else {
            $password_error = "Failed to change password: " . $conn->error;
        }
        $stmt->close();
    }
}

$page_title = "My Profile";
include 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Admin Profile</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- Profile Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($admin['address']); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Change Password Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Change Password</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($password_success)): ?>
                        <div class="alert alert-success"><?php echo $password_success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-danger"><?php echo $password_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>User ID:</strong> <?php echo $admin['id']; ?></p>
                            <p><strong>Role:</strong> Administrator</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Account Created:</strong> <?php echo date('M d, Y', strtotime($admin['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($admin['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
