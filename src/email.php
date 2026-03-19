<?php
// src/email.php - Enhanced Email System with SMTP Support

// Ensure config is loaded first
require_once __DIR__ . '/config.php';

/**
 * Email configuration
 * Note: Uses $_ENV (set by Dotenv) instead of getenv() for reliability
 */
function get_email_config() {
    // Helper function to get env var from $_ENV (set by Dotenv) or getenv as fallback
    $get_env = function($key, $default = '') {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    };
    
    $config = [
        'driver' => $get_env('MAIL_DRIVER', 'smtp'),
        'host' => $get_env('MAIL_HOST', 'smtp.gmail.com'),
        'port' => (int)$get_env('MAIL_PORT', 587),
        'username' => $get_env('MAIL_USERNAME', ''),
        'password' => $get_env('MAIL_PASSWORD', ''),
        'encryption' => $get_env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => $get_env('MAIL_FROM_ADDRESS', 'noreply@outsourcedtechnologies.co.ke'),
        'from_name' => $get_env('MAIL_FROM_NAME', 'Outsourced Technologies'),
    ];
    
    // Debug: Log the configuration (mask password)
    $debug_config = $config;
    $debug_config['password'] = $config['password'] ? '****' : '(empty)';
    @file_put_contents(
        __DIR__ . '/../logs/email_debug.log',
        "[" . date('Y-m-d H:i:s') . "] get_email_config: " . json_encode($debug_config) . "\n",
        FILE_APPEND
    );
    
    return $config;
}

/**
 * Send an email - uses mail() function (SMTP not available without PHPMailer)
 */
function send_email($to, $subject, $body, $is_html = true, $attachments = []) {
    $config = get_email_config();
    
    // Log email attempt
    $log_entry = "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject\n";
    @file_put_contents(__DIR__ . '/../logs/emails.log', $log_entry, FILE_APPEND);
    
    // Use mail() function - works without PHPMailer
    return send_email_phpmail($to, $subject, $body, $is_html, $config);
}

/**
 * Send email using PHP mail() function
 * For XAMPP: Configure sendmail in php.ini or use SMTP ini settings
 */
function send_email_phpmail($to, $subject, $body, $is_html, $config) {
    // For local development (XAMPP), try to use Gmail SMTP via ini settings
    if (getenv('APP_ENV') === 'development') {
        ini_set('SMTP', 'smtp.gmail.com');
        ini_set('smtp_port', 587);
        ini_set('sendmail_from', $config['from_address']);
    }
    
    $headers = "From: {$config['from_name']} <{$config['from_address']}>\r\n";
    $headers .= "Reply-To: {$config['from_address']}\r\n";
    
    if ($is_html) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $body, $headers);
}

/**
 * Send email using SMTP (falls back to mail() if PHPMailer not available)
 */
function send_email_smtp($to, $subject, $body, $is_html, $config, $attachments = []) {
    // Check if PHPMailer is available via Composer autoload
    $vendor_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendor_path)) {
        // Fall back to mail()
        @file_put_contents(
            __DIR__ . '/../logs/email_debug.log',
            "[" . date('Y-m-d H:i:s') . "] PHPMailer not found, using mail() fallback\n",
            FILE_APPEND
        );
        return send_email_phpmail($to, $subject, $body, $is_html, $config);
    }
    
    // Use Composer's autoload
    require_once $vendor_path;
    
    // Check for PHPMailer class
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        @file_put_contents(
            __DIR__ . '/../logs/email_debug.log',
            "[" . date('Y-m-d H:i:s') . "] PHPMailer class not found, using mail() fallback\n",
            FILE_APPEND
        );
        return send_email_phpmail($to, $subject, $body, $is_html, $config);
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['from_address'], $config['from_name']);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        // Attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
            }
        }
        
        $mail->send();
        return true;
    } catch (\Exception $e) {
        // Log error
        $error_msg = "[" . date('Y-m-d H:i:s') . "] Error: {$mail->ErrorInfo}\n";
        @file_put_contents(
            __DIR__ . '/../logs/email_errors.log',
            $error_msg,
            FILE_APPEND
        );
        @file_put_contents(
            __DIR__ . '/../logs/email_debug.log',
            "[" . date('Y-m-d H:i:s') . "] PHPMailer Exception: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        return false;
    }
}

