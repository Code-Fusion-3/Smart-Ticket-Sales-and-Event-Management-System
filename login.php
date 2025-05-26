<?php
$pageTitle = "Login";
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

$email = '';
$errors = [];

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // Set remember me cookie if checked
            if ($remember) {
                $token = generateRandomString(32);
                $userId = $result['user']['id'];
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store token in database
                $sql = "INSERT INTO user_tokens (user_id, token, expires_at) 
                        VALUES ($userId, '" . $db->escape($token) . "', FROM_UNIXTIME($expiry))";
                $db->query($sql);
                
                // Set cookie
                setcookie('remember_token', $token, $expiry, '/', '', false, true);
            }
            
            // Redirect based on user role
            if (hasRole('admin')) {
                redirect('admin/index.php');
            } elseif (hasRole('event_planner')) {
                redirect('planner/index.php');
            } elseif (hasRole('agent')) {
                redirect('agent/index.php');
            } else {
                redirect('');
            }
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Check for remember me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $sql = "SELECT u.* FROM users u 
            JOIN user_tokens t ON u.id = t.user_id 
            WHERE t.token = '" . $db->escape($token) . "' 
            AND t.expires_at > NOW()";
    
    $user = $db->fetchOne($sql);
    
    if ($user) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
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
    } else {
        // Invalid or expired token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden mt-4">
    <div class="py-4 px-6 bg-indigo-600 text-white text-center">
        <h2 class="text-2xl font-bold">Login to Your Account</h2>
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
                <label for="email" class="block text-gray-700 font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4">
                <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       required>
            </div>
            
            <div class="mb-4 flex items-center">
                <input type="checkbox" id="remember" name="remember" class="mr-2">
                <label for="remember" class="text-gray-700">Remember me</label>
            </div>
            
            <div class="mb-6">
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition duration-300">
                    Login
                </button>
            </div>
            
            <div class="text-center">
                <p class="text-gray-600 mb-2">
                    <a href="forgot-password.php" class="text-indigo-600 hover:text-indigo-800">Forgot your password?</a>
                </p>
                <p class="text-gray-600">
                    Don't have an account? <a href="register.php" class="text-indigo-600 hover:text-indigo-800">Register</a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>