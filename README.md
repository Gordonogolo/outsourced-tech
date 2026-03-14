# OUTSOURCED-TECHNOLOGIES- Complete E-Commerce Platform

A modern, full-featured e-commerce platform for selling tech products (hubs, switches, routers, computers, phones) and offering services (diagnostics, ISP services, repairs) with integrated M-Pesa payments, loyalty rewards, customer support chatbot, and system monitoring.

## Features

### Customer-Facing Features
- **Product Catalog**: Browse networking hardware, computers, and phones
- **Services Booking**: Schedule diagnostics, repairs, and ISP services
- **M-Pesa Integration**: Secure payment via M-Pesa STK Push
- **Flexible Delivery**: Free delivery (0-5km), paid delivery for extended areas, or store pickup
- **Loyalty Rewards**: Earn points and unlock exclusive badges (Bronze, Silver, Gold, Platinum, Diamond)
- **Customer Support Chatbot**: Real-time assistance
- **Shopping Cart**: Add products/services and manage orders
- **User Accounts**: Registration, login, order history, and loyalty tracking

### Admin Panel Features
- **Dashboard**: Overview of sales, orders, products, and users
- **Product Management**: Add, edit, and manage inventory
- **Service Management**: Manage service offerings and bookings
- **Order Management**: Track and update order statuses
- **User Management**: View customer data and loyalty points
- **Category Management**: Organize products and services
- **System Monitoring**: Health checks, logs, and status dashboard

### System Features (For 6-Month Stability)
- **Health Monitoring**: Real-time system health checks via API
- **Automated Backups**: Daily database backups
- **Scheduled Maintenance**: Automated cleanup and notifications
- **Activity Logging**: Track user actions and system events
- **Rate Limiting**: API protection against abuse
- **Email Notifications**: SMTP support for transactional emails

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 7.4+
- **Database**: PostgreSQL (via Supabase)
- **Payment Gateway**: M-Pesa Daraja API
- **Styling**: Custom CSS with modern design system

## New Features Added

### System Monitoring
- **Health Check Endpoint**: `/api/health.php` - Returns system status
- **Admin Dashboard**: `/admin/system-status.php` - Visual monitoring
- **Log Viewer**: `/admin/logs.php` - View error and activity logs

### Automated Tasks
- **Backup Script**: `/api/backup.php` - Automated database backups
- **Cron Jobs**: `/api/cron.php` - Scheduled maintenance tasks

### Security & Performance
- **Rate Limiting**: `/src/ratelimit.php` - API request limiting
- **Activity Logging**: `/src/activity.php` - User action tracking
- **Email System**: `/src/email.php` - SMTP email support

## Database Schema

The platform uses Supabase PostgreSQL with the following tables:
- `users` - Customer accounts with loyalty tracking
- `categories` - Product and service categories
- `products` - Tech products inventory
- `services` - Service offerings
- `orders` - Customer orders
- `order_items` - Order line items
- `service_bookings` - Service appointments
- `delivery_zones` - Delivery pricing zones
- `loyalty_tiers` - Reward badge levels
- `payments` - Payment transactions
- `admin_users` - Admin accounts
- `chatbot_conversations` - Chat history
- `activity_logs` - User activity tracking (NEW)

## Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- PostgreSQL database (Supabase account)
- M-Pesa Daraja API credentials
- Web server (Apache/Nginx)

### Step 1: Database Setup
The database schema is already created in Supabase. Configure the connection:

1. Get your Supabase credentials from the Supabase dashboard
2. Update `api/config.php` with your database credentials

### Step 2: Configure Environment Variables
Create a `.env` file or set the following environment variables:

```bash
# Supabase Configuration
SUPABASE_URL=your_supabase_url
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key
SUPABASE_DB_URL=postgresql://user:password@host:port/database

# M-Pesa Configuration
MPESA_CONSUMER_KEY=your_mpesa_consumer_key
MPESA_CONSUMER_SECRET=your_mpesa_consumer_secret
MPESA_SHORTCODE=your_mpesa_shortcode
MPESA_PASSKEY=your_mpesa_passkey
MPESA_CALLBACK_URL=https://yourdomain.com/api/mpesa.php
MPESA_ENVIRONMENT=sandbox  # or 'production'

# Email Configuration (SMTP)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Cron Security Keys
CRON_SECRET_KEY=your-secret-cron-key
BACKUP_SECRET_KEY=your-backup-key
```

### Step 3: Update Configuration Files
1. **Update `js/config.js`**:
```javascript
const CONFIG = {
    SUPABASE_URL: 'your_supabase_url',
    SUPABASE_ANON_KEY: 'your_anon_key',
    API_BASE_URL: '/api'
};
```

2. **Update `api/config.php`**:
Replace the placeholder values with your actual credentials.

### Step 4: Create Admin Account
Run this SQL in your Supabase SQL editor to create the first admin account:

