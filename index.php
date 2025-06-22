<?php
$page_title = "Home";
include_once 'includes/header.php';

// Fetch services for the homepage
$sql = "SELECT * FROM services WHERE active = 1 LIMIT 3";
$result = $conn->query($sql);
$services = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Welcome to Dry Drop</h1>
        <p>Your one-stop solution for all laundry needs. We offer professional washing, dry cleaning, and ironing services at affordable prices.</p>
        <a href="services.php" class="btn btn-primary btn-lg">View Our Services</a>
    </div>
</section>

<!-- Services Preview Section -->
<section class="container">
    <h2 class="text-center mb-4">Our Services</h2>
    <div class="row">
        <?php foreach ($services as $service): ?>
            <div class="col-md-4">
                <div class="card service-card">
                    <img src="assets/images/<?php echo $service['image']; ?>" class="card-img-top" alt="<?php echo $service['name']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $service['name']; ?></h5>
                        <p class="card-text"><?php echo $service['description']; ?></p>
                        <p class="card-text text-primary fw-bold">$<?php echo number_format($service['price'], 2); ?></p>
                        <a href="order.php?service_id=<?php echo $service['id']; ?>" class="btn btn-primary">Order Now</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
        <a href="services.php" class="btn btn-outline-primary">View All Services</a>
    </div>
</section>

<!-- How It Works Section -->
<section class="container my-5">
    <h2 class="text-center mb-4">How It Works</h2>
    <div class="row text-center">
        <div class="col-md-3">
            <div class="how-it-works-item">
                <div class="how-it-works-icon">
                    <i class="fas fa-user-plus fa-3x text-primary"></i>
                </div>
                <h4>Create Account</h4>
                <p>Register and set up your account with us</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="how-it-works-item">
                <div class="how-it-works-icon">
                    <i class="fas fa-shopping-cart fa-3x text-primary"></i>
                </div>
                <h4>Place Order</h4>
                <p>Choose services and schedule pickup</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="how-it-works-item">
                <div class="how-it-works-icon">
                    <i class="fas fa-truck fa-3x text-primary"></i>
                </div>
                <h4>Pickup & Delivery</h4>
                <p>Collect and return you items with care and efficiency</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="how-it-works-item">
                <div class="how-it-works-icon">
                    <i class="fas fa-thumbs-up fa-3x text-primary"></i>
                </div>
                <h4>Enjoy Clean Clothes</h4>
                <p>Receive fresh, clean clothes at your doorstep</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="bg-light py-5 mb-5">
    <div class="container">        <h2 class="text-center mb-4">What Our Customers Say</h2>
        <div class="row">
            <?php
            // Fetch recent feedbacks
            $sql = "SELECT f.*, u.name FROM feedbacks f 
                    JOIN users u ON f.user_id = u.id 
                    ORDER BY f.created_at DESC LIMIT 3";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                while ($feedback = $result->fetch_assoc()) {
                    ?>                    <div class="col-md-4 mb-4">
                        <div class="card testimonial-card h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <?php
                                    // Display star rating
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $feedback['rating']) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                </div>                                
                                <p class="card-text">"<?php echo htmlspecialchars($feedback['comment']); ?>"</p>
                                <p class="card-text text-end fw-bold">- <?php echo htmlspecialchars($feedback['name']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // Display dummy testimonials if no real ones exist
                $dummyTestimonials = [
                    [
                        'name' => 'Ashan Perera',
                        'comment' => 'Excellent service! My clothes have never been this clean. Will use again.',
                        'rating' => 5
                    ],
                    [
                        'name' => 'Nadeeka Fernando',
                        'comment' => 'Very fast pickup and delivery. The quality of cleaning is top-notch.',
                        'rating' => 4
                    ],
                    [
                        'name' => 'Chaminda Jayasinghe',
                        'comment' => 'Very professional service. The online ordering system is super easy!',
                        'rating' => 5
                    ]
                ];
                
                foreach ($dummyTestimonials as $testimonial) {
                    ?>                    <div class="col-md-4 mb-4">
                        <div class="card testimonial-card h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <?php
                                    // Display star rating
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $testimonial['rating']) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <p class="card-text">"<?php echo $testimonial['comment']; ?>"</p>
                                <p class="card-text text-end fw-bold">- <?php echo $testimonial['name']; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>
