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

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get user input and sanitize
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    $error_message = '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (strlen($username) < 3) {
        $error_message = "Username must be at least 3 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // Check if the email already exists in the database
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database prepare failed");
            }
            
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $error_message = "Email is already registered.";
            } else {
                // Check if username already exists
                $sql_username = "SELECT id FROM users WHERE username = ?";
                $stmt_username = $pdo->prepare($sql_username);
                
                if (!$stmt_username) {
                    throw new Exception("Database prepare failed");
                }
                
                $stmt_username->execute([$username]);
                $username_result = $stmt_username->fetch(PDO::FETCH_ASSOC);

                if ($username_result) {
                    $error_message = "Username is already taken.";
                } else {
                    // Hash the password before storing
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // Insert the new user into the database
                    $sql_insert = "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    
                    if (!$stmt_insert) {
                        throw new Exception("Database prepare failed");
                    }
                    
                    if ($stmt_insert->execute([$username, $email, $hashed_password])) {
                        // Redirect to login page after successful registration
                        header("Location: index.php?success=registered");
                        exit();
                    } else {
                        $error_message = "Error registering user. Please try again.";
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error_message = "An error occurred during registration. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Bukidnon</title>
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

        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background-color: #ef4444;
        }

        .strength-fair {
            background-color: #f97316;
        }

        .strength-good {
            background-color: #eab308;
        }

        .strength-strong {
            background-color: #22c55e;
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
                <h1 class="text-2xl font-bold text-white">Bukidnon</h1>
                <p class="text-green-100 text-sm mt-1">Admin Portal</p>
            </div>

            <!-- Register Form Section -->
            <div class="p-8">
                <h2 class="text-2xl font-semibold text-center text-gray-800 mb-2">Create Account</h2>
                <p class="text-center text-gray-500 text-sm mb-6">Sign up to access the admin dashboard</p>

                <!-- Display error message if registration fails -->
                <?php if (isset($error_message) && !empty($error_message)): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                        <i class="fas fa-exclamation-circle text-red-600 mt-0.5 flex-shrink-0"></i>
                        <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form action="" method="POST" class="space-y-4">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-user mr-2 text-green-600"></i>Username
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="input-focus w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" 
                            placeholder="Enter your username"
                            required
                            minlength="3"
                            maxlength="50"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                        <p class="text-xs text-gray-500 mt-1">3-50 characters, letters, numbers, and underscores only</p>
                    </div>

                    <!-- Email Field -->
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

                    <!-- Password Field -->
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
                            minlength="6"
                            onchange="checkPasswordStrength()"
                            oninput="checkPasswordStrength()"
                        >
                        <div id="strengthIndicator" class="password-strength"></div>
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <label for="confirm_password" class="block text-gray-700 font-medium mb-2">
                            <i class="fas fa-check-circle mr-2 text-green-600"></i>Confirm Password
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="input-focus w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" 
                            placeholder="Confirm your password"
                            required
                            minlength="6"
                            oninput="checkPasswordMatch()"
                        >
                        <p id="matchMessage" class="text-xs mt-1"></p>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="flex items-start">
                        <input 
                            type="checkbox" 
                            id="terms" 
                            name="terms" 
                            class="w-4 h-4 text-green-600 rounded focus:ring-green-500 mt-1" 
                            required
                        >
                        <label for="terms" class="ml-2 text-sm text-gray-600">
                            I agree to the <a href="#" class="text-green-600 hover:text-green-800 font-medium">Terms and Conditions</a>
                        </label>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-lg hover:from-green-700 hover:to-green-800 transition duration-300 transform hover:scale-105 active:scale-95 flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>

                <hr class="my-6">

                <!-- Login Link -->
                <div class="text-center">
                    <p class="text-gray-600 text-sm">
                        Already have an account? 
                        <a href="index.php" class="text-green-600 hover:text-green-800 font-semibold">Log in here</a>
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
            <p><i class="fas fa-info-circle mr-2"></i>For registration support, contact the administrator</p>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const indicator = document.getElementById('strengthIndicator');
            
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            indicator.classList.remove('strength-weak', 'strength-fair', 'strength-good', 'strength-strong');
            
            if (strength === 0) {
                indicator.classList.add('strength-weak');
            } else if (strength <= 2) {
                indicator.classList.add('strength-fair');
            } else if (strength <= 3) {
                indicator.classList.add('strength-good');
            } else {
                indicator.classList.add('strength-strong');
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchMessage = document.getElementById('matchMessage');
            
            if (confirmPassword === '') {
                matchMessage.textContent = '';
                matchMessage.className = 'text-xs mt-1';
            } else if (password === confirmPassword) {
                matchMessage.textContent = '✓ Passwords match';
                matchMessage.className = 'text-xs text-green-600 mt-1 font-semibold';
            } else {
                matchMessage.textContent = '✗ Passwords do not match';
                matchMessage.className = 'text-xs text-red-600 mt-1 font-semibold';
            }
        }
    </script>

</body>

</html>
