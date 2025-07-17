<?php
$pageTitle = "Create New Agent";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/notifications.php';

// Check if user has admin permission
checkPermission('admin');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($username) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    // Check for existing email
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = '" . $db->escape($email) . "'");
    if ($existing) {
        $errors[] = "Email is already registered.";
    }

    if (empty($errors)) {
        // Register agent
        $userId = registerUser($username, $email, $password, $phone, 'agent');
        if ($userId) {
            // Send welcome email
            $subject = "Welcome to " . SITE_NAME . " as an Agent";
            $body = "<h2>Welcome, $username!</h2>"
                . "<p>Your agent account has been created. You can now log in with these credentials:</p>"
                . "<ul>"
                . "<li><strong>Email:</strong> $email</li>"
                . "<li><strong>Password:</strong> $password</li>"
                . "</ul>"
                . "<p>If you have any questions, contact the admin.</p>";
            sendEmail($email, $subject, $body);
            $_SESSION['success_message'] = "Agent created and welcome email sent.";
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Failed to create agent. Please try again.";
        }
    }
}

include '../../includes/admin_header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6 max-w-xl">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-4">Create New Agent</h1>
        <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Full Name</label>
                <input type="text" name="username"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Email Address</label>
                <input type="email" name="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Phone Number</label>
                <input type="number" name="phone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Password</label>
                <input type="password" name="password" value="passowrd123"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    readonly>
            </div>
            <div class="mb-6 ">
                <label class="block text-gray-700 font-bold mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" value="password123"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    required>
            </div>
            <div class="flex justify-between items-center">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200">
                    <i class="fas fa-user-plus mr-2"></i>Create Agent
                </button>
                <a href="index.php" class="text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include '../../includes/admin_footer.php'; ?>