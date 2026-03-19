<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

require_login();

$page_title = 'Order Confirmation';
$order_id = (int)($_GET['order_id'] ?? 0);

$order = fetchOne(
    "SELECT * FROM orders WHERE id = ? AND user_id = ?",
    [$order_id, $_SESSION['user_id']]
);

if (!$order) {
    header("Location: profile.php");
    exit;
}
?>

<?php require_once __DIR__ . '/../templates/header.php'; ?>

<div class="text-center py-5">
    <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
    <h1 class="mb-3">Thank You!</h1>
    <h4 class="text-muted mb-4">Order #<?= htmlspecialchars($order['order_number']) ?> Confirmed</h4>

    <div class="card mx-auto" style="max-width:500px;">
        <div class="card-body">
            <p class="fs-5 mb-3">Your order has been placed successfully.</p>
            <p class="mb-2"><strong>Total Paid:</strong> KSh <?= number_format($order['total_amount']) ?></p>
            <p class="mb-4"><strong>Delivery:</strong> 
                <?= $order['delivery_type'] === 'pickup' ? 'Pickup at shop' : 'To your address' ?>
            </p>
            <p class="text-muted">We’ll notify you once your order is ready or shipped.</p>
        </div>
    </div>

    <a href="profile.php" class="btn btn-primary btn-lg mt-4 px-5">View My Orders</a>
    <a href="products.php" class="btn btn-outline-primary mt-3">Continue Shopping</a>
</div>

<?php require_once '../templates/footer.php'; ?>