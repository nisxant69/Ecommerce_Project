// Cart Functions
function updateCart(productId, quantity, absolute = false) {
    // Prevent duplicate requests
    if (window.isCartUpdating) {
        return;
    }
    
    window.isCartUpdating = true;
    
    const formData = new FormData();
    formData.append('product_id', productId);
    
    // Handle different cart actions
    if (quantity === 0) {
        formData.append('action', 'remove');
        formData.append('quantity', '0');
    } else if (absolute) {
        formData.append('action', 'update');
        formData.append('quantity', quantity);
        formData.append('absolute', '1');
    } else {
        // For increment/decrement in cart
        const quantityInput = document.querySelector(`.cart-quantity[data-product-id="${productId}"]`);
        if (quantityInput) {
            const currentQuantity = parseInt(quantityInput.value) || 0;
            const newQuantity = currentQuantity + quantity;
            
            if (newQuantity <= 0) {
                formData.append('action', 'remove');
                formData.append('quantity', '0');
            } else {
                formData.append('action', 'update');
                formData.append('quantity', newQuantity);
                formData.append('absolute', '1');
            }
        } else {
            // For adding to cart from product page
            formData.append('action', 'add');
            formData.append('quantity', Math.abs(quantity));
            formData.append('absolute', '0');
        }
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        showAlert('danger', 'Security token not found. Please refresh the page.');
        window.isCartUpdating = false;
        return;
    }
    
    formData.append('csrf_token', csrfToken.content);
    
    fetch('api/cart.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                cartCount.textContent = data.cart_count || '0';
            }
            
            // Update cart total if available
            if (data.cart_total !== undefined) {
                const cartTotal = document.querySelector('.cart-total');
                if (cartTotal) {
                    cartTotal.textContent = data.cart_total;
                }
            }
            
            // Show success message
            showAlert('success', data.message || 'Cart updated successfully');
            
            // Reload the page if we're on the cart page
            if (window.location.pathname.includes('/cart.php')) {
                window.location.reload();
            }
        } else {
            showAlert('danger', data.message || 'Error updating cart');
        }
    })
    .catch(error => {
        console.error('Cart API error:', error);
        showAlert('danger', 'Error updating cart. Please try again.');
    })
    .finally(() => {
        window.isCartUpdating = false;
    });
}

// Function to show alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '1050';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// Get CSRF token from meta tag
function getCsrfToken() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        showAlert('danger', 'Security token not found. Please refresh the page.');
        return '';
    }
    return csrfToken.content;
}

// Wishlist Functions
$(document).on('click', '.toggle-wishlist', function(e) {
    e.preventDefault();
    const button = this;
    const productId = button.dataset.productId;
    const isInWishlist = button.dataset.inWishlist === '1';
    const action = isInWishlist ? 'remove' : 'add';
    
    const csrfToken = getCsrfToken();
    if (!csrfToken) return;

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', action);
    formData.append('csrf_token', csrfToken);
    
    fetch('api/wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button state
            if (action === 'add') {
                button.classList.remove('btn-outline-danger');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="fas fa-heart"></i> Remove from Wishlist';
                button.dataset.inWishlist = '1';
            } else {
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-danger');
                button.innerHTML = '<i class="far fa-heart"></i> Add to Wishlist';
                button.dataset.inWishlist = '0';
            }
            
            // Show success message
            showAlert('success', data.message);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while updating wishlist');
    });
});

// Search Autocomplete
window.searchTimeout = null;  // Use window to avoid redeclaration
$('.search-input').on('input', function() {
    clearTimeout(window.searchTimeout);
    const query = $(this).val();
    
    if (query.length < 2) {
        $('.search-results').hide();
        return;
    }
    
    window.searchTimeout = setTimeout(function() {
        fetch(`api/products.php?search=${encodeURIComponent(query)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(response => {
            const results = response.products;
            const dropdown = $('.search-results');
            dropdown.empty();
            
            if (results && results.length > 0) {
                results.forEach(product => {
                    dropdown.append(`
                        <a class="dropdown-item" href="product.php?id=${product.id}">
                            ${product.name} - ${product.price}
                        </a>
                    `);
                });
                dropdown.show();
            } else {
                dropdown.hide();
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
    }, 300);
});

// Hide search results when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('.search-container').length) {
        $('.search-results').hide();
    }
});

// Form Validation
function validateForm(formId) {
    const form = $(`#${formId}`);
    let isValid = true;
    
    // Reset previous errors
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('.invalid-feedback').remove();
    
    // Check required fields
    form.find('[required]').each(function() {
        if (!$(this).val()) {
            $(this).addClass('is-invalid');
            $(this).after(`<div class="invalid-feedback">This field is required</div>`);
            isValid = false;
        }
    });
    
    // Check email format
    form.find('input[type="email"]').each(function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if ($(this).val() && !emailRegex.test($(this).val())) {
            $(this).addClass('is-invalid');
            $(this).after(`<div class="invalid-feedback">Please enter a valid email address</div>`);
            isValid = false;
        }
    });
    
    // Check password match
    const password = form.find('input[name="password"]');
    const confirmPassword = form.find('input[name="confirm_password"]');
    if (password.length && confirmPassword.length && password.val() !== confirmPassword.val()) {
        confirmPassword.addClass('is-invalid');
        confirmPassword.after(`<div class="invalid-feedback">Passwords do not match</div>`);
        isValid = false;
    }
    
    return isValid;
}

// Image Preview
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#imagePreview').attr('src', e.target.result).show();
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Initialize Bootstrap tooltips and popovers
$(function() {
    // Add to cart button click handler
    $(document).on('click', '.add-to-cart', function(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        const quantity = parseInt($('#quantity').val()) || 1;
        updateCart(productId, quantity);
    });

    // Quick add to cart button click handler
    $(document).on('click', '.quick-add-to-cart', function(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        updateCart(productId, 1);
    });

    // Cart quantity input change handler
    $(document).on('change', '.cart-quantity', function() {
        const productId = $(this).data('product-id');
        const quantity = parseInt($(this).val());
        if (!isNaN(quantity) && quantity >= 0) {
            updateCart(productId, quantity, true);
        }
    });

    // Existing initializations
    $('[data-bs-toggle="tooltip"]').tooltip();
    $('[data-bs-toggle="popover"]').popover();
});