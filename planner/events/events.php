<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Manage Events";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Handle actions
$action = $_GET['action'] ?? 'list';

// List all events
if ($action == 'list') {
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Build query
    $whereClause = "WHERE planner_id = $plannerId";

    if ($status != 'all') {
        $whereClause .= " AND status = '" . $db->escape($status) . "'";
    }

    if (!empty($search)) {
        $whereClause .= " AND (title LIKE '%" . $db->escape($search) . "%' OR 
                              venue LIKE '%" . $db->escape($search) . "%' OR 
                              city LIKE '%" . $db->escape($search) . "%')";
    }

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM events $whereClause";
    $countResult = $db->fetchOne($countSql);
    $totalEvents = $countResult['total'];
    $totalPages = ceil($totalEvents / $perPage);

    // Get events with calculated status
    $sql = "SELECT *, 
        CASE 
            WHEN status = 'canceled' THEN 'canceled'
            WHEN status = 'suspended' THEN 'suspended'
            WHEN DATE(CONCAT(start_date, ' ', COALESCE(start_time, '00:00:00'))) < NOW() THEN 'completed'
            WHEN status = 'active' AND DATE(CONCAT(start_date, ' ', COALESCE(start_time, '00:00:00'))) >= NOW() THEN 'active'
            ELSE status
        END as calculated_status,
        status as original_status
        FROM events $whereClause 
        ORDER BY start_date DESC 
        LIMIT $offset, $perPage";
    $events = $db->fetchAll($sql);

    include '../../includes/planner_header.php';
    ?>

    <div class="container mx-auto px-4 py-6">
        <?php include 'sub/filter.php'; ?>

        <!-- Events List -->
        <?php include 'sub/events_list.php'; ?>
    </div>

    <?php


} elseif ($action == 'create' || $action == 'edit') {
    // Get event data if editing
    $event = [];
    $eventId = 0;

    if ($action == 'edit' && isset($_GET['id'])) {
        $eventId = (int) $_GET['id'];
        $sql = "SELECT * FROM events WHERE id = $eventId AND planner_id = $plannerId";
        $event = $db->fetchOne($sql);

        if (!$event) {
            $_SESSION['error_message'] = "Event not found or you don't have permission to edit it.";
            redirect('planner/events/events.php');
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get form data
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        $venue = $_POST['venue'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $country = $_POST['country'] ?? '';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $totalTickets = (int) ($_POST['total_tickets'] ?? 0);
        $ticketPrice = (float) ($_POST['ticket_price'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        // Validate form data
        $errors = [];

        if (empty($title)) {
            $errors[] = "Title is required";
        }

        if (empty($venue)) {
            $errors[] = "Venue is required";
        }

        if (empty($startDate)) {
            $errors[] = "Start date is required";
        }

        if (empty($endDate)) {
            $errors[] = "End date is required";
        }

        if (empty($startTime)) {
            $errors[] = "Start time is required";
        }

        if (empty($endTime)) {
            $errors[] = "End time is required";
        }
        // Validate ticket types
        $ticketTypes = $_POST['ticket_types'] ?? [];

        if (empty($ticketTypes)) {
            $errors[] = "At least one ticket type is required";
        } else {
            $totalTickets = 0;

            foreach ($ticketTypes as $index => $type) {
                if (empty($type['name'])) {
                    $errors[] = "Ticket name is required for all ticket types";
                    break;
                }

                if (!isset($type['price']) || $type['price'] < 0) {
                    $errors[] = "Valid price is required for all ticket types";
                    break;
                }

                if (!isset($type['quantity']) || $type['quantity'] < 1) {
                    $errors[] = "Quantity must be at least 1 for all ticket types";
                    break;
                }

                $totalTickets += (int) $type['quantity'];
            }

            if ($totalTickets < 0) {
                $errors[] = "Total tickets must be at least 1";
            }
        }

        // Handle image upload
        $imagePath = $event['image'] ?? '';

        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $uploadDir = '../../uploads/events/';

            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            // Check if image file is a actual image
            $check = getimagesize($_FILES['image']['tmp_name']);
            if ($check === false) {
                $errors[] = "File is not an image";
            }

            // Check file size (limit to 5MB)
            if ($_FILES['image']['size'] > 5000000) {
                $errors[] = "File is too large (max 5MB)";
            }

            // Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
            }

            // If no errors, upload file
            if (empty($errors)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = '../../uploads/events/' . $fileName;
                } else {
                    $errors[] = "Failed to upload image";
                }
            }
        }

        // If no errors, save event
        if (empty($errors)) {
            // Calculate available tickets
            $availableTickets = $totalTickets;

            if ($action == 'edit') {
                // If editing, get current tickets sold
                $sql = "SELECT COUNT(*) as count FROM tickets WHERE event_id = $eventId AND status = 'sold'";
                $ticketCount = $db->fetchOne($sql);
                $soldTickets = $ticketCount['count'] ?? 0;

                // Available tickets = total tickets - sold tickets
                $availableTickets = $totalTickets - $soldTickets;

                if ($availableTickets < 0) {
                    $errors[] = "Total tickets cannot be less than tickets already sold";
                }
            }

            // Inside the form submission handling code
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {

                // Validate ticket types
                $ticketTypes = $_POST['ticket_types'] ?? [];

                if (empty($ticketTypes)) {
                    $errors[] = "At least one ticket type is required";
                } else {
                    $totalTickets = 0;

                    foreach ($ticketTypes as $index => $type) {
                        if (empty($type['name'])) {
                            $errors[] = "Ticket name is required for all ticket types";
                            break;
                        }

                        if (!isset($type['price']) || $type['price'] < 0) {
                            $errors[] = "Valid price is required for all ticket types";
                            break;
                        }

                        if (!isset($type['quantity']) || $type['quantity'] < 1) {
                            $errors[] = "Quantity must be at least 1 for all ticket types";
                            break;
                        }

                        $totalTickets += (int) $type['quantity'];
                    }

                    // Store total tickets for the event
                    $totalTickets = $totalTickets;
                }

                // If no errors, save event and ticket types
                if (empty($errors)) {
                    // Begin transaction
                    $db->query("START TRANSACTION");

                    try {
                        if ($action == 'create') {
                            // Insert new event
                            $sql = "INSERT INTO events (
                            planner_id, title, description, category, venue, address, city, country,
                            start_date, end_date, start_time, end_time, total_tickets, available_tickets,
                            ticket_price, image, status, created_at, updated_at
                        ) VALUES (
                            $plannerId, 
                            '" . $db->escape($title) . "', 
                            '" . $db->escape($description) . "', 
                            '" . $db->escape($category) . "', 
                            '" . $db->escape($venue) . "', 
                            '" . $db->escape($address) . "', 
                            '" . $db->escape($city) . "', 
                            '" . $db->escape($country) . "', 
                            '" . $db->escape($startDate) . "', 
                            '" . $db->escape($endDate) . "', 
                            '" . $db->escape($startTime) . "', 
                            '" . $db->escape($endTime) . "', 
                            $totalTickets, 
                            $totalTickets, 
                            0, 
                            '" . $db->escape($imagePath) . "', 
                            '" . $db->escape($status) . "',
                            NOW(),
                            NOW()
                        )";

                            // $db->query($sql);
                            $eventId = $db->insert($sql);

                            // Insert ticket types
                            foreach ($ticketTypes as $type) {
                                $typeName = $db->escape($type['name']);
                                $typeDesc = $db->escape($type['description'] ?? '');
                                $typePrice = (float) $type['price'];
                                $typeQuantity = (int) $type['quantity'];

                                $sql = "INSERT INTO ticket_types (
                                event_id, name, description, price, total_tickets, available_tickets, created_at, updated_at
                            ) VALUES (
                                $eventId,
                                '$typeName',
                                '$typeDesc',
                                $typePrice,
                                $typeQuantity,
                                $typeQuantity,
                                NOW(),
                                NOW()
                            )";

                                $db->query($sql);
                            }

                            $_SESSION['success_message'] = "Event created successfully with " . count($ticketTypes) . " ticket types";

                            // === Send notification to all active customers ===
                            require_once '../../includes/notifications.php';
                            // Fetch all active customers
                            $customerSql = "SELECT id, username, email FROM users WHERE role = 'customer' AND status = 'active'";
                            $customers = $db->fetchAll($customerSql);
                            $emailsSent = 0;
                            // Get planner info
                            $planner = getUserById($plannerId);
                            // Build event link
                            $eventLink = SITE_URL . "/event-details.php?id=" . $eventId;
                            // Build email subject
                            $emailSubject = "🎉 New Event: " . $title . " at " . $venue . " [" . SITE_NAME . "]";
                            // Build email body (HTML)
                            $emailBody = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>"
                                . "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>"
                                . "<h1 style='margin: 0; font-size: 24px;'>🎉 New Event Available!</h1>"
                                . "<p style='margin: 10px 0 0 0; opacity: 0.9;'>Don't miss out on this exciting opportunity</p>"
                                . "</div>"
                                . "<div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>"
                                . "<h2 style='color: #333; margin-top: 0;'>" . htmlspecialchars($title) . "</h2>"
                                . "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>"
                                . "<div style='display: flex; align-items: center; margin-bottom: 10px;'>"
                                . "<span style='background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-right: 10px;'>" . htmlspecialchars($category) . "</span>"
                                . "<span style='color: #666; font-size: 14px;'>Organized by " . htmlspecialchars($planner['username']) . "</span>"
                                . "</div>"
                                . "<div style='margin: 15px 0;'>"
                                . "<p style='margin: 5px 0; color: #333;'><strong>📍 Venue:</strong> " . htmlspecialchars($venue) . "</p>"
                                . "<p style='margin: 5px 0; color: #333;'><strong>🏙️ Location:</strong> " . htmlspecialchars($city) . ", " . htmlspecialchars($country) . "</p>"
                                . "<p style='margin: 5px 0; color: #333;'><strong>📅 Date:</strong> " . formatDate($startDate) . "</p>"
                                . "<p style='margin: 5px 0; color: #333;'><strong>⏰ Time:</strong> " . formatTime($startTime) . "</p>"
                                . "</div>"
                                . "</div>"
                                . "<div style='margin: 30px 0; text-align: center;'>"
                                . "<a href='" . $eventLink . "' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>🎫 View Event & Get Tickets</a>"
                                . "</div>"
                                . "<div style='border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;'><p style='color: #666; font-size: 14px; margin: 0;'>You're receiving this email because you're a registered customer of " . SITE_NAME . ". We'll keep you updated about new events and special offers!</p></div>"
                                . "</div>"
                                . "<div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'><p>© " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p></div>"
                                . "</div>";
                            // Send email to each customer
                            foreach ($customers as $customer) {
                                if (!empty($customer['email'])) {
                                    $result = sendEmail($customer['email'], $emailSubject, $emailBody);
                                    if ($result)
                                        $emailsSent++;
                                }
                            }
                            $_SESSION['success_message'] .= "<br>Notification sent to $emailsSent active customers.";
                        } else {
                            // Update existing event
                            $sql = "UPDATE events SET 
                            title = '" . $db->escape($title) . "', 
                            description = '" . $db->escape($description) . "', 
                            category = '" . $db->escape($category) . "', 
                            venue = '" . $db->escape($venue) . "', 
                            address = '" . $db->escape($address) . "', 
                            city = '" . $db->escape($city) . "', 
                            country = '" . $db->escape($country) . "', 
                            start_date = '" . $db->escape($startDate) . "', 
                            end_date = '" . $db->escape($endDate) . "', 
                            start_time = '" . $db->escape($startTime) . "', 
                            end_time = '" . $db->escape($endTime) . "', 
                            total_tickets = $totalTickets, 
                            status = '" . $db->escape($status) . "',
                            updated_at = NOW()";

                            if (!empty($imagePath)) {
                                $sql .= ", image = '" . $db->escape($imagePath) . "'";
                            }

                            $sql .= " WHERE id = $eventId AND planner_id = $plannerId";

                            $db->query($sql);

                            // Handle ticket types for existing event
                            // First, get existing ticket types to check if any tickets have been sold
                            $sql = "SELECT tt.id, tt.name, tt.total_tickets, 
                               (SELECT COUNT(*) FROM tickets t WHERE t.ticket_type_id = tt.id AND t.status = 'sold') as sold_tickets
                        FROM ticket_types tt
                        WHERE tt.event_id = $eventId";
                            $existingTypes = $db->fetchAll($sql);

                            // Create a map of existing types by ID
                            $existingTypesMap = [];
                            foreach ($existingTypes as $type) {
                                $existingTypesMap[$type['id']] = $type;
                            }

                            // Track which types we're keeping
                            $keepTypeIds = [];

                            // Insert/update ticket types
                            foreach ($ticketTypes as $type) {
                                $typeId = isset($type['id']) ? (int) $type['id'] : 0;
                                $typeName = $db->escape($type['name']);
                                $typeDesc = $db->escape($type['description'] ?? '');
                                $typePrice = (float) $type['price'];
                                $typeQuantity = (int) $type['quantity'];

                                if ($typeId > 0 && isset($existingTypesMap[$typeId])) {
                                    // This is an existing type - update it
                                    $soldTickets = $existingTypesMap[$typeId]['sold_tickets'];

                                    // Ensure we're not reducing below sold tickets
                                    if ($typeQuantity < $soldTickets) {
                                        throw new Exception("Cannot reduce ticket quantity below sold amount for {$type['name']}");
                                    }

                                    $availableTickets = $typeQuantity - $soldTickets;

                                    $sql = "UPDATE ticket_types SET 
                        name = '$typeName',
                        description = '$typeDesc',
                        price = $typePrice,
                        total_tickets = $typeQuantity,
                        available_tickets = $availableTickets,
                        updated_at = NOW()
                    WHERE id = $typeId AND event_id = $eventId";

                                    $db->query($sql);
                                    $keepTypeIds[] = $typeId;
                                } else {
                                    // This is a new type - insert it
                                    $sql = "INSERT INTO ticket_types (
                        event_id, name, description, price, total_tickets, available_tickets, created_at, updated_at
                    ) VALUES (
                        $eventId,
                        '$typeName',
                        '$typeDesc',
                        $typePrice,
                        $typeQuantity,
                        $typeQuantity,
                        NOW(),
                        NOW()
                    )";

                                    // $db->query($sql);
                                    $keepTypeIds[] = $db->insert($sql);
                                }
                            }

                            // Delete any ticket types that weren't in the form (if they have no sold tickets)
                            if (!empty($existingTypesMap)) {
                                $deleteTypeIds = [];

                                foreach ($existingTypesMap as $id => $type) {
                                    if (!in_array($id, $keepTypeIds)) {
                                        // Check if this type has sold tickets
                                        if ($type['sold_tickets'] > 0) {
                                            throw new Exception("Cannot delete ticket type '{$type['name']}' because it has sold tickets");
                                        }

                                        $deleteTypeIds[] = $id;
                                    }
                                }

                                if (!empty($deleteTypeIds)) {
                                    $deleteIdsStr = implode(',', $deleteTypeIds);
                                    $sql = "DELETE FROM ticket_types WHERE id IN ($deleteIdsStr) AND event_id = $eventId";
                                    $db->query($sql);
                                }
                            }

                            // Update available tickets in the events table
                            $sql = "UPDATE events SET 
                available_tickets = (SELECT SUM(available_tickets) FROM ticket_types WHERE event_id = $eventId),
                updated_at = NOW()
            WHERE id = $eventId";
                            $db->query($sql);

                            $_SESSION['success_message'] = "Event updated successfully with " . count($ticketTypes) . " ticket types";
                        }

                        // Commit transaction
                        $db->query("COMMIT");
                        redirect('planner/events/events.php');
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->query("ROLLBACK");
                        $errors[] = $e->getMessage();
                    }
                }
            }

            // If editing, load existing ticket types
            $ticketTypes = [];
            if ($action == 'edit' && isset($event['id'])) {
                $sql = "SELECT * FROM ticket_types WHERE event_id = " . $event['id'] . " ORDER BY price ASC";
                $ticketTypes = $db->fetchAll($sql);
            }

        }
    }

    include '../../includes/planner_header.php';
    ?>

    <?php include 'sub/create_edit.php'; ?>

    <?php

}
?>