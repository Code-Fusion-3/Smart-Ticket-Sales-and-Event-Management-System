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

                        <!-- Event Status Badge -->
                        <?php if ($event['event_status'] === 'ongoing'): ?>
                            <div
                                class="absolute bottom-0 left-0 bg-red-500 text-white px-3 py-1 m-2 rounded-lg text-xs animate-pulse">
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

<!-- Feedback Section -->
<div class="bg-gray-50 py-16">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">We'd Love to Hear From You</h2>
            <p class="text-gray-600 text-lg">Share your experience and help us improve our platform</p>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-8">
                <form id="feedbackForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="feedback-name" class="block text-gray-700 font-bold mb-2">Your Name *</label>
                            <input type="text" id="feedback-name" name="name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="Enter your full name">
                        </div>
                        <div>
                            <label for="feedback-email" class="block text-gray-700 font-bold mb-2">Email Address
                                *</label>
                            <input type="email" id="feedback-email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="Enter your email">
                        </div>
                    </div>

                    <div>
                        <label for="feedback-subject" class="block text-gray-700 font-bold mb-2">Subject *</label>
                        <input type="text" id="feedback-subject" name="subject" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="Brief description of your feedback">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Rating *</label>
                        <div class="flex space-x-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" required class="hidden">
                                    <span class="text-2xl text-gray-500 hover:text-yellow-400 transition-colors rating-star"
                                        data-rating="<?php echo $i; ?>">★</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Click on a star to rate your experience</p>
                    </div>

                    <div>
                        <label for="feedback-message-input" class="block text-gray-700 font-bold mb-2">Message *</label>
                        <textarea id="feedback-message-input" name="message" rows="5" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="Tell us about your experience, suggestions, or any issues you encountered..."></textarea>
                    </div>

                    <div id="feedback-response" class="hidden"></div>

                    <div class="text-center">
                        <button type="submit" id="feedback-submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg transition duration-300">
                            <span id="submit-text">Submit Feedback</span>
                            <span id="submit-loading" class="hidden">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Sending...
                            </span>
                        </button>
                    </div>
                </form>
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
    document.addEventListener('DOMContentLoaded', function () {
        // Animate progress bars on scroll
        const progressBars = document.querySelectorAll('[style*="width:"]');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.width = entry.target.getAttribute('data-width') || entry
                        .target.style.width;
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

        // === Feedback Form Handling ===
        const feedbackForm = document.getElementById('feedbackForm');
        const ratingStars = document.querySelectorAll('.rating-star');
        const feedbackResponse = document.getElementById('feedback-response');
        const submitButton = document.getElementById('feedback-submit');
        const submitText = document.getElementById('submit-text');
        const submitLoading = document.getElementById('submit-loading');

        // Star rating interaction
        ratingStars.forEach(star => {
            star.addEventListener('click', function () {
                const rating = this.getAttribute('data-rating');

                // Update radio button
                const radio = this.parentElement.querySelector('input[type="radio"]');
                radio.checked = true;

                // Update star colors
                ratingStars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#fbbf24'; // gold color
                    } else {
                        s.style.color = '#374151'; // gray-700 color
                    }
                });
            });

            // Hover effects
            star.addEventListener('mouseenter', function () {
                const rating = this.getAttribute('data-rating');
                ratingStars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#fbbf24'; // gold color
                    }
                });
            });

            star.addEventListener('mouseleave', function () {
                const selectedRating = document.querySelector('input[name="rating"]:checked');
                if (selectedRating) {
                    const rating = selectedRating.value;
                    ratingStars.forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = '#fbbf24'; // gold color
                        } else {
                            s.style.color = '#6b7280'; // gray-500 color
                        }
                    });
                } else {
                    ratingStars.forEach(s => {
                        s.style.color = '#6b7280'; // white color
                    });
                }
            });
        });

        // Form submission
        feedbackForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Show loading state
            submitButton.disabled = true;
            submitText.classList.add('hidden');
            submitLoading.classList.remove('hidden');
            feedbackResponse.classList.add('hidden');

            // Get form data
            const formData = new FormData(this);

            // Debug: Log form data
            console.log('Form data being sent:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }

            // Send AJAX request
            fetch('process_feedback.php', {
                method: 'POST',
                body: formData
            })
                .then(async response => {
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        data = {success: false, errors: ['Invalid server response']};
                    }
                    if (!response.ok) {
                        // Show server errors if present
                        throw data;
                    }
                    return data;
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        // Show success message
                        feedbackResponse.className =
                            'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
                        feedbackResponse.textContent = data.message;
                        feedbackResponse.classList.remove('hidden');

                        // Reset form
                        feedbackForm.reset();
                        ratingStars.forEach(s => s.style.color = '#6b7280');

                        // Scroll to message
                        feedbackResponse.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    } else {
                        // Show error message
                        feedbackResponse.className =
                            'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                        if (data.errors && Array.isArray(data.errors)) {
                            feedbackResponse.innerHTML = '<ul class="list-disc pl-4"><li>' + data.errors
                                .join('</li><li>') + '</li></ul>';
                        } else {
                            feedbackResponse.textContent = data.message ||
                                'An error occurred while submitting feedback.';
                        }
                        feedbackResponse.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    let errors = error.errors || [error.message || 'An error occurred.'];
                    feedbackResponse.className =
                        'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                    feedbackResponse.innerHTML = '<ul class="list-disc pl-4"><li>' + errors.join('</li><li>') + '</li></ul>';
                    feedbackResponse.classList.remove('hidden');
                    alert('Error submitting feedback. Please try again or contact support if the problem persists.');
                })
                .finally(() => {
                    // Reset button state
                    submitButton.disabled = false;
                    submitText.classList.remove('hidden');
                    submitLoading.classList.add('hidden');
                });
        });
    });
</script>
<?php include 'includes/footer.php'; ?>