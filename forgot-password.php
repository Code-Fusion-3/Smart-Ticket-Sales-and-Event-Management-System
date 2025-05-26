<?php
$pageTitle = "Forgot Password";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$email = '';
$errors = [];
$showResetForm = false;
$userId = null;

// Process email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If no errors, check if email exists
    if (empty($errors)) {
        $sql = "SELECT id FROM users WHERE email = '" . $db->escape($email) . "'";
        $user = $db->fetchOne($sql);
        
        if ($user) {
            // Email exists, show reset form
            $showResetForm = true;
            $userId = $user['id'];
        } else {
            // Email doesn't exist
            $errors[] = "Email not found. Please create an account.";
        }
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $userId = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, reset password
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password
        $sql = "UPDATE users SET password_hash = '" . $db->escape($passwordHash) . "' WHERE id = $userId";
        $result = $db->query($sql);
        
        if ($result) {
            // Set success message
            $_SESSION['success_message'] = "Your password has been reset successfully. You can now login with your new password.";
            redirect('login.php');
        } else {
            $errors[] = "Failed to reset password. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden mt-4">
    <div class="py-4 px-6 bg-indigo-600 text-white text-center">
        <h2 class="text-2xl font-bold"><?php echo $showResetForm ? 'Reset Password' : 'Forgot Password'; ?></h2>
    </div>
    
    <div class="p-6">
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($showResetForm): ?>
            <form method="POST" action="">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-bold mb-2">New Password</label>
                    <input type="password" id="password" name="password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="block text-gray-700 font-bold mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           required>
                </div>
                
                <div class="mb-6">
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-300">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="mb-4 text-gray-700">Enter your email address below to reset your password.</p>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-bold mb-2">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           required>
                </div>
                
                <div class="mb-6">
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-300">
                        Continue
                    </button>
                </div>
                
                <div class="text-center">
                    <p class="text-gray-600">
                        Remember your password? <a href="login.php" class="text-indigo-600 hover:text-indigo-800">Login</a>
                    </p>
                    <p class="text-gray-600 mt-2">
                        Don't have an account? <a href="register.php" class="text-indigo-600 hover:text-indigo-800">Register</a>
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
