<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/compare.php';

$page_title = 'Products';

// Handle compare action
if (isset($_GET['compare']) && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $action = $_GET['compare'];
    
    if ($action === 'add' && $product_id > 0) {
        // Verify product exists
        $product = fetchOne("SELECT id FROM products WHERE id = ? AND visible = 1", [$product_id]);
        if ($product) {
            if (!isset($_SESSION['comparison'])) {
                $_SESSION['comparison'] = [];
            }
            // Check if not already in comparison and limit to 4
            if (!in_array($product_id, $_SESSION['comparison']) && count($_SESSION['comparison']) < 4) {
                $_SESSION['comparison'][] = $product_id;
            }
        }
    } elseif ($action === 'remove' && $product_id > 0) {
        if (isset($_SESSION['comparison'])) {
            $_SESSION['comparison'] = array_filter($_SESSION['comparison'], function($id) use ($product_id) {
                return $id != $product_id;
            });
            $_SESSION['comparison'] = array_values($_SESSION['comparison']);
        }
    }
    
    // Redirect to remove query params
    header('Location: products.php');
    exit;
}

// Handle clear comparison
if (isset($_GET['clear_compare'])) {
    $_SESSION['comparison'] = [];
    header('Location: products.php');
    exit;
}

// Get comparison items for display
$comparison_items = [];
if (!empty($_SESSION['comparison'])) {
    $ids = $_SESSION['comparison'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $comparison_items = fetchAll("
        SELECT id, name, price, 
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
        FROM products p 
        WHERE id IN ($placeholders)
    ", $ids);
}

$comparison_count = count($comparison_items);

// Get categories for filter (exclude Laptops, Printers, Networking)
$categories = fetchAll("SELECT * FROM categories WHERE slug NOT IN ('laptops', 'printers', 'networking', 'storage') ORDER BY display_order ASC");

// Get current filters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// Sorting
$order_by = "p.name ASC";
switch ($sort_by) {
    case 'price_low':
        $order_by = "p.price ASC";
        break;
    case 'price_high':
        $order_by = "p.price DESC";
        break;
    case 'name_desc':
        $order_by = "p.name DESC";
        break;
    case 'newest':
        $order_by = "p.created_at DESC";
        break;
    default:
        $order_by = "p.name ASC";
}

// Build query with proper category filtering
$sql = "SELECT p.id, p.name, p.slug, p.price, p.short_description, p.stock, p.compare_at_price,
               c.name as category_name,
               (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.visible = 1 AND c.slug NOT IN ('laptops', 'printers', 'networking', 'storage')";

$params = [];

if ($category_filter) {
    $sql .= " AND c.slug = ?";
    $params[] = $category_filter;
}

$sql .= " ORDER BY $order_by";

$products = fetchAll($sql, $params);

// Product icons mapping
$product_icons = [
    'laptop' => 'fa-laptop',
    'macbook' => 'fa-laptop',
    'phone' => 'fa-mobile-alt',
    'samsung' => 'fa-mobile-alt',
    'tablet' => 'fa-tablet-alt',
    'router' => 'fa-wifi',
    'network' => 'fa-network-wired',
    'switch' => 'fa-network-wired',
    'mouse' => 'fa-computer-mouse',
    'keyboard' => 'fa-keyboard',
    'cable' => 'fa-ethernet',
    'ssd' => 'fa-hdd',
    'storage' => 'fa-hdd',
    'printer' => 'fa-print',
    'charger' => 'fa-battery-full',
    'battery' => 'fa-battery-full',
    'hub' => 'fa-plug',
    'headphone' => 'fa-headphones',
    'speaker' => 'fa-volume-up',
    'webcam' => 'fa-camera',
];

function get_product_icon($name) {
    global $product_icons;
    $name = strtolower($name);
    foreach ($product_icons as $key => $icon) {
        if (strpos($name, $key) !== false) {
            return $icon;
        }
    }
    return 'fa-box';
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Page Header -->
<section class="bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2">Our Products</h1>
                <p class="text-muted mb-0">
                    <?php if ($category_filter): ?>
                        Browsing: <strong><?= htmlspecialchars(ucfirst($category_filter)) ?></strong>
                    <?php else: ?>
                        Quality tech products - Laptops, phones, networking & accessories
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-md-end mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                        <li class="breadcrumb-item active">Products</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <!-- Filters & Sort -->
    <div class="row mb-4">
        <div class="col-md-8">
            <!-- Categories Pills -->
            <div class="d-flex flex-wrap gap-2">
                <a href="products.php" class="btn btn-sm <?= empty($category_filter) ? 'btn-primary' : 'btn-outline-primary' ?>">
                    All
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="products.php?category=<?= htmlspecialchars($cat['slug']) ?>" 
                       class="btn btn-sm <?= $category_filter === $cat['slug'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-md-4">
            <form method="get" class="d-flex align-items-center gap-2 justify-content-md-end">
                <?php if ($category_filter): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                <?php endif; ?>
                <select name="sort" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Sort: A-Z</option>
                    <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Sort: Z-A</option>
                    <option value="price_low" <?= $sort_by === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_high" <?= $sort_by === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Results Count -->
    <p class="text-muted mb-3">Showing <strong><?= count($products) ?></strong> products</p>

    <!-- Products Grid (Cube Cards) -->
    <?php if (empty($products)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="fas fa-box fa-4x mb-3"></i>
            <h4>No products in this category</h4>
            <p>Try a different category or clear filters.</p>
        </div>
    <?php else: ?>
        <div class="row g-4 justify-content-center">
            <?php foreach ($products as $p): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100 border-0 product-card">
                        <!-- Image Area (Square) -->
                        <div class="product-image-container">
                            <?php if (!empty($p['image'])): ?>
                                <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($p['image']) ?>" 
                                     alt="<?= htmlspecialchars($p['name']) ?>"
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    <i class="fas <?= get_product_icon($p['name']) ?> fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <?php if (!empty($p['compare_at_price']) && $p['compare_at_price'] > $p['price']): ?>
                                <span class="product-badge badge bg-danger">SALE</span>
                            <?php endif; ?>
                            <?php if ($p['stock'] == 0): ?>
                                <span class="product-badge badge bg-secondary">Out</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Text Container -->
                        <div class="card-body p-3">
                            <h5 class="product-title"><?= htmlspecialchars($p['name']) ?></h5>
                            
                            <p class="product-price">
                                <?php if (!empty($p['compare_at_price']) && $p['compare_at_price'] > $p['price']): ?>
                                    <span class="text-muted text-decoration-line-through small">KSh <?= number_format($p['compare_at_price']) ?></span>
                                    <span class="d-block">KSh <?= number_format($p['price']) ?></span>
                                <?php else: ?>
                                    KSh <?= number_format($p['price']) ?>
                                <?php endif; ?>
                            </p>
                            
                            <!-- Buttons -->
                            <div class="d-flex gap-2">
                                <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                    View
                                </a>
                                <?php if ($p['stock'] > 0): ?>
                                    <button class="btn btn-primary btn-sm flex-grow-1 add-to-cart" data-id="<?= $p['id'] ?>">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                <?php endif; ?>
                                <?php 
                                // Check if product is already in comparison
                                $in_compare = isset($_SESSION['comparison']) && in_array($p['id'], $_SESSION['comparison']);
                                ?>
                                <a href="?compare=add&id=<?= $p['id'] ?>" class="btn btn-sm <?= $in_compare ? 'btn-success' : 'btn-outline-secondary' ?>" title="Compare">
                                    <i class="fas fa-balance-scale"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Cube Card Styling */
.product-card {
    width: 100%;
    max-width: 260px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    margin: 0 auto;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}

.product-image-container {
    position: relative;
    width: 100%;
    padding-top: 75%;
    overflow: hidden;
    border-radius: 10px 10px 0 0;
    background: #f8f9fa;
}

.product-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 10px;
}

.product-image-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.product-title {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
    height: 36px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    margin-bottom: 6px;
}

.product-price {
    font-size: 16px;
    font-weight: 700;
    color: #0d6efd;
    margin-bottom: 10px;
}

.product-card .btn {
    height: 36px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
}

@media (max-width: 768px) {
    .product-card {
        max-width: 100%;
    }
}

/* Compare Bar Styles */
.compare-bar {
    position: fixed;
    bottom: -200px;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
    padding: 15px 20px;
    z-index: 1000;
    transition: bottom 0.3s ease;
}

.compare-bar.show {
    bottom: 0;
}

.compare-bar-items {
    display: flex;
    gap: 10px;
    align-items: center;
    overflow-x: auto;
    padding-bottom: 5px;
}

.compare-bar-item {
    min-width: 80px;
    max-width: 80px;
    text-align: center;
    position: relative;
}

.compare-bar-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.compare-bar-item .remove-compare {
    position: absolute;
    top: -8px;
    right: 8px;
    background: red;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.compare-bar-item .item-name {
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.compare-bar .btn-compare {
    white-space: nowrap;
}
</style>

<!-- Floating Compare Bar -->
<?php if ($comparison_count > 0): ?>
<div class="compare-bar show" id="compareBar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div class="compare-bar-items flex-grow-1" id="compareBarItems">
                <?php foreach ($comparison_items as $item): ?>
                <div class="compare-bar-item">
                    <a href="?compare=remove&id=<?= $item['id'] ?>" class="remove-compare">&times;</a>
                    <?php if (!empty($item['image'])): ?>
                    <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="width:60px;height:60px;border-radius:6px;"><i class="fas fa-image text-muted"></i></div>
                    <?php endif; ?>
                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 align-items-center ms-3">
                <a href="?clear_compare=1" class="btn btn-outline-danger btn-sm">Clear</a>
                <a href="compare.php" class="btn btn-primary btn-compare">
                    Compare (<?= $comparison_count ?>)
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Simple cart functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.id;
            
            fetch('<?= BASE_URL ?>api/v1/cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=add&product_id=' + productId + '&quantity=1'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Added to cart!');
                    const badge = document.getElementById('cart-count');
                    if (badge && data.cart_count !== undefined) badge.textContent = data.cart_count;
                } else {
                    alert(data.message || 'Error adding to cart');
                }
            })
            .catch(err => { console.error(err); alert('Error'); });
        });
    });
});
</script>

