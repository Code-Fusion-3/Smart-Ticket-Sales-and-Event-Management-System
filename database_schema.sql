-- Create database
CREATE DATABASE IF NOT EXISTS ticket_management_system;
USE ticket_management_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    role ENUM('admin', 'event_planner', 'customer', 'agent') NOT NULL,
    profile_image VARCHAR(255),
    balance DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone_number),
    INDEX idx_role (role)
);

-- Events table
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planner_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    venue VARCHAR(100),
    address VARCHAR(255),
    city VARCHAR(50),
    country VARCHAR(50),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_tickets INT NOT NULL,
    available_tickets INT NOT NULL,
    ticket_price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    status ENUM('active', 'completed', 'canceled', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (planner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_planner (planner_id),
    INDEX idx_start_date (start_date),
    INDEX idx_status (status)
);

-- Tickets table
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    recipient_name VARCHAR(100),
    recipient_email VARCHAR(100),
    recipient_phone VARCHAR(20),
    qr_code VARCHAR(255),
    purchase_price DECIMAL(10, 2) NOT NULL,
    status ENUM('available', 'sold', 'used', 'reselling', 'resold') DEFAULT 'sold',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_event (event_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('deposit', 'withdrawal', 'purchase', 'sale', 'resale', 'system_fee') NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    reference_id VARCHAR(100),
    payment_method ENUM('credit_card', 'mobile_money', 'airtel_money', 'balance'),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status)
);

-- Withdrawals table
CREATE TABLE withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    fee DECIMAL(10, 2) NOT NULL,
    net_amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_details TEXT,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- Deposits table
CREATE TABLE deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference_number VARCHAR(100),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- Cart table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- Cart Items table
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    event_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    recipient_name VARCHAR(100),
    recipient_email VARCHAR(100),
    recipient_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES cart(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_cart (cart_id)
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'confirmed', 'canceled') DEFAULT 'pending',
    expiry_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_event (event_id),
    INDEX idx_status (status)
);

-- Booking Items table
CREATE TABLE booking_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    recipient_name VARCHAR(100),
    recipient_email VARCHAR(100),
    recipient_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id)
);

-- Ticket Resales table
CREATE TABLE IF NOT EXISTS ticket_resales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_id INT NULL,
    resale_price DECIMAL(10, 2) NOT NULL,
    platform_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    seller_earnings DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    description TEXT,
    status ENUM('active', 'sold', 'canceled', 'expired') DEFAULT 'active',
    listed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sold_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_seller (seller_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_status (status),
    INDEX idx_listed_at (listed_at)
);

-- Ticket Verifications table
CREATE TABLE ticket_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    agent_id INT NOT NULL,
    verification_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('verified', 'rejected') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_agent (agent_id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('event_reminder', 'payment', 'ticket', 'system') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_is_read (is_read)
);

-- Email Logs table
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed','pending') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SMS Logs table
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed','pending') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System Settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- System Fees table
CREATE TABLE system_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_type ENUM('ticket_sale', 'withdrawal', 'resale') NOT NULL UNIQUE,
    percentage DECIMAL(5, 2) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password_hash, phone_number, role, status)
VALUES ('admin', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1234567890', 'admin', 'active');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description)
VALUES 
('site_name', 'Smart Ticket System', 'Name of the website'),
('site_email', 'contact@smartticket.com', 'Contact email for the website'),
('ticket_expiry_hours', '2', 'Number of hours before a booking expires');

-- Insert default system fees
INSERT INTO system_fees (fee_type, percentage)
VALUES 
('ticket_sale', 5.00),
('withdrawal', 2.50),
('resale', 3.00);


-- new tables
-- Ticket Types table
CREATE TABLE ticket_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    total_tickets INT NOT NULL,
    available_tickets INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id)
);
-- Update Tickets table to reference ticket_types
ALTER TABLE tickets 
ADD COLUMN ticket_type_id INT AFTER event_id,
ADD FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id);

-- new sql
ALTER TABLE cart_items 
ADD COLUMN ticket_type_id INT AFTER event_id,
ADD FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id);

CREATE TABLE agent_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT,
    ticket_id INT,
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('valid', 'invalid', 'duplicate')
);

-- Update system_settings table to ensure it has an ID field
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- Insert additional useful system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('max_login_attempts', '5', 'Maximum number of failed login attempts before account lockout'),
('session_timeout', '60', 'Session timeout in minutes'),
('password_min_length', '8', 'Minimum password length requirement'),
('require_strong_password', '1', 'Require strong passwords with mixed case, numbers, and symbols'),
('enable_two_factor', '0', 'Enable two-factor authentication system-wide'),
('email_enabled', '1', 'Enable email notifications'),
('sms_enabled', '0', 'Enable SMS notifications'),
('smtp_host', '', 'SMTP server hostname'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username'),
('smtp_password', '', 'SMTP password'),
('sms_api_key', '', 'SMS service API key'),
('sms_api_secret', '', 'SMS service API secret'),
('maintenance_mode', '0', 'Enable maintenance mode'),
('registration_enabled', '1', 'Allow new user registrations'),
('max_file_upload_size', '5', 'Maximum file upload size in MB'),
('allowed_image_types', 'jpg,jpeg,png,gif', 'Allowed image file extensions'),
('timezone', 'Africa/Kigali', 'System timezone'),
('currency_symbol', 'Rwf', 'Currency symbol'),
('date_format', 'Y-m-d', 'System date format'),
('time_format', 'H:i', 'System time format');

-- Create a settings cache table for better performance
CREATE TABLE IF NOT EXISTS settings_cache (
    cache_key VARCHAR(100) PRIMARY KEY,
    cache_value TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
