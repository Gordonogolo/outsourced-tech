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

// public/forgot-password.php - Forgot password page with OTP

// DEBUG: Log request start
error_log('[DEBUG forgot-password.php] Request method: ' . $_SERVER['REQUEST_METHOD']);

// Process POST request BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../src/config.php';
    
    // DEBUG: Check session status
    error_log('[DEBUG forgot-password.php] Session ID: ' . session_id());
    error_log('[DEBUG forgot-password.php] Session status: ' . session_status());
    
    $email_or_phone = sanitize($_POST['email_or_phone'] ?? '');
    
    error_log('[DEBUG forgot-password.php] Email/Phone submitted: ' . $email_or_phone);
    
    if (empty($email_or_phone)) {
        // Store message in session for display
        $_SESSION['forgot_password_message'] = 'Please enter your email address';
        $_SESSION['forgot_password_message_type'] = 'danger';
    } else {
        require_once __DIR__ . '/../src/password.php';
        $result = request_password_reset_otp($email_or_phone);
        
        // DEBUG: Log the full result
        error_log('[DEBUG forgot-password.php] Result: ' . print_r($result, true));
        
        // If successful - always redirect to verify page (email is now working)
        if ($result['success'] && isset($result['email'])) {
            // Store email in session for the verify page
            $_SESSION['otp_email'] = $email_or_phone;
            
            // Redirect to verify-otp page
            header('Location: verify-otp.php');
            exit;
        } else {
            // Store error message in session
            $_SESSION['forgot_password_message'] = $result['message'];
            $_SESSION['forgot_password_message_type'] = 'danger';
        }
    }
}

// Normal page load - retrieve any message from session
$message = $_SESSION['forgot_password_message'] ?? '';
$message_type = $_SESSION['forgot_password_message_type'] ?? '';

// Clear the message from session
unset($_SESSION['forgot_password_message'], $_SESSION['forgot_password_message_type']);

// Include config to get APP_NAME constant
require_once __DIR__ . '/../src/config.php';

// Now include header (this outputs HTML)
$page_title = 'Forgot Password - ' . APP_NAME;
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h4 class="card-title">Forgot Password?</h4>
                        <p class="text-muted">Enter your email address and we'll send you a verification code to reset your password.</p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email_or_phone" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email_or_phone" name="email_or_phone" 
                                   placeholder="Enter your email address" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Send Verification Code</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="login.php">Remember your password? Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
