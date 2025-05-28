<?php
/**
 * Cart management functions
 */

/**
 * Add item to cart
 */
function addToCart($userId, $eventId, $ticketTypeId, $quantity) {
    global $db;
    
    // Get or create cart
    $cartId = getOrCreateCart($userId);
    
    // Check if item already exists in cart
    $existingSql = "SELECT id, quantity FROM cart_items 
                   WHERE cart_id = $cartId AND event_id = $eventId AND ticket_type_id = $ticketTypeId";
    $existing = $db->fetchOne($existingSql);
    
    if ($existing) {
        // Update existing item
        $newQuantity = $existing['quantity'] + $quantity;
        $updateSql = "UPDATE cart_items SET quantity = $newQuantity, updated_at = NOW() 
                     WHERE id = {$existing['id']}";
        return $db->query($updateSql);
    } else {
        // Add new item
        $insertSql = "INSERT INTO cart_items (cart_id, event_id, ticket_type_id, quantity, created_at) 
                     VALUES ($cartId, $eventId, $ticketTypeId, $quantity, NOW())";
        return $db->query($insertSql);
    }
}

/**
 * Get or create cart for user
 */
function getOrCreateCart($userId) {
    global $db;
    
    $cartSql = "SELECT id FROM cart WHERE user_id = $userId";
    $cartResult = $db->fetchOne($cartSql);
    
    if ($cartResult) {
        return $cartResult['id'];
    } else {
        $createSql = "INSERT INTO cart (user_id, created_at) VALUES ($userId, NOW())";
        return $db->insert($createSql);
    }
}

/**
 * Get cart items count for user
 */
function getCartItemsCount($userId) {
    global $db;
    
    $sql = "SELECT COALESCE(SUM(ci.quantity), 0) as total_items
            FROM cart c
            JOIN cart_items ci ON c.id = ci.cart_id
            WHERE c.user_id = $userId";
    $result = $db->fetchOne($sql);
    
    return $result['total_items'] ?? 0;
}

/**
 * Get cart total amount for user
 */
function getCartTotal($userId) {
    global $db;
    
    $sql = "SELECT COALESCE(SUM(ci.quantity * COALESCE(tt.price, e.ticket_price)), 0) as total_amount
            FROM cart c
            JOIN cart_items ci ON c.id = ci.cart_id
            JOIN events e ON ci.event_id = e.id
            LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
            WHERE c.user_id = $userId AND e.status = 'active'";
    $result = $db->fetchOne($sql);
    
    return $result['total_amount'] ?? 0;
}

/**
 * Remove item from cart
 */
function removeFromCart($userId, $itemId) {
    global $db;
    
    $sql = "DELETE ci FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.id
            WHERE c.user_id = $userId AND ci.id = $itemId";
    return $db->query($sql);
}

/**
 * Clear entire cart for user
 */
function clearCart($userId) {
    global $db;
    
    $sql = "DELETE ci FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.id
            WHERE c.user_id = $userId";
    return $db->query($sql);
}

/**
 * Validate cart items availability
 */
function validateCartItems($userId) {
    global $db;
    
    $sql = "SELECT ci.id, ci.quantity, ci.event_id, ci.ticket_type_id,
                   e.title, e.status,
                   COALESCE(tt.name, 'Standard Ticket') as ticket_name,
                   COALESCE(tt.available_tickets, e.available_tickets) as available_tickets
            FROM cart c
            JOIN cart_items ci ON c.id = ci.cart_id
            JOIN events e ON ci.event_id = e.id
            LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
            WHERE c.user_id = $userId";
    
    $items = $db->fetchAll($sql);
    $issues = [];
    
    foreach ($items as $item) {
        if ($item['status'] !== 'active') {
            $issues[] = [
                'type' => 'inactive_event',
                'item_id' => $item['id'],
                'message' => "Event '{$item['title']}' is no longer active"
            ];
        } elseif ($item['available_tickets'] < $item['quantity']) {
            $issues[] = [
                'type' => 'insufficient_tickets',
                'item_id' => $item['id'],
                'message' => "Only {$item['available_tickets']} tickets available for '{$item['ticket_name']}' in '{$item['title']}'"
            ];
        }
    }
    
    return $issues;
}

/**
 * Update cart item quantity
 */
function updateCartItemQuantity($userId, $itemId, $quantity) {
    global $db;
    
    if ($quantity <= 0) {
        return removeFromCart($userId, $itemId);
    }
    
    $sql = "UPDATE cart_items ci
            JOIN cart c ON ci.cart_id = c.id
            SET ci.quantity = $quantity, ci.updated_at = NOW()
            WHERE c.user_id = $userId AND ci.id = $itemId";
    
    return $db->query($sql);
}
?>