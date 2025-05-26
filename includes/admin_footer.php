        </div>
        </div>

        <!-- Scripts -->
        <script>
// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const closeSidebar = document.getElementById('close-sidebar');
    const mobileUserMenu = document.getElementById('mobile-user-menu');
    const mobileUserDropdown = document.getElementById('mobile-user-dropdown');

    // Toggle mobile sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('hidden');
        document.body.classList.toggle('overflow-hidden');
    }

    // Close mobile sidebar
    function closeMobileSidebar() {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // Mobile menu button click
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', toggleSidebar);
    }

    // Close sidebar button click
    if (closeSidebar) {
        closeSidebar.addEventListener('click', closeMobileSidebar);
    }

    // Overlay click to close sidebar
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileSidebar);
    }

    // Mobile user menu toggle
    if (mobileUserMenu && mobileUserDropdown) {
        mobileUserMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileUserDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            mobileUserDropdown.classList.add('hidden');
        });

        mobileUserDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Close sidebar when clicking on sidebar links (mobile)
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 1024) { // lg breakpoint
                closeMobileSidebar();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) { // lg breakpoint
            closeMobileSidebar();
        }
    });

    // Auto-hide success/error messages after 5 seconds
    const alerts = document.querySelectorAll('.alert-auto-hide');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Confirmation dialogs for delete actions
    window.confirmDelete = function(message = 'Are you sure you want to delete this item?') {
        return confirm(message);
    };

    // Toggle dropdown menus
    window.toggleDropdown = function(id) {
        const dropdown = document.getElementById(id);
        if (dropdown) {
            dropdown.classList.toggle('hidden');
        }
    };

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(function(dropdown) {
            if (!dropdown.contains(event.target) && !event.target.closest('.dropdown-toggle')) {
                dropdown.classList.add('hidden');
            }
        });
    });

    // Responsive table handling
    function handleResponsiveTables() {
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            const wrapper = table.parentElement;
            if (wrapper && wrapper.classList.contains('overflow-x-auto')) {
                // Add touch scrolling for mobile
                wrapper.style.webkitOverflowScrolling = 'touch';
            }
        });
    }

    handleResponsiveTables();
});

// Utility functions for responsive behavior
function isMobile() {
    return window.innerWidth < 768;
}

function isTablet() {
    return window.innerWidth >= 768 && window.innerWidth < 1024;
}

function isDesktop() {
    return window.innerWidth >= 1024;
}

// Handle responsive form elements
function adjustFormElements() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (isMobile()) {
                input.classList.add('text-base'); // Prevent zoom on iOS
            }
        });
    });
}

// Call on load and resize
window.addEventListener('load', adjustFormElements);
window.addEventListener('resize', adjustFormElements);

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Loading states for buttons
function showLoading(button, text = 'Loading...') {
    const originalText = button.innerHTML;
    button.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${text}`;
    button.disabled = true;
    button.dataset.originalText = originalText;
}

function hideLoading(button) {
    if (button.dataset.originalText) {
        button.innerHTML = button.dataset.originalText;
        button.disabled = false;
        delete button.dataset.originalText;
    }
}

// Make these functions globally available
window.showLoading = showLoading;
window.hideLoading = hideLoading;

// Handle form submissions with loading states
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            showLoading(submitBtn, 'Processing...');
        }
    });
});

// Keyboard navigation for dropdowns
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close all open dropdowns
        document.querySelectorAll('.dropdown-menu:not(.hidden)').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });

        // Close mobile sidebar
        if (!sidebar.classList.contains('hidden')) {
            closeMobileSidebar();
        }
    }
});

// Touch gestures for mobile sidebar
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const swipeThreshold = 50;
    const swipeDistance = touchEndX - touchStartX;

    if (Math.abs(swipeDistance) > swipeThreshold) {
        if (swipeDistance > 0 && touchStartX < 50) {
            // Swipe right from left edge - open sidebar
            if (window.innerWidth < 1024 && sidebar.classList.contains('sidebar-mobile')) {
                toggleSidebar();
            }
        } else if (swipeDistance < 0 && !sidebar.classList.contains('hidden')) {
            // Swipe left - close sidebar
            if (window.innerWidth < 1024) {
                closeMobileSidebar();
            }
        }
    }
}
        </script>
        </body>

        </html>