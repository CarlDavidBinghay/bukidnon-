<?php
// Include the database connection
include('db_config.php');

// Start the session
session_start();

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login_page.php");
    exit();
}

// Fetch roles from the database
$sql = "SELECT * FROM roles";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50 font-sans antialiased">

    <!-- Sidebar -->
    <aside class="w-64 bg-gradient-to-b from-green-600 to-green-800 text-white h-screen p-6 fixed">
        <div class="text-center mb-6">
            <img src="img/bukidnon_logo.png" alt="Bukidnon Logo" class="w-28 h-28 rounded-full mx-auto border-4 border-white shadow-lg">
            <h2 class="text-xl font-bold mt-4">Bukidnon Admin</h2>
            <p class="text-green-200 text-sm">Management Portal</p>
        </div>

        <nav class="mt-10 space-y-3">
            <a href="dashboard.php" class="flex items-center gap-3 text-white px-4 py-3 rounded-lg hover:bg-green-500">
                <i class="fas fa-tachometer-alt text-lg"></i>
                Dashboard
            </a>
            <a href="roles.php" class="flex items-center gap-3 bg-green-600 px-4 py-3 rounded-lg text-white">
                <i class="fas fa-user-shield text-lg"></i>
                Roles
            </a>
            <a href="users.php" class="flex items-center gap-3 text-white px-4 py-3 rounded-lg hover:bg-green-500">
                <i class="fas fa-users text-lg"></i>
                Users
            </a>
            <a href="settings.php" class="flex items-center gap-3 text-white px-4 py-3 rounded-lg hover:bg-green-500">
                <i class="fas fa-cogs text-lg"></i>
                Settings
            </a>
            <a href="?logout=true" class="flex items-center gap-3 text-white px-4 py-3 rounded-lg hover:bg-red-600 mt-auto">
                <i class="fas fa-sign-out-alt text-lg"></i>
                Logout
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="ml-64 p-8">

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-semibold text-gray-800">Manage Roles</h1>
            <button class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-300">Add New Role</button>
        </div>

        <!-- Table -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($role['role_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($role['permissions']) ?></td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <button class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                <button class="text-red-600 hover:text-red-900 ml-2">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>
