<?php
// Header Component
// Top navigation bar with search, notifications, and user dropdown
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/database.php';

// User info (would typically come from session)
$user_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$user_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Administrator';
$user_initials = strtoupper(substr($user_name, 0, 2));

// Get real notifications from database
$notifications = [];
try {
    // Recent pending orders
    $pending_orders = fetchAll("SELECT id, order_number, created_at FROM orders WHERE status = 'pending' ORDER BY created_at DESC LIMIT 3");
    foreach ($pending_orders as $order) {
        $notifications[] = [
            'id' => 'order_' . $order['id'],
            'title' => 'Pending Order',
            'text' => 'Order #' . htmlspecialchars($order['order_number']) . ' awaiting processing',
            'icon' => 'warning',
            'time' => time_ago($order['created_at']),
            'url' => '../orders/list.php?search=' . $order['order_number']
        ];
    }
    
    // Low stock products
    $low_stock = fetchAll("SELECT id, name, stock, low_stock_threshold FROM products WHERE visible = 1 AND stock <= low_stock_threshold ORDER BY stock ASC LIMIT 3");
    foreach ($low_stock as $product) {
        $stock_status = $product['stock'] == 0 ? 'Out of Stock' : 'Low Stock';
        $notifications[] = [
            'id' => 'stock_' . $product['id'],
            'title' => $stock_status,
            'text' => htmlspecialchars($product['name']) . ' - ' . $product['stock'] . ' units left',
            'icon' => $product['stock'] == 0 ? 'danger' : 'warning',
            'time' => 'Now',
            'url' => '../products/edit.php?id=' . $product['id']
        ];
    }
    
    // Recent service bookings
    $recent_bookings = fetchAll("SELECT sb.id, sb.booking_date, s.name FROM service_bookings sb JOIN services s ON sb.service_id = s.id WHERE sb.status = 'confirmed' ORDER BY sb.booking_date DESC LIMIT 2");
    foreach ($recent_bookings as $booking) {
        $notifications[] = [
            'id' => 'booking_' . $booking['id'],
            'title' => 'New Booking',
            'text' => htmlspecialchars($booking['name']) . ' on ' . date('M d', strtotime($booking['booking_date'])),
            'icon' => 'success',
            'time' => 'Today',
            'url' => '../service-bookings/list.php'
        ];
    }
    
    // Sort notifications by time (most recent first)
    usort($notifications, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    
} catch (Exception $e) {
    // Fallback to empty if DB error
    $notifications = [];
}

// Quick actions
$quick_actions = [
    ['title' => 'Add Product', 'icon' => 'fa-box', 'url' => 'products/add.php'],
    ['title' => 'Add Service', 'icon' => 'fa-tools', 'url' => 'services/add.php'],
    ['title' => 'View Orders', 'icon' => 'fa-shopping-cart', 'url' => 'orders/list.php'],
    ['title' => 'Low Stock', 'icon' => 'fa-exclamation-triangle', 'url' => 'products/list.php?stock=low']
];

// Helper function for time ago
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d', $timestamp);
}
?>
<!-- Top Navbar -->
<header class="admin-navbar" id="navbar">
    <div class="navbar-left">
        <!-- Search Bar -->
        <div class="navbar-search">
            <i class="fa-solid fa-magnifying-glass navbar-search-icon"></i>
            <input 
                type="text" 
                class="navbar-search-input" 
                placeholder="Search anything..."
                id="globalSearch"
            >
            <span class="navbar-search-hint">Cmd+K</span>
        </div>
    </div>
    
    <div class="navbar-right">
        <!-- Add New Button -->
        <button class="navbar-btn-primary" id="addNewBtn">
            <i class="fa-solid fa-plus"></i>
            <span>Add New</span>
        </button>
        
        <!-- Notifications Dropdown -->
        <div class="navbar-dropdown">
            <button class="navbar-btn" id="notificationBtn">
                <i class="fa-regular fa-bell"></i>
                <span class="badge"></span>
            </button>
            <div class="dropdown-menu" id="notificationDropdown">
                <div class="dropdown-header">
                    <h4>Notifications</h4>
                </div>
                <div class="dropdown-body">
                    <?php foreach ($notifications as $notification): ?>
                    <div class="dropdown-item">
                        <div class="dropdown-item-icon <?php echo $notification['icon']; ?>">
                            <i class="fa-solid fa-<?php 
                                echo $notification['icon'] === 'success' ? 'check' : 
                                    ($notification['icon'] === 'warning' ? 'exclamation' : 'xmark');
                            ?>"></i>
                        </div>
                        <div class="dropdown-item-content">
                            <div class="dropdown-item-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="dropdown-item-text"><?php echo htmlspecialchars($notification['text']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="dropdown-footer">
                    <a href="logs.php">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Dropdown -->
        <div class="navbar-dropdown">
            <button class="navbar-btn" id="quickActionsBtn">
                <i class="fa-solid fa-plus-circle"></i>
            </button>
            <div class="dropdown-menu" id="quickActionsDropdown">
                <div class="dropdown-header">
                    <h4>Quick Actions</h4>
                </div>
                <div class="dropdown-body">
                    <?php foreach ($quick_actions as $action): ?>
                    <a href="<?php echo htmlspecialchars($action['url']); ?>" class="dropdown-item">
                        <div class="dropdown-item-icon">
                            <i class="fa-solid <?php echo htmlspecialchars($action['icon']); ?>"></i>
                        </div>
                        <div class="dropdown-item-content">
                            <div class="dropdown-item-title"><?php echo htmlspecialchars($action['title']); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- User Profile Dropdown -->
        <div class="navbar-dropdown user-dropdown">
            <button class="user-toggle" id="userDropdownBtn">
                <div class="user-toggle-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                <div class="user-toggle-info">
                    <div class="user-toggle-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-toggle-role"><?php echo htmlspecialchars($user_role); ?></div>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: var(--text-muted);"></i>
            </button>
            <div class="dropdown-menu" id="userDropdown" style="min-width: 200px;">
                <div class="dropdown-body">
                    <a href="../public/profile.php" class="dropdown-item" target="_blank">
                        <div class="dropdown-item-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="dropdown-item-content">
                            <div class="dropdown-item-title">View Profile</div>
                        </div>
                    </a>
                    <a href="system-status.php" class="dropdown-item">
                        <div class="dropdown-item-icon">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <div class="dropdown-item-content">
                            <div class="dropdown-item-title">Settings</div>
                        </div>
                    </a>
                    <a href="logs.php" class="dropdown-item">
                        <div class="dropdown-item-icon">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                        <div class="dropdown-item-content">
                            <div class="dropdown-item-title">Activity Log</div>
                        </div>
                    </a>
                    <div class="dropdown-item" style="cursor: pointer;" onclick="document.location.href='logout.php'">
                        <div class="dropdown-item-icon" style="background: rgba(239, 68, 68, 0.2); color: var(--danger-color);">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </div>
                        <div class="dropdown-item-content">
                            <div class="dropdown-item-title">Logout</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Command Palette Modal -->
