<?php
$page_title = "My Profile";
include_once 'includes/header.php';

$success = '';
$error = '';

// Process profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $firstname = sanitize_input($_POST['firstname']);
    $lastname = sanitize_input($_POST['lastname']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    
    // Validate input
    if (empty($firstname) || empty($lastname) || empty($phone)) {
        $error = "Please fill in all required fields";
    } else {
        // Update user profile
        $update_sql = "UPDATE users SET firstname = ?, lastname = ?, phone = ?, address = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssi", $firstname, $lastname, $phone, $address, $user_id);
        
        if ($update_stmt->execute()) {
            $success = "Profile updated successfully";
            
            // Update session variable
            $_SESSION['user_name'] = $firstname . ' ' . $lastname;
            
            // Refresh user data
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Verify current password
        $password_sql = "SELECT password FROM users WHERE id = ?";
        $password_stmt = $conn->prepare($password_sql);
        $password_stmt->bind_param("i", $user_id);
        $password_stmt->execute();
        $password_result = $password_stmt->get_result();
        $password_row = $password_result->fetch_assoc();
        
        if (password_verify($current_password, $password_row['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password changed successfully";
            } else {
                $error = "Failed to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
}
?>

<h1 class="mb-4">My Profile</h1>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar">
        <i class="fas fa-user"></i>
    </div>
    <div class="profile-info">
        <h2 class="profile-name"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></h2>
        <p class="profile-email"><?php echo $user['email']; ?></p>
        <p class="mb-0">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <!-- Personal Information -->
        <div class="profile-section">
            <h4 class="profile-section-title">Personal Information</h4>
            
            <form method="post" action="" class="needs-validation" novalidate>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="firstname" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo $user['firstname']; ?>" required>
                        <div class="invalid-feedback">First name is required</div>
                    </div>
                    <div class="col-md-6">
                        <label for="lastname" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo $user['lastname']; ?>" required>
                        <div class="invalid-feedback">Last name is required</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" value="<?php echo $user['email']; ?>" readonly disabled>
                    <div class="form-text">Email address cannot be changed</div>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>" required>
                    <div class="invalid-feedback">Phone number is required</div>
                </div>
                
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo $user['address']; ?></textarea>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-6">
        <!-- Change Password -->
        <div class="profile-section">
            <h4 class="profile-section-title">Change Password</h4>
            
            <form method="post" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password *</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <div class="invalid-feedback">Current password is required</div>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password *</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <div class="form-text">Password must be at least 6 characters long</div>
                    <div class="invalid-feedback">New password is required</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback">Please confirm your new password</div>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </form>
        </div>
        
        <!-- Account Statistics -->
        <div class="profile-section mt-4">
            <h4 class="profile-section-title">Account Statistics</h4>
            
            <?php
            // Get user statistics
            $stats_sql = "SELECT 
                            COUNT(*) as total_orders,
                            SUM(total_amount) as total_spent,
                            MAX(created_at) as last_order_date
                        FROM orders 
                        WHERE user_id = ?";
            $stats_stmt = $conn->prepare($stats_sql);
            $stats_stmt->bind_param("i", $user_id);
            $stats_stmt->execute();
            $stats_result = $stats_stmt->get_result();
            $stats = $stats_result->fetch_assoc();
            ?>
            
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Total Orders:</span>
                    <span class="badge bg-primary rounded-pill"><?php echo $stats['total_orders']; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Total Spent:</span>
                    <span class="fw-bold">$<?php echo number_format($stats['total_spent'] ?: 0, 2); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Last Order:</span>
                    <span><?php echo $stats['last_order_date'] ? date('M d, Y', strtotime($stats['last_order_date'])) : 'N/A'; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Account Status:</span>
                    <span class="badge bg-success">Active</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
