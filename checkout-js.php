<script>
document.addEventListener('DOMContentLoaded', function() {
    // User information for quick fill
    const userInfo = {
        name: '<?php echo addslashes($user['username']); ?>',
        email: '<?php echo addslashes($user['email']); ?>',
        phone: '<?php echo addslashes($user['phone_number']); ?>'
    };

    // Payment method handling
    const paymentMethods = document.querySelectorAll('.payment-method-radio');
    const paymentDetails = document.querySelectorAll('.payment-details');
    const useBalanceCheckbox = document.getElementById('use_balance');
    const paymentMethodSelection = document.getElementById('payment-method-selection');
    const paymentButton = document.getElementById('payment-button');
    const selectedPaymentMethodSpan = document.getElementById('selected-payment-method');
    const balanceUsage = document.getElementById('balance-usage');
    const finalTotal = document.getElementById('final-total');

    // Quick fill functions
    window.fillMyInfo = function(button, itemId, ticketIndex) {
        const container = button.closest('.bg-gray-50');
        container.querySelector(`input[name="recipient_name_${itemId}_${ticketIndex}"]`).value = userInfo
            .name;
        container.querySelector(`input[name="recipient_email_${itemId}_${ticketIndex}"]`).value = userInfo
            .email;
        container.querySelector(`input[name="recipient_phone_${itemId}_${ticketIndex}"]`).value = userInfo
            .phone;

        // Add visual feedback
        button.style.backgroundColor = '#10B981';
        button.style.color = 'white';
        button.innerHTML = '<i class="fas fa-check mr-1"></i>Applied';
        setTimeout(() => {
            button.style.backgroundColor = '';
            button.style.color = '';
            button.innerHTML = 'Use My Info';
        }, 2000);
    };

    window.copyFromPrevious = function(button, itemId, ticketIndex) {
        if (ticketIndex > 0) {
            const currentContainer = button.closest('.bg-gray-50');
            const previousContainer = currentContainer.parentElement.children[ticketIndex - 1];

            const prevName = previousContainer.querySelector(
                `input[name="recipient_name_${itemId}_${ticketIndex - 1}"]`).value;
            const prevEmail = previousContainer.querySelector(
                `input[name="recipient_email_${itemId}_${ticketIndex - 1}"]`).value;
            const prevPhone = previousContainer.querySelector(
                `input[name="recipient_phone_${itemId}_${ticketIndex - 1}"]`).value;

            currentContainer.querySelector(`input[name="recipient_name_${itemId}_${ticketIndex}"]`).value =
                prevName;
            currentContainer.querySelector(`input[name="recipient_email_${itemId}_${ticketIndex}"]`).value =
                prevEmail;
            currentContainer.querySelector(`input[name="recipient_phone_${itemId}_${ticketIndex}"]`).value =
                prevPhone;

            // Add visual feedback
            button.style.backgroundColor = '#10B981';
            button.style.color = 'white';
            button.innerHTML = '<i class="fas fa-check mr-1"></i>Copied';
            setTimeout(() => {
                button.style.backgroundColor = '';
                button.style.color = '';
                button.innerHTML = 'Copy from Previous';
            }, 2000);
        }
    };

    // Payment method selection
    function togglePaymentDetails() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value ||
            'credit_card';

        // Hide all payment details
        paymentDetails.forEach(detail => {
            detail.classList.add('hidden');
        });

        // Show selected payment method details
        if (selectedMethod === 'credit_card') {
            document.getElementById('credit-card-details').classList.remove('hidden');
            selectedPaymentMethodSpan.textContent = 'Credit Card';
        } else if (selectedMethod === 'mobile_money') {
            document.getElementById('mobile-money-details').classList.remove('hidden');
            selectedPaymentMethodSpan.textContent = 'Mobile Money';
        }

        // Update payment method option styling
        document.querySelectorAll('.payment-method-option').forEach(option => {
            option.classList.remove('border-indigo-500', 'bg-indigo-50');
            option.classList.add('border-gray-200');
        });

        const selectedOption = document.querySelector(`input[value="${selectedMethod}"]`).closest(
            '.payment-method-option');
        selectedOption.classList.remove('border-gray-200');
        selectedOption.classList.add('border-indigo-500', 'bg-indigo-50');
    }

    // Balance usage handling
    function togglePaymentMethodSection() {
        const userBalance = <?php echo $user['balance']; ?>;
        const totalAmount = <?php echo $total; ?>;

        if (useBalanceCheckbox && useBalanceCheckbox.checked) {
            if (userBalance >= totalAmount) {
                // Balance covers full amount
                paymentMethodSelection.classList.add('hidden');
                paymentDetails.forEach(detail => detail.classList.add('hidden'));
                paymentButton.innerHTML = '<i class="fas fa-wallet mr-2"></i>Complete Purchase Using Balance';
                selectedPaymentMethodSpan.textContent = 'Account Balance';
                finalTotal.textContent = '<?php echo formatCurrency(0); ?>';
            } else {
                // Partial balance usage
                paymentMethodSelection.classList.remove('hidden');
                togglePaymentDetails();
                paymentButton.innerHTML =
                    '<i class="fas fa-credit-card mr-2"></i>Complete Purchase - <?php echo formatCurrency($total - $user['balance']); ?>';
                finalTotal.textContent = '<?php echo formatCurrency($total - $user['balance']); ?>';
            }
            balanceUsage.style.display = 'flex';
        } else {
            // No balance usage
            paymentMethodSelection.classList.remove('hidden');
            togglePaymentDetails();
            paymentButton.innerHTML =
                '<i class="fas fa-lock mr-2"></i>Complete Secure Purchase - <?php echo formatCurrency($total); ?>';
            selectedPaymentMethodSpan.textContent = document.querySelector(
                    'input[name="payment_method"]:checked')?.value === 'mobile_money' ? 'Mobile Money' :
                'Credit Card';
            finalTotal.textContent = '<?php echo formatCurrency($total); ?>';
            balanceUsage.style.display = 'none';
        }
    }

    // Event listeners
    paymentMethods.forEach(method => {
        method.addEventListener('change', togglePaymentDetails);
    });

    if (useBalanceCheckbox) {
        useBalanceCheckbox.addEventListener('change', togglePaymentMethodSection);
    }

    // Card number formatting
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            if (formattedValue !== e.target.value) {
                e.target.value = formattedValue;
            }
        });
    }

    // Form validation and submission
    const form = document.getElementById('checkout-form');
    form.addEventListener('submit', function(e) {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        const useBalance = useBalanceCheckbox?.checked || false;
        const userBalance = <?php echo $user['balance']; ?>;
        const totalAmount = <?php echo $total; ?>;

        // If using balance and balance covers total, proceed without additional validation
        if (useBalance && userBalance >= totalAmount) {
            showProcessingOverlay('Processing payment using account balance...');
            return true;
        }

        // Validate payment details based on selected method
        if (selectedMethod === 'credit_card') {
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const cardName = document.getElementById('card_name').value.trim();
            const cardCvv = document.getElementById('card_cvv').value.trim();

            if (!cardNumber || !cardName || !cardCvv) {
                e.preventDefault();
                alert('Please fill in all credit card details.');
                return false;
            }

            if (cardNumber !== '4242424242424242' && !confirm(
                    'For testing, please use card number 4242 4242 4242 4242. Continue anyway?')) {
                e.preventDefault();
                return false;
            }

            showProcessingOverlay('Processing credit card payment...');

        } else if (selectedMethod === 'mobile_money') {
            const mobileNumber = document.getElementById('mobile_number').value.trim();

            if (!mobileNumber) {
                e.preventDefault();
                alert('Please enter your mobile number.');
                return false;
            }

            if (mobileNumber !== '0700000000' && !confirm(
                    'For testing, please use mobile number 0700000000. Continue anyway?')) {
                e.preventDefault();
                return false;
            }

            showProcessingOverlay('Sending payment request to your mobile phone...');
        }

        // Validate recipient information
        const requiredFields = form.querySelectorAll('input[required]');
        for (let field of requiredFields) {
            if (!field.value.trim()) {
                e.preventDefault();
                field.focus();
                alert('Please fill in all required fields.');
                return false;
            }
        }

        return true;
    });

    // Processing overlay function
    function showProcessingOverlay(message) {
        let overlay = document.getElementById('payment-processing-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'payment-processing-overlay';
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            overlay.innerHTML = `
                <div class="bg-white p-8 rounded-lg shadow-lg text-center max-w-md mx-4">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-500 mx-auto mb-6"></div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Processing Payment</h3>
                    <p id="processing-message" class="text-gray-600 mb-4">${message}</p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            This is a demo environment. No actual payment is being processed.
                        </p>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        } else {
            document.getElementById('processing-message').textContent = message;
            overlay.classList.remove('hidden');
        }
    }

    // Auto-fill email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('border-red-500');
                this.classList.remove('border-gray-300');

                // Show error message
                let errorMsg = this.parentElement.querySelector('.email-error');
                if (!errorMsg) {
                    errorMsg = document.createElement('p');
                    errorMsg.className = 'email-error text-xs text-red-500 mt-1';
                    errorMsg.innerHTML =
                        '<i class="fas fa-exclamation-circle mr-1"></i>Please enter a valid email address';
                    this.parentElement.appendChild(errorMsg);
                }
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-gray-300');

                // Remove error message
                const errorMsg = this.parentElement.querySelector('.email-error');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
        });
    });

    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + ' ' + value.slice(3);
                } else {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6,
                        9);
                }
            }
            e.target.value = value;
        });
    });

    // Initialize
    togglePaymentDetails();
    if (useBalanceCheckbox) {
        togglePaymentMethodSection();
    }

    // Add smooth animations
    const formSections = document.querySelectorAll('.bg-white.rounded-lg.shadow-md');
    formSections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';

        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // Add loading states to buttons
    const buttons = document.querySelectorAll('button[type="button"]');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            }, 1000);
        });
    });

    // Real-time form validation feedback
    const requiredInputs = document.querySelectorAll('input[required]');
    requiredInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('border-red-300');
                this.classList.add('border-green-300');
            } else {
                this.classList.remove('border-green-300');
                this.classList.add('border-red-300');
            }
        });
    });

    // Scroll to first error on form submission
    form.addEventListener('invalid', function(e) {
        e.preventDefault();
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            firstInvalid.focus();
        }
    }, true);
});

// Additional utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-RW', {
        style: 'currency',
        currency: 'RWF',
        minimumFractionDigits: 0
    }).format(amount);
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-exclamation-circle' :
                'fa-info-circle'
            } mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}
</script>