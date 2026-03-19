-- Supabase (PostgreSQL) Database Schema
-- Run this in Supabase SQL Editor

-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    phone VARCHAR(50),
    role VARCHAR(20) DEFAULT 'customer',
    loyalty_points INT DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Categories Table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Products Table
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    sku VARCHAR(100),
    short_description VARCHAR(500),
    description TEXT,
    specifications TEXT,
    price DECIMAL(12,2) NOT NULL,
    compare_price DECIMAL(12,2),
    stock INT DEFAULT 0,
    category_id INT,
    brand VARCHAR(100),
    featured BOOLEAN DEFAULT FALSE,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Product Images Table
CREATE TABLE product_images (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    is_main BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Orders Table
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    subtotal DECIMAL(12,2) NOT NULL,
    tax DECIMAL(12,2) DEFAULT 0,
    delivery_fee DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'pending',
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    delivery_address TEXT,
    delivery_zone_id INT,
    notes TEXT,
    loyalty_points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Order Items Table
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Payments Table
CREATE TABLE payments (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW()
);

-- Services Table
CREATE TABLE services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(12,2),
    duration VARCHAR(50),
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Service Bookings Table
CREATE TABLE service_bookings (
    id SERIAL PRIMARY KEY,
    service_id INT NOT NULL,
    user_id INT,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    booking_date DATE,
    booking_time VARCHAR(20),
    status VARCHAR(50) DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Coupons Table
CREATE TABLE coupons (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type VARCHAR(20),
    discount_value DECIMAL(12,2),
    min_order_amount DECIMAL(12,2),
    max_uses INT,
    used_count INT DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Delivery Zones Table
CREATE TABLE delivery_zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    delivery_fee DECIMAL(12,2) DEFAULT 0,
    free_delivery_threshold DECIMAL(12,2),
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Delivery Tracking Table
CREATE TABLE delivery_tracking (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50),
    location VARCHAR(255),
    estimated_delivery TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Loyalty Tiers Table
CREATE TABLE loyalty_tiers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    min_points INT DEFAULT 0,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Loyalty Transactions Table
CREATE TABLE loyalty_transactions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT,
    points INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    description TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- OTP Verifications Table
CREATE TABLE otp_verifications (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(10) NOT NULL,
    purpose VARCHAR(50) DEFAULT 'password_reset',
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP
);

-- Password Resets Table
CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Product Reviews Table
CREATE TABLE product_reviews (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    rating INT NOT NULL,
    comment TEXT,
    verified_purchase BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Wishlists Table
CREATE TABLE wishlists (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Product Comparisons Table
CREATE TABLE product_comparisons (
    id SERIAL PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(64),
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Recently Viewed Table
CREATE TABLE recently_viewed (
    id SERIAL PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(64),
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Abandoned Carts Table
CREATE TABLE abandoned_carts (
    id SERIAL PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(64),
    cart_data JSONB NOT NULL,
    cart_total DECIMAL(12,2) NOT NULL,
    recovery_email_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Product Interactions Table
CREATE TABLE product_interactions (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    session_id VARCHAR(64),
    interaction_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Product Recommendations Table
CREATE TABLE product_recommendations (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    recommended_product_id INT NOT NULL,
    score DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Similar Products Table
CREATE TABLE similar_products (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    similar_product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Product Search Table
CREATE TABLE product_search (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    search_term VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Newsletter Subscribers Table
CREATE TABLE newsletter_subscribers (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    subscribed BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Chatbot Conversations Table
CREATE TABLE chatbot_conversations (
    id SERIAL PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(64),
    user_message TEXT NOT NULL,
    bot_response TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Admin Users Table
CREATE TABLE admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    role VARCHAR(50) DEFAULT 'admin',
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Inventory Alerts Table
CREATE TABLE inventory_alerts (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    alert_type VARCHAR(50),
    previous_stock INT,
    current_stock INT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Refunds Table
CREATE TABLE refunds (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW()
);

-- SMS Log Table
CREATE TABLE sms_log (
    id SERIAL PRIMARY KEY,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW()
);

-- Insert sample data
INSERT INTO categories (name, slug, description, display_order) VALUES 
('Laptops', 'laptops', 'Premium laptops and notebooks', 1),
('Phones', 'phones', 'Smartphones and tablets', 2),
('Networking', 'networking', 'Routers, modems and network equipment', 3),
('Accessories', 'accessories', 'Computer accessories and peripherals', 4),
('Storage', 'storage', 'Hard drives, SSDs and memory cards', 5),
('Printers', 'printers', 'Printers and scanning devices', 6);

INSERT INTO loyalty_tiers (name, min_points, discount_percent) VALUES
('Bronze', 0, 0),
('Silver', 500, 5),
('Gold', 1500, 10),
('Platinum', 5000, 15);

INSERT INTO delivery_zones (name, description, delivery_fee, free_delivery_threshold) VALUES
('Mlolongo Area', 'Within Mlolongo town', 0, 5000),
('Athi River', 'Athi River and nearby areas', 300, 8000),
('Nairobi East', 'East Nairobi areas', 500, 10000),
('Other Areas', 'Other locations', 1000, 20000);

INSERT INTO admin_users (username, password_hash, email, role) VALUES
('admin', '$2y$10$abcdefghijklmnopqrstuv', 'admin@outsourcedtechnologies.co.ke', 'super_admin');
