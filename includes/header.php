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
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="hover:text-indigo-200 hidden">Profile</a>
                            <a href="<?php echo SITE_URL; ?>/my-tickets.php" class="hover:text-indigo-200">My Tickets</a>
                            <a href="<?php echo SITE_URL; ?>/marketplace.php" class="hover:text-indigo-200">Marketplace</a>
                            <a href="<?php echo SITE_URL; ?>/transactions.php"
                                class="hover:text-indigo-200 hidden">Transactions</a>
                            <a href="<?php echo SITE_URL; ?>/cart.php" class="hover:text-indigo-200">
                                <i class="fas fa-shopping-cart"></i> Cart
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
                                    echo "<span class='bg-red-500 text-white rounded-full px-2 py-1 text-xs'>$cartCount</span>";
                                }
                                ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/logout.php" class="hover:text-indigo-200">Logout</a>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="deposit.php" class="hover:text-indigo-600 font-semibold">Deposit Funds</a></li>
                            <li><a href="finances.php" class="hover:text-indigo-600 font-semibold">Finances</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/marketplace.php" class="hover:text-indigo-200">Marketplace</a>

                        <a href="<?php echo SITE_URL; ?>/login.php" class="hover:text-indigo-200">Login</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="hover:text-indigo-200">Register</a>
                    <?php endif; ?>
                </nav>

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
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/profile.php" class="block py-2 hover:text-indigo-200">Profile</a>
                        <a href="<?php echo SITE_URL; ?>/my-tickets.php" class="block py-2 hover:text-indigo-200">My Tickets</a>
                        <a href="<?php echo SITE_URL; ?>/transactions.php"
                            class="block py-2 hover:text-indigo-200">Transactions</a>
                        <a href="<?php echo SITE_URL; ?>/cart.php" class="block py-2 hover:text-indigo-200">
                            <i class="fas fa-shopping-cart"></i> Cart
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="block py-2 hover:text-indigo-200">Logout</a>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="deposit.php" class="block py-2 hover:text-indigo-600 font-semibold">Deposit Funds</a></li>
                        <li><a href="finances.php" class="block py-2 hover:text-indigo-600 font-semibold">Finances</a></li>
                    <?php endif; ?>
                <?php else: ?>
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