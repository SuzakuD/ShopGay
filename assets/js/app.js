// Fishing Gear Store - Main JavaScript Application

// Global variables
let currentUser = null;
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let products = [];
let categories = [];
let brands = [];
let currentFilters = {};
let currentSort = 'name';
let currentProductId = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

async function initializeApp() {
    try {
        // Load initial data
        await loadCategories();
        await loadBrands();
        await loadProducts();
        
        // Setup event listeners
        setupEventListeners();
        
        // Update UI
        updateCartCount();
        updateUserInterface();
        
        // Check for saved user session
        checkUserSession();
        
    } catch (error) {
        console.error('Error initializing app:', error);
        showAlert('Error loading application. Please refresh the page.', 'danger');
    }
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Filter inputs
    const filterInputs = ['minPrice', 'maxPrice', 'inStockOnly'];
    filterInputs.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', applyFilters);
        }
    });
    
    // Form submissions
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', handleContact);
    }
    
    const addReviewForm = document.getElementById('addReviewForm');
    if (addReviewForm) {
        addReviewForm.addEventListener('submit', handleAddReview);
    }
    
    // Rating input
    const ratingInputs = document.querySelectorAll('.rating-input i');
    ratingInputs.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            setRating(rating);
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            highlightRating(rating);
        });
    });
    
    const ratingContainer = document.querySelector('.rating-input');
    if (ratingContainer) {
        ratingContainer.addEventListener('mouseleave', clearRatingHighlight);
    }
}

// API Functions
async function apiCall(endpoint, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(`api/${endpoint}`, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'API call failed');
        }
        
        return result;
    } catch (error) {
        console.error('API call error:', error);
        throw error;
    }
}

// Data Loading Functions
async function loadProducts() {
    try {
        showLoading(true);
        const result = await apiCall('products.php');
        products = result.data || [];
        displayProducts(products);
    } catch (error) {
        console.error('Error loading products:', error);
        showAlert('Error loading products', 'danger');
    } finally {
        showLoading(false);
    }
}

