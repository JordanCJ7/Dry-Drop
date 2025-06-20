<?php
$page_title = "Our Services";
include_once 'includes/header.php';

// Fetch all active services
$sql = "SELECT * FROM services WHERE active = 1";
$result = $conn->query($sql);
$services = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}
?>

<!-- Services Banner -->
<div class="bg-light py-5 mb-4">
    <div class="container">
        <h1 class="text-center">Our Services</h1>
        <p class="text-center lead">Discover our range of professional laundry services</p>
    </div>
</div>

<!-- Services List -->
<div class="container">
    <div class="row">
        <?php if (count($services) > 0): ?>
            <?php foreach ($services as $service): ?>
                <div class="col-md-4 mb-4">
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
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <h3>No services available at the moment.</h3>
                <p>Please check back later.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Special Packages -->
<div class="container my-5">
    <h2 class="text-center mb-4">Special Packages</h2>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card service-card bg-light">
                <div class="card-body text-center">
                    <h4 class="card-title">Weekly Package</h4>
                    <div class="display-4 my-3 text-primary">$89.99</div>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item bg-light">Up to 20 garments</li>
                        <li class="list-group-item bg-light">Free pickup and delivery</li>
                        <li class="list-group-item bg-light">Includes washing and ironing</li>
                        <li class="list-group-item bg-light">1 regular delivery per week</li>
                    </ul>
                    <a href="order.php?package=weekly" class="btn btn-primary">Select Package</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card service-card bg-primary text-white">
                <div class="card-body text-center">
                    <h4 class="card-title">Monthly Package</h4>
                    <div class="display-4 my-3">$299.99</div>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item bg-primary text-white border-light">Up to 80 garments</li>
                        <li class="list-group-item bg-primary text-white border-light">Free priority pickup and delivery</li>
                        <li class="list-group-item bg-primary text-white border-light">Includes all services</li>
                        <li class="list-group-item bg-primary text-white border-light">4 deliveries per month</li>
                    </ul>
                    <a href="order.php?package=monthly" class="btn btn-light text-primary">Select Package</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card service-card bg-light">
                <div class="card-body text-center">
                    <h4 class="card-title">Family Package</h4>
                    <div class="display-4 my-3 text-primary">$199.99</div>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item bg-light">Up to 50 garments</li>
                        <li class="list-group-item bg-light">Free pickup and delivery</li>
                        <li class="list-group-item bg-light">Includes washing, dry cleaning and ironing</li>
                        <li class="list-group-item bg-light">2 deliveries per month</li>
                    </ul>
                    <a href="order.php?package=family" class="btn btn-primary">Select Package</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FAQ Section -->
<div class="container my-5">
    <h2 class="text-center mb-4">Frequently Asked Questions</h2>
    <div class="accordion" id="servicesFAQ">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    How long does it take to process my laundry?
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#servicesFAQ">
                <div class="accordion-body">
                    Standard processing time is 48 hours. If you need your laundry faster, we offer express service with 24-hour turnaround for an additional charge.
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                    How do I schedule a pickup?
                </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#servicesFAQ">
                <div class="accordion-body">
                    You can schedule a pickup through our website by placing an order and selecting your preferred date and time, or by calling our customer service number.
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    What payment methods do you accept?
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#servicesFAQ">
                <div class="accordion-body">
                    We accept cash on delivery, credit/debit cards, and online payments through PayPal and other payment processors. You can choose your preferred payment method during checkout.
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFour">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                    How do you handle delicate items?
                </button>
            </h2>
            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#servicesFAQ">
                <div class="accordion-body">
                    Delicate items like silk, wool, and other specialty fabrics are processed using our gentle dry cleaning service. We follow the care instructions on each garment to ensure they are handled properly.
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFive">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                    What areas do you service?
                </button>
            </h2>
            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#servicesFAQ">
                <div class="accordion-body">
                    We currently service all areas within a 20-mile radius of our main facility. If you're unsure whether we cover your location, please contact our customer service.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
