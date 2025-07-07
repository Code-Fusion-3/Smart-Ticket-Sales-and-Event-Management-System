<?php
$pageTitle = "Edit Agent";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkPermission('admin');

$agentId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
if ($agentId <= 0) {
    $_SESSION['error_message'] = "Invalid agent ID.";
    header('Location: index.php');
    exit;
}

$agent = $db->fetchOne("SELECT * FROM users WHERE id = $agentId AND role = 'agent'");
if (!$agent) {
    $_SESSION['error_message'] = "Agent not found.";
    header('Location: index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($email) || empty($phone)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }
    // Check for duplicate email (exclude self)
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = '" . $db->escape($email) . "' AND id != $agentId");
    if ($existing) {
        $errors[] = "Email is already registered to another user.";
    }
    if (empty($errors)) {
        $sql = "UPDATE users SET username = '" . $db->escape($username) . "', email = '" . $db->escape($email) . "', phone_number = '" . $db->escape($phone) . "', updated_at = NOW() WHERE id = $agentId";
        if ($db->query($sql)) {
            $_SESSION['success_message'] = "Agent profile updated.";
            header('Location: view.php?id=' . $agentId);
            exit;
        } else {
            $errors[] = "Failed to update agent. Please try again.";
        }
    }
} else {
    $username = $agent['username'];
    $email = $agent['email'];
    $phone = $agent['phone_number'];
}

include '../../includes/admin_header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6 max-w-xl">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-4 flex items-center">
            <i class="fas fa-user-edit mr-2 text-blue-600"></i>Edit Agent
        </h1>
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
            <input type="hidden" name="id" value="<?php echo $agentId; ?>">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Full Name</label>
                <input type="text" name="username"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Email Address</label>
                <input type="email" name="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 font-bold mb-2">Phone Number</label>
                <input type="text" name="phone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    value="<?php echo htmlspecialchars($phone); ?>" required>
            </div>
            <div class="flex justify-between items-center">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
                <a href="view.php?id=<?php echo $agentId; ?>" class="text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include '../../includes/admin_footer.php'; ?>