<script>
// Compare functionality
let compareItems = [];

// Load compare items from session on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCompareItems();
});

function loadCompareItems() {
    fetch('<?= BASE_URL ?>api/v1/compare.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                compareItems = data.data.products || [];
                updateCompareBar();
                updateCompareButtons();
            }
        })
        .catch(error => console.error('Error loading compare items:', error));
}

function addToCompare(productId) {
    fetch('<?= BASE_URL ?>api/v1/compare.php?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCompareItems();
            alert('Product added to compare!');
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function removeFromCompare(productId) {
    fetch('<?= BASE_URL ?>api/v1/compare.php?action=remove', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        loadCompareItems();
    })
    .catch(error => console.error('Error:', error));
}

function clearCompare() {
    fetch('<?= BASE_URL ?>api/v1/compare.php?action=clear', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        compareItems = [];
        updateCompareBar();
        updateCompareButtons();
    })
    .catch(error => console.error('Error:', error));
}

function updateCompareBar() {
    const bar = document.getElementById('compareBar');
    const itemsContainer = document.getElementById('compareBarItems');
    const countSpan = document.getElementById('compareCount');
    
    countSpan.textContent = compareItems.length;
    
    if (compareItems.length > 0) {
        bar.classList.add('show');
    } else {
        bar.classList.remove('show');
    }
    
    itemsContainer.innerHTML = compareItems.map(item => `
        <div class="compare-bar-item">
            <button class="remove-compare" onclick="removeFromCompare(${item.id})">×</button>
            <img src="${item.image ? BASE_URL + 'assets/images/products/' + item.image : BASE_URL + 'assets/images/products/'}" alt="${item.name}">
            <div class="item-name">${item.name}</div>
        </div>
    `).join('');
}

function updateCompareButtons() {
    document.querySelectorAll('.add-to-compare').forEach(btn => {
        const productId = parseInt(btn.dataset.id);
        const inCompare = compareItems.some(item => item.id === productId);
        
        if (inCompare) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.title = 'In Compare';
        } else {
            btn.classList.add('btn-outline-secondary');
            btn.classList.remove('btn-success');
            btn.innerHTML = '<i class="fas fa-balance-scale"></i>';
            btn.title = 'Compare';
        }
    });
}

// Add click handlers to compare buttons
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.add-to-compare');
    if (btn) {
        const productId = parseInt(btn.dataset.id);
        const inCompare = compareItems.some(item => item.id === productId);
        
        if (inCompare) {
            removeFromCompare(productId);
        } else {
            addToCompare(productId);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