async function loadCategories() {
    try {
        const result = await apiCall('categories.php');
        categories = result.data || [];
        displayCategories();
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

async function loadBrands() {
    try {
        const result = await apiCall('brands.php');
        brands = result.data || [];
        displayBrands();
    } catch (error) {
        console.error('Error loading brands:', error);
    }
}

// Display Functions
function displayProducts(productsToShow) {
    const productsGrid = document.getElementById('productsGrid');
    if (!productsGrid) return;
    
    if (productsToShow.length === 0) {
        document.getElementById('noProductsMessage').style.display = 'block';
        productsGrid.innerHTML = '';
        return;
    }
    
    document.getElementById('noProductsMessage').style.display = 'none';
    
    productsGrid.innerHTML = productsToShow.map(product => `
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card product-card h-100">
                <div class="position-relative">
                    <img src="${product.image || 'assets/images/placeholder.jpg'}" 
                         class="card-img-top product-image" 
                         alt="${product.name}"
                         onclick="showProductDetail(${product.id})">
                    <div class="position-absolute top-0 end-0 m-2">
                        <span class="badge ${product.stock > 0 ? 'bg-success' : 'bg-danger'}">
                            ${product.stock > 0 ? 'In Stock' : 'Out of Stock'}
                        </span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title product-title">${product.name}</h5>
                    <p class="card-text text-muted small flex-grow-1">${product.description || ''}</p>
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="product-price">$${parseFloat(product.price).toFixed(2)}</span>
                            <div class="product-rating">
                                ${generateStarRating(product.rating || 0)}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="product-stock">Stock: ${product.stock}</small>
                            <button class="btn btn-primary btn-sm" 
                                    onclick="addToCart(${product.id})"
                                    ${product.stock === 0 ? 'disabled' : ''}>
                                <i class="fas fa-cart-plus me-1"></i>Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function displayCategories() {
    const categoryList = document.getElementById('categoryList');
    if (!categoryList) return;
    
    categoryList.innerHTML = categories.map(category => `
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="${category.id}" id="category_${category.id}">
            <label class="form-check-label" for="category_${category.id}">
                ${category.name} (${category.product_count || 0})
            </label>
        </div>
    `).join('');
}

function displayBrands() {
    const brandList = document.getElementById('brandList');
    if (!brandList) return;
    
    brandList.innerHTML = brands.map(brand => `
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="${brand.id}" id="brand_${brand.id}">
            <label class="form-check-label" for="brand_${brand.id}">
                ${brand.name} (${brand.product_count || 0})
            </label>
        </div>
    `).join('');
}

// Search and Filter Functions
function handleSearch(event) {
    const query = event.target.value.toLowerCase();
    const suggestions = document.getElementById('searchSuggestions');
    
    if (query.length < 2) {
        suggestions.style.display = 'none';
        return;
    }
    
    const filteredProducts = products.filter(product => 
        product.name.toLowerCase().includes(query) ||
        product.description.toLowerCase().includes(query) ||
        product.brand_name.toLowerCase().includes(query)
    );
    
    if (filteredProducts.length > 0) {
        suggestions.innerHTML = filteredProducts.slice(0, 5).map(product => `
            <div class="list-group-item" onclick="selectSearchSuggestion('${product.name}')">
                <div class="d-flex align-items-center">
                    <img src="${product.image || 'assets/images/placeholder.jpg'}" 
                         class="me-3" style="width: 40px; height: 40px; object-fit: cover;">
                    <div>
                        <div class="fw-bold">${product.name}</div>
                        <small class="text-muted">$${parseFloat(product.price).toFixed(2)}</small>
                    </div>
                </div>
            </div>
        `).join('');
        suggestions.style.display = 'block';
    } else {
        suggestions.style.display = 'none';
    }
}

function selectSearchSuggestion(productName) {
    document.getElementById('searchInput').value = productName;
    document.getElementById('searchSuggestions').style.display = 'none';
    applyFilters();
}

function applyFilters() {
    const searchQuery = document.getElementById('searchInput').value.toLowerCase();
    const minPrice = parseFloat(document.getElementById('minPrice').value) || 0;
    const maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
    const inStockOnly = document.getElementById('inStockOnly').checked;
    
    // Get selected categories
    const selectedCategories = Array.from(document.querySelectorAll('#categoryList input:checked'))
        .map(input => parseInt(input.value));
    
    // Get selected brands
    const selectedBrands = Array.from(document.querySelectorAll('#brandList input:checked'))
        .map(input => parseInt(input.value));
    
    let filteredProducts = products.filter(product => {
        // Search filter
        if (searchQuery && !product.name.toLowerCase().includes(searchQuery) && 
            !product.description.toLowerCase().includes(searchQuery) &&
            !product.brand_name.toLowerCase().includes(searchQuery)) {
            return false;
        }
        
        // Price filter
        const price = parseFloat(product.price);
        if (price < minPrice || price > maxPrice) {
            return false;
        }
        
        // Category filter
        if (selectedCategories.length > 0 && !selectedCategories.includes(product.category_id)) {
            return false;
        }
        
        // Brand filter
        if (selectedBrands.length > 0 && !selectedBrands.includes(product.brand_id)) {
            return false;
        }
        
        // Stock filter
        if (inStockOnly && product.stock === 0) {
            return false;
        }
        
        return true;
    });
    
    // Apply sorting
    filteredProducts = sortProductsArray(filteredProducts, currentSort);
    
    displayProducts(filteredProducts);
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('minPrice').value = '';
    document.getElementById('maxPrice').value = '';
    document.getElementById('inStockOnly').checked = false;
    
    // Clear category checkboxes
    document.querySelectorAll('#categoryList input').forEach(input => input.checked = false);
    
    // Clear brand checkboxes
    document.querySelectorAll('#brandList input').forEach(input => input.checked = false);
    
    // Reset sort
    document.getElementById('sortSelect').value = 'name';
    currentSort = 'name';
    
    displayProducts(products);
}

function sortProducts() {
    const sortSelect = document.getElementById('sortSelect');
    currentSort = sortSelect.value;
    applyFilters();
}

function sortProductsArray(productsArray, sortBy) {
    return productsArray.sort((a, b) => {
        switch (sortBy) {
            case 'name':
                return a.name.localeCompare(b.name);
            case 'price_low':
                return parseFloat(a.price) - parseFloat(b.price);
            case 'price_high':
                return parseFloat(b.price) - parseFloat(a.price);
            case 'newest':
                return new Date(b.created_at) - new Date(a.created_at);
            case 'rating':
                return (b.rating || 0) - (a.rating || 0);
            default:
                return 0;
        }
    });
}

// Cart Functions
function addToCart(productId, quantity = 1) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    
    if (product.stock === 0) {
        showAlert('This product is out of stock', 'warning');
        return;
    }
    
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        if (existingItem.quantity + quantity > product.stock) {
            showAlert('Not enough stock available', 'warning');
            return;
        }
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image: product.image,
            quantity: quantity,
            stock: product.stock
        });
    }
    
    saveCart();
    updateCartCount();
    showAlert(`${product.name} added to cart`, 'success');
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    saveCart();
    updateCartCount();
    if (document.getElementById('cartModal').classList.contains('show')) {
        displayCart();
    }
}

function updateCartQuantity(productId, quantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        if (quantity <= 0) {
            removeFromCart(productId);
        } else if (quantity <= item.stock) {
            item.quantity = quantity;
            saveCart();
            updateCartCount();
            if (document.getElementById('cartModal').classList.contains('show')) {
                displayCart();
            }
        } else {
            showAlert('Not enough stock available', 'warning');
        }
    }
}

function clearCart() {
    cart = [];
    saveCart();
    updateCartCount();
    displayCart();
    showAlert('Cart cleared', 'info');
}

function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
    }
}

function displayCart() {
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    
    if (!cartItems || !cartTotal) return;
    
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Your cart is empty</h5>
                <p class="text-muted">Add some products to get started!</p>
            </div>
        `;
        cartTotal.textContent = '0.00';
        return;
    }
    
    cartItems.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div class="row align-items-center">
                <div class="col-3">
                    <img src="${item.image || 'assets/images/placeholder.jpg'}" 
                         class="cart-item-image" alt="${item.name}">
                </div>
                <div class="col-5">
                    <h6 class="mb-1">${item.name}</h6>
                    <small class="text-muted">$${parseFloat(item.price).toFixed(2)} each</small>
                </div>
                <div class="col-2">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary" type="button" 
                                onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})">-</button>
                        <input type="number" class="form-control text-center" 
                               value="${item.quantity}" min="1" max="${item.stock}"
                               onchange="updateCartQuantity(${item.id}, parseInt(this.value))">
                        <button class="btn btn-outline-secondary" type="button" 
                                onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})">+</button>
                    </div>
                </div>
                <div class="col-2 text-end">
                    <div class="fw-bold">$${(parseFloat(item.price) * item.quantity).toFixed(2)}</div>
                    <button class="btn btn-sm btn-outline-danger" 
                            onclick="removeFromCart(${item.id})" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    const total = cart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
    cartTotal.textContent = total.toFixed(2);
}

