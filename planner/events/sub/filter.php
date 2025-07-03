<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold">Manage Events</h1>
    <a href="?action=create" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Create New Event
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="" class="flex flex-wrap items-end gap-4">
        <input type="hidden" name="action" value="list">

        <div class="w-full md:w-auto flex-grow">
            <label for="search" class="block text-gray-700 font-bold mb-2">Search</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                placeholder="Search by title, venue, city...">
        </div>

        <div class="w-full md:w-auto">
            <label for="status" class="block text-gray-700 font-bold mb-2">Status</label>
            <select id="status" name="status"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="canceled" <?php echo $status == 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>

        <div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-search mr-2"></i> Filter
            </button>
        </div>

        <?php if (!empty($search) || $status != 'all'): ?>
        <div>
            <a href="?action=list" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-times mr-1"></i> Clear Filters
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>