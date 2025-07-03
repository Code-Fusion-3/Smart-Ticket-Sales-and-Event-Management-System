<?php
$pageTitle = "Home";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

/// Get upcoming and ongoing events with ticket type pricing
$sql = "SELECT e.*, u.username as planner_name,
        (SELECT MIN(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as min_price,
        (SELECT MAX(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as max_price,
        (SELECT COUNT(*) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as ticket_types_count,
        (SELECT SUM(tt.available_tickets) FROM ticket_types tt WHERE tt.event_id = e.id) as total_available_tickets,
        CASE 
            WHEN CURDATE() < e.start_date THEN 'upcoming'
            WHEN CURDATE() BETWEEN e.start_date AND e.end_date THEN 'ongoing'
            ELSE 'past'
        END as event_status
        FROM events e 
        JOIN users u ON e.planner_id = u.id 
        WHERE e.end_date >= CURDATE() 
        AND e.status = 'active' 
        AND e.available_tickets > 0
        ORDER BY 
            CASE WHEN CURDATE() BETWEEN e.start_date AND e.end_date THEN 1 ELSE 2 END,
            e.start_date ASC 
        LIMIT 6";
$events = $db->fetchAll($sql);


include 'includes/header.php';
?>
<style>
.backdrop-blur-sm {
    backdrop-filter: blur(4px);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

/* Hover effects for stat cards */
.bg-white.bg-opacity-10:hover {
    background-color: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Progress bar animations */
.progress-bar {
    transition: width 1s ease-in-out;
}
</style>
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
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Upcoming Events</h2>
        <p class="text-gray-600 text-lg">Don't miss out on these amazing upcoming events</p>
    </div>

  <?php if (empty($events)): ?>
<div class="text-center py-12">
    <div class="text-gray-400 mb-4">
        <i class="fas fa-calendar-times text-6xl"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Available Events</h3>
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
            <div class="w-full h-48 bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
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

            <!-- Event Status Badge -->
            <?php if ($event['event_status'] === 'ongoing'): ?>
            <div class="absolute bottom-0 left-0 bg-red-500 text-white px-3 py-1 m-2 rounded-lg text-xs animate-pulse">
                <i class="fas fa-circle mr-1"></i>LIVE NOW
            </div>
            <?php elseif ($event['event_status'] === 'upcoming'): ?>
            <div class="absolute bottom-0 left-0 bg-green-500 text-white px-3 py-1 m-2 rounded-lg text-xs">
                <i class="fas fa-clock mr-1"></i>UPCOMING
            </div>
            <?php endif; ?>
        </div>

        <div class="p-4">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($event['title']); ?></h3>
                <?php if ($event['event_status'] === 'ongoing'): ?>
                    <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">
                        LIVE
                    </span>
                <?php endif; ?>
            </div>

            <div class="flex items-center text-gray-600 mb-2">
                <i class="fas fa-map-marker-alt mr-2"></i>
                <span><?php echo htmlspecialchars($event['venue']) . ', ' . htmlspecialchars($event['city']); ?></span>
            </div>

            <div class="flex items-center text-gray-600 mb-3">
                <i class="fas fa-calendar-day mr-2"></i>
                <span>
                    <?php echo formatDate($event['start_date']); ?>
                    <?php if ($event['start_date'] !== $event['end_date']): ?>
                        - <?php echo formatDate($event['end_date']); ?>
                    <?php endif; ?>
                    at <?php echo formatTime($event['start_time']); ?>
                </span>
            </div>

            <!-- Event Duration Info for Multi-day Events -->
            <?php if ($event['start_date'] !== $event['end_date']): ?>
            <div class="flex items-center text-gray-500 mb-3 text-sm">
                <i class="fas fa-clock mr-2"></i>
                <span>
                    <?php 
                    $startDate = new DateTime($event['start_date']);
                    $endDate = new DateTime($event['end_date']);
                    $duration = $startDate->diff($endDate)->days + 1;
                    echo $duration . ' day' . ($duration > 1 ? 's' : '') . ' event';
                    ?>
                </span>
            </div>
            <?php endif; ?>

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
                    <?php echo $event['event_status'] === 'ongoing' ? 'Join Now' : 'View Details'; ?>
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
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Why Choose Our Platform?</h2>
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

<!-- Enhanced Statistics Section -->
<div class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <!-- Main Statistics Title -->
        <div class="text-center mb-12">
            <h2 class="text-2xl font-bold mb-4">Platform Statistics</h2>
            <p class="text-indigo-200 text-lg">Real-time insights into our event ecosystem</p>
        </div>
<?php
// Enhanced platform statistics with proper date handling
$statsSql = "SELECT 
            -- Future Events (haven't started yet)
            (SELECT COUNT(*) FROM events WHERE status = 'active' AND start_date > CURDATE()) as upcoming_events,
            
            -- Ongoing Events (currently happening)
            (SELECT COUNT(*) FROM events WHERE status = 'active' AND CURDATE() BETWEEN start_date AND end_date) as ongoing_events,
            
            -- Past Events (completely finished)
            (SELECT COUNT(*) FROM events WHERE status = 'active' AND end_date < CURDATE()) as past_events,
            
            -- Total Active Events
            (SELECT COUNT(*) FROM events WHERE status = 'active') as total_active_events,
            
            -- Available Events (upcoming + ongoing)
            (SELECT COUNT(*) FROM events WHERE status = 'active' AND end_date >= CURDATE()) as available_events,
            
            -- Ticket Statistics - FIXED VARIABLE NAMES
            (SELECT COUNT(*) FROM tickets WHERE status = 'sold') as tickets_sold,
            (SELECT COUNT(*) FROM tickets t JOIN events e ON t.event_id = e.id 
             WHERE t.status = 'sold' AND e.end_date >= CURDATE()) as upcoming_tickets_sold,
            (SELECT COUNT(*) FROM tickets t JOIN events e ON t.event_id = e.id 
             WHERE t.status = 'sold' AND e.end_date < CURDATE()) as past_tickets_sold,
            
            -- User Statistics
            (SELECT COUNT(*) FROM users WHERE role = 'customer') as customers,
            (SELECT COUNT(*) FROM users WHERE role = 'event_planner') as planners,
            
            -- Revenue Statistics
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'purchase' AND status = 'completed') as total_revenue,
            (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t 
             JOIN tickets tk ON t.reference_id LIKE CONCAT('%', tk.id, '%')
             JOIN events e ON tk.event_id = e.id 
             WHERE t.type = 'purchase' AND t.status = 'completed' AND e.end_date >= CURDATE()) as upcoming_revenue,
            
            -- This Month Statistics
            (SELECT COUNT(*) FROM events WHERE status = 'active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as events_this_month,
            (SELECT COUNT(*) FROM tickets WHERE status = 'sold' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as tickets_this_month";

$stats = $db->fetchOne($statsSql);

// Calculate percentages for better insights
$totalEvents = $stats['total_active_events'];
$upcomingPercentage = $totalEvents > 0 ? round(($stats['upcoming_events'] / $totalEvents) * 100) : 0;
$ongoingPercentage = $totalEvents > 0 ? round(($stats['ongoing_events'] / $totalEvents) * 100) : 0;
$pastPercentage = $totalEvents > 0 ? round(($stats['past_events'] / $totalEvents) * 100) : 0;

// Calculate ticket distribution percentages - FIXED
$totalTickets = $stats['tickets_sold'] ?? 0;
$upcomingTicketPercentage = $totalTickets > 0 ? 
    round(($stats['upcoming_tickets_sold'] / $totalTickets) * 100) : 0;
$pastTicketPercentage = $totalTickets > 0 ? 
    round(($stats['past_tickets_sold'] / $totalTickets) * 100) : 0;
?>

<!-- Customer Benefits Section -->
<div class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold mb-4">Why Choose Our Platform?</h2>
            <p class="text-indigo-200 text-lg">Everything you need for the perfect event experience</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="bg-white bg-opacity-10 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-search text-3xl text-green-300"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3">Discover Events</h3>
                <p class="text-indigo-200">Find amazing events happening near you, from concerts to workshops</p>
            </div>

            <div class="text-center">
                <div class="bg-white bg-opacity-10 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-credit-card text-3xl text-blue-300"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3">Easy Booking</h3>
                <p class="text-indigo-200">Book tickets in seconds with secure payment options</p>
            </div>

            <div class="text-center">
                <div class="bg-white bg-opacity-10 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-qrcode text-3xl text-purple-300"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3">Digital Tickets</h3>
                <p class="text-indigo-200">Get instant digital tickets with QR codes for easy entry</p>
            </div>
        </div>

        <!-- Simple stats bar -->
        <div class="mt-12 text-center">
            <?php
            $quickStats = $db->fetchOne("SELECT 
                (SELECT COUNT(*) FROM events WHERE status = 'active' AND end_date >= CURDATE()) as events,
                (SELECT COUNT(*) FROM users WHERE role = 'customer') as customers");
            ?>
            <div class="bg-white bg-opacity-10 rounded-lg p-6 inline-block">
                <span class="text-2xl font-bold text-green-300"><?php echo number_format($quickStats['events'] ?? 0); ?></span>
                <span class="text-indigo-200 mx-4">events available</span>
                <span class="text-2xl font-bold text-blue-300"><?php echo number_format($quickStats['customers'] ?? 0); ?>+</span>
                <span class="text-indigo-200">happy customers</span>
            </div>
        </div>
    </div>
</div>



        <!-- Detailed Breakdown Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Events Breakdown -->
            <div class="bg-white bg-opacity-10 rounded-lg p-6 backdrop-blur-sm">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    Events Overview
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200">Upcoming Events</span>
                        <span class="font-bold text-green-300">
                            <?php echo number_format($stats['upcoming_events'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200">Ongoing Events</span>
                        <span class="font-bold text-yellow-300">
                            <?php echo number_format($stats['ongoing_events'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200">Past Events</span>
                        <span class="font-bold text-orange-300">
                            <?php echo number_format($stats['past_events'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center border-t border-indigo-400 pt-2">
                        <span class="text-indigo-200">Total Active</span>
                        <span class="font-bold text-white">
                            <?php echo number_format($stats['total_active_events'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200 text-sm">This Month</span>
                        <span class="font-bold text-blue-300">
                            <?php echo number_format($stats['events_this_month'] ?? 0); ?>
                        </span>
                    </div>
                </div>
            </div>

           <!-- Tickets Breakdown -->
<div class="bg-white bg-opacity-10 rounded-lg p-6 backdrop-blur-sm">
    <h3 class="text-lg font-semibold mb-4 flex items-center">
        <i class="fas fa-ticket-alt mr-2"></i>
        Ticket Sales
    </h3>
    <div class="space-y-3">
        <div class="flex justify-between items-center">
            <span class="text-indigo-200">Available Events</span>
            <span class="font-bold text-green-300">
                <?php echo number_format($stats['upcoming_tickets_sold'] ?? 0); ?>
            </span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-indigo-200">Past Events</span>
            <span class="font-bold text-orange-300">
                <?php echo number_format($stats['past_tickets_sold'] ?? 0); ?>
            </span>
        </div>
        <div class="flex justify-between items-center border-t border-indigo-400 pt-2">
            <span class="text-indigo-200">Total Sold</span>
            <span class="font-bold text-white">
                <?php echo number_format($stats['tickets_sold'] ?? 0); ?>
            </span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-indigo-200 text-sm">This Month</span>
            <span class="font-bold text-yellow-300">
                <?php echo number_format($stats['tickets_this_month'] ?? 0); ?>
            </span>
        </div>
    </div>
</div>


            <!-- Revenue & Growth -->
            <div class="bg-white bg-opacity-10 rounded-lg p-6 backdrop-blur-sm">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-chart-line mr-2"></i>
                    Revenue Insights
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200">Total Revenue</span>
                        <span class="font-bold text-green-300">
                            <?php echo formatCurrency($stats['total_revenue'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200">Available Events</span>
                        <span class="font-bold text-blue-300">
                            <?php echo formatCurrency($stats['available_revenue'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center border-t border-indigo-400 pt-2">
                        <span class="text-indigo-200">Avg per Ticket</span>
                        <span class="font-bold text-white">
                            <?php 
                            $avgTicketPrice = $stats['tickets_sold'] > 0 ? 
                                ($stats['total_revenue'] / $stats['tickets_sold']) : 0;
                            echo formatCurrency($avgTicketPrice); 
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-indigo-200 text-sm">Growth Rate</span>
                        <span class="font-bold text-yellow-300">
                            <i class="fas fa-arrow-up text-xs"></i> 
                            <?php 
                            $growthRate = $stats['events_this_month'] > 0 ? 
                                min(round(($stats['events_this_month'] / max($stats['total_active_events'], 1)) * 100), 100) : 0;
                            echo $growthRate; 
                            ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visual Progress Bars -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Events Distribution -->
            <div class="bg-white bg-opacity-10 rounded-lg p-6 backdrop-blur-sm">
                <h4 class="text-lg font-semibold mb-4">Events Distribution</h4>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Upcoming Events</span>
                            <span><?php echo $upcomingPercentage; ?>%</span>
                        </div>
                        <div class="w-full bg-indigo-800 rounded-full h-2">
                            <div class="bg-green-400 h-2 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $upcomingPercentage; ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Past Events</span>
                            <span><?php echo $pastPercentage; ?>%</span>
                        </div>
                        <div class="w-full bg-indigo-800 rounded-full h-2">
                            <div class="bg-orange-400 h-2 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $pastPercentage; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

           <!-- Ticket Sales Distribution -->
<div class="bg-white bg-opacity-10 rounded-lg p-6 backdrop-blur-sm">
    <h4 class="text-lg font-semibold mb-4">Ticket Sales Distribution</h4>
    <div class="space-y-3">
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span>Available Events</span>
                <span><?php echo $upcomingTicketPercentage; ?>%</span>
            </div>
            <div class="w-full bg-indigo-800 rounded-full h-2">
                <div class="bg-blue-400 h-2 rounded-full transition-all duration-500" 
                     style="width: <?php echo $upcomingTicketPercentage; ?>%"></div>
            </div>
        </div>
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span>Past Events</span>
                <span><?php echo $pastTicketPercentage; ?>%</span>
            </div>
            <div class="w-full bg-indigo-800 rounded-full h-2">
                <div class="bg-purple-400 h-2 rounded-full transition-all duration-500" 
                     style="width: <?php echo $pastTicketPercentage; ?>%"></div>
            </div>
        </div>
    </div>
</div>

        </div>

       <!-- Quick Insights -->
        <div class="mt-8 text-center">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white bg-opacity-5 rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-300 mb-1">
                        <?php 
                        $successRate = $stats['total_active_events'] > 0 ? 
                            round((($stats['past_events'] + $stats['upcoming_events']) / $stats['total_active_events']) * 100) : 0;
                        echo $successRate; 
                        ?>%
                    </div>
                    <div class="text-indigo-200 text-sm">Event Success Rate</div>
                </div>
                
                <div class="bg-white bg-opacity-5 rounded-lg p-4">
                    <div class="text-2xl font-bold text-blue-300 mb-1">
                        <?php 
                        $avgTicketsPerEvent = $stats['total_active_events'] > 0 ? 
                            round($stats['tickets_sold'] / $stats['total_active_events']) : 0;
                        echo number_format($avgTicketsPerEvent); 
                        ?>
                    </div>
                    <div class="text-indigo-200 text-sm">Avg Tickets per Event</div>
                </div>
                
                <div class="bg-white bg-opacity-5 rounded-lg p-4">
                    <div class="text-2xl font-bold text-purple-300 mb-1">
                        <?php 
                        $customerEngagement = $stats['customers'] > 0 ? 
                            round($stats['tickets_sold'] / $stats['customers'], 1) : 0;
                        echo $customerEngagement; 
                        ?>
                    </div>
                    <div class="text-indigo-200 text-sm">Tickets per Customer</div>
                </div>
            </div>
        </div>

        <!-- Real-time Status Indicators -->
        <div class="mt-8 flex justify-center space-x-8">
            <div class="flex items-center">
                <div class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                <span class="text-indigo-200 text-sm">
                    <?php echo number_format($stats['upcoming_events'] ?? 0); ?> Events Coming Up
                </span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-yellow-400 rounded-full mr-2 animate-pulse"></div>
                <span class="text-indigo-200 text-sm">
                    <?php echo number_format($stats['tickets_this_month'] ?? 0); ?> Tickets Sold This Month
                </span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-blue-400 rounded-full mr-2 animate-pulse"></div>
                <span class="text-indigo-200 text-sm">
                    <?php echo number_format($stats['planners'] ?? 0); ?> Active Planners
                </span>
            </div>
        </div>
    </div>
</div>


<!-- Testimonials Section -->
<div class="py-16">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">What Our Users Say</h2>
            <p class="text-gray-600 text-lg">Real feedback from our community</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Testimonial 1 -->
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
                <p class="text-gray-600">"Amazing platform! I've attended multiple events and the booking process is always smooth and hassle-free."</p>
            </div>

            <!-- Testimonial 2 -->
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
                <p class="text-gray-600">"As an event planner, this system has revolutionized how I manage ticket sales. The analytics and reporting features are fantastic!"</p>
            </div>

            <!-- Testimonial 3 -->
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
                <p class="text-gray-600">"The mobile tickets with QR codes make entry so convenient. No more worrying about losing paper tickets!"</p>
            </div>
        </div>

        <!-- Additional Testimonials Row (Optional) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
            <!-- Testimonial 4 -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-indigo-100 rounded-full w-12 h-12 flex items-center justify-center mr-4">
                        <span class="text-indigo-600 font-bold text-xl">E</span>
                    </div>
                    <div>
                        <h4 class="font-semibold">Emily Davis</h4>
                        <div class="text-yellow-400">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                <p class="text-gray-600">"Customer support is outstanding! They helped me resolve an issue within minutes. Highly recommend this platform."</p>
            </div>

            <!-- Testimonial 5 -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-indigo-100 rounded-full w-12 h-12 flex items-center justify-center mr-4">
                        <span class="text-indigo-600 font-bold text-xl">R</span>
                    </div>
                    <div>
                        <h4 class="font-semibold">Robert Wilson</h4>
                        <div class="text-yellow-400">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                <p class="text-gray-600">"The variety of events available is impressive. I've discovered so many great local events through this platform!"</p>
            </div>
        </div>
    </div>
</div>

   

            <!-- Call to Action Section -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-16">
                <div class="container mx-auto px-4 text-center">
                    <h2 class="text-2xl font-bold mb-4">Ready to Get Started?</h2>
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
<script>
// Optional: Add some JavaScript for dynamic effects
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars on scroll
    const progressBars = document.querySelectorAll('[style*="width:"]');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.width = entry.target.getAttribute('data-width') || entry.target.style.width;
            }
        });
    });
    
    progressBars.forEach(bar => {
        observer.observe(bar);
    });
    
    // Add counter animation effect
    const counters = document.querySelectorAll('.text-3xl.font-bold');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/,/g, ''));
        let current = 0;
        const increment = target / 50;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                counter.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };
        
        // Start animation when element is visible
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    counterObserver.unobserve(entry.target);
                }
            });
        });
        
        counterObserver.observe(counter);
    });
});
</script>
            <?php include 'includes/footer.php'; ?>