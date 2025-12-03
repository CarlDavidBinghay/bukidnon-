<?php
// Include the database connection
include('db_config.php');

// Start the session
session_start();

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

// User login validation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get user input and sanitize
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate input
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } else {
        try {
            // Query the database for the user
            $sql = "SELECT id, username, email, password FROM users WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database prepare failed");
            }
            
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Debug: Log password comparison
                error_log("Login attempt for: " . $email);
                error_log("Stored hash: " . $user['password']);
                error_log("Password match: " . (password_verify($password, $user['password']) ? 'YES' : 'NO'));
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Store user info in session
                    $_SESSION['logged_in'] = true;
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_id'] = $user['id'];

                    // Redirect to dashboard after successful login
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Invalid password error message
                    $error_message = "Invalid password. Please try again.";
                }
            } else {
                // No user found with that email
                $error_message = "No user found with that email. Please check and try again.";
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Login failed. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bukidnon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-600 to-green-800 flex justify-center items-center min-h-screen p-4">

    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-2xl overflow-hidden fade-in">
            <!-- Header with gradient background -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 p-8 text-center">
                <!-- Logo Section -->
                <div class="flex justify-center mb-4">
                    <img src="img/bukidnon_logo.png" alt="Bukidnon Logo" class="w-20 h-20 object-contain rounded-full border-4 border-white shadow-lg">
                </div>
                <h1 class="text-2xl font-bold text-white">BUKIDNON</h1>
                <p class="text-green-100 text-sm mt-1">Admin Portal</p>
            </div>

            <!-- Login Form Section -->
            <div class="p-8">
                <h2 class="text-2xl font-semibold text-center text-gray-800 mb-2">Sign In</h2>
                <p class="text-center text-gray-500 text-sm mb-6">Enter your credentials to access the dashboard</p>

                <!-- Display error message if login fails -->
                <?php if (isset($error_message)): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                        <i class="fas fa-exclamation-circle text-red-600 mt-0.5 flex-shrink-0"></i>
                        <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form action="" method="POST" class="space-y-4">
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-envelope mr-2 text-green-600"></i>Email Address
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="input-focus w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" 
                            placeholder="Enter your email"
                            required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-lock mr-2 text-green-600"></i>Password
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="input-focus w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center text-gray-600">
                            <input type="checkbox" class="w-4 h-4 text-green-600 rounded focus:ring-green-500">
                            <span class="ml-2">Remember me</span>
                        </label>
                        <a href="#" class="text-green-600 hover:text-green-800 font-medium">Forgot password?</a>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-lg hover:from-green-700 hover:to-green-800 transition duration-300 transform hover:scale-105 active:scale-95 flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In
                    </button>
                </form>

                <hr class="my-6">

                <!-- Register Link -->
                <div class="text-center">
                    <p class="text-gray-600 text-sm">
                        Don't have an account? 
                        <a href="register.php" class="text-green-600 hover:text-green-800 font-semibold">Register here</a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t text-center text-gray-500 text-xs">
                <p>&copy; 2024 Bukidnon Admin Portal. All rights reserved.</p>
            </div>
        </div>

        <!-- Help Text -->
        <div class="mt-6 text-center text-white text-sm">
            <p><i class="fas fa-info-circle mr-2"></i>For login support, contact the administrator</p>
        </div>
    </div>

</body>
</html>