</main>

<footer class="bg-gray-800 text-white py-8 mt-auto">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between">
            <div class="mb-6 md:mb-0">
                <h2 class="text-xl font-bold mb-4"><?php echo SITE_NAME; ?></h2>
                <p class="text-gray-400">Your one-stop solution for event ticketing and management.</p>
            </div>

            <div class="mb-6 md:mb-0">
                <h3 class="text-lg font-semibold mb-3">Quick Links</h3>
                <ul class="space-y-2">
                    <li><a href="<?php echo SITE_URL; ?>" class="text-gray-400 hover:text-white">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/events.php" class="text-gray-400 hover:text-white">Events</a>
                    </li>
                    <?php if (!isLoggedIn()): ?>
                        <li><a href="<?php echo SITE_URL; ?>/login.php" class="text-gray-400 hover:text-white">Login</a>
                        </li>
                        <li><a href="<?php echo SITE_URL; ?>/register.php"
                                class="text-gray-400 hover:text-white">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div>
                <h3 class="text-lg font-semibold mb-3">Contact Us</h3>
                <ul class="space-y-2">
                    <li class="flex items-center">
                        <i class="fas fa-envelope mr-2 text-indigo-400"></i>
                        <a href="mailto:contact@smartticket.com"
                            class="text-gray-400 hover:text-white">contact@smartticket.com</a>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-phone mr-2 text-indigo-400"></i>
                        <span class="text-gray-400">+250786729283</span>
                    </li>
                </ul>
                <div class="mt-4 flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-700 text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- JavaScript -->
<script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // User dropdown toggle
    const userDropdownButton = document.getElementById('user-dropdown-button');
    const userDropdown = document.getElementById('user-dropdown');

    if (userDropdownButton && userDropdown) {
        userDropdownButton.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userDropdownButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });

        // Close dropdown when pressing Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                userDropdown.classList.add('hidden');
            }
        });
    }
</script>
<script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
</body>

</html>