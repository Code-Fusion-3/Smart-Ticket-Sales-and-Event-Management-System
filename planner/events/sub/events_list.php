<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (empty($events)): ?>
    <div class="p-6 text-center">
        <p class="text-gray-500">No events found. Create your first event!</p>
        <a href="?action=create" class="mt-2 inline-block text-indigo-600 hover:text-indigo-800">
            <i class="fas fa-plus mr-1"></i> Create Event
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th
                        class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Event
                    </th>
                    <th
                        class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date & Time
                    </th>
                    <th
                        class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Venue
                    </th>
                    <th
                        class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tickets
                    </th>
                    <th
                        class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th
                        class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                <tr>
                    <td class="py-4 px-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <?php if (!empty($event['image'])): ?>
                                <img class="h-10 w-10 rounded-full object-cover" src="<?php echo $event['image']; ?>"
                                    alt="">
                                <?php else: ?>
                                <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-indigo-600"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo $event['title']; ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo $event['category']; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-4 border-b border-gray-200">
                        <div class="text-sm text-gray-900">
                            <?php echo formatDate($event['start_date']); ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo formatTime($event['start_time']); ?> -
                            <?php echo formatTime($event['end_time']); ?>
                        </div>
                    </td>
                    <td class="py-4 px-4 border-b border-gray-200">
                        <div class="text-sm text-gray-900">
                            <?php echo $event['venue']; ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo $event['city']; ?>, <?php echo $event['country']; ?>
                        </div>
                    </td>
                    <td class="py-4 px-4 border-b border-gray-200">
                        <?php
                                // Get ticket types for this event
                                $sql = "SELECT * FROM ticket_types WHERE event_id = " . $event['id'] . " ORDER BY price ASC";
                                $ticketTypes = $db->fetchAll($sql);
                                
                                // Get total tickets sold for this event
                                $sql = "SELECT COUNT(*) as count,SUM(t.purchase_price) as revenue FROM tickets t 
                                 JOIN ticket_types tt ON t.ticket_type_id = tt.id WHERE tt.event_id = " . $event['id'] . " 
                                 AND t.status = 'sold'";
                                $ticketStats = $db->fetchOne($sql);
                                
                                $sold = $ticketStats['count'] ?? 0;
                                $revenue = $ticketStats['revenue'] ?? 0;
                                $total = $event['total_tickets'];
                                $percentage = ($total > 0) ? round(($sold / $total) * 100) : 0;
                                
                                // Get price range
                                $minPrice = !empty($ticketTypes) ? min(array_column($ticketTypes, 'price')) : 0;
                                $maxPrice = !empty($ticketTypes) ? max(array_column($ticketTypes, 'price')) : 0;
                                ?>
                        <div class="text-sm text-gray-900">
                            <?php echo $sold; ?> / <?php echo $total; ?> (<?php echo $percentage; ?>%)
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-1">
                            <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%">
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php if (count($ticketTypes) > 0): ?>
                            <?php echo count($ticketTypes); ?> ticket types
                            <?php if ($minPrice == $maxPrice): ?>
                            • <?php echo formatCurrency($minPrice); ?>
                            <?php else: ?>
                            • <?php echo formatCurrency($minPrice); ?> - <?php echo formatCurrency($maxPrice); ?>
                            <?php endif; ?>
                            <?php else: ?>
                            No ticket types defined
                            <?php endif; ?>
                        </div>
                        <?php if ($revenue > 0): ?>
                        <div class="text-xs text-green-600 font-medium">
                            Revenue: <?php echo formatCurrency($revenue); ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <td class="py-4 px-4 border-b border-gray-200">
                        <?php
    // Calculate actual status based on date
    $currentDateTime = new DateTime();
    $eventDateTime = new DateTime($event['start_date'] . ' ' . ($event['start_time'] ?? '00:00:00'));
    
    // Determine actual status
    $actualStatus = $event['status'];
    if ($event['status'] === 'active' && $eventDateTime < $currentDateTime) {
        $actualStatus = 'completed';
    } elseif ($event['status'] === 'active' && $eventDateTime >= $currentDateTime) {
        $actualStatus = 'active';
    }
    
    $statusClasses = [
        'active' => 'bg-green-100 text-green-800',
        'completed' => 'bg-blue-100 text-blue-800',
        'canceled' => 'bg-red-100 text-red-800',
        'suspended' => 'bg-yellow-100 text-yellow-800',
        'expired' => 'bg-gray-100 text-gray-800'
    ];
    $statusClass = $statusClasses[$actualStatus] ?? 'bg-gray-100 text-gray-800';
    ?>
                        <span
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                            <?php echo ucfirst($actualStatus); ?>
                            <?php if ($actualStatus !== $event['status']): ?>
                            <span class="ml-1 text-xs opacity-75">(Auto)</span>
                            <?php endif; ?>
                        </span>
                    </td>

                    <td class="py-4 px-4 border-b border-gray-200 text-sm">
                        <a href="?action=edit&id=<?php echo $event['id']; ?>"
                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="event-details.php?id=<?php echo $event['id']; ?>"
                            class="text-blue-600 hover:text-blue-900 mr-3">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="../tickets/tickets.php?event_id=<?php echo $event['id']; ?>"
                            class="text-green-600 hover:text-green-900">
                            <i class="fas fa-ticket-alt"></i> Tickets
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
        <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?action=list&page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?action=list&page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to
                        <span class="font-medium"><?php echo min($offset + $perPage, $totalEvents); ?></span> of
                        <span class="font-medium"><?php echo $totalEvents; ?></span> events
                    </p>
                </div>

                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?action=list&page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>

                        <?php
                                    // Display page numbers
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    // Always show first page
                                    if ($startPage > 1) {
                                        echo '<a href="?action=list&page=1&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                1
                                              </a>';
                                        
                                        if ($startPage > 2) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white">
                                                    ...
                                                  </span>';
                                        }
                                    }
                                    
                                    // Page numbers
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isCurrentPage = $i === $page;
                                        $pageClass = $isCurrentPage 
                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' 
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
                                            
                                        echo '<a href="?action=list&page=' . $i . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $pageClass . '">
                                                ' . $i . '
                                              </a>';
                                    }
                                    
                                    // Always show last page
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white">
                                                    ...
                                                  </span>';
                                        }
                                        
                                        echo '<a href="?action=list&page=' . $totalPages . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                ' . $totalPages . '
                                              </a>';
                                    }
                                    ?>

                        <?php if ($page < $totalPages): ?>
                        <a href="?action=list&page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>