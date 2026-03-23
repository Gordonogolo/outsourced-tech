<?php
// templates/navbar.php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/loyalty.php';

// Get user tier if logged in
$navbar_user_tier = null;
if (isset($_SESSION['user_id'])) {
    $navbar_user_tier = get_user_tier($_SESSION['user_id']);
}

$tier_badges = [
    'Bronze' => ['icon' => '🥉', 'color' => '#cd7f32'],
    'Silver' => ['icon' => '🥈', 'color' => '#c0c0c0'],
    'Gold' => ['icon' => '🥇', 'color' => '#ffd700'],
    'Platinum' => ['icon' => '💎', 'color' => '#e5e4e2'],
];

// Get categories for mega menu
$menu_categories = [];
try {
    global $db;
    $stmt = $db->query("SELECT id, name, slug FROM categories ORDER BY display_order ASC LIMIT 8");
    $menu_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silently fail - menu will just show links without categories
}
?>

<style>
.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
}

.navbar-brand i {
    color: #0d6efd;
}

/* Mega Menu Styles */
.megamenu {
    position: static;
}

.megamenu .dropdown-menu {
    width: 100%;
    left: 0;
    right: 0;
    padding: 20px;
    margin-top: 0;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.megamenu-category {
    display: block;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.2s;
}

.megamenu-category:hover {
    background: #f8f9fa;
    color: #0d6efd;
    padding-left: 20px;
}

.megamenu-category i {
    margin-right: 10px;
    width: 20px;
}

/* Search Autocomplete */
.search-container {
    position: relative;
    flex-grow: 1;
    max-width: 400px;
}

.search-input {
    width: 100%;
    padding: 8px 40px 8px 15px;
    border: 1px solid #ddd;
    border-radius: 25px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    max-height: 400px;
    overflow-y: auto;
    display: none;
    z-index: 1001;
}

.search-results.active {
    display: block;
}

.search-result-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.search-result-item:hover {
    background: #f8f9fa;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 5px;
    margin-right: 12px;
}

.search-result-item .item-info {
    flex: 1;
}

.search-result-item .item-name {
    font-weight: 600;
    font-size: 14px;
}

.search-result-item .item-price {
    font-size: 13px;
    color: #0d6efd;
    font-weight: 600;
}

.search-result-item .item-type {
    font-size: 11px;
    color: #888;
    background: #eee;
    padding: 2px 8px;
    border-radius: 10px;
}

.search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    cursor: pointer;
}

/* Nav CTA Button */
.nav-cta {
    background: #0d6efd;
    color: white !important;
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 600;
}

.nav-cta:hover {
    background: #0b5ed7;
}

/* Service Badge */
.service-badge {
    background: #198754;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>">
            <i class="fas fa-microchip me-2"></i>
            <?= APP_NAME ?>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Main Navigation -->
        <div class="collapse navbar-collapse" id="mainNav">
            <!-- Unified Search -->
            <div class="search-container mx-3">
                <input type="text" class="search-input" id="globalSearch" placeholder="Search products or services...">
                <i class="fas fa-search search-icon"></i>
                <div class="search-results" id="searchResults"></div>
            </div>
            
            <ul class="navbar-nav me-auto">
                <!-- Products Mega Menu -->
                <li class="nav-item megamenu">
                    <a class="nav-link dropdown-toggle" href="#" id="productsDropdown" role="button" data-bs-toggle="dropdown">
                        Shop Products <i class="fas fa-chevron-down ms-1"></i>
                    </a>
                    <div class="dropdown-menu">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="dropdown-header fw-bold">Browse Categories</h6>
                                    <div class="row">
                                        <?php foreach (array_slice($menu_categories, 0, 6) as $cat): ?>
                                        <div class="col-6">
                                            <a href="products.php?category=<?= urlencode($cat['slug']) ?>" class="megamenu-category">
                                                <i class="fas fa-angle-right"></i>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </a>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 bg-light rounded p-3">
                                    <h6 class="fw-bold">Quick Links</h6>
                                    <a href="products.php?featured=1" class="d-block text-decoration-none mb-2">
                                        <i class="fas fa-star text-warning me-2"></i>Featured Products
                                    </a>
                                    <a href="products.php" class="d-block text-decoration-none mb-2">
                                        <i class="fas fa-th-large text-primary me-2"></i>All Products
                                    </a>
                                    <a href="compare.php" class="d-block text-decoration-none">
                                        <i class="fas fa-balance-scale text-info me-2"></i>Compare Products
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                
                <!-- Services Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown">
                        Book Services <span class="service-badge">NEW</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="services.php">All Services</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="services.php?type=repair">
                            <i class="fas fa-tools me-2"></i>Repairs
                        </a></li>
                        <li><a class="dropdown-item" href="services.php?type=installation">
                            <i class="fas fa-plug me-2"></i>Installation
                        </a></li>
                        <li><a class="dropdown-item" href="services.php?type=consultation">
                            <i class="fas fa-comments me-2"></i>Consultation
                        </a></li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="track-order.php">Track Order</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
            </ul>
            
            <!-- Right Side -->
            <ul class="navbar-nav align-items-center">
                <!-- WhatsApp Link -->
                <li class="nav-item">
                    <a class="nav-link text-success" href="https://wa.me/254712345678" target="_blank" title="Chat on WhatsApp">
                        <i class="fab fa-whatsapp fa-lg"></i>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link position-relative" href="cart.php">
                        <i class="fas fa-shopping-cart fa-lg"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                            0
                        </span>
                    </a>
                </li>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="wishlist.php">
                            <i class="fas fa-heart"></i>
                        </a>
                    </li>
                    <?php if ($navbar_user_tier): ?>
                        <?php $badge = $tier_badges[$navbar_user_tier['name']] ?? $tier_badges['Bronze']; ?>
                        <li class="nav-item">
                            <span class="nav-link" title="<?= htmlspecialchars($navbar_user_tier['name']) ?> Member">
                                <?= $badge['icon'] ?>
                            </span>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> Account
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-cta" href="register.php">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
// Define BASE_URL for JavaScript (check if already defined to avoid duplicate declaration error)
if (typeof window.BASE_URL === 'undefined') {
    window.BASE_URL = '<?= BASE_URL ?>';
}

// Simple search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.classList.remove('active');
                return;
            }
            
            // Fetch search results
            fetch('api/v1/search.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.products && data.products.length > 0) {
                        let html = '';
                        data.products.slice(0, 5).forEach(product => {
                            html += `
                                <a href="product.php?slug=${product.slug}" class="search-result-item">
                                    <img src="${product.image ? BASE_URL + 'assets/images/products/' + product.image : BASE_URL + 'assets/images/products/placeholder.jpg'}" alt="${product.name}">
                                    <div class="item-info">
                                        <div class="item-name">${product.name}</div>
                                        <div class="item-price">KSh ${parseInt(product.price).toLocaleString()}</div>
                                    </div>
                                    <span class="item-type">Product</span>
                                </a>
                            `;
                        });
                        searchResults.innerHTML = html;
                        searchResults.classList.add('active');
                    } else {
                        searchResults.innerHTML = '<div class="p-3 text-center text-muted">No results found</div>';
                        searchResults.classList.add('active');
                    }
                })
                .catch(() => {
                    searchResults.classList.remove('active');
                });
        });
        
        // Close on click outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('active');
            }
        });
    }
});
</script>