```sql
INSERT INTO admin_users (username, email, password_hash, role, is_active)
VALUES (
    'admin',
    'admin@techhubpro.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: 'password'
    'super_admin',
    true
);
```

**Important**: Change this password immediately after first login!

### Step 5: Run Additional SQL Setup
Run the SQL from `database/activity_logs.sql` to create the activity logging table:

```sql
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_action (action),
    INDEX idx_user_id (user_id)
);
```

### Step 6: M-Pesa Setup
1. Register for M-Pesa Daraja API at https://developer.safaricom.co.ke/
2. Create an app and get your credentials
3. For testing, use the sandbox environment
4. Configure the callback URL to point to your `api/mpesa.php` endpoint
5. Ensure your server is accessible via HTTPS (M-Pesa requires SSL)

### Step 7: Deploy
1. Upload all files to your web server
2. Ensure PHP has write permissions for session files
3. Configure your web server to serve `index.html` as the default page
4. Set up SSL certificate (required for M-Pesa)
5. Test the application

## System Monitoring

### Health Check
Access the health check endpoint:
```
GET /api/health.php
```

Returns JSON with:
- PHP version
- Disk space
- PHP extensions status
- Log directory status
- Database connection status

### Admin System Status
Access the monitoring dashboard:
```
/admin/system-status.php
```

### Log Viewer
Access system logs:
```
/admin/logs.php
```

## Cron Jobs Setup

### Automated Maintenance
Configure these cron jobs on your server:

```bash
# Run every 5 minutes for maintenance tasks
*/5 * * * * curl -s https://yourdomain.com/api/cron.php?key=YOUR_CRON_KEY

# Run daily at midnight for backup
0 0 * * * curl -s https://yourdomain.com/api/backup.php?key=YOUR_BACKUP_KEY
```

### Cron Tasks Include:
- Clean old chatbot conversations (30+ days)
- Low stock product notifications
- Cancel unpaid orders (24+ hours)
- Clean expired password reset tokens
- Clean old log files
- Check stale pending payments

## M-Pesa Payment Flow

1. Customer selects items and proceeds to checkout
2. Customer enters M-Pesa phone number
3. System initiates STK Push via Daraja API
4. Customer receives payment prompt on their phone
5. Customer enters M-Pesa PIN to complete payment
6. System receives callback and updates order status
7. Order is confirmed and loyalty points are awarded

## Loyalty Program

- **Bronze**: 0+ points (0% discount)
- **Silver**: 500+ points (5% discount)
- **Gold**: 1500+ points (10% discount)
- **Platinum**: 3000+ points (15% discount)
- **Diamond**: 5000+ points (20% discount)

Customers earn 1 point for every KES 100 spent.

## Delivery Zones

- **City Center (0-5km)**: Free delivery
- **Suburban (5-15km)**: KES 200
- **Extended Area (15-30km)**: KES 500
- **Far Areas (30km+)**: KES 1000
- **Store Pickup**: Free

## Security Features

- Password hashing with bcrypt
- SQL injection prevention via prepared statements
- XSS protection through input sanitization
- CORS headers for API security
- Row Level Security (RLS) on database
- Session management for admin panel
- HTTPS required for M-Pesa integration
- API rate limiting
- Activity logging for audit trails

## Troubleshooting

### Database Connection Issues
- Verify Supabase credentials in `api/config.php`
- Check that database URL is correctly formatted
- Ensure SSL mode is enabled for Supabase connection

### M-Pesa Integration Issues
- Verify credentials in environment variables
- Check callback URL is publicly accessible
- Ensure SSL certificate is valid
- Test with sandbox environment first
- Check M-Pesa API logs in Daraja portal

### Admin Login Issues
- Verify admin account exists in database
- Check password hash is correct
- Clear browser cookies and try again

### System Monitoring Issues
- Check logs directory exists and is writable
- Verify cron jobs are configured
- Check PHP extensions are installed

## Support & Maintenance

### Regular Maintenance Tasks
1. Monitor order status and update as needed
2. Restock products when inventory is low
3. Respond to service bookings promptly
4. Review and respond to chatbot conversations
5. Update loyalty tiers as business grows
6. Monitor M-Pesa transactions and reconcile payments
7. Check system health regularly via /api/health.php
8. Review system logs in /admin/logs.php

### Backup
- Automated backups run daily via cron job
- Manual backups can be triggered via /api/backup.php
- Supabase provides automatic backups

## Production Deployment Checklist

- [ ] Change default admin password
- [ ] Update all placeholder credentials
- [ ] Configure environment variables
- [ ] Set up SSL certificate
- [ ] Test M-Pesa integration end-to-end
- [ ] Add real product images and descriptions
- [ ] Configure email notifications (SMTP)
- [ ] Set up monitoring and error tracking
- [ ] Configure cron jobs for maintenance
- [ ] Test all user flows
- [ ] Backup database
- [ ] Update contact information
- [ ] Test on multiple devices and browsers

## License

This project is proprietary software for TechHub Pro.

## Contact
