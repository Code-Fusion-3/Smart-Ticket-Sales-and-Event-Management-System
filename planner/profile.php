<?php
$pageTitle = "My Profile";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get user ID
$userId = getCurrentUserId();

// Get user data
$sql = "SELECT * FROM users WHERE id = $userId";
$user = $db->fetchOne($sql);

if (!$user) {
    $_SESSION['error_message'] = "User not found.";
    redirect('index.php');
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    // Check if email is already in use by another user
    if ($email !== $user['email']) {
        $sql = "SELECT id FROM users WHERE email = '" . $db->escape($email) . "' AND id != $userId";
        $existingUser = $db->fetchOne($sql);
        
        if ($existingUser) {
            $errors[] = "Email is already in use by another account";
        }
    }
    
    // Handle password change if requested
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (empty($newPassword)) {
            $errors[] = "New password is required";
        } elseif (strlen($newPassword) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // Handle profile image upload
    $profileImage = $user['profile_image'];
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
        $uploadDir = '../uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
        $targetFile = $uploadDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['profile_image']['tmp_name']);
        if ($check === false) {
            $errors[] = "File is not an image";
        }
        
        // Check file size (limit to 2MB)
        if ($_FILES['profile_image']['size'] > 2000000) {
            $errors[] = "File is too large (max 2MB)";
        }
        
        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
        }
        
        // If no errors, upload file
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
                $profileImage = 'uploads/profiles/' . $fileName;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        // Start building the SQL query
        $sql = "UPDATE users SET 
                username = '" . $db->escape($username) . "', 
                email = '" . $db->escape($email) . "', 
                phone_number = '" . $db->escape($phone) . "'";
        
        // Add profile image if it was updated
        if ($profileImage !== $user['profile_image']) {
            $sql .= ", profile_image = '" . $db->escape($profileImage) . "'";
        }
        
        // Add password if it was changed
        if (!empty($newPassword)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql .= ", password_hash = '" . $db->escape($passwordHash) . "'";
        }
        
        $sql .= " WHERE id = $userId";
        
        // Execute the update
        $db->query($sql);
        
        // Update session username
        $_SESSION['username'] = $username;
        
        $success = true;
        $_SESSION['success_message'] = "Profile updated successfully.";
        
        // Refresh user data
        $sql = "SELECT * FROM users WHERE id = $userId";
        $user = $db->fetchOne($sql);
    }
}

include '../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold mb-6">My Profile</h1>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <p class="font-bold">Please fix the following errors:</p>
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Information -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Profile Information</h2>
                </div>

                <div class="p-6">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="username" class="block text-gray-700 font-bold mb-2">Username *</label>
                                <input type="text" id="username" name="username"
                                    value="<?php echo htmlspecialchars($user['username']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>

                            <div>
                                <label for="email" class="block text-gray-700 font-bold mb-2">Email Address *</label>
                                <input type="email" id="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>

                            <div>
                                <label for="phone" class="block text-gray-700 font-bold mb-2">Phone Number *</label>
                                <input type="text" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($user['phone_number']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>

                            <div>
                                <label for="role" class="block text-gray-700 font-bold mb-2">Account Type</label>
                                <input type="text" id="role"
                                    value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                                <p class="text-sm text-gray-500 mt-1">Account type cannot be changed</p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="profile_image" class="block text-gray-700 font-bold mb-2">Profile Image</label>
                            <div class="flex items-center">
                                <div class="mr-4">
                                    <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $user['profile_image']; ?>" alt="Profile"
                                        class="w-24 h-24 rounded-full object-cover">
                                    <?php else: ?>
                                    <div class="w-24 h-24 rounded-full bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500 text-4xl"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <input type="file" id="profile_image" name="profile_image"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                                    <p class="text-sm text-gray-500 mt-1">Max file size: 2MB. Supported formats: JPG,
                                        PNG, GIF</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-6 mt-6">
                            <h3 class="text-lg font-bold mb-4">Change Password</h3>
                            <p class="text-gray-600 mb-4">Leave these fields empty if you don't want to change your
                                password.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="current_password" class="block text-gray-700 font-bold mb-2">Current
                                        Password</label>
                                    <input type="password" id="current_password" name="current_password"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                                </div>

                                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="new_password" class="block text-gray-700 font-bold mb-2">New
                                            Password</label>
                                        <input type="password" id="new_password" name="new_password"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                                        <p class="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
                                    </div>

                                    <div>
                                        <label for="confirm_password" class="block text-gray-700 font-bold mb-2">Confirm
                                            New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Summary -->
        <div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Account Summary</h2>
                </div>

                <div class="p-6">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b">
                        <div class="text-gray-600">Account Balance</div>
                        <div class="text-xl font-bold text-indigo-600"><?php echo formatCurrency($user['balance']); ?>
                        </div>
                    </div>

                    <?php
                    // Get account statistics
                    $sql = "SELECT COUNT(*) as total_events FROM events WHERE planner_id = $userId";
                    $eventsStats = $db->fetchOne($sql);
                    
                    $sql = "SELECT 
                                COUNT(*) as total_tickets,
                                SUM(t.purchase_price) as total_sales
                            FROM tickets t
                            JOIN events e ON t.event_id = e.id
                            WHERE e.planner_id = $userId
                            AND t.status = 'sold'";
                    $ticketStats = $db->fetchOne($sql);
                    
                    $sql = "SELECT created_at FROM users WHERE id = $userId";
                    $userCreated = $db->fetchOne($sql);
                    ?>

                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <div class="text-gray-600">Total Events</div>
                            <div class="font-medium"><?php echo $eventsStats['total_events'] ?? 0; ?></div>
                        </div>

                        <div class="flex justify-between">
                            <div class="text-gray-600">Tickets Sold</div>
                            <div class="font-medium"><?php echo $ticketStats['total_tickets'] ?? 0; ?></div>
                        </div>

                        <div class="flex justify-between">
                            <div class="text-gray-600">Total Sales</div>
                            <div class="font-medium"><?php echo formatCurrency($ticketStats['total_sales'] ?? 0); ?>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <div class="text-gray-600">Member Since</div>
                            <div class="font-medium"><?php echo formatDate($userCreated['created_at'] ?? ''); ?></div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="finances/index.php"
                            class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center font-bold py-2 px-4 rounded">
                            View Financial Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Account Actions</h2>
                </div>

                <div class="p-6">
                    <div class="space-y-4">
                        <a href="events/events.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-calendar-alt mr-2"></i> Manage Events
                        </a>

                        <a href="tickets/tickets.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-ticket-alt mr-2"></i> Manage Tickets
                        </a>

                        <a href="finances/withdraw.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-money-bill-wave mr-2"></i> Withdraw Funds
                        </a>

                        <a href="settings.php" class="hidden items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-cog mr-2"></i> Account Settings
                        </a>

                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <a href="../logout.php" class="flex items-center text-red-600 hover:text-red-800">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php  ?>