/**
 * Send order confirmation email
 */
function send_order_confirmation($order, $user, $items) {
    $subject = "Order Confirmation - " . $order['order_number'];
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .total { font-weight: bold; font-size: 18px; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Outsourced Technologies</h1>
                <p>Order Confirmation</p>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user['full_name'] ?? $user['username']) . ",</p>
                <p>Thank you for your order! Here are your order details:</p>
                
                <p><strong>Order Number:</strong> " . htmlspecialchars($order['order_number']) . "</p>
                <p><strong>Date:</strong> " . date('F d, Y', strtotime($order['created_at'])) . "</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
    ";
    
    foreach ($items as $item) {
        $html .= "
                        <tr>
                            <td>" . htmlspecialchars($item['product_name'] ?? $item['name']) . "</td>
                            <td>" . $item['quantity'] . "</td>
                            <td>KSh " . number_format($item['price']) . "</td>
                            <td>KSh " . number_format($item['price'] * $item['quantity']) . "</td>
                        </tr>
        ";
    }
    
    $html .= "
                    </tbody>
                </table>
                
                <p class='total'>Total: KSh " . number_format($order['total_amount']) . "</p>
                
                <p><strong>Delivery:</strong> " . ucfirst($order['delivery_type'] ?? 'delivery') . "</p>
    ";
    
    if (($order['delivery_type'] ?? 'delivery') == 'delivery' && !empty($order['delivery_address'])) {
        $html .= "<p><strong>Address:</strong> " . htmlspecialchars($order['delivery_address']) . "</p>";
    }
    
    $html .= "
                <p>We'll notify you when your order status changes.</p>
                <p>Track your order: <a href='https://outsourcedtechnologies.co.ke/orders.php'>View Orders</a></p>
            </div>
            <div class='footer'>
                <p>Outsourced Technologies - Mlolongo, Kenya</p>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return send_email($user['email'], $subject, $html, true);
}

/**
 * Send order status update email
 */
function send_order_status_update($order, $user) {
    $status_messages = [
        'pending' => 'Your order is being reviewed',
        'processing' => 'Your order is being prepared',
        'ready_for_delivery' => 'Your order is ready for delivery',
        'shipped' => 'Your order has been shipped',
        'delivered' => 'Your order has been delivered',
        'cancelled' => 'Your order has been cancelled'
    ];
    
    $subject = "Order Update - " . $order['order_number'];
    $status_message = $status_messages[$order['status']] ?? 'Your order status has been updated';
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .status { font-size: 18px; font-weight: bold; color: #0d6efd; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Outsourced Technologies</h1>
                <p>Order Status Update</p>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user['full_name'] ?? $user['username']) . ",</p>
                <p class='status'>" . $status_message . "</p>
                
                <p><strong>Order Number:</strong> " . htmlspecialchars($order['order_number']) . "</p>
                <p><strong>Status:</strong> " . ucfirst($order['status']) . "</p>
                
                <p>Track your order: <a href='https://outsourcedtechnologies.co.ke/orders.php'>View Orders</a></p>
            </div>
            <div class='footer'>
                <p>Outsourced Technologies</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return send_email($user['email'], $subject, $html, true);
}

/**
 * Send booking confirmation email
 */
function send_booking_confirmation($booking, $user, $service) {
    $subject = "Service Booking Confirmed - " . $service['name'];
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #198754; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Outsourced Technologies</h1>
                <p>Service Booking Confirmed</p>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Customer') . ",</p>
                <p>Your service booking has been confirmed!</p>
                
                <p><strong>Service:</strong> " . htmlspecialchars($service['name']) . "</p>
                <p><strong>Date:</strong> " . date('F d, Y', strtotime($booking['booking_date'])) . "</p>
                <p><strong>Time:</strong> " . date('h:i A', strtotime($booking['booking_time'])) . "</p>
                <p><strong>Price:</strong> KSh " . number_format($service['price']) . "</p>
    ";
    
    if (!empty($booking['notes'])) {
        $html .= "<p><strong>Notes:</strong> " . htmlspecialchars($booking['notes']) . "</p>";
    }
    
    $html .= "
                <p>We'll contact you if we need any additional information.</p>
            </div>
            <div class='footer'>
                <p>Outsourced Technologies - Mlolongo, Kenya</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $to = $user['email'] ?? '';
    if (empty($to)) {
        return false;
    }
    
    return send_email($to, $subject, $html, true);
}