<div class="command-palette-backdrop" id="commandPaletteBackdrop"></div>
<div class="command-palette" id="commandPalette">
    <div class="command-palette-header">
        <div class="command-palette-input-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input 
                type="text" 
                class="command-palette-input" 
                placeholder="Type a command or search..."
                id="commandPaletteInput"
            >
            <span class="command-palette-hint">ESC</span>
        </div>
    </div>
    <div class="command-palette-body">
        <div class="command-palette-section">
            <div class="command-palette-section-title">Quick Actions</div>
            <div class="command-palette-item" data-action="add-product">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">Add New Product</div>
                    <div class="command-palette-item-text">Create a new product listing</div>
                </div>
            </div>
            <div class="command-palette-item" data-action="add-service">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-tools"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">Add New Service</div>
                    <div class="command-palette-item-text">Create a new service offering</div>
                </div>
            </div>
            <div class="command-palette-item" data-action="view-orders">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-shopping-cart"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">View Orders</div>
                    <div class="command-palette-item-text">Go to orders management</div>
                </div>
            </div>
        </div>
        <div class="command-palette-section">
            <div class="command-palette-section-title">Navigation</div>
            <div class="command-palette-item" data-action="dashboard">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">Dashboard</div>
                    <div class="command-palette-item-text">Go to dashboard</div>
                </div>
            </div>
            <div class="command-palette-item" data-action="products">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">Products</div>
                    <div class="command-palette-item-text">Manage products</div>
                </div>
            </div>
            <div class="command-palette-item" data-action="users">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">Users</div>
                    <div class="command-palette-item-text">Manage users</div>
                </div>
            </div>
            <div class="command-palette-item" data-action="settings">
                <div class="command-palette-item-icon">
                    <i class="fa-solid fa-gear"></i>
                </div>
                <div class="command-palette-item-content">
                    <div class="command-palette-item-title">Settings</div>
                    <div class="command-palette-item-text">System settings</div>
                </div>
            </div>
        </div>
    </div>
    <div class="command-palette-footer">
        <div class="command-palette-shortcuts">
            <span class="shortcut"><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
            <span class="shortcut"><kbd>Enter</kbd> Select</span>
            <span class="shortcut"><kbd>Esc</kbd> Close</span>
        </div>
    </div>
