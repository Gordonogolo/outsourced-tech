<?php
// Load .env file for environment variables
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
    
    $dotenv_path = __DIR__ . '/..';
    if (file_exists($dotenv_path . '/.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable($dotenv_path);
        $dotenv->load();
    }
}

// public/login-otp.php - Login with OTP page
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/password.php';

// Process POST request BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $_SESSION['login_otp_message'] = 'Please enter your email address';
        $_SESSION['login_otp_message_type'] = 'danger';
    } else {
        // Check if user exists
        $user = fetchOne(
            "SELECT id, email, full_name FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user) {
            // Don't reveal if user exists
            $_SESSION['login_otp_message'] = 'If an account exists, a verification code will be sent to your email';
            $_SESSION['login_otp_message_type'] = 'success';
        } else {
            // Generate and send OTP
            $otp = generate_otp(6);
            
            // Store OTP in database
            if (store_otp($user['email'], $otp, 10, 'login_verification')) {
                // Send OTP via email
                $email_result = send_otp_email($user['email'], $otp, 'login_verification');
                
                if ($email_result['success']) {
                    // Store email in session for verification
                    $_SESSION['login_otp_email'] = $email;
                    header('Location: verify-login-otp.php');
                    exit;
                } else {
                    $_SESSION['login_otp_message'] = 'Failed to send verification code. Please try again.';
                    $_SESSION['login_otp_message_type'] = 'danger';
                }
            } else {
                $_SESSION['login_otp_message'] = 'Failed to generate verification code. Please try again.';
                $_SESSION['login_otp_message_type'] = 'danger';
            }
        }
    }
}

// Normal page load
$message = $_SESSION['login_otp_message'] ?? '';
$message_type = $_SESSION['login_otp_message_type'] ?? '';

// Clear the message from session
unset($_SESSION['login_otp_message'], $_SESSION['login_otp_message_type']);

// Now include header
$page_title = 'Login with OTP - ' . APP_NAME;
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h4 class="card-title">Login with OTP</h4>
                        <p class="text-muted">Enter your email address and we'll send you a verification code to login.</p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your email address" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Send Verification Code</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-2">Or login with password?</p>
                        <a href="login.php" class="btn btn-outline-secondary">Login with Password</a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="register.php">Don't have an account? Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
