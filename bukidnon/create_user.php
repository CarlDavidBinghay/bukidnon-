<?php
include('db_config.php');

$messages = [];
$success = true;

// Test 1: Check database connection
try {
    $pdo->query("SELECT 1");
    $messages[] = "✓ Database connection successful";
} catch (Exception $e) {
    $messages[] = "✗ Database connection failed: " . $e->getMessage();
    $success = false;
}

// Test 2: Check if users table exists
try {
    $result = $pdo->query("DESCRIBE users");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('username', $columns) && in_array('email', $columns) && in_array('password', $columns)) {
        $messages[] = "✓ Users table structure is correct";
    } else {
        $messages[] = "✗ Users table missing required columns";
        $success = false;
    }
} catch (Exception $e) {
    $messages[] = "✗ Users table error: " . $e->getMessage();
    $success = false;
}

// Test 3: Create/Update admin user with correct password
try {
    $username = 'admin';
    $email = 'admin@bukidnon.com';
    $password = 'password123';
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Delete existing admin user
    $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
    
    // Insert new admin user
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $hashed_password]);
    
    $messages[] = "✓ Admin user created/updated successfully";
    $messages[] = "  Username: <strong>admin</strong>";
    $messages[] = "  Email: <strong>admin@bukidnon.com</strong>";
    $messages[] = "  Password: <strong>password123</strong>";
    
} catch (Exception $e) {
    $messages[] = "✗ Failed to create admin user: " . $e->getMessage();
    $success = false;
}

// Test 4: Verify admin user can be retrieved
try {
    $stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE email = ?");
    $stmt->execute(['admin@bukidnon.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $password_match = password_verify('password123', $user['password']);
        if ($password_match) {
            $messages[] = "✓ Admin user password verification successful";
        } else {
            $messages[] = "✗ Admin user password verification failed";
            $success = false;
        }
    } else {
        $messages[] = "✗ Admin user not found in database";
        $success = false;
    }
} catch (Exception $e) {
    $messages[] = "✗ Failed to verify admin user: " . $e->getMessage();
    $success = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Bukidnon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-600 to-blue-800 flex justify-center items-center min-h-screen p-4">
    <div class="w-full max-w-md bg-white rounded-lg shadow-2xl overflow-hidden">
        <div class="bg-blue-600 p-6 text-center">
            <i class="fas fa-tools text-5xl text-white mb-3"></i>
            <h1 class="text-2xl font-bold text-white">System Setup</h1>
        </div>
        
        <div class="p-8">
            <div class="space-y-3">
                <?php foreach ($messages as $msg): ?>
                    <div class="p-3 bg-gray-50 border-l-4 <?php echo (strpos($msg, '✓') === 0) ? 'border-green-500' : 'border-red-500'; ?>">
                        <p class="text-sm <?php echo (strpos($msg, '✓') === 0) ? 'text-green-700' : 'text-red-700'; ?>">
                            <?php echo $msg; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($success): ?>
                <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg text-center">
                    <p class="text-green-700 font-semibold mb-4">✓ All checks passed!</p>
                    <p class="text-sm text-green-600 mb-4">You can now login with your admin account.</p>
                    <a href="index.php" class="inline-block px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg text-center">
                    <p class="text-red-700 font-semibold">✗ Some checks failed</p>
                    <p class="text-sm text-red-600 mt-2">Please contact your administrator</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>