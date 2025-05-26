<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Top Navigation -->
    <nav class="bg-indigo-600 text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="<?php echo SITE_URL; ?>" class="text-xl font-bold">
                        <?php echo SITE_NAME; ?>
                    </a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="<?php echo SITE_URL; ?>/planner/index.php" class="hover:text-indigo-200 px-3 py-2 rounded-md">Dashboard</a>
                    <a href="<?php echo SITE_URL; ?>/planner/events/events.php" class="hover:text-indigo-200 px-3 py-2 rounded-md">Events</a>
                    <a href="<?php echo SITE_URL; ?>/planner/tickets/tickets.php" class="hover:text-indigo-200 px-3 py-2 rounded-md">Tickets</a>
                    <a href="<?php echo SITE_URL; ?>/planner/reports.php" class="hover:text-indigo-200 px-3 py-2 rounded-md">Reports</a>
                    <a href="<?php echo SITE_URL; ?>/planner/finances/index.php" class="hover:text-indigo-200 px-3 py-2 rounded-md">Finances</a>
                    
                    <div class="relative ml-3">
                        <button id="user-menu-button" class="flex items-center text-sm focus:outline-none">
                            <span class="mr-2"><?php echo $_SESSION['username']; ?></span>
                            <img class="h-8 w-8 rounded-full" src="<?php echo getUserProfileImage(); ?>" alt="Profile">
                        </button>
                        
                        <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="<?php echo SITE_URL; ?>/planner/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Profile
                            </a>
                            <a href="<?php echo SITE_URL; ?>/planner/settings.php" class="hidden px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Settings
                            </a>
                            <div class="border-t border-gray-100"></div>
                            <a href="<?php echo SITE_URL; ?>/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="hidden md:hidden pb-4">
                <a href="<?php echo SITE_URL; ?>/planner/index.php" class="block py-2 hover:text-indigo-200">Dashboard</a>
                <a href="<?php echo SITE_URL; ?>/planner/events/events.php" class="block py-2 hover:text-indigo-200">Events</a>
                <a href="<?php echo SITE_URL; ?>/planner/tickets/tickets.php" class="block py-2 hover:text-indigo-200">Tickets</a>
                <a href="<?php echo SITE_URL; ?>/planner/reports.php" class="block py-2 hover:text-indigo-200">Reports</a>
                <a href="<?php echo SITE_URL; ?>/planner/finances/index.php" class="block py-2 hover:text-indigo-200">Finances</a>
                <div class="border-t border-indigo-500 my-2"></div>
                <a href="<?php echo SITE_URL; ?>/planner/profile.php" class="block py-2 hover:text-indigo-200">Profile</a>
                <a href="<?php echo SITE_URL; ?>/planner/settings.php" class="block py-2 hover:text-indigo-200">Settings</a>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="block py-2 hover:text-indigo-200">Logout</a>
            </div>
        </div>
    </nav>
    
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 container mx-auto mt-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 container mx-auto mt-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="flex-grow">
    <!-- Main Content -->
    <main class="flex-grow">
        
    <!-- Add JavaScript for dropdown and mobile menu -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // User dropdown toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');
            
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('hidden');
                });
            }
            
            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Close menus when clicking outside
            document.addEventListener('click', function() {
                if (userDropdown) {
                    userDropdown.classList.add('hidden');
                }
                
                if (mobileMenu && window.innerWidth < 768) { // Only close on outside click for mobile view
                    mobileMenu.classList.add('hidden');
                }
            });
            
            // Prevent closing when clicking inside the dropdown
            if (userDropdown) {
                userDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Close alert messages when clicking the close button
            const closeButtons = document.querySelectorAll('.bg-green-100 .absolute, .bg-red-100 .absolute');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.parentElement.remove();
                });
            });
        });
    </script>
