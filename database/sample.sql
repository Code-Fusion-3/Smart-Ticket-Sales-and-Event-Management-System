-- Admin (already inserted in schema, but adding more for testing)
INSERT INTO users (username, email, password_hash, phone_number, role, status)
VALUES 
('admin2', 'admin2@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1111111111', 'admin', 'active');

-- Event Planners
INSERT INTO users (username, email, password_hash, phone_number, role, status)
VALUES 
('planner1', 'planner1@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2222222222', 'event_planner', 'active'),
('planner2', 'planner2@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '3333333333', 'event_planner', 'active');

-- Customers
INSERT INTO users (username, email, password_hash, phone_number, role, status)
VALUES 
('customer1', 'customer1@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '4444444444', 'customer', 'active'),
('customer2', 'customer2@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '5555555555', 'customer', 'active');

-- Agents
INSERT INTO users (username, email, password_hash, phone_number, role, status)
VALUES 
('agent1', 'agent1@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '6666666666', 'agent', 'active');

INSERT INTO events (planner_id, title, description, category, venue, address, city, country, start_date, end_date, start_time, end_time, total_tickets, available_tickets, ticket_price, image, status)
VALUES
(2, 'Music Fest 2024', 'Annual music festival', 'Music', 'Stadium A', '123 Main St', 'Kigali', 'Rwanda', '2024-08-01', '2024-08-01', '18:00', '23:00', 100, 80, 10000, 'musicfest.jpg', 'active'),
(3, 'Tech Expo', 'Technology and innovation exhibition', 'Expo', 'Expo Center', '456 Tech Rd', 'Kigali', 'Rwanda', '2024-09-10', '2024-09-12', '09:00', '17:00', 200, 150, 5000, 'techexpo.jpg', 'active');

INSERT INTO ticket_types (event_id, name, description, price, total_tickets, available_tickets)
VALUES
(1, 'VIP', 'VIP Access', 20000, 20, 15),
(1, 'Regular', 'Regular Access', 10000, 80, 65),
(2, 'Standard', 'Standard Entry', 5000, 200, 150);

INSERT INTO tickets (event_id, ticket_type_id, user_id, recipient_name, recipient_email, recipient_phone, qr_code, purchase_price, status)
VALUES
(1, 1, 4, 'Alice Customer', 'customer1@system.com', '4444444444', 'QR123VIP', 20000, 'sold'),
(1, 2, 5, 'Bob Customer', 'customer2@system.com', '5555555555', 'QR124REG', 10000, 'sold'),
(2, 3, 4, 'Alice Customer', 'customer1@system.com', '4444444444', 'QR125STD', 5000, 'sold');

INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
VALUES
(4, 20000, 'purchase', 'completed', 'TXN001', 'credit_card', 'VIP ticket for Music Fest'),
(5, 10000, 'purchase', 'completed', 'TXN002', 'mobile_money', 'Regular ticket for Music Fest'),
(4, 5000, 'purchase', 'completed', 'TXN003', 'balance', 'Standard ticket for Tech Expo');

INSERT INTO withdrawals (user_id, amount, fee, net_amount, payment_method, payment_details, status)
VALUES
(2, 100000, 2500, 97500, 'mobile_money', '0788888888', 'approved');

INSERT INTO deposits (user_id, amount, payment_method, reference_number, status)
VALUES
(4, 50000, 'mobile_money', 'DEP001', 'completed'),
(5, 30000, 'credit_card', 'DEP002', 'completed');

INSERT INTO cart (user_id) VALUES (4), (5);

INSERT INTO cart_items (cart_id, event_id, ticket_type_id, quantity, recipient_name, recipient_email, recipient_phone)
VALUES
(1, 1, 2, 2, 'Alice Customer', 'customer1@system.com', '4444444444'),
(2, 2, 3, 1, 'Bob Customer', 'customer2@system.com', '5555555555');

INSERT INTO bookings (user_id, event_id, quantity, status, expiry_time)
VALUES
(4, 1, 2, 'confirmed', '2024-07-31 18:00:00');

INSERT INTO booking_items (booking_id, recipient_name, recipient_email, recipient_phone)
VALUES
(1, 'Alice Customer', 'customer1@system.com', '4444444444'),
(1, 'Friend of Alice', 'friend@system.com', '7777777777');

INSERT INTO ticket_resales (ticket_id, seller_id, resale_price, platform_fee, seller_earnings, description, status)
VALUES
(2, 5, 7500, 225, 7275, 'Resale of Regular ticket', 'active');

INSERT INTO ticket_verifications (ticket_id, agent_id, status, notes)
VALUES
(1, 6, 'verified', 'Entry at main gate'),
(2, 6, 'rejected', 'QR code already used');

INSERT INTO notifications (user_id, title, message, type)
VALUES
(4, 'Ticket Purchase', 'Your ticket for Music Fest 2024 is confirmed!', 'ticket'),
(5, 'Ticket Resale', 'Your ticket resale listing is now active.', 'ticket');

INSERT INTO email_logs (recipient_email, subject, message, status)
VALUES
('customer1@system.com', 'Your Ticket', 'Here is your ticket for Music Fest 2024', 'sent');

INSERT INTO sms_logs (recipient_phone, message, status)
VALUES
('4444444444', 'Your ticket for Music Fest 2024 is confirmed!', 'sent');

INSERT INTO agent_scans (agent_id, ticket_id, status)
VALUES
(6, 1, 'valid'),
(6, 2, 'invalid');