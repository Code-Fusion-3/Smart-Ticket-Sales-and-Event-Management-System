<?php
if (!defined('SITE_URL')) {
    require_once 'config.php';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?> Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .sidebar-link.active {
        background-color: #3B82F6;
        color: white;
    }

    /* Mobile sidebar overlay */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 40;
    }

    .sidebar-mobile {
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
    }

    .sidebar-mobile.open {
        transform: translateX(0);
    }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white shadow-lg relative z-50">
        <div class="container mx-auto px-2 sm:px-4">
            <div class="flex justify-between items-center py-3 sm:py-4">
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-btn" class="lg:hidden text-white hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>

                    <a href="<?php echo SITE_URL; ?>/admin" class="text-lg sm:text-xl font-bold truncate">
                        <i class="fas fa-shield-alt mr-1 sm:mr-2"></i>
                        <span class="hidden sm:inline"><?php echo SITE_NAME; ?> Admin</span>
                        <span class="sm:hidden">Admin</span>
                    </a>
                </div>

                <div class="flex items-center space-x-2 sm:space-x-4">
                    <span class="text-xs sm:text-sm truncate max-w-24 sm:max-w-none">
                        <span class="hidden sm:inline">Welcome,
                        </span><?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>

                    <!-- Desktop links -->
                    <div class="hidden md:flex items-center space-x-4">
                        <a href="<?php echo SITE_URL; ?>" class="text-gray-300 hover:text-white text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i>View Site
                        </a>
                        <a href="<?php echo SITE_URL; ?>/logout.php" class="text-gray-300 hover:text-white text-sm">
                            <i class="fas fa-sign-out-alt mr-1"></i>Logout
                        </a>
                    </div>

                    <!-- Mobile dropdown button -->
                    <div class="md:hidden relative">
                        <button id="mobile-user-menu" class="text-gray-300 hover:text-white focus:outline-none">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div id="mobile-user-dropdown"
                            class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="<?php echo SITE_URL; ?>"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-external-link-alt mr-2"></i>View Site
                            </a>
                            <a href="<?php echo SITE_URL; ?>/logout.php"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen relative">
        <!-- Mobile Sidebar Overlay -->
        <div id="sidebar-overlay" class="sidebar-overlay hidden lg:hidden"></div>

        <!-- Sidebar -->
        <div id="sidebar"
            class="sidebar-mobile lg:transform-none fixed lg:relative w-64 bg-white shadow-lg z-40 h-full lg:h-auto">
            <div class="p-3 sm:p-4 h-full overflow-y-auto">
                <!-- Mobile close button -->
                <div class="lg:hidden flex justify-end mb-4">
                    <button id="close-sidebar" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <nav class="space-y-1 sm:space-y-2">
                    <a href="<?php echo SITE_URL; ?>/admin/index.php"
                        class="sidebar-link flex items-center px-3 sm:px-4 py-2 sm:py-3 text-gray-700 rounded-lg hover:bg-gray-100 text-sm sm:text-base <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt mr-2 sm:mr-3 text-sm sm:text-base"></i>
                        Dashboard
                    </a>

                    <a href="<?php echo SITE_URL; ?>/admin/users/index.php"
                        class="sidebar-link flex items-center px-3 sm:px-4 py-2 sm:py-3 text-gray-700 rounded-lg hover:bg-gray-100 text-sm sm:text-base <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/users/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-users mr-2 sm:mr-3 text-sm sm:text-base"></i>
                        <span class="truncate">User Management</span>
                    </a>

                    <a href="<?php echo SITE_URL; ?>/admin/events/index.php"
                        class="sidebar-link flex items-center px-3 sm:px-4 py-2 sm:py-3 text-gray-700 rounded-lg hover:bg-gray-100 text-sm sm:text-base <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/events/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt mr-2 sm:mr-3 text-sm sm:text-base"></i>
                        <span class="truncate">Event Management</span>
                    </a>

                    <a href="<?php echo SITE_URL; ?>/admin/finances/index.php"
                        class="sidebar-link flex items-center px-3 sm:px-4 py-2 sm:py-3 text-gray-700 rounded-lg hover:bg-gray-100 text-sm sm:text-base <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/finances/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line mr-2 sm:mr-3 text-sm sm:text-base"></i>
                        <span class="truncate">Financial Management</span>
                    </a>

                    <a href="<?php echo SITE_URL; ?>/admin/reports/index.php"
                        class="sidebar-link flex items-center px-3 sm:px-4 py-2 sm:py-3 text-gray-700 rounded-lg hover:bg-gray-100 text-sm sm:text-base <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/reports/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt mr-2 sm:mr-3 text-sm sm:text-base"></i>
                        Reports
                    </a>

                    <div class="border-t border-gray-200 my-3 sm:my-4"></div>

                    <a href="<?php echo SITE_URL; ?>/admin/settings.php"
                        class="sidebar-link flex items-center px-3 sm:px-4 py-2 sm:py-3 text-gray-700 rounded-lg hover:bg-gray-100 text-sm sm:text-base <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cog mr-2 sm:mr-3 text-sm sm:text-base"></i>
                        <span class="truncate">System Settings</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden min-w-0">
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