/**
 * Send password reset email
 */
function send_password_reset($user, $reset_token) {
    $subject = "Password Reset - Outsourced Technologies";
    $reset_link = "https://outsourcedtechnologies.co.ke/reset-password.php?token=" . $reset_token;
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .btn { display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Outsourced Technologies</h1>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user['full_name'] ?? $user['username']) . ",</p>
                <p>We received a request to reset your password. Click the button below to reset it:</p>
                <p><a href='$reset_link' class='btn'>Reset Password</a></p>
                <p>Or copy this link: $reset_link</p>
                <p><strong>This link expires in 1 hour.</strong></p>
                <p>If you didn't request this, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>Outsourced Technologies</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return send_email($user['email'], $subject, $html, true);
}

/**
 * Send welcome email to new users
 */
function send_welcome_email($user) {
    $subject = "Welcome to Outsourced Technologies";
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to Outsourced Technologies!</h1>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($user['full_name'] ?? $user['username']) . ",</p>
                <p>Thank you for joining us! We're excited to have you as a customer.</p>
                <p>With your account you can:</p>
                <ul>
                    <li>Browse and purchase products</li>
                    <li>Book service appointments</li>
                    <li>Track your orders</li>
                    <li>Earn loyalty points</li>
                </ul>
                <p><a href='https://outsourcedtechnologies.co.ke' class='btn'>Start Shopping</a></p>
            </div>
            <div class='footer'>
                <p>Outsourced Technologies - Mlolongo, Kenya</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return send_email($user['email'], $subject, $html, true);
}

/**
 * Send OTP (One-Time Password) email
 * @param string $email Recipient email address
 * @param string $otp The 6-digit OTP code
 * @param string $purpose Purpose of OTP (default: 'password_reset')
 * @return array Result with success status and message
 */
function send_otp_email($email, $otp, $purpose = 'password_reset') {
    $config = get_email_config();
    
    $subject = "Your Verification Code - Outsourced Technologies";
    
    $purpose_text = match($purpose) {
        'password_reset' => 'Password Reset',
        'email_verification' => 'Email Verification',
        'login_verification' => 'Login Verification',
        default => 'Verification',
    };
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #198754; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .otp-code { 
                font-size: 32px; 
                font-weight: bold; 
                letter-spacing: 8px; 
                text-align: center; 
                padding: 20px; 
                background: #fff; 
                border: 2px dashed #198754; 
                border-radius: 10px;
                margin: 20px 0;
            }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .warning { color: #dc3545; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Outsourced Technologies</h1>
                <p>$purpose_text</p>
            </div>
            <div class='content'>
                <p>Dear Customer,</p>
                <p>Your verification code is:</p>
                <div class='otp-code'>$otp</div>
                <p><strong>This code will expire in 10 minutes.</strong></p>
                <p class='warning'>If you didn't request this code, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>Outsourced Technologies - Mlolongo, Kenya</p>
                <p>For security reasons, never share this code with anyone.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Log email attempt
    $log_entry = "[" . date('Y-m-d H:i:s') . "] OTP to: $email | Purpose: $purpose\n";
    @file_put_contents(__DIR__ . '/../logs/emails.log', $log_entry, FILE_APPEND);
    
    // Debug: Log the full send_otp_email call
    @file_put_contents(
        __DIR__ . '/../logs/email_debug.log',
        "[" . date('Y-m-d H:i:s') . "] send_otp_email called - Email: $email, Purpose: $purpose, OTP: $otp\n",
        FILE_APPEND
    );
    
    // Use SMTP (PHPMailer) for sending emails
    $result = send_email_smtp($email, $subject, $html, true, $config);
    
    // Debug: Log the result
    @file_put_contents(
        __DIR__ . '/../logs/email_debug.log',
        "[" . date('Y-m-d H:i:s') . "] send_otp_email result - Success: " . ($result ? 'true' : 'false') . "\n",
        FILE_APPEND
    );
    
    if ($result) {
        return ['success' => true, 'message' => 'OTP sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to send OTP email'];
    }
}