// User Authentication Functions
async function handleLogin(event) {
    event.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    try {
        const result = await apiCall('auth.php', 'POST', {
            action: 'login',
            email: email,
            password: password
        });
        
        currentUser = result.user;
        localStorage.setItem('user', JSON.stringify(currentUser));
        
        updateUserInterface();
        showAlert('Login successful!', 'success');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
        modal.hide();
        
        // Clear form
        document.getElementById('loginForm').reset();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function handleRegister(event) {
    event.preventDefault();
    
    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('registerConfirmPassword').value;
    
    if (password !== confirmPassword) {
        showAlert('Passwords do not match', 'danger');
        return;
    }
    
    try {
        const result = await apiCall('auth.php', 'POST', {
            action: 'register',
            name: name,
            email: email,
            password: password
        });
        
        showAlert('Registration successful! Please login.', 'success');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
        modal.hide();
        
        // Clear form
        document.getElementById('registerForm').reset();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function logout() {
    currentUser = null;
    localStorage.removeItem('user');
    updateUserInterface();
    showAlert('Logged out successfully', 'info');
}

function checkUserSession() {
    const savedUser = localStorage.getItem('user');
    if (savedUser) {
        try {
            currentUser = JSON.parse(savedUser);
            updateUserInterface();
        } catch (error) {
            localStorage.removeItem('user');
        }
    }
}

function updateUserInterface() {
    const loginNav = document.getElementById('login-nav');
    const registerNav = document.getElementById('register-nav');
    const userNav = document.getElementById('user-nav');
    const userName = document.getElementById('user-name');
    const adminLink = document.getElementById('admin-link');
    const adminPanel = document.getElementById('adminPanel');
    
    if (currentUser) {
        loginNav.style.display = 'none';
        registerNav.style.display = 'none';
        userNav.style.display = 'block';
        userName.textContent = currentUser.name;
        
        if (currentUser.role === 'admin') {
            adminLink.style.display = 'block';
            if (adminPanel) {
                adminPanel.style.display = 'block';
            }
        } else {
            adminLink.style.display = 'none';
            if (adminPanel) {
                adminPanel.style.display = 'none';
            }
        }
    } else {
        loginNav.style.display = 'block';
        registerNav.style.display = 'block';
        userNav.style.display = 'none';
        if (adminPanel) {
            adminPanel.style.display = 'none';
        }
    }
}

// Product Detail Functions
async function showProductDetail(productId) {
    currentProductId = productId;
    
    try {
        const result = await apiCall(`products.php?id=${productId}`);
        const product = result.data;
        
        if (!product) {
            showAlert('Product not found', 'danger');
            return;
        }
        
        // Update modal content
        document.getElementById('productDetailTitle').textContent = product.name;
        document.getElementById('productDetailName').textContent = product.name;
        document.getElementById('productDetailPrice').textContent = `$${parseFloat(product.price).toFixed(2)}`;
        document.getElementById('productDetailStock').textContent = `Stock: ${product.stock}`;
        document.getElementById('productDetailDescription').textContent = product.description || 'No description available';
        
        // Update rating
        const ratingContainer = document.getElementById('productDetailRating');
        ratingContainer.innerHTML = generateStarRating(product.rating || 0);
        
        // Update review count
        document.getElementById('productDetailReviewCount').textContent = 
            `${product.review_count || 0} review${(product.review_count || 0) !== 1 ? 's' : ''}`;
        
        // Update images
        const imagesContainer = document.getElementById('productImages');
        const images = product.images ? product.images.split(',') : [product.image];
        imagesContainer.innerHTML = images.map((image, index) => `
            <div class="carousel-item ${index === 0 ? 'active' : ''}">
                <img src="${image || 'assets/images/placeholder.jpg'}" 
                     class="d-block w-100" alt="${product.name}" 
                     style="height: 400px; object-fit: cover;">
            </div>
        `).join('');
        
        // Load reviews
        await loadProductReviews(productId);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error loading product details:', error);
        showAlert('Error loading product details', 'danger');
    }
}

async function loadProductReviews(productId) {
    try {
        const result = await apiCall(`reviews.php?product_id=${productId}`);
        const reviews = result.data || [];
        
        const reviewsContainer = document.getElementById('productReviews');
        if (reviews.length === 0) {
            reviewsContainer.innerHTML = '<p class="text-muted">No reviews yet. Be the first to review!</p>';
            return;
        }
        
        reviewsContainer.innerHTML = reviews.map(review => `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">${review.user_name}</h6>
                            <div class="rating">${generateStarRating(review.rating)}</div>
                        </div>
                        <small class="text-muted">${formatDate(review.created_at)}</small>
                    </div>
                    <h6 class="card-title">${review.title}</h6>
                    <p class="card-text">${review.review}</p>
                    ${review.images ? `
                        <div class="review-images">
                            ${review.images.split(',').map(image => `
                                <img src="${image}" class="img-thumbnail me-2" style="width: 80px; height: 80px; object-fit: cover;">
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error loading reviews:', error);
    }
}

// Review Functions
function setRating(rating) {
    const stars = document.querySelectorAll('.rating-input i');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

function highlightRating(rating) {
    const stars = document.querySelectorAll('.rating-input i');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.style.color = '#ffc107';
        } else {
            star.style.color = '#e9ecef';
        }
    });
}

function clearRatingHighlight() {
    const stars = document.querySelectorAll('.rating-input i');
    stars.forEach(star => {
        star.style.color = '';
    });
}

async function handleAddReview(event) {
    event.preventDefault();
    
    if (!currentUser) {
        showAlert('Please login to write a review', 'warning');
        return;
    }
    
    const rating = document.querySelector('.rating-input i.active') ? 
        document.querySelectorAll('.rating-input i.active').length : 0;
    const title = document.getElementById('reviewTitle').value;
    const review = document.getElementById('reviewText').value;
    const images = document.getElementById('reviewImages').files;
    
    if (rating === 0) {
        showAlert('Please select a rating', 'warning');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'add_review');
        formData.append('product_id', currentProductId);
        formData.append('rating', rating);
        formData.append('title', title);
        formData.append('review', review);
        
        for (let i = 0; i < images.length; i++) {
            formData.append('images[]', images[i]);
        }
        
        const response = await fetch('api/reviews.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to add review');
        }
        
        showAlert('Review added successfully!', 'success');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('addReviewModal'));
        modal.hide();
        
        // Clear form
        document.getElementById('addReviewForm').reset();
        clearRatingHighlight();
        
        // Reload reviews
        await loadProductReviews(currentProductId);
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

// Contact Functions
async function handleContact(event) {
    event.preventDefault();
    
    const name = document.getElementById('contactName').value;
    const email = document.getElementById('contactEmail').value;
    const subject = document.getElementById('contactSubject').value;
    const message = document.getElementById('contactMessage').value;
    
    try {
        await apiCall('contact.php', 'POST', {
            name: name,
            email: email,
            subject: subject,
            message: message
        });
        
        showAlert('Message sent successfully! We will get back to you soon.', 'success');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('contactModal'));
        modal.hide();
        
        // Clear form
        document.getElementById('contactForm').reset();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

// Checkout Functions
function showCheckout() {
    if (cart.length === 0) {
        showAlert('Your cart is empty', 'warning');
        return;
    }
    
    if (!currentUser) {
        showAlert('Please login to proceed with checkout', 'warning');
        return;
    }
    
    // Update checkout summary
    const summary = document.getElementById('checkoutSummary');
    const total = cart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
    
    summary.innerHTML = `
        <div class="d-flex justify-content-between">
            <span>Subtotal:</span>
            <span>$${total.toFixed(2)}</span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Shipping:</span>
            <span>$10.00</span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Tax:</span>
            <span>$${(total * 0.08).toFixed(2)}</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between fw-bold">
            <span>Total:</span>
            <span>$${(total + 10 + (total * 0.08)).toFixed(2)}</span>
        </div>
    `;
    
    // Pre-fill shipping info if user is logged in
    if (currentUser) {
        document.getElementById('shippingName').value = currentUser.name;
    }
    
    // Close cart modal and show checkout modal
    const cartModal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
    cartModal.hide();
    
    const checkoutModal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    checkoutModal.show();
}

async function processPayment() {
    const form = document.getElementById('checkoutForm');
    const formData = new FormData(form);
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    try {
        const orderData = {
            shipping_name: document.getElementById('shippingName').value,
            shipping_address: document.getElementById('shippingAddress').value,
            shipping_city: document.getElementById('shippingCity').value,
            shipping_state: document.getElementById('shippingState').value,
            shipping_zip: document.getElementById('shippingZip').value,
            card_number: document.getElementById('cardNumber').value,
            expiry_date: document.getElementById('expiryDate').value,
            cvv: document.getElementById('cvv').value,
            coupon_code: document.getElementById('couponCode').value,
            items: cart
        };
        
        const result = await apiCall('orders.php', 'POST', orderData);
        
        // Clear cart
        cart = [];
        saveCart();
        updateCartCount();
        
        // Show order confirmation
        document.getElementById('orderId').textContent = result.order_id;
        document.getElementById('orderTotal').textContent = result.total.toFixed(2);
        
        // Close checkout modal
        const checkoutModal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
        checkoutModal.hide();
        
        // Show confirmation modal
        const confirmationModal = new bootstrap.Modal(document.getElementById('orderConfirmationModal'));
        confirmationModal.show();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

// Navigation Functions
function showHome() {
    document.getElementById('pageTitle').textContent = 'Fishing Equipment';
    displayProducts(products);
}

function showCart() {
    displayCart();
    const modal = new bootstrap.Modal(document.getElementById('cartModal'));
    modal.show();
}

function showContact() {
    const modal = new bootstrap.Modal(document.getElementById('contactModal'));
    modal.show();
}

function showOrderHistory() {
    // Implementation for order history
    showAlert('Order history feature coming soon!', 'info');
}

function showAdminPanel() {
    if (currentUser && currentUser.role === 'admin') {
        // Admin panel is already visible in the sidebar
        showAlert('Admin panel is available in the sidebar', 'info');
    }
}

// Utility Functions
function generateStarRating(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    let stars = '';
    
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="fas fa-star"></i>';
    }
    
    if (hasHalfStar) {
        stars += '<i class="fas fa-star-half-alt"></i>';
    }
    
    const emptyStars = 5 - Math.ceil(rating);
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="far fa-star"></i>';
    }
    
    return stars;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function showLoading(show) {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = show ? 'block' : 'none';
    }
}

function showAlert(message, type = 'info') {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Print Functions
function printReceipt() {
    const orderId = document.getElementById('orderId').textContent;
    const orderTotal = document.getElementById('orderTotal').textContent;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Receipt - Order ${orderId}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
                    .order-info { margin-bottom: 20px; }
                    .total { font-size: 18px; font-weight: bold; text-align: right; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>FishingGear Pro</h1>
                    <p>Thank you for your purchase!</p>
                </div>
                <div class="order-info">
                    <p><strong>Order ID:</strong> ${orderId}</p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <p><strong>Total:</strong> $${orderTotal}</p>
                </div>
                <p>Your order has been confirmed and will be processed shortly.</p>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Admin Functions (placeholder implementations)
function showAddProductModal() {
    showAlert('Add product functionality will be implemented', 'info');
}

function showAddCategoryModal() {
    showAlert('Add category functionality will be implemented', 'info');
}

function showAddPromotionModal() {
    showAlert('Add promotion functionality will be implemented', 'info');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Hide search suggestions when clicking outside
    document.addEventListener('click', function(event) {
        const searchInput = document.getElementById('searchInput');
        const suggestions = document.getElementById('searchSuggestions');
        
        if (searchInput && suggestions && !searchInput.contains(event.target) && !suggestions.contains(event.target)) {
            suggestions.style.display = 'none';
        }
    });
});