<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config.php';
$page_title = 'Dashboard';

// Fetch dashboard statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE visible = 1");
    $stats['total_products'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE visible = 1");
    $stats['total_services'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $stats['total_orders'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid'");
    $stats['total_revenue'] = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    // Low stock count for stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE visible = 1 AND stock <= low_stock_threshold");
    $stats['low_stock_count'] = $stmt->fetch()['total'];

    // Get recent orders
    $stmt = $pdo->query("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $recentOrders = $stmt->fetchAll();
    
    // Sales by month (last 6 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
               SUM(total_amount) as revenue, 
               COUNT(*) as orders
        FROM orders 
        WHERE payment_status = 'paid' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $salesData = $stmt->fetchAll();
    
    // Top products
    $stmt = $pdo->query("
        SELECT p.name, SUM(oi.quantity) as qty, SUM(oi.price * oi.quantity) as revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        GROUP BY p.id
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll();
    
    // Low stock alerts - products below threshold
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.stock, p.low_stock_threshold, p.sku,
               c.name as category_name,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.visible = 1 AND p.stock <= p.low_stock_threshold
        ORDER BY p.stock ASC
        LIMIT 10
    ");
    $lowStockProducts = $stmt->fetchAll();
    $lowStockCount = count($lowStockProducts);
} catch (PDOException $e) {
    $stats = [
        'total_products' => 0,
        'total_services' => 0,
        'total_orders' => 0,
        'total_users' => 0,
        'total_revenue' => 0,
        'pending_orders' => 0
    ];
    $recentOrders = [];
    $salesData = [];
    $topProducts = [];
}

// Prepare chart data
$months = [];
$revenues = [];
$orderCounts = [];
foreach ($salesData as $row) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $revenues[] = (float)$row['revenue'];
    $orderCounts[] = (int)$row['orders'];
}

// Get admin name for welcome message
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #0d6efd; --dark: #212529; }
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .sidebar a i { width: 25px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .bg-products { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .bg-services { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
        .bg-orders { background: rgba(20, 184, 166, 0.15); color: #14b8a6; }
        .bg-users { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .bg-revenue { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .bg-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .bg-low-stock { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .low-stock-item { border-left: 3px solid #ef4444; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products/list.php"><i class="fas fa-box"></i> Products</a>
        <a href="services/list.php"><i class="fas fa-tools"></i> Services</a>
        <a href="orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="service-bookings/list.php"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="chatbot/conversations.php"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="coupons/manage.php"><i class="fas fa-ticket"></i> Coupons</a>
        <a href="loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="reviews/manage.php"><i class="fas fa-star"></i> Reviews</a>
        <a href="system-status.php"><i class="fas fa-server"></i> System</a>
        <a href="logs.php"><i class="fas fa-file-lines"></i> Logs</a>
        <a href="delivery-map.php"><i class="fas fa-map"></i> Map</a>
        <a href="logout.php" style="margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="bg-primary bg-gradient text-white p-4 rounded mb-4">
            <h4 class="mb-1">Welcome back, <?= htmlspecialchars($admin_name) ?>!</h4>
            <p class="mb-0 opacity-75">Here's what's happening with your store today.</p>
        </div>

        <!-- Stats Cards Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-products me-3">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= number_format($stats['total_products']) ?></h3>
                            <small class="text-muted">Total Products</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-services me-3">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= number_format($stats['total_services']) ?></h3>
                            <small class="text-muted">Total Services</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-orders me-3">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= number_format($stats['total_orders']) ?></h3>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-users me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= number_format($stats['total_users']) ?></h3>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-revenue me-3">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h3 class="mb-0">KES <?= number_format($stats['total_revenue']) ?></h3>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-pending me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?= number_format($stats['pending_orders']) ?></h3>
                            <small class="text-muted">Pending Orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts Section -->
        <?php if (!empty($lowStockProducts)): ?>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alerts</h5>
                        <span class="badge bg-white text-danger"><?= $lowStockCount ?> item(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Threshold</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockProducts as $product): ?>
                                        <tr class="low-stock-item">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($product['image'])): ?>
                                                        <img src="../assets/images/products/<?= htmlspecialchars($product['image']) ?>" 
                                                             class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                    <?php else: ?>
                                                        <div class="bg-light me-2 d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px; border-radius: 4px;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><code><?= htmlspecialchars($product['sku'] ?? 'N/A') ?></code></td>
                                            <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $product['stock'] == 0 ? 'danger' : 'warning' ?>">
                                                    <?= $product['stock'] ?> units
                                                </span>
                                            </td>
                                            <td><?= $product['low_stock_threshold'] ?> units</td>
                                            <td>
                                                <?php if ($product['stock'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Low Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="products/edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Restock
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <a href="products/list.php?stock=low" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-list me-1"></i> View All Low Stock Items
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts and Top Products Row -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Revenue & Orders (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topProducts)): ?>
                            <p class="text-muted mb-0">No sales data yet</p>
                        <?php else: ?>
                            <?php foreach ($topProducts as $i => $product): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="badge bg-<?= $i === 0 ? 'warning' : ($i === 1 ? 'secondary' : ($i === 2 ? 'dark' : 'light')) ?> text-dark rounded-circle me-3" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                        <?= $i + 1 ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= htmlspecialchars($product['name']) ?></div>
                                        <small class="text-muted"><?= number_format($product['qty']) ?> sold</small>
                                    </div>
                                    <div class="text-success fw-bold">KES <?= number_format($product['revenue']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Recent Orders</h5>
                <a href="orders/list.php" class="btn btn-sm btn-primary">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No orders yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="orders/list.php?search=<?= urlencode($order['order_number']) ?>" class="text-decoration-none">
                                                <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                            </a>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($order['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                                        </td>
                                        <td><strong>KES <?= number_format($order['total_amount']) ?></strong></td>
                                        <td>
                                            <?php
                                            $statusClass = match($order['status']) {
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $paymentClass = $order['payment_status'] === 'paid' ? 'success' : 'warning';
                                            ?>
                                            <span class="badge bg-<?= $paymentClass ?>">
                                                <?= ucfirst($order['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Script -->
    <script>
        // Sales Chart
        const ctx = document.getElementById("salesChart");
        if (ctx) {
            new Chart(ctx, {
                type: "line",
                data: {
                    labels: <?= json_encode($months) ?>,
                    datasets: [{
                        label: "Revenue (KES)",
                        data: <?= json_encode($revenues) ?>,
                        borderColor: "#3b82f6",
                        backgroundColor: "rgba(59, 130, 246, 0.1)",
                        fill: true,
                        tension: 0.4
                    }, {
                        label: "Orders",
                        data: <?= json_encode($orderCounts) ?>,
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16, 185, 129, 0.1)",
                        fill: true,
                        tension: 0.4,
                        yAxisID: "y1"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: "index",
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Revenue (KES)"
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: "right",
                            title: {
                                display: true,
                                text: "Orders"
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
