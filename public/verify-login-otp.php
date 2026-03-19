<?php
// public/verify-login-otp.php - OTP Verification page for login
ob_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/password.php';

// Check if email is in session BEFORE any HTML output
if (!isset($_SESSION['login_otp_email'])) {
    header('Location: login-otp.php');
    exit;
}

$email = $_SESSION['login_otp_email'];

$page_title = 'Verify OTP - ' . APP_NAME;
require_once __DIR__ . '/../templates/header.php';
$message = '';
$message_type = '';

// Check for resend request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    require_once __DIR__ . '/../src/password.php';
    
    // Generate new OTP
    $otp = generate_otp(6);
    store_otp($email, $otp, 10, 'login_verification');
    $email_result = send_otp_email($email, $otp, 'login_verification');
    
    $message = 'A new verification code has been sent to your email.';
    $message_type = 'success';
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = sanitize($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $message = 'Please enter the verification code';
        $message_type = 'danger';
    } else {
        require_once __DIR__ . '/../src/password.php';
        
        // Verify OTP
        $verify_result = verify_otp($email, $otp, 'login_verification', 3);
        
        if (!$verify_result['success']) {
            $message = $verify_result['message'];
            $message_type = 'danger';
        } else {
            // OTP verified - log the user in
            $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
            
            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['logged_in'] = true;
                
                // Clear OTP session
                unset($_SESSION['login_otp_email']);
                
                // Redirect to home or intended page
                $redirect = $_SESSION['login_redirect'] ?? 'index.php';
                unset($_SESSION['login_redirect']);
                
                header("Location: $redirect");
                exit;
            } else {
                $message = 'User not found. Please try again.';
                $message_type = 'danger';
            }
        }
    }
}

// Get masked email for display
$masked_email = mask_email($email);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h4 class="card-title">Verify Your Identity</h4>
                        <p class="text-muted">Enter the 6-digit code sent to <strong><?= htmlspecialchars($masked_email) ?></strong></p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="otp" class="form-label">Verification Code</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" 
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                                   required autocomplete="one-time-code" style="letter-spacing: 8px; font-size: 24px;">
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-2">Didn't receive the code?</p>
                        <a href="?resend=1" class="btn btn-outline-secondary btn-sm">Resend Code</a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="login-otp.php">Use a different email</a>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-focus on OTP input and handle input formatting
document.addEventListener('DOMContentLoaded', function() {
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.focus();
        
        // Only allow numbers
        otpInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
