<?php
$page_title = "Contact Us";
include_once 'includes/header.php';

$message = '';
$message_type = '';

// Process contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contact_submit'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $subject = sanitize_input($_POST['subject']);
    $message_content = sanitize_input($_POST['message']);
    
    // Simple validation
    if (empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $message = 'Please fill in all fields';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $message_type = 'danger';
    } else {
        // In a real application, you would send an email here
        // For this demo, we'll just show a success message
        $message = 'Thank you for your message. We will get back to you soon!';
        $message_type = 'success';
        
        // Clear form data on successful submission
        $name = $email = $subject = $message_content = '';
    }
}
?>

<!-- Contact Banner -->
<div class="bg-light py-5 mb-4">
    <div class="container">
        <h1 class="text-center">Contact Us</h1>
        <p class="text-center lead">Get in touch with our team</p>
    </div>
</div>

<!-- Contact Content -->
<div class="container my-5">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <h2>Send Us a Message</h2>
            <p class="mb-4">Have questions or feedback? Fill out the form below and we'll get back to you as soon as possible.</p>
            
            <form method="post" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Your Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? $name : ''; ?>" required>
                    <div class="invalid-feedback">Please enter your name</div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                    <div class="invalid-feedback">Please enter a valid email address</div>
                </div>
                
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($subject) ? $subject : ''; ?>" required>
                    <div class="invalid-feedback">Please enter a subject</div>
                </div>
                
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message_content) ? $message_content : ''; ?></textarea>
                    <div class="invalid-feedback">Please enter your message</div>
                </div>
                
                <button type="submit" name="contact_submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
        
        <div class="col-lg-6">
            <h2>Contact Information</h2>
            <p class="mb-4">You can also reach us using the information below:</p>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-map-marker-alt text-primary me-2"></i> Address</h5>
                    <p class="card-text">No. 45, Galle Road<br>Colombo 03<br>Sri Lanka</p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-phone text-primary me-2"></i> Phone</h5>
                    <p class="card-text">Customer Service: (+94) 71 234-5678<br>Support: (+94) 77 123-4567</p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-envelope text-primary me-2"></i> Email</h5>
                    <p class="card-text">info@drydrop.com<br>support@drydrop.com</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-clock text-primary me-2"></i> Business Hours</h5>
                    <p class="card-text">
                        Monday - Friday: 8:00 AM - 8:00 PM<br>
                        Saturday: 9:00 AM - 5:00 PM<br>
                        Sunday: Closed
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Map Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-4">Our Location</h2>
            <div class="ratio ratio-16x9">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d63316.29348798251!2d79.8380058!3d6.9270786!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae2595c8c6b8b6b%3A0x6b8b6b8b6b8b6b8b!2sColombo%2003%2C%20Sri%20Lanka!5e0!3m2!1sen!2slk!4v1718012345678!5m2!1sen!2slk"
                    width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
