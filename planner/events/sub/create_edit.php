<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold"><?php echo $action == 'create' ? 'Create New Event' : 'Edit Event'; ?></h1>
        <a href="events.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to Events
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc pl-4">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="<?php echo $action; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div>
                    <h2 class="text-xl font-bold mb-4">Basic Information</h2>

                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 font-bold mb-2">Event Title *</label>
                        <input type="text" id="title" name="title"
                            value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                    </div>

                    <div class="mb-4">
                        <label for="category" class="block text-gray-700 font-bold mb-2">Category</label>
                        <select id="category" name="category"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                            <option value="">Select Category</option>
                            <option value="Conference"
                                <?php echo ($event['category'] ?? '') == 'Conference' ? 'selected' : ''; ?>>
                                Conference
                            </option>
                            <option value="Concert"
                                <?php echo ($event['category'] ?? '') == 'Concert' ? 'selected' : ''; ?>>Concert
                            </option>
                            <option value="Exhibition"
                                <?php echo ($event['category'] ?? '') == 'Exhibition' ? 'selected' : ''; ?>>
                                Exhibition
                            </option>
                            <option value="Workshop"
                                <?php echo ($event['category'] ?? '') == 'Workshop' ? 'selected' : ''; ?>>Workshop
                            </option>
                            <option value="Seminar"
                                <?php echo ($event['category'] ?? '') == 'Seminar' ? 'selected' : ''; ?>>Seminar
                            </option>
                            <option value="Festival"
                                <?php echo ($event['category'] ?? '') == 'Festival' ? 'selected' : ''; ?>>Festival
                            </option>
                            <option value="Sports"
                                <?php echo ($event['category'] ?? '') == 'Sports' ? 'selected' : ''; ?>>Sports
                            </option>
                            <option value="Other"
                                <?php echo ($event['category'] ?? '') == 'Other' ? 'selected' : ''; ?>>
                                Other
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="5"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="image" class="block text-gray-700 font-bold mb-2">Event Image</label>
                        <?php if (!empty($event['image'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo $event['image']; ?>" alt="Event Image"
                                class="w-32 h-32 object-cover rounded">
                            <p class="text-sm text-gray-500 mt-1">Current image</p>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        <p class="text-sm text-gray-500 mt-1">Recommended size: 1200x630 pixels. Max size: 5MB.</p>
                    </div>
                </div>

                <!-- Venue & Time -->
                <div>
                    <h2 class="text-xl font-bold mb-4">Venue & Time</h2>

                    <div class="mb-4">
                        <label for="venue" class="block text-gray-700 font-bold mb-2">Venue Name *</label>
                        <input type="text" id="venue" name="venue"
                            value="<?php echo htmlspecialchars($event['venue'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                    </div>

                    <div class="mb-4">
                        <label for="address" class="block text-gray-700 font-bold mb-2">Address</label>
                        <input type="text" id="address" name="address"
                            value="<?php echo htmlspecialchars($event['address'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="city" class="block text-gray-700 font-bold mb-2">City</label>
                            <input type="text" id="city" name="city"
                                value="<?php echo htmlspecialchars($event['city'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="country" class="block text-gray-700 font-bold mb-2">Country</label>
                            <input type="text" id="country" name="country"
                                value="<?php echo htmlspecialchars($event['country'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="start_date" class="block text-gray-700 font-bold mb-2">Start Date *</label>
                            <input type="date" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($event['start_date'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                        <div>
                            <label for="end_date" class="block text-gray-700 font-bold mb-2">End Date *</label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($event['end_date'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="start_time" class="block text-gray-700 font-bold mb-2">Start Time *</label>
                            <input type="time" id="start_time" name="start_time"
                                value="<?php echo htmlspecialchars($event['start_time'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                        <div>
                            <label for="end_time" class="block text-gray-700 font-bold mb-2">End Time *</label>
                            <input type="time" id="end_time" name="end_time"
                                value="<?php echo htmlspecialchars($event['end_time'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tickets & Status -->
            <div class="mt-6 border-t pt-6">
                <h2 class="text-xl font-bold mb-4">Ticket Types & Pricing</h2>

                <div id="ticket-types-container">
                    <?php
                    // Check if we're editing and have ticket types to display
                    if ($action == 'edit' && isset($event['id'])):
                        // Fetch ticket types for this event
                        $sql = "SELECT * FROM ticket_types WHERE event_id = " . $event['id'] . " ORDER BY price ASC";
                        $ticketTypes = $db->fetchAll($sql);

                        if (!empty($ticketTypes)):
                            foreach ($ticketTypes as $index => $type):
                                ?>
                    <div class="ticket-type-row bg-gray-50 p-4 rounded-md mb-4 relative">
                        <?php if (count($ticketTypes) > 1): ?>
                        <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700"
                            onclick="this.parentElement.remove()">
                            <i class="fas fa-times-circle"></i>
                        </button>
                        <?php endif; ?>
                        <input type="hidden" name="ticket_types[<?php echo $index; ?>][id]"
                            value="<?php echo $type['id']; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                                <input type="text" name="ticket_types[<?php echo $index; ?>][name]"
                                    value="<?php echo htmlspecialchars($type['name']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Price
                                    (<?php echo CURRENCY_SYMBOL; ?>)
                                    *</label>
                                <input type="number" name="ticket_types[<?php echo $index; ?>][price]"
                                    value="<?php echo htmlspecialchars($type['price']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="0" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                                <input type="number" name="ticket_types[<?php echo $index; ?>][quantity]"
                                    value="<?php echo htmlspecialchars($type['total_tickets']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="1" required>
                                <?php
                                            // Get tickets sold for this type
                                            $sql = "SELECT COUNT(*) as count FROM tickets WHERE ticket_type_id = " . $type['id'] . " AND status = 'sold'";
                                            $ticketCount = $db->fetchOne($sql);
                                            $soldTickets = $ticketCount['count'] ?? 0;

                                            if ($soldTickets > 0):
                                                ?>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php echo $soldTickets; ?> tickets already sold. You cannot set quantity less
                                    than
                                    this.
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea name="ticket_types[<?php echo $index; ?>][description]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                rows="2"><?php echo htmlspecialchars($type['description']); ?></textarea>
                        </div>
                    </div>
                    <?php
                            endforeach;
                        else:
                            // No ticket types found, show default empty form
                            ?>
                    <div class="ticket-type-row bg-gray-50 p-4 rounded-md mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                                <input type="text" name="ticket_types[0][name]" placeholder="e.g. Regular, VIP, etc."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Price
                                    (<?php echo CURRENCY_SYMBOL; ?>)
                                    *</label>
                                <input type="number" name="ticket_types[0][price]" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="0" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                                <input type="number" name="ticket_types[0][quantity]" placeholder="100"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="1" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea name="ticket_types[0][description]"
                                placeholder="Describe what's included with this ticket type..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                rows="2"></textarea>
                        </div>
                    </div>
                    <?php
                        endif;
                    else:
                        // Creating a new event, show default empty form
                        ?>
                    <div class="ticket-type-row bg-gray-50 p-4 rounded-md mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                                <input type="text" name="ticket_types[0][name]" placeholder="e.g. Regular, VIP, etc."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Price
                                    (<?php echo CURRENCY_SYMBOL; ?>)
                                    *</label>
                                <input type="number" name="ticket_types[0][price]" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="0" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                                <input type="number" name="ticket_types[0][quantity]" placeholder="100"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="1" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea name="ticket_types[0][description]"
                                placeholder="Describe what's included with this ticket type..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                rows="2"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-6">
                    <button type="button" id="add-ticket-type"
                        class="text-indigo-600 hover:text-indigo-800 font-medium">
                        <i class="fas fa-plus-circle mr-1"></i> Add Another Ticket Type
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="status" class="block text-gray-700 font-bold mb-2">Event Status</label>
                        <select id="status" name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                            <option value="active"
                                <?php echo ($event['status'] ?? '') == 'active' ? 'selected' : ''; ?>>
                                Active
                            </option>
                            <option value="completed"
                                <?php echo ($event['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed
                            </option>
                            <option value="canceled"
                                <?php echo ($event['status'] ?? '') == 'canceled' ? 'selected' : ''; ?>>Canceled
                            </option>
                            <option value="suspended"
                                <?php echo ($event['status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- JavaScript for dynamic ticket types -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('ticket-types-container');
                const addButton = document.getElementById('add-ticket-type');
                let ticketTypeCount =
                    <?php echo ($action == 'edit' && !empty($ticketTypes)) ? count($ticketTypes) : 1; ?>;

                addButton.addEventListener('click', function() {
                    const newRow = document.createElement('div');
                    newRow.className = 'ticket-type-row bg-gray-50 p-4 rounded-md mb-4 relative';

                    newRow.innerHTML = `
            <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700" onclick="this.parentElement.remove()">
                <i class="fas fa-times-circle"></i>
            </button>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                    <input type="text" name="ticket_types[${ticketTypeCount}][name]" placeholder="e.g. Regular, VIP, etc." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Price (<?php echo CURRENCY_SYMBOL; ?>) *</label>
                    <input type="number" name="ticket_types[${ticketTypeCount}][price]" placeholder="0.00" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           min="0" step="0.01" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                    <input type="number" name="ticket_types[${ticketTypeCount}][quantity]" placeholder="100" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           min="1" required>
                </div>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Description</label>
                <textarea name="ticket_types[${ticketTypeCount}][description]" placeholder="Describe what's included with this ticket type..." 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                          rows="2"></textarea>
            </div>
        `;

                    container.appendChild(newRow);
                    ticketTypeCount++;
                });

                // === Event date and time validation ===
                const form = document.querySelector('form');
                const startDateInput = document.getElementById('start_date');
                const endDateInput = document.getElementById('end_date');
                const startTimeInput = document.getElementById('start_time');
                const endTimeInput = document.getElementById('end_time');

                // Set min attribute to today for date pickers
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                const todayStr = `${yyyy}-${mm}-${dd}`;
                startDateInput.setAttribute('min', todayStr);
                endDateInput.setAttribute('min', todayStr);

                // Create error message element
                let dateError = document.createElement('div');
                dateError.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4';
                dateError.style.display = 'none';
                form.insertBefore(dateError, form.firstChild);

                // Add error message containers below end date and end time fields
                const endDateField = document.getElementById('end_date');
                let endDateError = document.createElement('div');
                endDateError.className = 'text-red-600 text-sm mt-1';
                endDateError.style.display = 'none';
                endDateField.parentNode.appendChild(endDateError);

                const endTimeField = document.getElementById('end_time');
                let endTimeError = document.createElement('div');
                endTimeError.className = 'text-red-600 text-sm mt-1';
                endTimeError.style.display = 'none';
                endTimeField.parentNode.appendChild(endTimeError);

                form.addEventListener('submit', function(e) {
                    dateError.style.display = 'none';
                    dateError.innerHTML = '';
                    endDateError.style.display = 'none';
                    endDateError.innerHTML = '';
                    endTimeError.style.display = 'none';
                    endTimeError.innerHTML = '';
                    let hasError = false;

                    const startDate = startDateInput.value;
                    const endDate = endDateInput.value;
                    const startTime = startTimeInput.value;
                    const endTime = endTimeInput.value;

                    // Check for past dates
                    if (startDate && startDate < todayStr) {
                        hasError = true;
                        dateError.innerHTML += '<p>Start date cannot be in the past.</p>';
                        dateError.style.display = 'block';
                    }
                    if (endDate && endDate < todayStr) {
                        hasError = true;
                        dateError.innerHTML += '<p>End date cannot be in the past.</p>';
                        dateError.style.display = 'block';
                    }

                    if (startDate && endDate) {
                        if (endDate < startDate) {
                            hasError = true;
                            endDateError.innerHTML = 'End date cannot be before start date.';
                            endDateError.style.display = 'block';
                        } else if (endDate === startDate && startTime && endTime) {
                            if (endTime <= startTime) {
                                hasError = true;
                                endTimeError.innerHTML =
                                    'End time must be after start time when the dates are the same.';
                                endTimeError.style.display = 'block';
                            }
                        }
                    }

                    if (hasError) {
                        e.preventDefault();
                    }
                });
            });
            </script>



            <div class="mt-6 border-t pt-6 flex justify-end">
                <a href="events.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded mr-2">
                    Cancel
                </a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <?php echo $action == 'create' ? 'Create Event' : 'Update Event'; ?>
                </button>
            </div>
        </form>
    </div>
</div>


<div class="mt-6 border-t pt-6 flex justify-end">
    <a href="events.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded mr-2">
        Cancel
    </a>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
        <?php echo $action == 'create' ? 'Create Event' : 'Update Event'; ?>
    </button>
</div>
</form>
</div>
</div>