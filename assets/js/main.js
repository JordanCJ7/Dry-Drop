// Main JavaScript file for the Dry Drop

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });

    // Service quantity counters
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    if (quantityInputs) {
        quantityInputs.forEach(input => {
            const minusBtn = input.previousElementSibling;
            const plusBtn = input.nextElementSibling;
            
            if (minusBtn && plusBtn) {
                minusBtn.addEventListener('click', () => {
                    if (parseInt(input.value) > 1) {
                        input.value = parseInt(input.value) - 1;
                        updateTotal();
                    }
                });
                
                plusBtn.addEventListener('click', () => {
                    input.value = parseInt(input.value) + 1;
                    updateTotal();
                });
                
                input.addEventListener('change', () => {
                    if (parseInt(input.value) < 1) {
                        input.value = 1;
                    }
                    updateTotal();                });
            }
        });
    }    // Star rating system
    const ratingStars = document.querySelectorAll('.rating-star');
    const ratingInput = document.getElementById('rating');
    
    if (ratingStars.length > 0 && ratingInput) {
        // Initialize stars
        const setRating = (rating) => {
            ratingInput.value = rating;
            
            ratingStars.forEach(star => {
                const starValue = parseInt(star.getAttribute('data-value'));
                
                if (starValue <= rating) {
                    star.classList.remove('far');
                    star.classList.add('fas');
                } else {
                    star.classList.remove('fas');
                    star.classList.add('far');
                }
            });
        };
        
        // Add event listeners to stars
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-value'));
                setRating(rating);
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-value'));
                
                ratingStars.forEach(s => {
                    const starValue = parseInt(s.getAttribute('data-value'));
                    
                    if (starValue <= rating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = parseInt(ratingInput.value) || 0;
                
                ratingStars.forEach(s => {
                    const starValue = parseInt(s.getAttribute('data-value'));
                    
                    if (starValue <= currentRating) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            });
        });
    }

    // Update order total amount
    function updateTotal() {
        const orderForm = document.getElementById('orderForm');
        if (orderForm) {
            const items = orderForm.querySelectorAll('.service-item');
            let total = 0;
            
            items.forEach(item => {
                const price = parseFloat(item.querySelector('.service-price').dataset.price);
                const quantity = parseInt(item.querySelector('.quantity-input').value);
                const itemTotal = price * quantity;
                
                item.querySelector('.item-total').textContent = '$' + itemTotal.toFixed(2);
                total += itemTotal;
            });
            
            document.getElementById('orderTotal').textContent = '$' + total.toFixed(2);
            document.getElementById('totalAmount').value = total.toFixed(2);
        }
    }

    // Date and time picker initialization
    const dateInputs = document.querySelectorAll('input[type="date"]');
    const timeInputs = document.querySelectorAll('input[type="time"]');
    
    if (dateInputs) {
        dateInputs.forEach(input => {
            // Set min date to today
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const yyyy = today.getFullYear();
            input.min = yyyy + '-' + mm + '-' + dd;
        });
    }

    // Payment method toggle
    const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
    const onlinePaymentDiv = document.getElementById('onlinePaymentDetails');
      if (paymentMethodRadios && onlinePaymentDiv) {
        paymentMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'online') {
                    onlinePaymentDiv.classList.remove('d-none');
                } else {
                    onlinePaymentDiv.classList.add('d-none');
                }
            });
        });
    }
});
