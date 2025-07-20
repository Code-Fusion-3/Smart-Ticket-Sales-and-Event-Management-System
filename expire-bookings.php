<?php
// Booking Expiration Script
// This script should be run as a cron job to automatically expire bookings
// that are past their 24-hour deadline before the event starts.
// 
// Usage: php expire-bookings.php
// Recommended cron schedule: */5 * * * * (every 5 minutes)

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "Starting booking expiration check...\n";

try {
    // Get all pending bookings that are past their deadline
    $expireSql = "SELECT b.*, e.title, e.start_date, e.start_time, u.username, u.email
                  FROM bookings b 
                  JOIN events e ON b.event_id = e.id 
                  JOIN users u ON b.user_id = u.id
                  WHERE b.status = 'pending' 
                  AND DATE_SUB(e.start_date, INTERVAL 24 HOUR) < NOW()
                  AND e.start_date > NOW()";

    $expiredBookings = $db->fetchAll($expireSql);

    if (empty($expiredBookings)) {
        echo "No expired bookings found.\n";
        exit(0);
    }

    echo "Found " . count($expiredBookings) . " expired bookings.\n";

    $db->query("START TRANSACTION");

    foreach ($expiredBookings as $booking) {
        echo "Processing booking ID: {$booking['id']} for event: {$booking['title']}\n";

        // Update booking status to expired (no refund - user loses deposit)
        $updateBookingSql = "UPDATE bookings SET status = 'expired', updated_at = NOW() WHERE id = {$booking['id']}";
        $db->query($updateBookingSql);

        // Create transaction record for lost deposit (no refund)
        $depositAmount = $booking['amount_paid'];
        $transactionSql = "INSERT INTO transactions (user_id, type, amount, description, status, reference_id, created_at)
                          VALUES ({$booking['user_id']}, 'withdrawal', $depositAmount, 
                                  'Booking deposit forfeited for {$booking['title']} (expired)', 'completed', 
                                  '{$booking['transaction_id']}', NOW())";
        $db->insert($transactionSql);

        // Send notification to user about lost deposit
        $notificationSql = "INSERT INTO notifications (user_id, title, message, type, created_at)
                           VALUES ({$booking['user_id']}, 'Booking Expired - Deposit Lost', 
                                   'Your booking for {$booking['title']} has expired. Your deposit of " . formatCurrency($depositAmount) . " has been forfeited as per booking terms.', 
                                   'payment', NOW())";
        $db->insert($notificationSql);

        echo "  - Booking expired, deposit forfeited: " . formatCurrency($depositAmount) . "\n";
    }

    $db->query("COMMIT");
    echo "Successfully processed " . count($expiredBookings) . " expired bookings.\n";

} catch (Exception $e) {
    $db->query("ROLLBACK");
    echo "Error processing expired bookings: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Booking expiration check completed.\n";
exit(0);