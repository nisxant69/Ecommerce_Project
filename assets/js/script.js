// Cart Functions
function updateCart(productId, quantity) {
    $.ajax({
        url: '/ecomfinal/api/cart.php',
        type: 'POST',
        data: {
            action: 'update',
            product_id: productId,
            quantity: quantity,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                updateCartCount(response.cart_total);
                updateCartTotal(response.cart_price_total);
                showAlert('success', 'Cart updated successfully');
            } else {
                showAlert('danger', response.message || 'Error updating cart');
            }
        },
        error: function() {
            showAlert('danger', 'Error updating cart');
        }
    });
}

function updateCartCount(count) {
    $('.cart-count').text(count);
}

function updateCartTotal(total) {
    $('.cart-total').text('$' + total);
}

// Wishlist Functions
function toggleWishlist(productId) {
    $.ajax({
        url: '/ecomfinal/api/wishlist.php',
        type: 'POST',
        data: {
            action: $('#wishlist-' + productId + ' i').hasClass('far') ? 'add' : 'remove',
            product_id: productId,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                const icon = $(`#wishlist-${productId} i`);
                icon.toggleClass('fas far');
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message || 'Error updating wishlist');
            }
        },
        error: function() {
            showAlert('danger', 'Error updating wishlist');
        }
    });
}

// Search Autocomplete
let searchTimeout;
$('.search-input').on('input', function() {
    clearTimeout(searchTimeout);
    const query = $(this).val();
    
    if (query.length < 2) return;
    
    searchTimeout = setTimeout(function() {
        $.ajax({
            url: '/ecomfinal/api/products.php',
            type: 'GET',
            data: { search: query },
            success: function(response) {
                const results = response.products;
                const dropdown = $('.search-results');
                dropdown.empty();
                
                results.forEach(product => {
                    dropdown.append(`
                        <a class="dropdown-item" href="/ecomfinal/product.php?id=${product.id}">
                            ${product.name} - $${product.price}
                        </a>
                    `);
                });
                
                dropdown.show();
            }
        });
    }, 300);
});

// Alert Functions
function showAlert(type, message) {
    const alert = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.alert-container').html(alert);
    
    // Auto hide after 3 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 3000);
}

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
    $('[data-bs-toggle="tooltip"]').tooltip();
    $('[data-bs-toggle="popover"]').popover();
});
