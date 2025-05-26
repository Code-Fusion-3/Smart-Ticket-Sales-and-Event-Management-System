<?php
$pageTitle = "Register";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect based on user role
    if (hasRole('admin')) {
        redirect('admin/index.php');
    } elseif (hasRole('event_planner')) {
        redirect('planner/index.php');
    } elseif (hasRole('agent')) {
        redirect('agent/index.php');
    } else {
        redirect('customer/index.php');
    }
}

$username = '';
$email = '';
$phone = '';
$role = 'customer';
$errors = [];

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'customer';
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    if (!in_array($role, ['customer', 'event_planner'])) {
        $errors[] = "Invalid role selected";
    }
    
    // If no errors, register user
    if (empty($errors)) {
        $result = registerUser($username, $email, $password, $phone, $role);
        
        if ($result['success']) {
            // Set success message
            $_SESSION['success_message'] = "Registration successful! You can now login.";
            redirect('login.php');
        } else {
            $errors[] = $result['message'];
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden mt-4">
    <div class="py-4 px-6 bg-indigo-600 text-white text-center">
        <h2 class="text-2xl font-bold">Create an Account</h2>
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
        
        <form method="POST" action="">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 font-bold mb-2">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4">
                <label for="phone" class="block text-gray-700 font-bold mb-2">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4">
                <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4">
                <label for="confirm_password" class="block text-gray-700 font-bold mb-2">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Account Type</label>
                <div class="flex space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="role" value="customer" <?php echo $role === 'customer' ? 'checked' : ''; ?> class="mr-2">
                        <span>Customer</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="role" value="event_planner" <?php echo $role === 'event_planner' ? 'checked' : ''; ?> class="mr-2">
                        <span>Event Planner</span>
                    </label>
                </div>
            </div>
            
            <div class="mb-6">
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-300">
                    Register
                </button>
            </div>
            
            <div class="text-center">
                <p class="text-gray-600">
                    Already have an account? <a href="login.php" class="text-indigo-600 hover:text-indigo-800">Login</a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>