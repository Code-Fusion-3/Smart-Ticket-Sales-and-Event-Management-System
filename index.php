<?php
$pageTitle = "Home";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get upcoming events with ticket type pricing
$sql = "SELECT e.*, u.username as planner_name,
        (SELECT MIN(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as min_price,
        (SELECT MAX(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as max_price,
        (SELECT COUNT(*) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as ticket_types_count,
        (SELECT SUM(tt.available_tickets) FROM ticket_types tt WHERE tt.event_id = e.id) as total_available_tickets
        FROM events e 
        JOIN users u ON e.planner_id = u.id 
        WHERE e.start_date >= CURDATE() 
        AND e.status = 'active' 
        ORDER BY e.start_date ASC 
        LIMIT 6";
$events = $db->fetchAll($sql);

include 'includes/header.php';
?>

<div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
    <div class="container mx-auto px-4 py-16">
        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-4">Find Amazing Events</h1>
            <p class="text-xl md:text-2xl mb-8">Discover and book tickets for the best events in your area</p>
            <a href="events.php"
                class="bg-white text-indigo-600 font-bold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-300">
                Browse All Events
            </a>
        </div>
    </div>
</div>

<!-- Featured Events Section -->
<div class="container mx-auto px-4 py-12">
    <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-gray-900 mb-4">Upcoming Events</h2>
        <p class="text-gray-600 text-lg">Don't miss out on these amazing upcoming events</p>
    </div>

    <?php if (empty($events)): ?>
    <div class="text-center py-12">
        <div class="text-gray-400 mb-4">
            <i class="fas fa-calendar-times text-6xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Upcoming Events</h3>
        <p class="text-gray-500">Check back later for new events!</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($events as $event): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
            <div class="relative">
                <?php if (!empty($event['image'])): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $event['image']; ?>"
                    alt="<?php echo htmlspecialchars($event['title']); ?>" class="w-full h-48 object-cover">
                <?php else: ?>
                <div
                    class="w-full h-48 bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-white text-4xl"></i>
                </div>
                <?php endif; ?>

                <!-- Pricing Badge -->
                <div class="absolute top-0 right-0 bg-indigo-600 text-white px-3 py-1 m-2 rounded-lg text-sm">
                    <?php 
                            // Display pricing based on ticket types
                            if ($event['ticket_types_count'] > 0) {
                                if ($event['min_price'] == $event['max_price']) {
                                    // Single price
                                    echo formatCurrency($event['min_price']);
                                } else {
                                    // Price range
                                    echo formatCurrency($event['min_price']) . ' - ' . formatCurrency($event['max_price']);
                                }
                            } else {
                                // Fallback to event base price
                                echo formatCurrency($event['ticket_price']);
                            }
                            ?>
                </div>

                <!-- Category Badge -->
                <?php if (!empty($event['category'])): ?>
                <div class="absolute top-0 left-0 bg-black bg-opacity-50 text-white px-3 py-1 m-2 rounded-lg text-xs">
                    <?php echo htmlspecialchars($event['category']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-4">
                <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($event['title']); ?></h3>

                <div class="flex items-center text-gray-600 mb-2">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <span><?php echo htmlspecialchars($event['venue']) . ', ' . htmlspecialchars($event['city']); ?></span>
                </div>

                <div class="flex items-center text-gray-600 mb-3">
                    <i class="fas fa-calendar-day mr-2"></i>
                    <span><?php echo formatDate($event['start_date']); ?> at
                        <?php echo formatTime($event['start_time']); ?></span>
                </div>

                <div class="flex items-center text-gray-600 mb-4 text-sm">
                    <i class="fas fa-user mr-2"></i>
                    <span>By <?php echo htmlspecialchars($event['planner_name']); ?></span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">
                        <?php 
                                $availableTickets = $event['total_available_tickets'] ?? $event['available_tickets'];
                                echo $availableTickets; 
                                ?> tickets left
                    </span>
                    <a href="event-details.php?id=<?php echo $event['id']; ?>"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                        View Details
                    </a>
                </div>

                <!-- Ticket Types Preview -->
                <?php if ($event['ticket_types_count'] > 1): ?>
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <div class="text-xs text-gray-500 mb-1">Available ticket types:</div>
                    <?php
                                // Get ticket types for this event
                                $ticketTypesSql = "SELECT name, price, available_tickets 
                                                  FROM ticket_types 
                                                  WHERE event_id = " . $event['id'] . " 
                                                  AND available_tickets > 0 
                                                  ORDER BY price ASC 
                                                  LIMIT 3";
                                $ticketTypes = $db->fetchAll($ticketTypesSql);
                                ?>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($ticketTypes as $type): ?>
                        <span class="inline-block bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">
                            <?php echo htmlspecialchars($type['name']); ?>:
                            <?php echo formatCurrency($type['price']); ?>
                        </span>
                        <?php endforeach; ?>
                        <?php if ($event['ticket_types_count'] > 3): ?>
                        <span class="inline-block text-gray-500 text-xs px-2 py-1">
                            +<?php echo ($event['ticket_types_count'] - 3); ?> more
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-12">
        <a href="events.php"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg transition duration-300">
            View All Events
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Features Section -->
<div class="bg-gray-50 py-16">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Why Choose Our Platform?</h2>
            <p class="text-gray-600 text-lg">Experience the best in event ticketing</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="bg-indigo-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-ticket-alt text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Easy Booking</h3>
                <p class="text-gray-600">Simple and secure ticket booking process with multiple payment options</p>
            </div>

            <div class="text-center">
                <div class="bg-indigo-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-mobile-alt text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Mobile Tickets</h3>
                <p class="text-gray-600">Digital tickets with QR codes for quick and contactless entry</p>
            </div>

            <div class="text-center">
                <div class="bg-indigo-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-headset text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">24/7 Support</h3>
                <p class="text-gray-600">Round-the-clock customer support for all your ticketing needs</p>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center">
            <?php
            // Get platform statistics
            $statsSql = "SELECT 
                        (SELECT COUNT(*) FROM events WHERE status = 'active') as active_events,
                        (SELECT COUNT(*) FROM tickets WHERE status = 'sold') as tickets_sold,
                        (SELECT COUNT(*) FROM users WHERE role = 'customer') as customers,
                        (SELECT COUNT(*) FROM users WHERE role = 'event_planner') as planners";
            $stats = $db->fetchOne($statsSql);
            ?>

            <div>
                <div class="text-3xl font-bold mb-2"><?php echo number_format($stats['active_events'] ?? 0); ?></div>
                <div class="text-indigo-200">Active Events</div>
            </div>

            <div>
                <div class="text-3xl font-bold mb-2"><?php echo number_format($stats['tickets_sold'] ?? 0); ?></div>
                <div class="text-indigo-200">Tickets Sold</div>
            </div>

            <div>
                <div class="text-3xl font-bold mb-2"><?php echo number_format($stats['customers'] ?? 0); ?></div>
                <div class="text-indigo-200">Happy Customers</div>
            </div>

            <div>
                <div class="text-3xl font-bold mb-2"><?php echo number_format($stats['planners'] ?? 0); ?></div>
                <div class="text-indigo-200">Event Planners</div>
            </div>
        </div>
    </div>
</div>

<!-- Testimonials Section -->
<div class="py-16">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">What Our Users Say</h2>
            <p class="text-gray-600 text-lg">Real feedback from our community</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-indigo-100 rounded-full w-12 h-12 flex items-center justify-center mr-4">
                        <span class="text-indigo-600 font-bold text-xl">S</span>
                    </div>
                    <div>
                        <h4 class="font-semibold">Sarah Johnson</h4>
                        <div class="text-yellow-400">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                <p class="text-gray-600">"Amazing platform! I've attended multiple events and the booking process is
                    always smooth and hassle-free."</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-indigo-100 rounded-full w-12 h-12 flex items-center justify-center mr-4">
                        <span class="text-indigo-600 font-bold text-xl">J</span>
                    </div>
                    <div>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-indigo-100 rounded-full w-12 h-12 flex items-center justify-center mr-4">
                                    <span class="text-indigo-600 font-bold text-xl">J</span>
                                </div>
                                <div>
                                    <h4 class="font-semibold">John Smith</h4>
                                    <div class="text-yellow-400">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-600">"As an event planner, this system has revolutionized how I manage
                                ticket sales. The analytics and reporting features are fantastic!"</p>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-indigo-100 rounded-full w-12 h-12 flex items-center justify-center mr-4">
                                    <span class="text-indigo-600 font-bold text-xl">M</span>
                                </div>
                                <div>
                                    <h4 class="font-semibold">Michael Brown</h4>
                                    <div class="text-yellow-400">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-600">"The mobile tickets with QR codes make entry so convenient. No more
                                worrying about losing paper tickets!"</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action Section -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-16">
                <div class="container mx-auto px-4 text-center">
                    <h2 class="text-3xl font-bold mb-4">Ready to Get Started?</h2>
                    <p class="text-xl mb-8">Join thousands of event organizers and attendees on our platform</p>
                    <div class="space-x-4">
                        <?php if (!isLoggedIn()): ?>
                        <a href="register.php"
                            class="bg-white text-indigo-600 font-bold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-300">
                            Sign Up Now
                        </a>
                        <a href="login.php"
                            class="border-2 border-white text-white font-bold py-3 px-8 rounded-lg hover:bg-white hover:text-indigo-600 transition duration-300">
                            Login
                        </a>
                        <?php else: ?>
                        <a href="events.php"
                            class="bg-white text-indigo-600 font-bold py-3 px-8 rounded-lg hover:bg-gray-100 transition duration-300">
                            Browse Events
                        </a>
                        <?php if (hasRole('event_planner')): ?>
                        <a href="planner/events/events.php?action=create"
                            class="border-2 border-white text-white font-bold py-3 px-8 rounded-lg hover:bg-white hover:text-indigo-600 transition duration-300">
                            Create Event
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>