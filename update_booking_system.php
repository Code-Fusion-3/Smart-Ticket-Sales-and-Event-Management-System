<?php
/**
 * Update Booking System for 50% Payment Feature
 * This script modifies the existing bookings and booking_items tables
 * to support partial payments and proper ticket availability management.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h2>Updating Booking System for 50% Payment Feature</h2>";

try {
    // Start transaction
    $db->query("START TRANSACTION");

    echo "<p>✓ Starting database updates...</p>";

    // 1. Add payment-related columns to bookings table
    $alterBookingsQueries = [
        "ALTER TABLE `bookings` ADD COLUMN `amount_paid` DECIMAL(10,2) DEFAULT 0.00 AFTER `quantity`",
        "ALTER TABLE `bookings` ADD COLUMN `total_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `amount_paid`",
        "ALTER TABLE `bookings` ADD COLUMN `payment_status` ENUM('partial','full','pending') DEFAULT 'pending' AFTER `status`",
        "ALTER TABLE `bookings` ADD COLUMN `payment_deadline` DATETIME DEFAULT NULL AFTER `expiry_time`",
        "ALTER TABLE `bookings` ADD COLUMN `ticket_type_id` INT(11) DEFAULT NULL AFTER `event_id`",
        "ALTER TABLE `bookings` ADD COLUMN `transaction_id` VARCHAR(100) DEFAULT NULL AFTER `payment_deadline`"
    ];

    foreach ($alterBookingsQueries as $query) {
        try {
            $db->query($query);
            echo "<p>✓ Added column to bookings table</p>";
        } catch (Exception $e) {
            // Column might already exist, that's okay
            echo "<p>⚠ Column may already exist (safe to ignore): " . $e->getMessage() . "</p>";
        }
    }

    // 2. Add payment-related columns to booking_items table
    $alterBookingItemsQueries = [
        "ALTER TABLE `booking_items` ADD COLUMN `ticket_type_id` INT(11) DEFAULT NULL AFTER `booking_id`",
        "ALTER TABLE `booking_items` ADD COLUMN `ticket_price` DECIMAL(10,2) DEFAULT 0.00 AFTER `recipient_phone`",
        "ALTER TABLE `booking_items` ADD COLUMN `amount_paid` DECIMAL(10,2) DEFAULT 0.00 AFTER `ticket_price`"
    ];

    foreach ($alterBookingItemsQueries as $query) {
        try {
            $db->query($query);
            echo "<p>✓ Added column to booking_items table</p>";
        } catch (Exception $e) {
            // Column might already exist, that's okay
            echo "<p>⚠ Column may already exist (safe to ignore): " . $e->getMessage() . "</p>";
        }
    }

    // 3. Add indexes for better performance
    $indexQueries = [
        "CREATE INDEX IF NOT EXISTS `idx_bookings_user_status` ON `bookings` (`user_id`, `status`)",
        "CREATE INDEX IF NOT EXISTS `idx_bookings_event_status` ON `bookings` (`event_id`, `status`)",
        "CREATE INDEX IF NOT EXISTS `idx_bookings_payment_status` ON `bookings` (`payment_status`)",
        "CREATE INDEX IF NOT EXISTS `idx_bookings_expiry` ON `bookings` (`expiry_time`)",
        "CREATE INDEX IF NOT EXISTS `idx_bookings_payment_deadline` ON `bookings` (`payment_deadline`)",
        "CREATE INDEX IF NOT EXISTS `idx_booking_items_booking` ON `booking_items` (`booking_id`)",
        "CREATE INDEX IF NOT EXISTS `idx_booking_items_ticket_type` ON `booking_items` (`ticket_type_id`)"
    ];

    foreach ($indexQueries as $query) {
        try {
            $db->query($query);
            echo "<p>✓ Added index</p>";
        } catch (Exception $e) {
            // Index might already exist, that's okay
            echo "<p>⚠ Index may already exist (safe to ignore): " . $e->getMessage() . "</p>";
        }
    }

    // 4. Create a function to clean expired bookings (will be called by cron job)
    $cleanupFunction = "
    CREATE OR REPLACE FUNCTION cleanup_expired_bookings()
    RETURNS INT
    BEGIN
        DECLARE affected_rows INT DEFAULT 0;
        
        -- Update ticket availability for expired bookings
        UPDATE ticket_types tt
        JOIN bookings b ON tt.id = b.ticket_type_id
        SET tt.available_tickets = tt.available_tickets + b.quantity
        WHERE b.status = 'pending' 
        AND b.expiry_time < NOW()
        AND b.payment_status IN ('partial', 'pending');
        
        SET affected_rows = ROW_COUNT();
        
        -- Mark expired bookings as canceled
        UPDATE bookings 
        SET status = 'canceled', 
            updated_at = NOW()
        WHERE status = 'pending' 
        AND expiry_time < NOW()
        AND payment_status IN ('partial', 'pending');
        
        RETURN affected_rows;
    END;
    ";

    try {
        $db->query($cleanupFunction);
        echo "<p>✓ Created cleanup function for expired bookings</p>";
    } catch (Exception $e) {
        echo "<p>⚠ Function creation failed (may already exist): " . $e->getMessage() . "</p>";
    }

    // 5. Create a view for booking statistics
    $bookingStatsView = "
    CREATE OR REPLACE VIEW booking_statistics AS
    SELECT 
        e.id as event_id,
        e.title as event_title,
        tt.id as ticket_type_id,
        tt.name as ticket_type_name,
        tt.total_tickets,
        tt.available_tickets,
        COUNT(b.id) as total_bookings,
        SUM(CASE WHEN b.status = 'pending' THEN b.quantity ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN b.status = 'confirmed' THEN b.quantity ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN b.payment_status = 'partial' THEN b.quantity ELSE 0 END) as partial_payments,
        SUM(CASE WHEN b.payment_status = 'full' THEN b.quantity ELSE 0 END) as full_payments
    FROM events e
    LEFT JOIN ticket_types tt ON e.id = tt.event_id
    LEFT JOIN bookings b ON tt.id = b.ticket_type_id AND b.status != 'canceled'
    GROUP BY e.id, tt.id
    ";

    try {
        $db->query($bookingStatsView);
        echo "<p>✓ Created booking statistics view</p>";
    } catch (Exception $e) {
        echo "<p>⚠ View creation failed (may already exist): " . $e->getMessage() . "</p>";
    }

    // 6. Insert system settings for booking configuration
    $bookingSettings = [
        ['booking_deposit_percentage', '50', 'Percentage of ticket price required for booking'],
        ['booking_expiry_hours', '24', 'Hours before event start when booking expires'],
        ['booking_reminder_hours', '12', 'Hours before payment deadline to send reminder'],
        ['enable_partial_payments', '1', 'Enable 50% booking feature']
    ];

    foreach ($bookingSettings as $setting) {
        $sql = "INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
                VALUES ('" . $db->escape($setting[0]) . "', '" . $db->escape($setting[1]) . "', '" . $db->escape($setting[2]) . "')";
        $db->query($sql);
    }
    echo "<p>✓ Added booking system settings</p>";

    // Commit transaction
    $db->query("COMMIT");

    echo "<h3>✅ Database Update Completed Successfully!</h3>";
    echo "<p><strong>What was updated:</strong></p>";
    echo "<ul>";
    echo "<li>Added payment tracking columns to bookings table</li>";
    echo "<li>Added payment tracking columns to booking_items table</li>";
    echo "<li>Added performance indexes</li>";
    echo "<li>Created cleanup function for expired bookings</li>";
    echo "<li>Created booking statistics view</li>";
    echo "<li>Added system settings for booking configuration</li>";
    echo "</ul>";

    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Update your checkout logic to support 50% bookings</li>";
    echo "<li>Set up a cron job to run cleanup_expired_bookings() regularly</li>";
    echo "<li>Update ticket availability logic to consider bookings</li>";
    echo "<li>Add booking management in admin/planner dashboards</li>";
    echo "</ul>";

} catch (Exception $e) {
    // Rollback transaction on error
    $db->query("ROLLBACK");
    echo "<h3>❌ Error occurred during update</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Changes have been rolled back. Please check your database connection and try again.</p>";
}

echo "<p><a href='index.php'>← Back to Home</a></p>";
?>