</div>

<script>
    // Header dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown toggle functionality
        const dropdowns = document.querySelectorAll('.navbar-dropdown');
        
        dropdowns.forEach(dropdown => {
            const btn = dropdown.querySelector('button');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    dropdowns.forEach(d => {
                        if (d !== dropdown) {
                            d.querySelector('.dropdown-menu').classList.remove('show');
                        }
                    });
                    
                    menu.classList.toggle('show');
                });
            }
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu && !dropdown.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        });
        
        // Command Palette functionality
        const searchInput = document.getElementById('globalSearch');
        const commandPalette = document.getElementById('commandPalette');
        const commandPaletteBackdrop = document.getElementById('commandPaletteBackdrop');
        const commandPaletteInput = document.getElementById('commandPaletteInput');
        
        function openCommandPalette() {
            commandPalette.classList.add('show');
            commandPaletteBackdrop.classList.add('show');
            commandPaletteInput.focus();
        }
        
        function closeCommandPalette() {
            commandPalette.classList.remove('show');
            commandPaletteBackdrop.classList.remove('show');
            commandPaletteInput.value = '';
        }
        
        // Open command palette on Cmd+K or Ctrl+K
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                openCommandPalette();
            }
            
            if (e.key === 'Escape' && commandPalette.classList.contains('show')) {
                closeCommandPalette();
            }
        });
        
        // Click on search hint to open command palette
        const searchHint = document.querySelector('.navbar-search-hint');
        if (searchHint) {
            searchHint.style.cursor = 'pointer';
            searchHint.addEventListener('click', openCommandPalette);
        }
        
        // Close command palette on backdrop click
        commandPaletteBackdrop.addEventListener('click', closeCommandPalette);
        
        // Command palette navigation
        const paletteItems = document.querySelectorAll('.command-palette-item');
        let selectedIndex = 0;
        
        commandPaletteInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, paletteItems.length - 1);
                updateSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateSelection();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (paletteItems[selectedIndex]) {
                    executeAction(paletteItems[selectedIndex].dataset.action);
                }
            }
        });
        
        function updateSelection() {
            paletteItems.forEach((item, index) => {
                item.classList.toggle('selected', index === selectedIndex);
            });
        }
        
        function executeAction(action) {
            const baseUrl = window.location.href.substring(0, window.location.href.lastIndexOf('/admin/') + 7);
            
            switch(action) {
                case 'add-product':
                    window.location.href = baseUrl + '/products/add.php';
                    break;
                case 'add-service':
                    window.location.href = baseUrl + '/services/add.php';
                    break;
                case 'view-orders':
                    window.location.href = baseUrl + '/orders/list.php';
                    break;
                case 'dashboard':
                    window.location.href = baseUrl + '/index.php';
                    break;
                case 'products':
                    window.location.href = baseUrl + '/products/list.php';
                    break;
                case 'users':
                    window.location.href = baseUrl + '/users/list.php';
                    break;
                case 'settings':
                    window.location.href = baseUrl + '/system-status.php';
                    break;
            }
            
            closeCommandPalette();
        }
        
        // Add click handlers to palette items
        paletteItems.forEach((item, index) => {
            item.addEventListener('click', function() {
                executeAction(this.dataset.action);
            });
            
            item.addEventListener('mouseenter', function() {
                selectedIndex = index;
                updateSelection();
            });
        });
    });
</script>
