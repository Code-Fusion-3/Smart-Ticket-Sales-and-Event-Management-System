-- Database Updates for Smart Ticket System
-- Run these SQL statements to add new features
-- This file contains only NEW updates not already in the schema

-- 1. Update ticket_verifications table to include 'duplicate' status
ALTER TABLE ticket_verifications MODIFY COLUMN status ENUM('verified', 'rejected', 'duplicate') NOT NULL;

-- 2. Add indexes for better performance (only new ones not in schema)
CREATE INDEX IF NOT EXISTS idx_transactions_user_type ON transactions(user_id, type);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_tickets_user_status ON tickets(user_id, status);
CREATE INDEX IF NOT EXISTS idx_tickets_event_status ON tickets(event_id, status);
CREATE INDEX IF NOT EXISTS idx_events_planner_status ON events(planner_id, status);
CREATE INDEX IF NOT EXISTS idx_events_start_date ON events(start_date);

-- 3. Add notification settings table (NEW - not in schema)
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    push_notifications BOOLEAN DEFAULT TRUE,
    event_reminders BOOLEAN DEFAULT TRUE,
    payment_notifications BOOLEAN DEFAULT TRUE,
    marketing_emails BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
);

-- 4. Add user preferences table (NEW - not in schema)
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    currency VARCHAR(3) DEFAULT 'USD',
    language VARCHAR(5) DEFAULT 'en',
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id)
);

-- 5. Add audit log table for tracking important actions (NEW - not in schema)
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- 6. Insert default notification settings for existing users
INSERT IGNORE INTO notification_settings (user_id)
SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM notification_settings);

-- 7. Insert default user preferences for existing users
INSERT IGNORE INTO user_preferences (user_id)
SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM user_preferences);

-- 8. Update existing transactions to include more details if needed
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_gateway VARCHAR(50) AFTER payment_method;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS gateway_transaction_id VARCHAR(100) AFTER payment_gateway;

-- 9. Add event categories table for better organization (NEW - not in schema)
CREATE TABLE IF NOT EXISTS event_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#3B82F6',
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 10. Add category_id to events table
ALTER TABLE events ADD COLUMN IF NOT EXISTS category_id INT AFTER planner_id;
ALTER TABLE events ADD FOREIGN KEY IF NOT EXISTS (category_id) REFERENCES event_categories(id) ON DELETE SET NULL;

-- 11. Insert default event categories
INSERT IGNORE INTO event_categories (name, description, color, icon) VALUES
('Music', 'Concerts, festivals, and musical performances', '#EF4444', 'fas fa-music'),
('Sports', 'Sports events and competitions', '#10B981', 'fas fa-futbol'),
('Business', 'Conferences, seminars, and business events', '#3B82F6', 'fas fa-briefcase'),
('Education', 'Workshops, training, and educational events', '#8B5CF6', 'fas fa-graduation-cap'),
('Entertainment', 'Movies, shows, and entertainment events', '#F59E0B', 'fas fa-theater-masks'),
('Technology', 'Tech conferences and IT events', '#06B6D4', 'fas fa-microchip'),
('Food & Drink', 'Food festivals, wine tastings, and culinary events', '#84CC16', 'fas fa-utensils'),
('Art & Culture', 'Art exhibitions, museums, and cultural events', '#EC4899', 'fas fa-palette');

-- 12. Add event tags table for flexible categorization (NEW - not in schema)
CREATE TABLE IF NOT EXISTS event_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tag_name (name)
);

-- 13. Add event_tag_relations table for many-to-many relationship (NEW - not in schema)
CREATE TABLE IF NOT EXISTS event_tag_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES event_tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_tag (event_id, tag_id)
);

-- 14. Add ticket transfer functionality (NEW - not in schema)
CREATE TABLE IF NOT EXISTS ticket_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    transfer_reason VARCHAR(255),
    transfer_fee DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_from_user_id (from_user_id),
    INDEX idx_to_user_id (to_user_id),
    INDEX idx_status (status)
);

-- 15. Add event waitlist functionality (NEW - not in schema)
CREATE TABLE IF NOT EXISTS event_waitlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    ticket_type_id INT,
    quantity INT DEFAULT 1,
    status ENUM('waiting', 'notified', 'expired') DEFAULT 'waiting',
    notified_at DATETIME,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_event_ticket (user_id, event_id, ticket_type_id),
    INDEX idx_event_id (event_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- 16. Update system_settings table structure (enhanced version)
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string' AFTER setting_value;
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE AFTER description;

-- 17. Insert additional system settings (only new ones not in schema)
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_description', 'Advanced ticket sales and event management system', 'string', 'Website description', TRUE),
('default_currency', 'USD', 'string', 'Default currency for transactions', TRUE),
('ticket_transfer_fee', '5.00', 'string', 'Fee for transferring tickets between users', TRUE),
('max_tickets_per_user', '10', 'integer', 'Maximum tickets a user can buy per event', TRUE),
('waitlist_expiry_hours', '24', 'integer', 'Hours before waitlist notification expires', FALSE),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', FALSE),
('email_notifications_enabled', 'true', 'boolean', 'Enable email notifications', FALSE),
('sms_notifications_enabled', 'false', 'boolean', 'Enable SMS notifications', FALSE);

-- 18. Add user sessions table for better session management (NEW - not in schema)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);

-- 19. Add API keys table for future API functionality (NEW - not in schema)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at DATETIME,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_api_key (api_key),
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
);

-- 20. Add event analytics table for tracking (NEW - not in schema)
CREATE TABLE IF NOT EXISTS event_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    date DATE NOT NULL,
    page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    tickets_viewed INT DEFAULT 0,
    tickets_sold INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_date (event_id, date),
    INDEX idx_event_id (event_id),
    INDEX idx_date (date)
);

-- 21. Add admin_notes column to withdrawals table for admin comments
ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS admin_notes TEXT AFTER payment_details;

-- Create feedback table
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating TINYINT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Show completion message
SELECT 'Database updates completed successfully!' as status; 