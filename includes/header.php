<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>

<body class="bg-[#E1EEF2] min-h-screen flex flex-col">
    <header class="bg-[#265B93] text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div>
                    <a href="<?php echo SITE_URL; ?>" class="text-2xl font-bold">
                        <?php echo SITE_NAME; ?>
                    </a>
                </div>
                <nav class="hidden md:flex space-x-6">
                    <a href="<?php echo SITE_URL; ?>" class="hover:text-indigo-200">Home</a>
                    <a href="<?php echo SITE_URL; ?>/events.php" class="hover:text-indigo-200">Events</a>
                    <?php if (isLoggedIn() === true): ?>
                        <?php if (hasRole('admin')): ?>
                            <a href="<?php echo SITE_URL; ?>/admin" class="hover:text-indigo-200">Admin Dashboard</a>
                        <?php elseif (hasRole('event_planner')): ?>
                            <a href="<?php echo SITE_URL; ?>/planner" class="hover:text-indigo-200">Planner Dashboard</a>
                        <?php elseif (hasRole('agent')): ?>
                            <a href="<?php echo SITE_URL; ?>/agent" class="hover:text-indigo-200">Agent Dashboard</a>
                        <?php elseif (hasRole('customer')): ?>
                            <!-- Customer Navigation -->
                            <a href="<?php echo SITE_URL; ?>/marketplace.php" class="hover:text-indigo-200 flex items-center">
                                <i class="fas fa-store mr-1"></i>Marketplace
                            </a>
                            <a href="<?php echo SITE_URL; ?>/cart.php" class="hover:text-indigo-200 flex items-center relative">
                                <i class="fas fa-shopping-cart mr-1"></i>Cart
                                <?php
                                // Get cart count
                                global $db;
                                $userId = getCurrentUserId();
                                $sql = "SELECT COUNT(ci.id) as count FROM cart_items ci 
                                        JOIN cart c ON c.id = ci.cart_id 
                                        WHERE c.user_id = $userId";
                                $result = $db->fetchOne($sql);
                                $cartCount = $result ? $result['count'] : 0;

                                if ($cartCount > 0) {
                                    echo "<span class='absolute -top-2 -right-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs'>$cartCount</span>";
                                }
                                ?>
                            </a>

                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/marketplace.php" class="hover:text-indigo-200">Marketplace</a>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="hover:text-indigo-200">Login</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="hover:text-indigo-200">Register</a>
                    <?php endif; ?>
                </nav>

                <!-- User Profile Dropdown (for logged in users) -->
                <?php if (isLoggedIn()): ?>
                    <div class="hidden md:block relative">
                        <button id="user-dropdown-button"
                            class="flex items-center text-white hover:text-indigo-200 focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-indigo-200 flex items-center justify-center mr-2">
                                <i class="fas fa-user text-indigo-600"></i>
                            </div>
                            <span><?php echo $_SESSION['username'] ?? 'User'; ?></span>
                            <i class="fas fa-chevron-down ml-1"></i>
                        </button>

                        <div id="user-dropdown"
                            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                            <a href="<?php echo SITE_URL; ?>/profile.php"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>My Profile
                            </a>
                            <?php if (hasRole('customer')): ?>
                                <a href="<?php echo SITE_URL; ?>/my-tickets.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-ticket-alt mr-2"></i>My Tickets
                                </a>
                                <a href="<?php echo SITE_URL; ?>/my-bookings.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-calendar-check mr-2"></i>My Bookings
                                </a>
                                <a href="<?php echo SITE_URL; ?>/notifications.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-bell mr-2"></i>Notifications
                                    <?php
                                    // Get unread notifications count
                                    global $db;
                                    $userId = getCurrentUserId();
                                    $unreadSql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $userId AND is_read = 0";
                                    $unreadResult = $db->fetchOne($unreadSql);
                                    $unreadCount = $unreadResult ? $unreadResult['count'] : 0;

                                    if ($unreadCount > 0) {
                                        echo "<span class='ml-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs'>$unreadCount</span>";
                                    }
                                    ?>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/finances.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-wallet mr-2"></i>Finances
                                </a>
                                <a href="<?php echo SITE_URL; ?>/withdraw.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Withdraw
                                </a>
                                <a href="<?php echo SITE_URL; ?>/deposit.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-ticket-alt mr-2"></i>Deposits
                                </a>
                            <?php endif; ?>
                            <div class="border-t border-gray-100"></div>
                            <a href="<?php echo SITE_URL; ?>/logout.php"
                                class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="md:hidden hidden mt-2 pb-2">
                <a href="<?php echo SITE_URL; ?>" class="block py-2 hover:text-indigo-200">Home</a>
                <a href="<?php echo SITE_URL; ?>/events.php" class="block py-2 hover:text-indigo-200">Events</a>
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?php echo SITE_URL; ?>/admin" class="block py-2 hover:text-indigo-200">Admin Dashboard</a>
                    <?php elseif (hasRole('event_planner')): ?>
                        <a href="<?php echo SITE_URL; ?>/planner" class="block py-2 hover:text-indigo-200">Planner Dashboard</a>
                    <?php elseif (hasRole('agent')): ?>
                        <a href="<?php echo SITE_URL; ?>/agent" class="block py-2 hover:text-indigo-200">Agent Dashboard</a>
                    <?php elseif (hasRole('customer')): ?>
                        <!-- Customer Mobile Navigation -->
                        <a href="<?php echo SITE_URL; ?>/marketplace.php" class="block py-2 hover:text-indigo-200">
                            <i class="fas fa-store mr-2"></i>Marketplace
                        </a>
                        <a href="<?php echo SITE_URL; ?>/cart.php" class="block py-2 hover:text-indigo-200">
                            <i class="fas fa-shopping-cart mr-2"></i>Cart
                            <?php
                            // Get cart count for mobile
                            global $db;
                            $userId = getCurrentUserId();
                            $sql = "SELECT COUNT(ci.id) as count FROM cart_items ci 
                                    JOIN cart c ON c.id = ci.cart_id 
                                    WHERE c.user_id = $userId";
                            $result = $db->fetchOne($sql);
                            $cartCount = $result ? $result['count'] : 0;

                            if ($cartCount > 0) {
                                echo "<span class='ml-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs'>$cartCount</span>";
                            }
                            ?>
                        </a>
                        <div class="border-t border-indigo-400 pt-2 mt-2">
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="block py-2 hover:text-indigo-200">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="<?php echo SITE_URL; ?>/my-tickets.php" class="block py-2 hover:text-indigo-200">
                                <i class="fas fa-ticket-alt mr-2"></i>My Tickets
                            </a>
                            <a href="<?php echo SITE_URL; ?>/my-bookings.php" class="block py-2 hover:text-indigo-200">
                                <i class="fas fa-calendar-check mr-2"></i>My Bookings
                            </a>
                            <a href="<?php echo SITE_URL; ?>/notifications.php" class="block py-2 hover:text-indigo-200">
                                <i class="fas fa-bell mr-2"></i>Notifications
                                <?php
                                // Get unread notifications count for mobile
                                global $db;
                                $userId = getCurrentUserId();
                                $unreadSql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $userId AND is_read = 0";
                                $unreadResult = $db->fetchOne($unreadSql);
                                $unreadCount = $unreadResult ? $unreadResult['count'] : 0;

                                if ($unreadCount > 0) {
                                    echo "<span class='ml-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs'>$unreadCount</span>";
                                }
                                ?>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/finances.php" class="block py-2 hover:text-indigo-200">
                                <i class="fas fa-wallet mr-2"></i>Finances
                            </a>
                            <a href="<?php echo SITE_URL; ?>/withdraw.php" class="block py-2 hover:text-indigo-200">
                                <i class="fas fa-money-bill-wave mr-2"></i>Withdraw
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="border-t border-indigo-400 pt-2 mt-2">
                        <a href="<?php echo SITE_URL; ?>/logout.php" class="block py-2 hover:text-indigo-200">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/marketplace.php"
                        class="block py-2 hover:text-indigo-200">Marketplace</a>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="block py-2 hover:text-indigo-200">Login</a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="block py-2 hover:text-indigo-200">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-6">
        <?php
        // Display flash messages
        if (isset($_SESSION['success_message'])) {
            echo displaySuccess($_SESSION['success_message']);
            unset($_SESSION['success_message']);
        }

        if (isset($_SESSION['error_message'])) {
            echo displayError($_SESSION['error_message']);
            unset($_SESSION['error_message']);
        }

        if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
            echo displayError("You don't have permission to access this page.");
        }
        ?>