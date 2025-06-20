<?php
$page_title = "Forgot Password";
include_once 'includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: customer/index.php");
    }
    exit;
}

$error = '';
$success = '';

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    
    // Validate input
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists
        $sql = "SELECT id, firstname FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Generate password reset token
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', time() + 3600); // Token valid for 1 hour
            
            // Store token in database (would need a password_reset_tokens table in real application)
            // For this demo, we'll just show a success message
            
            // In a real application, you would send an email with the reset link
            // $reset_link = "https://yourdomain.com/reset_password.php?token=$token";
            
            $success = "If your email exists in our system, you will receive password reset instructions shortly.";
            $email = '';
        } else {
            // Don't reveal if email exists or not for security
            $success = "If your email exists in our system, you will receive password reset instructions shortly.";
            $email = '';
        }
    }
}
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Forgot Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-primary">Return to Login</a>
                        </div>
                    <?php else: ?>
                        <p>Enter your email address below and we'll send you instructions to reset your password.</p>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <a href="login.php">Back to Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
