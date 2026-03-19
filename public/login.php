<?php
// public/login.php

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/security.php';

$page_title = 'Login';

if (is_logged_in()) {
    header("Location: profile.php");
    exit;
}

// Apply rate limiting to prevent brute force
$identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!check_rate_limit('login_' . $identifier, 5, 300)) {
    $error = 'Too many login attempts. Please try again in 5 minutes.';
} else {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            $error = 'Invalid request. Please try again.';
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Please fill in all fields.';
            } else {
                $user = fetchOne(
                    "SELECT id, username, password_hash 
                     FROM users 
                     WHERE email = ?",
                    [$email]
                );

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    header("Location: profile.php");
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/../templates/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h2 class="text-center mb-4">Login</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
                </form>

                <p class="text-center mt-4">
                    Don't have an account? <a href="register.php">Register here</a>
                </p>
                <p class="text-center">
                    <a href="forgot-password.php">Forgot your password?</a>
                </p>
                <hr>
                <p class="text-center mb-1">
                    <strong>Or login with:</strong>
                </p>
                <p class="text-center">
                    <a href="login-otp.php" class="btn btn-outline-primary">Login with OTP</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>