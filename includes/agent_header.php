<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Agent Dashboard'; ?> - Smart Ticket System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        .dropdown-menu {
            display: none;
        }
        .dropdown-menu.show {
            display: block;
        }
        .alert-auto-hide {
            animation: fadeOut 5s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-indigo-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <a href="../agent/index.php" class="text-white font-bold text-xl">
                            <i class="fas fa-qrcode mr-2"></i>Agent Portal
                        </a>
                    </div>
                    
                    <div class="hidden md:block ml-10">
                        <div class="flex items-baseline space-x-4">
                            <a href="../agent/index.php" 
                                class="text-white hover:text-indigo-200 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                            </a>
                            <a href="../agent/verify_ticket.php" 
                                class="text-white hover:text-indigo-200 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-search mr-1"></i>Verify Ticket
                            </a>
                            <a href="../agent/scan_history.php" 
                                class="text-white hover:text-indigo-200 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-history mr-1"></i>Scan History
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <!-- User Menu -->
                    <div class="relative">
                        <button onclick="toggleDropdown('user-dropdown')" 
                            class="flex items-center text-white hover:text-indigo-200 focus:outline-none">
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full bg-indigo-300 flex items-center justify-center">
                                    <i class="fas fa-user text-indigo-700"></i>
                                </div>
                                <span class="ml-2 text-sm font-medium">
                                    <?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Agent'); ?>
                                </span>
                                <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </div>
                        </button>
                        
                        <div id="user-dropdown" 
                            class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                            <a href="../profile.php" 
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="../logout.php" 
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="md:hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="../agent/index.php" 
                    class="text-white hover:text-indigo-200 block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="../agent/verify_ticket.php" 
                    class="text-white hover:text-indigo-200 block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-search mr-2"></i>Verify Ticket
                </a>
                <a href="../agent/scan_history.php" 
                    class="text-white hover:text-indigo-200 block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-history mr-2"></i>Scan History
                </a>
            </div>
        </div>
    </nav>

    <!-- JavaScript for dropdowns -->
    <script>
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            
            // Close all other dropdowns
            allDropdowns.forEach(d => {
                if (d.id !== id) {
                    d.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
</html> 