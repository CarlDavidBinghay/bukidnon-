<?php
// Start the session
session_start();
// Add error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug logging
error_log("=== Profile Upload Debug ===");
error_log("Session ID: " . session_id());
error_log("Logged in: " . (isset($_SESSION['logged_in']) ? 'YES' : 'NO'));
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login_page.php");
    exit();
}

// Include database connection
include('db_config.php');

// Get user ID from session (set this during login)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Debug: Log user_id
error_log("User ID variable: " . ($user_id ?? 'NULL'));

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle profile image upload
$profileImagePath = 'uploads/default-profile.png'; // Default profile image

// Load user's profile image from database
if ($user_id) {
    try {
        $sql = "SELECT image_path FROM profile_images WHERE user_id = ? ORDER BY upload_date DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("DB Query result: " . print_r($result, true));
        
        if ($result && !empty($result['image_path'])) {
            if (file_exists($result['image_path'])) {
                $profileImagePath = $result['image_path'];
                error_log("Using existing image: " . $profileImagePath);
            } else {
                error_log("File does not exist: " . $result['image_path']);
            }
        } else {
            error_log("No profile image found for user_id: " . $user_id);
        }
    } catch (Exception $e) {
        error_log("Error loading profile image: " . $e->getMessage());
    }
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profileImage'])) {
    error_log("File upload detected");
    error_log("File info: " . print_r($_FILES['profileImage'], true));
    
    if (!$user_id) {
        $uploadError = "User ID not found. Please log in again.";
        error_log("Upload error: User ID is null");
    } else {
        $file = $_FILES['profileImage'];
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileError = $file['error'];
        $fileSize = $file['size'];
        
        error_log("File upload details:");
        error_log("  Name: $fileName");
        error_log("  Temp: $fileTmp");
        error_log("  Error: $fileError");
        error_log("  Size: $fileSize");

        // Validate file
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName_ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileName_ext, $allowed)) {
            $uploadError = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            error_log("Invalid file type: $fileName_ext");
        } elseif ($fileSize > 5000000) { // 5MB max
            $uploadError = "File size is too large. Maximum 5MB allowed.";
            error_log("File too large: $fileSize bytes");
        } elseif ($fileError !== 0) {
            $uploadError = "Error uploading file. Please try again.";
            error_log("File upload error code: $fileError");
        } else {
            // Create uploads directory if it doesn't exist
            if (!is_dir('uploads')) {
                error_log("Creating uploads directory");
                if (!mkdir('uploads', 0755, true)) {
                    $uploadError = "Failed to create uploads directory.";
                    error_log("Failed to create uploads directory");
                }
            }
            
            // Check if directory is writable
            if (!is_writable('uploads')) {
                $uploadError = "Uploads directory is not writable.";
                error_log("Uploads directory is not writable");
            } else {
                // Generate unique filename with user_id
                $newFileName = 'profile_' . $user_id . '_' . time() . '.' . $fileName_ext;
                $uploadPath = 'uploads/' . $newFileName;
                
                error_log("Upload path: " . $uploadPath);

                // Delete old profile images for this user
                try {
                    $sqlOld = "SELECT image_path FROM profile_images WHERE user_id = ?";
                    $stmtOld = $pdo->prepare($sqlOld);
                    $stmtOld->execute([$user_id]);
                    $oldFiles = $stmtOld->fetchAll(PDO::FETCH_ASSOC);
                    
                    error_log("Found " . count($oldFiles) . " old files to delete");
                    
                    foreach ($oldFiles as $oldFile) {
                        if (file_exists($oldFile['image_path'])) {
                            if (unlink($oldFile['image_path'])) {
                                error_log("Deleted old file: " . $oldFile['image_path']);
                            } else {
                                error_log("Failed to delete old file: " . $oldFile['image_path']);
                            }
                        }
                    }
                    
                    // Delete old database records
                    $sqlDelete = "DELETE FROM profile_images WHERE user_id = ?";
                    $stmtDelete = $pdo->prepare($sqlDelete);
                    $stmtDelete->execute([$user_id]);
                    error_log("Deleted old database records for user_id: $user_id");
                } catch (Exception $e) {
                    error_log("Error deleting old profile images: " . $e->getMessage());
                }

                // Move uploaded file
                error_log("Attempting to move uploaded file from $fileTmp to $uploadPath");
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    error_log("File moved successfully");
                    
                    // Check if file was actually created
                    if (file_exists($uploadPath)) {
                        error_log("File exists at destination: " . filesize($uploadPath) . " bytes");
                    } else {
                        error_log("File does not exist at destination!");
                    }
                    
                    // Insert into database
                    try {
                        $uploadDate = date('Y-m-d H:i:s');
                        $sqlInsert = "INSERT INTO profile_images (user_id, image_path, upload_date) VALUES (?, ?, ?)";
                        $stmtInsert = $pdo->prepare($sqlInsert);
                        $stmtInsert->execute([$user_id, $uploadPath, $uploadDate]);
                        
                        $profileImagePath = $uploadPath;
                        $uploadSuccess = "Profile picture updated successfully!";
                        error_log("Database insert successful. New image path: $uploadPath");
                        
                        // Force page refresh to show new image
                        error_log("Upload completed successfully");
                    } catch (Exception $e) {
                        unlink($uploadPath); // Delete the file if DB insert fails
                        $uploadError = "Database error: " . $e->getMessage();
                        error_log("Database error: " . $e->getMessage());
                    }
                } else {
                    $uploadError = "Failed to upload file. Please try again.";
                    error_log("move_uploaded_file failed");
                    error_log("Is uploads writable? " . (is_writable('uploads') ? 'YES' : 'NO'));
                    error_log("Is temp file readable? " . (is_readable($fileTmp) ? 'YES' : 'NO'));
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bukidnon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            scroll-behavior: smooth;
        }

        /* Hover effect for the sidebar links */
        .nav-link {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link:hover {
            transform: translateX(5px);
        }

        /* Card hover effect */
        .stat-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 30px -10px rgba(0, 0, 0, 0.15);
        }

        .chart-container {
            transition: all 0.3s ease;
        }

        .chart-container:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Page animations */
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

        /* Sidebar animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-72 bg-gradient-to-b from-green-700 to-green-800 text-white p-6 flex flex-col shadow-2xl">
        <div class="mb-10 text-center">
            <img src="img/bukidnon_logo.png" alt="Bukidnon Logo" class="w-28 h-28 object-contain rounded-full mx-auto border-4 border-white shadow-lg">
            <h2 class="mt-4 text-xl font-bold">Bukidnon Admin</h2>
            <p class="text-green-200 text-sm">Management Portal</p>
        </div>

        <nav class="flex-grow space-y-2">
    <a href="#" id="nav-dashboard" class="nav-link flex items-center gap-3 bg-green-600 px-4 py-3 rounded-lg hover:bg-green-500 hover:pl-6" onclick="showPage('dashboard')">
        <i class="fas fa-tachometer-alt text-lg"></i>
        <span class="font-medium">Dashboard</span>
    </a>
    <a href="#" id="nav-users" class="nav-link flex items-center gap-3 text-white hover:bg-green-600 px-4 py-3 rounded-lg hover:pl-6" onclick="showPage('users')">
        <i class="fas fa-users text-lg"></i>
        <span class="font-medium">Users</span>
    </a>
    <a href="#" id="nav-analytics" class="nav-link flex items-center gap-3 text-white hover:bg-green-600 px-4 py-3 rounded-lg hover:pl-6" onclick="showPage('analytics')">
        <i class="fas fa-chart-line text-lg"></i>
        <span class="font-medium">Analytics</span>
    </a>
    <a href="#" id="nav-upload-files" class="nav-link flex items-center gap-3 text-white hover:bg-green-600 px-4 py-3 rounded-lg hover:pl-6" onclick="showPage('upload-files')">
        <i class="fas fa-cloud-upload-alt text-lg"></i>
        <span class="font-medium">Upload Files</span>
    </a>
    <a href="#" id="nav-settings" class="nav-link flex items-center gap-3 text-white hover:bg-green-600 px-4 py-3 rounded-lg hover:pl-6" onclick="showPage('settings')">
        <i class="fas fa-cogs text-lg"></i>
        <span class="font-medium">Settings</span>
    </a>
    <a href="#" id="nav-roles" class="nav-link flex items-center gap-3 text-white hover:bg-green-600 px-4 py-3 rounded-lg hover:pl-6" onclick="showPage('roles')">
        <i class="fas fa-user-shield text-lg"></i>
        <span class="font-medium">Roles</span>
    </a>
</nav>

        <div class="mt-auto pt-6 border-t border-green-600">
            <a href="?logout=true" class="flex items-center gap-3 text-white hover:bg-red-600 px-4 py-3 rounded-lg transition duration-300">
                <i class="fas fa-sign-out-alt text-lg"></i>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </aside>

    
    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8">
        <!-- Header -->
        <header class="bg-white shadow-md sticky top-0 z-10">
            <div class="flex justify-between items-center px-8 py-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-500 text-sm mt-1">Welcome back, monitor your platform's performance</p>
                </div>

                <div class="flex items-center gap-6">
                    <div class="text-right">
                        <p class="font-semibold text-gray-800"><?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin User' ?></p>
                        <p class="text-sm text-gray-500">Administrator</p>
                    </div>
                    <div class="relative group">
                        <img src="<?= htmlspecialchars($profileImagePath) ?>" alt="Profile" 
                            class="w-14 h-14 rounded-full border-2 border-green-600 shadow-md object-cover cursor-pointer hover:opacity-90 transition-opacity" 
                            id="profileImageTrigger"
                            title="Click to upload profile picture">
                        
                        <form action="" method="POST" enctype="multipart/form-data" id="profileUploadForm">
                            <input type="file" name="profileImage" id="profileImage" 
                                accept="image/*" class="hidden" onchange="handleProfileImageChange(event)">
                        </form>
                        
                        <div class="absolute -bottom-1 -right-1 bg-green-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-lg pointer-events-none">
                            <i class="fas fa-camera text-xs"></i>
                    </div>
                    </div>
                </div>
            </div>
            
            
            <!-- Success/Error Messages -->
            <?php if (isset($uploadSuccess)): ?>
                <div class="mx-8 mt-2 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i><?= $uploadSuccess ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($uploadError)): ?>
                <div class="mx-8 mt-2 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= $uploadError ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- Dashboard Content Sections -->
        <div id="content" class="mt-6">
            <!-- Dashboard Page -->
            <section id="dashboard" class="page fade-in">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Dashboard Overview</h2>
                        <p class="text-sm text-gray-500 mt-1">Summary of platform activity and quick actions</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="date" class="border rounded-lg px-3 py-2 text-sm hidden md:inline-block">
                        <div class="relative">
                            <input type="text" placeholder="Search users, reports..." class="w-64 pl-10 pr-3 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-green-300 text-sm">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <!-- Primary stat cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg rounded-xl p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-green-100 text-sm font-medium mb-2">Total Users</p>
                                <h3 class="text-3xl font-bold mb-1">1,250</h3>
                                <p class="text-green-100 text-sm"><i class="fas fa-arrow-up mr-1"></i>12% since last month</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg rounded-xl p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-blue-100 text-sm font-medium mb-2">Active Sessions</p>
                                <h3 class="text-3xl font-bold mb-1">342</h3>
                                <p class="text-blue-100 text-sm"><i class="fas fa-arrow-up mr-1"></i>4% today</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <i class="fas fa-signal text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-600 text-white shadow-lg rounded-xl p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-purple-100 text-sm font-medium mb-2">New Signups</p>
                                <h3 class="text-3xl font-bold mb-1">48</h3>
                                <p class="text-purple-100 text-sm"><i class="fas fa-arrow-down mr-1"></i>2% since yesterday</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <i class="fas fa-user-plus text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white shadow rounded-xl p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm text-gray-500 mb-2">System Health</p>
                                <h3 class="text-3xl font-bold mb-1 text-gray-800">Good</h3>
                                <p class="text-sm text-gray-500">Uptime 99.8%</p>
                            </div>
                            <div class="flex flex-col items-end">
                                <div class="text-green-600 font-semibold">Stable</div>
                                <div class="mt-2 w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-700 font-bold">✓</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary content: chart + recent activity + quick actions -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="lg:col-span-2 bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm text-gray-500">User Growth (last 6 months)</p>
                            <div class="text-xs text-gray-400">Preview</div>
                        </div>
                        <canvas id="dashboardUsersChart" class="w-full h-52 chart-container"></canvas>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-sm text-gray-500 mb-3">Quick Actions</p>
                        <div class="grid grid-cols-1 gap-3">
                            <button class="flex items-center gap-3 px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-500">
                                <i class="fas fa-user-plus"></i><span class="text-sm">Create User</span>
                            </button>
                            <button class="flex items-center gap-3 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500">
                                <i class="fas fa-upload"></i><span class="text-sm">Import CSV</span>
                            </button>
                            <button class="flex items-center gap-3 px-4 py-2 rounded-lg bg-yellow-500 text-white hover:bg-yellow-400">
                                <i class="fas fa-envelope"></i><span class="text-sm">Send Announcement</span>
                            </button>
                        </div>

                        <hr class="my-4">

                        <p class="text-sm text-gray-500 mb-2">Recent Activity</p>
                        <ul class="space-y-3 text-sm text-gray-700">
                            <li class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-600"><i class="fas fa-user"></i></div>
                                <div>
                                    <div class="font-medium">Maria Santos</div>
                                    <div class="text-xs text-gray-500">Registered a new account — 2 hours ago</div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-600"><i class="fas fa-file-alt"></i></div>
                                <div>
                                    <div class="font-medium">Report Export</div>
                                    <div class="text-xs text-gray-500">Monthly usage report exported — 1 day ago</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Compact top users table -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm text-gray-500">Top Active Users</p>
                        <a href="#" class="text-sm text-green-600 hover:underline">View all</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Active</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr>
                                    <td class="px-4 py-3 flex items-center gap-3">
                                        <img src="uploads/default-profile.png" alt="avatar" class="w-8 h-8 rounded-full object-cover border">
                                        <div>
                                            <div class="font-medium text-gray-900">Juan Dela Cruz</div>
                                            <div class="text-xs text-gray-400">juan.jdc@example.com</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">Administrator</td>
                                    <td class="px-4 py-3">2 hours ago</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-red-600 hover:text-red-900"><i class="fas fa-ban"></i></a>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px-4 py-3 flex items-center gap-3">
                                        <img src="img/avatar2.png" alt="avatar" class="w-8 h-8 rounded-full object-cover border">
                                        <div>
                                            <div class="font-medium text-gray-900">Maria Santos</div>
                                            <div class="text-xs text-gray-400">maria.s@example.com</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">Moderator</td>
                                    <td class="px-4 py-3">Yesterday</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-red-600 hover:text-red-900"><i class="fas fa-ban"></i></a>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px-4 py-3 flex items-center gap-3">
                                        <img src="img/avatar3.png" alt="avatar" class="w-8 h-8 rounded-full object-cover border">
                                        <div>
                                            <div class="font-medium text-gray-900">Pedro Reyes</div>
                                            <div class="text-xs text-gray-400">pedro.r@example.com</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">Teacher</td>
                                    <td class="px-4 py-3">3 days ago</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-eye"></i></a>
                                        <a href="#" class="text-red-600 hover:text-red-900"><i class="fas fa-ban"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Upload Files Page -->
            <section id="upload-files" class="page fade-in hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Upload Required Documents</h2>
                        <p class="text-sm text-gray-500 mt-1">Upload all necessary documents for verification and enrollment</p>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="uploadMessage" class="mb-4 hidden"></div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Upload Area -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow p-8">
                            <h3 class="text-lg font-semibold text-gray-800 mb-6">Document Upload</h3>
                            
                            <!-- Upload Form -->
                            <form id="documentUploadForm" enctype="multipart/form-data">
                                <!-- Upload Dropzone -->
                                <div id="dropzone" class="border-2 border-dashed border-green-300 rounded-lg p-8 text-center bg-green-50 cursor-pointer hover:border-green-500 transition" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" onclick="document.getElementById('fileInput').click()">
                                    <div class="mb-4">
                                        <i class="fas fa-cloud-upload-alt text-5xl text-green-600"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-2">Drag and drop your files here</h4>
                                    <p class="text-sm text-gray-600 mb-4">or click to browse from your computer</p>
                                    <p class="text-xs text-gray-500">Supported formats: PDF, JPG, PNG, DOC, DOCX (Max 10MB per file)</p>
                                    <input type="file" id="fileInput" name="documents[]" multiple class="hidden" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="handleFileSelect(event)">
                                </div>

                                <!-- Selected Files Preview -->
                                <div id="selectedFilesPreview" class="mt-6 hidden">
                                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Files to Upload</h4>
                                    <div id="previewList" class="space-y-2 mb-4"></div>
                                </div>

                                <!-- Upload Progress -->
                                <div id="uploadProgress" class="mt-6 hidden">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Uploading...</span>
                                            <span id="progressPercent" class="text-sm text-gray-500">0%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="progressBar" class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Uploaded Files List -->
                                <div id="uploadedFilesContainer" class="mt-6 hidden">
                                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Uploaded Files</h4>
                                    <div id="uploadedFilesList" class="space-y-2"></div>
                                </div>

                                <div class="mt-6 flex gap-3">
                                    <button type="submit" id="submitBtn" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition hidden">
                                        <i class="fas fa-cloud-upload-alt mr-2"></i>Upload Files
                                    </button>
                                    <button type="button" id="clearBtn" class="px-6 py-2 border rounded-lg text-gray-700 hover:bg-gray-50 transition" onclick="clearFilePreview()">
                                        <i class="fas fa-redo mr-2"></i>Clear Selection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Required Documents Checklist -->
                    <div>
                        <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Required Documents</h3>
                            <div class="space-y-3">
                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">Good Moral Form</p>
                                        <p class="text-xs text-gray-500">From previous school</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">Form 137</p>
                                        <p class="text-xs text-gray-500">School report card</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">Birth Certificate</p>
                                        <p class="text-xs text-gray-500">Official birth certificate</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">Certificate of Completion</p>
                                        <p class="text-xs text-gray-500">From Grade 6</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">ESC Certificate G11 & G12</p>
                                        <p class="text-xs text-gray-500">Educational Service Contract</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">Report Card G11 & G12</p>
                                        <p class="text-xs text-gray-500">Senior high school grades</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <input type="checkbox" class="w-4 h-4 text-green-600 mt-1 doc-checker">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">2x2 ID Picture</p>
                                        <p class="text-xs text-gray-500">Recent passport-style photo</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Completion Status -->
                            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Completion Status</p>
                                <div class="w-full bg-blue-200 rounded-full h-2 mt-2">
                                    <div id="completionBar" class="bg-green-600 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                                </div>
                                <p class="text-xs text-blue-600 mt-2 font-semibold"><span id="completionText">0</span> of 7 documents submitted</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Roles Management Section -->
            <section id="roles" class="page fade-in hidden">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-800">Roles</h2>
                    <p class="text-sm text-gray-500">Create, edit and manage user roles and permissions.</p>
                    <button class="bg-green-600 text-white px-4 py-2 rounded-lg" onclick="openAddRoleModal()">
                        <i class="fas fa-plus mr-2"></i>Add Role
                    </button>
                </div>

                <div class="bg-white rounded-lg shadow p-4">
                    <div class="overflow-x-auto">
                        <table id="rolesTable" class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rolesTbody" class="bg-white divide-y divide-gray-100">
                                <!-- Roles loaded by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Add Role Modal -->
            <div id="addRoleModal" class="fixed inset-0 bg-black bg-opacity-40 flex hidden items-center justify-center z-50">
                <div class="bg-white rounded-lg w-11/12 md:w-1/2 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Create Role</h3>
                        <button onclick="closeAddRoleModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                    </div>
                    <form id="addRoleForm" onsubmit="saveRole(event)">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Role name</label>
                                <input id="addRoleName" type="text" class="w-full border rounded px-3 py-2" required>
                            </div>
                            <!-- Replace your permissions select dropdown with this: -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                            <div class="border rounded-lg p-4 bg-gray-50 max-h-60 overflow-y-auto">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Admin">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Admin</span>
                                            <p class="text-xs text-gray-500">Full system access</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Staff">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Staff</span>
                                            <p class="text-xs text-gray-500">Administrative access</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Student">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Student</span>
                                            <p class="text-xs text-gray-500">Student access</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Faculty">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Faculty</span>
                                            <p class="text-xs text-gray-500">Faculty member access</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Guest">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Guest</span>
                                            <p class="text-xs text-gray-500">Limited access</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Moderator">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Moderator</span>
                                            <p class="text-xs text-gray-500">Content moderation</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Editor">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Editor</span>
                                            <p class="text-xs text-gray-500">Edit content access</p>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                        <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Viewer">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">Viewer</span>
                                            <p class="text-xs text-gray-500">View-only access</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Selected permissions display -->
                            <div id="selectedPermissionsDisplay" class="mt-3">
                                <label class="block text-sm text-gray-600 mb-2">Selected Permissions:</label>
                                <div id="selectedTags" class="flex flex-wrap gap-2 p-3 border rounded-lg bg-blue-50 min-h-12">
                                    <span class="text-sm text-gray-500">No permissions selected</span>
                                </div>
                            </div>
                            
                            <!-- Hidden input to store comma-separated values -->
                            <input type="hidden" id="permissionsValue" name="permissions">
                        </div>

                            <!-- Add this hidden custom input field -->
                            <div id="customPermissionsContainer" class="mt-2 hidden">
                                <label class="block text-sm text-gray-600 mb-1">Custom Permissions</label>
                                <input type="text" id="customPermissions" class="w-full border rounded px-3 py-2 text-sm" placeholder="Enter comma-separated permissions...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea id="addRoleDescription" class="w-full border rounded px-3 py-2" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" onclick="closeAddRoleModal()" class="px-4 py-2 border rounded">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Role Modal -->
            <div id="editRoleModal" class="fixed inset-0 bg-black bg-opacity-40 hidden flex items-center justify-center z-50">
                <div class="bg-white rounded-lg w-11/12 md:w-1/2 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Edit Role</h3>
                        <button onclick="closeEditRoleModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                    </div>
                    <form id="editRoleForm" onsubmit="updateRole(event)">
                        <input type="hidden" id="editRoleId">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Role name</label>
                                <input id="editRoleName" type="text" class="w-full border rounded px-3 py-2" required>
                            </div>
                            <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                        <div class="border rounded-lg p-4 bg-gray-50 max-h-60 overflow-y-auto">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Admin">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Admin</span>
                                        <p class="text-xs text-gray-500">Full system access</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Staff">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Staff</span>
                                        <p class="text-xs text-gray-500">Administrative access</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Student">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Student</span>
                                        <p class="text-xs text-gray-500">Student access</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Faculty">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Faculty</span>
                                        <p class="text-xs text-gray-500">Faculty member access</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Guest">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Guest</span>
                                        <p class="text-xs text-gray-500">Limited access</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Moderator">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Moderator</span>
                                                        <p class="text-xs text-gray-500">Content moderation</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Editor">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Editor</span>
                                        <p class="text-xs text-gray-500">Edit content access</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center space-x-3 p-2 border rounded-lg bg-white hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" class="permission-checkbox h-5 w-5 text-green-600 rounded" value="Viewer">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Viewer</span>
                                        <p class="text-xs text-gray-500">View-only access</p>
                                    </div>
                                </label>
                            </div>
                            
                            
                        </div>
                        
                        <!-- Selected permissions display -->
                        <div id="editSelectedPermissionsDisplay" class="mt-3">
                            <label class="block text-sm text-gray-600 mb-2">Selected Permissions:</label>
                            <div id="editSelectedTags" class="flex flex-wrap gap-2 p-3 border rounded-lg bg-yellow-50 min-h-12">
                                <span class="text-sm text-gray-500">No permissions selected</span>
                            </div>
                        </div>
                        
                        <!-- Hidden input to store comma-separated values -->
                        <input type="hidden" id="editPermissions" name="permissions">
                    </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea id="editRoleDescription" class="w-full border rounded px-3 py-2" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" onclick="closeEditRoleModal()" class="px-4 py-2 border rounded">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded">Update</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users / Student Management Page -->
            <section id="users" class="page fade-in hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Student Management</h2>
                        <p class="text-sm text-gray-500 mt-1">View, filter and manage student records</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input id="studentSearch" type="text" placeholder="Search students by name, email or ID..." class="w-80 pl-10 pr-3 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-green-300 text-sm">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                        </div>

                        <select id="gradeFilter" class="border rounded-lg px-3 py-2 text-sm">
                            <option value="">All Grades</option>
                            <option>Grade 7</option>
                            <option>Grade 8</option>
                            <option>Grade 9</option>
                            <option>Grade 10</option>
                        </select>

                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-500" onclick="openAddStudentModal()">Add Student</button>
                    </div>
                </div>  

                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input id="bulkToggle" type="checkbox" class="form-checkbox h-4 w-4 text-green-600">
                                <span>Bulk select</span>
                            </label>
                            <button id="bulkDelete" class="hidden bg-red-600 text-white text-sm px-3 py-1 rounded hover:bg-red-500">Delete Selected</button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button class="text-sm px-3 py-1 border rounded hover:bg-gray-50">Import CSV</button>
                            <button class="text-sm px-3 py-1 border rounded hover:bg-gray-50">Export</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="studentsTable" class="min-w-full divide-y divide-gray-200 text-sm">
                                <<thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                                        <!-- REMOVED: Status and Last Active columns -->
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100" id="studentsTbody">
                                    <?php
                                    // Fetch students from database
                                    try {
                                        $sql = "SELECT * FROM students ORDER BY created_at DESC";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($students)) {
                                            echo '<tr>
                                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                                    <div class="flex flex-col items-center">
                                                        <i class="fas fa-users text-3xl text-gray-300 mb-2"></i>
                                                        <p class="text-sm">No students found</p>
                                                        <p class="text-xs text-gray-400 mt-1">Click "Add Student" to create your first student record</p>
                                                    </div>
                                                </td>
                                            </tr>';
                                        } else {
                                            $counter = 1;
                                            foreach ($students as $student) {
                                                // Get profile image
                                                $profileImage = 'uploads/default-profile.png';
                                                $sql_img = "SELECT image_path FROM profile_images WHERE user_id = ? ORDER BY upload_date DESC LIMIT 1";
                                                $stmt_img = $pdo->prepare($sql_img);
                                                $stmt_img->execute([$student['id']]);
                                                $img_result = $stmt_img->fetch(PDO::FETCH_ASSOC);
                                                if ($img_result && file_exists($img_result['image_path'])) {
                                                    $profileImage = $img_result['image_path'];
                                                }
                                                
                                                // Determine status
                                                $status_class = 'bg-gray-100 text-gray-800';
                                                $status_text = 'Inactive';
                                                
                                                // if ($student['is_active'] == 1) {
                                                //     $status_class = 'bg-green-100 text-green-800';
                                                //     $status_text = 'Active';
                                                // }
                                                
                                                // Get last active (simplified - add last_active column to your students table if needed)
                                                $last_active = $student['created_at'] ?? 'Never';
                                                if ($last_active != 'Never') {
                                                    $date = new DateTime($last_active);
                                                    $now = new DateTime();
                                                    $interval = $date->diff($now);
                                                    
                                                    if ($interval->days == 0) {
                                                        if ($interval->h == 0) {
                                                            $last_active = $interval->i . ' minutes ago';
                                                        } else {
                                                            $last_active = $interval->h . ' hours ago';
                                                        }
                                                    } elseif ($interval->days == 1) {
                                                        $last_active = 'Yesterday';
                                                    } elseif ($interval->days < 30) {
                                                        $last_active = $interval->days . ' days ago';
                                                    } else {
                                                        $last_active = $date->format('M j, Y');
                                                    }
                                                }
                                                
                                                // Escape output
                                                $full_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                                                $email = htmlspecialchars($student['email']);
                                                $student_id = htmlspecialchars($student['student_id']);
                                                $grade = htmlspecialchars($student['grade'] ?? '');
                                                $section = htmlspecialchars($student['section'] ?? '');
                                                
                                                echo '<tr data-id="' . $student['id'] . '" 
                                                data-name="' . strtolower(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'])) . '"
                                                data-email="' . strtolower(htmlspecialchars($student['email'])) . '"
                                                data-grade="' . htmlspecialchars($student['grade'] ?? '') . '">
                                                <td class="px-4 py-3">
                                                    <input type="checkbox" class="row-checkbox form-checkbox h-4 w-4 text-green-600" value="' . $student['id'] . '">
                                                </td>
                                                <td class="px-4 py-3 flex items-center gap-3">
                                                    <div>
                                                        <div class="font-medium text-gray-900">' . $full_name . '</div>
                                                        <div class="text-xs text-gray-400">' . $student_id . '</div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">' . $email . '</td>
                                                <td class="px-4 py-3">' . $grade . '</td>
                                                <td class="px-4 py-3">' . $section . '</td>
                                                <td class="px-4 py-3 text-right">
                                                   
                                                    
                                                    <button class="text-red-600 hover:text-red-800" onclick="deleteStudent(' . $student['id'] . ', \'' . htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name']), ENT_QUOTES) . '\')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                            $counter++;
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log("Error fetching students: " . $e->getMessage());
                                        echo '<tr>
                                            <td colspan="8" class="px-4 py-8 text-center text-red-500">
                                                <div class="flex flex-col items-center">
                                                    <i class="fas fa-exclamation-triangle text-3xl text-red-300 mb-2"></i>
                                                    <p class="text-sm">Error loading students</p>
                                                    <p class="text-xs text-gray-400 mt-1">Please try refreshing the page</p>
                                                </div>
                                            </td>
                                        </tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-4">
                        <div class="text-sm text-gray-600">Showing 1 to 3 of 3 students</div>
                        <div class="space-x-2">
                            <button class="px-3 py-1 border rounded text-sm">Prev</button>
                            <button class="px-3 py-1 border rounded text-sm">1</button>
                            <button class="px-3 py-1 border rounded text-sm">Next</button>
                        </div>
                    </div>
                </div>

                <!-- Simple Add/Edit Modal (hidden, lightweight) -->
                <!-- Enhanced Student Modal -->
<div id="studentModal" class="fixed flex inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-11/12 md:w-3/4 lg:w-2/3 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 id="studentModalTitle" class="text-lg font-semibold">Add Student</h3>
            <button onclick="closeStudentModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <form id="studentForm" onsubmit="saveStudent(event)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Student ID -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student ID *</label>
                    <input type="text" id="stuId" class="w-full border rounded px-3 py-2" required>
                </div>
                
                <!-- First Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" id="stuName" class="w-full border rounded px-3 py-2" required>
                </div>
                
                <!-- Last Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" id="stuLastName" class="w-full border rounded px-3 py-2" required>
                </div>
                
                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" id="stuEmail" class="w-full border rounded px-3 py-2" required>
                </div>
                
                <!-- Grade -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                    <select id="stuGrade" class="w-full border rounded px-3 py-2">
                        <option value="">Select Grade</option>
                        <option>Grade 7</option>
                        <option>Grade 8</option>
                        <option>Grade 9</option>
                        <option>Grade 10</option>
                        <option>Grade 11</option>
                        <option>Grade 12</option>
                    </select>
                </div>
                
                <!-- Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                    <input type="text" id="stuSection" placeholder="e.g., Section A" class="w-full border rounded px-3 py-2">
                </div>
                
                
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeStudentModal()" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-500">Save Student</button>
            </div>
        </form>
    </div>
</div>
            </section>

            <!-- Analytics Page -->
            <section id="analytics" class="page fade-in hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Analytics & Reports</h2>
                        <p class="text-sm text-gray-500 mt-1">View system performance and user statistics</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 border rounded-lg hover:bg-gray-50"><i class="fas fa-download mr-2"></i>Export PDF</button>
                        <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500"><i class="fas fa-file-csv mr-2"></i>Generate Report</button>
                    </div>
                </div>

                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Login Attempts</p>
                                <h3 class="text-3xl font-bold text-gray-800">2,847</h3>
                                <p class="text-xs text-green-600 mt-1"><i class="fas fa-arrow-up mr-1"></i>8.5% this month</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xl">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Failed Logins</p>
                                <h3 class="text-3xl font-bold text-red-600">124</h3>
                                <p class="text-xs text-red-600 mt-1"><i class="fas fa-arrow-up mr-1"></i>Security alert</p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center text-red-600 text-xl">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Avg Session Duration</p>
                                <h3 class="text-3xl font-bold text-purple-600">45m 32s</h3>
                                <p class="text-xs text-green-600 mt-1"><i class="fas fa-arrow-up mr-1"></i>5% higher</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 text-xl">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Active Now</p>
                                <h3 class="text-3xl font-bold text-green-600">342</h3>
                                <p class="text-xs text-green-600 mt-1"><i class="fas fa-arrow-up mr-1"></i>Online users</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600 text-xl">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-800">User Growth (Last 12 Months)</h3>
                            <select class="text-xs border rounded px-2 py-1">
                                <option>Last 12 months</option>
                                <option>Last 6 months</option>
                                <option>Last 30 days</option>
                            </select>
                        </div>
                        <canvas id="usersGrowthChart" class="w-full h-64 chart-container"></canvas>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-800">User Status Distribution</h3>
                            <span class="text-xs text-gray-500">Total: 2,847</span>
                        </div>
                        <canvas id="statusDistributionChart" class="w-full h-64 chart-container"></canvas>
                    </div>
                </div>

                <!-- Additional Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-800">Login Activity (Last 7 Days)</h3>
                            <span class="text-xs text-gray-500">Hourly breakdown</span>
                        </div>
                        <canvas id="loginActivityChart" class="w-full h-64 chart-container"></canvas>
                    </div>

                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-800">Page Views</h3>
                            <span class="text-xs text-gray-500">Top pages</span>
                        </div>
                        <canvas id="pageViewsChart" class="w-full h-64 chart-container"></canvas>
                    </div>
                </div>

                <!-- Detailed Statistics Table -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">User Activity Report</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Users</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Active Users</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Login Attempts</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed Logins</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Duration</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">Dec 1, 2024</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">+12</span></td>
                                    <td class="px-4 py-3">2,847</td>
                                    <td class="px-4 py-3">342</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">8</span></td>
                                    <td class="px-4 py-3">45m 32s</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">Nov 30, 2024</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">+15</span></td>
                                    <td class="px-4 py-3">2,835</td>
                                    <td class="px-4 py-3">298</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">5</span></td>
                                    <td class="px-4 py-3">42m 15s</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">Nov 29, 2024</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">+8</span></td>
                                    <td class="px-4 py-3">2,820</td>
                                    <td class="px-4 py-3">315</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">12</span></td>
                                    <td class="px-4 py-3">48m 45s</td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">Nov 28, 2024</td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">+22</span></td>
                                    <td class="px-4 py-3">2,812</td>
                                    <td class="px-4 py-3">287</td>
                                    <td class="px-2 py-3"><span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">3</span></td>
                                    <td class="px-4 py-3">43m 20s</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Settings Page -->
            <section id="settings" class="page fade-in hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Settings</h2>
                        <p class="text-sm text-gray-500 mt-1">Manage platform configuration and preferences</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Settings Sidebar Menu -->
                    <div class="bg-white rounded-lg shadow p-4 h-fit">
                        <nav class="space-y-2">
                            <button onclick="showSettingsTab('general')" class="w-full text-left px-4 py-3 rounded-lg bg-green-100 text-green-700 font-medium hover:bg-green-200 transition">
                                <i class="fas fa-cog mr-2"></i>General
                            </button>
                            <button onclick="showSettingsTab('security')" class="w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-lock mr-2"></i>Security
                            </button>
                            <button onclick="showSettingsTab('notifications')" class="w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-bell mr-2"></i>Notifications
                            </button>
                            <button onclick="showSettingsTab('users')" class="w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-users mr-2"></i>User Management
                            </button>
                            <button onclick="showSettingsTab('backup')" class="w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-database mr-2"></i>Backup
                            </button>
                        </nav>
                    </div>

                    <!-- Settings Content -->
                    <div class="lg:col-span-3 space-y-6">
                        <!-- General Settings Tab -->
                        <div id="general-tab" class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">General Settings</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Platform Name</label>
                                    <input type="text" value="Bukidnon Management System" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Platform URL</label>
                                    <input type="text" value="https://bukidnon.edu.ph" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>
                                    <input type="email" value="admin@bukidnon.edu.ph" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Support Phone</label>
                                    <input type="tel" value="+63-9XX-XXX-XXXX" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                    <select class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option>Asia/Manila (UTC +8)</option>
                                        <option>UTC</option>
                                        <option>Asia/Bangkok (UTC +7)</option>
                                    </select>
                                </div>
                                <div class="pt-4">
                                    <button class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Security Settings Tab -->
                        <div id="security-tab" class="bg-white rounded-lg shadow p-6 hidden">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Security Settings</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition">
                                    <div>
                                        <p class="font-medium text-gray-800">Two-Factor Authentication</p>
                                        <p class="text-sm text-gray-500">Require 2FA for all admin users</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition">
                                    <div>
                                        <p class="font-medium text-gray-800">Force HTTPS</p>
                                        <p class="text-sm text-gray-500">Redirect all traffic to HTTPS</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition">
                                    <div>
                                        <p class="font-medium text-gray-800">Login Attempt Limit</p>
                                        <p class="text-sm text-gray-500">Maximum failed attempts before lockout</p>
                                    </div>
                                    <input type="number" value="5" class="w-20 border rounded-lg px-3 py-1 text-center">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
                                    <input type="number" value="30" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                </div>

                                <div class="pt-4">
                                    <button class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications Settings Tab -->
                        <div id="notifications-tab" class="bg-white rounded-lg shadow p-6 hidden">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Notification Settings</h3>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="checkbox" class="w-4 h-4 text-green-600" checked>
                                    <div>
                                        <p class="font-medium text-gray-800">Email on new registrations</p>
                                        <p class="text-sm text-gray-500">Get notified when users sign up</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="checkbox" class="w-4 h-4 text-green-600" checked>
                                    <div>
                                        <p class="font-medium text-gray-800">Security alerts</p>
                                        <p class="text-sm text-gray-500">Failed login attempts and suspicious activity</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="checkbox" class="w-4 h-4 text-green-600">
                                    <div>
                                        <p class="font-medium text-gray-800">Weekly reports</p>
                                        <p class="text-sm text-gray-500">Summary of platform activity</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="checkbox" class="w-4 h-4 text-green-600" checked>
                                    <div>
                                        <p class="font-medium text-gray-800">System maintenance notices</p>
                                        <p class="text-sm text-gray-500">Updates about scheduled downtime</p>
                                    </div>
                                </label>

                                <div class="pt-4">
                                    <button class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- User Management Settings Tab -->
                        <div id="users-tab" class="bg-white rounded-lg shadow p-6 hidden">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">User Management</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Default User Role</label>
                                    <select class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option>Student</option>
                                        <option>Teacher</option>
                                        <option>Staff</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Max Users</label>
                                    <input type="number" value="10000" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                </div>

                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition">
                                    <div>
                                        <p class="font-medium text-gray-800">Auto-approve new users</p>
                                        <p class="text-sm text-gray-500">Skip manual verification</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                                    </label>
                                </div>

                                <div class="pt-4">
                                    <button class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Backup Settings Tab -->
                        <div id="backup-tab" class="bg-white rounded-lg shadow p-6 hidden">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Backup & Database</h3>
                            <div class="space-y-4">
                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i><span class="font-medium">Last backup:</span> 2 hours ago</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Auto-backup Frequency</label>
                                    <select class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option>
                                        <option>Daily</option>
                                        <option>Weekly</option>
                                        <option>Monthly</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Backup Retention Period</label>
                                    <select class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option>30 days</option>
                                        <option>60 days</option>
                                        <option>90 days</option>
                                        <option>1 year</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-3">
                                    <input type="checkbox" id="backupNotifyEmail" class="form-checkbox h-4 w-4 text-green-600">
                                    <label for="backupNotifyEmail" class="text-sm text-gray-700">Send email notifications for backups</label>
                                </div>

                                <div class="pt-4">
                                    <button class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // JavaScript function to show/hide pages based on sidebar selection
function showPage(page) {
    // Hide all pages
    const pages = document.querySelectorAll('.page');
    pages.forEach(p => p.classList.add('hidden'));
    
    // Show selected page
    document.getElementById(page).classList.remove('hidden');
    
    // Update active navigation link
    updateActiveNav(page);
}

// Function to update active navigation link
function updateActiveNav(activePage) {
    // Remove active classes from all nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('bg-green-600', 'text-white');
        link.classList.add('text-white', 'hover:bg-green-600');
    });
    
    // Add active classes to clicked nav link
    const activeLink = document.getElementById(`nav-${activePage}`);
    if (activeLink) {
        activeLink.classList.add('bg-green-600', 'text-white');
        activeLink.classList.remove('hover:bg-green-600');
    }
}

// Settings tab function with proper active state
function showSettingsTab(tab) {
    // Hide all settings tabs
    const tabs = document.querySelectorAll('[id$="-tab"]');
    tabs.forEach(t => t.classList.add('hidden'));
    
    // Show selected settings tab
    document.getElementById(tab + '-tab').classList.remove('hidden');
    
    // Update active button in settings sidebar
    const settingsButtons = document.querySelectorAll('#settings .bg-white button');
    settingsButtons.forEach(btn => {
        btn.classList.remove('bg-green-100', 'text-green-700', 'font-medium');
        btn.classList.add('text-gray-700', 'hover:bg-gray-100');
    });
    
    // Find and activate the clicked button
    const clickedButton = event.target.closest('button');
    if (clickedButton) {
        clickedButton.classList.add('bg-green-100', 'text-green-700', 'font-medium');
        clickedButton.classList.remove('text-gray-700', 'hover:bg-gray-100');
    }
}

// Set initial active state on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set dashboard as active
    updateActiveNav('dashboard');
    
    // Set General as active in settings if settings page is shown
    if (!document.getElementById('settings').classList.contains('hidden')) {
        const settingsButtons = document.querySelectorAll('#settings .bg-white button');
        settingsButtons.forEach(btn => {
            btn.classList.remove('bg-green-100', 'text-green-700', 'font-medium');
            btn.classList.add('text-gray-700', 'hover:bg-gray-100');
        });
        
        // Activate General tab
        const generalButton = document.querySelector('#settings .bg-white button:first-child');
        if (generalButton) {
            generalButton.classList.add('bg-green-100', 'text-green-700', 'font-medium');
            generalButton.classList.remove('text-gray-700', 'hover:bg-gray-100');
        }
    }
});

        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('dashboardUsersChart');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ['Jan','Feb','Mar','Apr','May','Jun'],
                        datasets: [{
                            label: 'New Users',
                            data: [50, 75, 60, 90, 120, 150],
                            borderColor: 'rgba(34,197,94,0.9)',
                            backgroundColor: 'rgba(34,197,94,0.1)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false } } }
                });
            }

            const usersCtx = document.getElementById('usersChart');
            if (usersCtx) {
                new Chart(usersCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                        datasets: [{
                            label: 'Registrations',
                            data: [65, 59, 80, 81, 56, 55, 40, 75, 85, 95, 110, 120],
                            backgroundColor: 'rgba(34,197,94,0.7)'
                        }]
                    },
                    options: { responsive: true, indexAxis: 'x' }
                });
            }

            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                new Chart(statusCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Active','Pending','Inactive'],
                        datasets: [{
                            data: [2650, 145, 52],
                            backgroundColor: ['#10B981','#F59E0B','#EF4444']
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });
            }
        });
    </script>

    <!-- ===== FILE UPLOAD FUNCTIONS ===== -->
    <script>
        let selectedFilesData = [];

        function handleFileSelect(event) {
            const files = event.target.files;
            const preview = document.getElementById('previewList');
            const previewContainer = document.getElementById('selectedFilesPreview');
            const submitBtn = document.getElementById('submitBtn');

            preview.innerHTML = '';
            selectedFilesData = [];

            if (files.length > 0) {
                previewContainer.classList.remove('hidden');
                submitBtn.classList.remove('hidden');

                Array.from(files).forEach((file, index) => {
                    const ext = file.name.split('.').pop().toLowerCase();
                    const size = (file.size / 1024 / 1024).toFixed(2);

                    // Validate file
                    const allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    if (!allowed.includes(ext)) {
                        showNotification('Invalid file type: ' + file.name, 'error');
                        return;
                    }

                    if (file.size > 10000000) {
                        showNotification('File too large: ' + file.name + ' (Max 10MB)', 'error');
                        return;
                    }

                    selectedFilesData.push(file);

                    let icon = 'fa-file-pdf';
                    let color = 'text-red-600';

                    if (['doc', 'docx'].includes(ext)) {
                        icon = 'fa-file-word';
                        color = 'text-blue-600';
                    } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
                        icon = 'fa-file-image';
                        color = 'text-green-600';
                    }

                    const fileItem = document.createElement('div');
                    fileItem.className = 'flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg';
                    fileItem.innerHTML = `
                        <div class="flex items-center gap-3">
                            <i class="fas ${icon} ${color} text-lg"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-800">${file.name}</p>
                                <p class="text-xs text-gray-500">${size} MB • Ready to upload</p>
                            </div>
                        </div>
                        <button type="button" onclick="removeSelectedFile(${index})" class="text-gray-400 hover:text-red-600 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(fileItem);
                });
            } else {
                previewContainer.classList.add('hidden');
                submitBtn.classList.add('hidden');
            }
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.currentTarget.style.borderColor = '#059669';
            event.currentTarget.style.backgroundColor = '#dcfce7';
        }

        function handleDragLeave(event) {
            event.currentTarget.style.borderColor = '#86efac';
            event.currentTarget.style.backgroundColor = '#f0fdf4';
        }

        function handleDrop(event) {
            event.preventDefault();
            event.currentTarget.style.borderColor = '#86efac';
            event.currentTarget.style.backgroundColor = '#f0fdf4';

            const files = event.dataTransfer.files;
            document.getElementById('fileInput').files = files;
            handleFileSelect({ target: { files: files } });
        }

        function removeSelectedFile(index) {
            const fileInput = document.getElementById('fileInput');
            const dt = new DataTransfer();
            const { files } = fileInput;

            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }

            fileInput.files = dt.files;
            handleFileSelect({ target: fileInput });
        }

        function clearFilePreview() {
            document.getElementById('fileInput').value = '';
            document.getElementById('selectedFilesPreview').classList.add('hidden');
            document.getElementById('submitBtn').classList.add('hidden');
            document.getElementById('previewList').innerHTML = '';
            selectedFilesData = [];
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-3 rounded-lg text-white font-medium z-50 animate-slideIn ${
                type === 'success' ? 'bg-green-600' : 'bg-red-600'
            }`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>${message}
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Document upload form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('documentUploadForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const files = document.getElementById('fileInput').files;
                    if (files.length === 0) {
                        showNotification('Please select files to upload', 'error');
                        return;
                    }

                    const formData = new FormData();
                    for (let i = 0; i < files.length; i++) {
                        formData.append('documents[]', files[i]);
                    }

                    try {
                        document.getElementById('uploadProgress').classList.remove('hidden');
                        document.getElementById('selectedFilesPreview').classList.add('hidden');

                        let progress = 0;
                        const progressInterval = setInterval(() => {
                            progress += Math.random() * 30;
                            if (progress > 90) progress = 90;
                            updateProgress(progress);
                        }, 300);

                        const response = await fetch('upload.php', {
                            method: 'POST',
                            body: formData
                        });

                        clearInterval(progressInterval);
                        updateProgress(100);

                        const data = await response.json();

                        setTimeout(() => {
                            document.getElementById('uploadProgress').classList.add('hidden');
                            
                            if (data.success) {
                                showNotification(data.message, 'success');
                                clearFilePreview();
                                loadUploadedFiles();
                            } else {
                                showNotification(data.message || 'Upload failed', 'error');
                            }
                        }, 500);

                    } catch (error) {
                        console.error('Upload error:', error);
                        showNotification('Upload failed: ' + error.message, 'error');
                        document.getElementById('uploadProgress').classList.add('hidden');
                    }
                });
            }
        });

        function updateProgress(percent) {
            document.getElementById('progressPercent').textContent = Math.round(percent) + '%';
            document.getElementById('progressBar').style.width = percent + '%';
        }

        async function loadUploadedFiles() {
            try {
                const response = await fetch('upload.php?action=getFiles');
                const data = await response.json();

                if (data.success) {
                    const filesList = document.getElementById('uploadedFilesList');
                    const container = document.getElementById('uploadedFilesContainer');
                    
                    filesList.innerHTML = '';

                    if (data.files.length > 0) {
                        container.classList.remove('hidden');

                        data.files.forEach((file) => {
                            const ext = file.name.split('.').pop().toLowerCase();
                            const size = (file.size / 1024 / 1024).toFixed(2);

                            let icon = 'fa-file-pdf';
                            let color = 'text-red-600';

                            if (['doc', 'docx'].includes(ext)) {
                                icon = 'fa-file-word';
                                color = 'text-blue-600';
                            } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
                                icon = 'fa-file-image';
                                color = 'text-green-600';
                            }

                            const fileItem = document.createElement('div');
                            fileItem.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg animate-fadeIn';
                            fileItem.innerHTML = `
                                <div class="flex items-center gap-3 flex-1">
                                    <i class="fas ${icon} ${color} text-lg"></i>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">${file.name}</p>
                                        <p class="text-xs text-gray-500">${size} MB • Uploaded ${file.time}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">✓ Uploaded</span>
                                    <a href="upload.php?action=downloadFile&fileId=${file.id}" title="Download" class="text-gray-400 hover:text-blue-600 transition">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button onclick="deleteUploadedFile(${file.id})" class="text-gray-400 hover:text-red-600 transition flex items-center justify-center mx-auto">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            `;
                            filesList.appendChild(fileItem);
                        });

                        // Update completion status
                        updateCompletionStatus(data.count);
                    } else {
                        container.classList.add('hidden');
                        updateCompletionStatus(0);
                    }
                }
            } catch (error) {
                console.error('Error loading files:', error);
            }
        }

        function updateCompletionStatus(count) {
            const total = 7;
            const percentage = (count / total) * 100;
            document.getElementById('completionBar').style.width = percentage + '%';
            document.getElementById('completionText').textContent = count;
        }

        async function deleteUploadedFile(fileId) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'deleteFile');
                formData.append('fileId', fileId);

                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('File deleted successfully', 'success');
                    loadUploadedFiles();
                } else {
                    showNotification(data.message || 'Error deleting file', 'error');
                }
            } catch (error) {
                console.error('Delete error:', error);
                showNotification('Delete failed: ' + error.message, 'error');
            }
        }

        // Function to refresh the student table without page reload
        async function refreshStudentTable() {
            try {
                const response = await fetch('manage_roles.php?action=get_students');
                const data = await response.json();
                
                if (data.success) {
                    const tbody = document.getElementById('studentsTbody');
                    
                    if (data.students.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No students found</td></tr>';
                        return;
                    }
                    
                    let html = '';
                    data.students.forEach((student, index) => {
                        const status_class = student.is_active == 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                        const status_text = student.is_active == 1 ? 'Active' : 'Inactive';
                        
                        html += `
                        <tr data-id="${student.id}" 
                            data-name="${(student.first_name + ' ' + student.last_name).toLowerCase()}"
                            data-email="${student.email.toLowerCase()}"
                            data-grade="${student.grade || ''}">
                            <td class="px-4 py-3">
                                <input type="checkbox" class="row-checkbox form-checkbox h-4 w-4 text-green-600" value="${student.id}">
                            </td>
                            <td class="px-4 py-3 flex items-center gap-3">
                                <img src="${student.profile_image || 'uploads/default-profile.png'}" alt="avatar" class="w-8 h-8 rounded-full object-cover border">
                                <div>
                                    <div class="font-medium text-gray-900">${student.first_name} ${student.last_name}</div>
                                    <div class="text-xs text-gray-400">${student.student_id}</div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">${student.email}</td>
                            <td class="px-4 py-3">${student.grade || ''}</td>
                            <td class="px-4 py-3">${student.section || ''}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${status_class}">${status_text}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">${formatTimeAgo(student.created_at)}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="text-indigo-600 hover:text-indigo-900 mr-3" onclick="viewStudent(${student.id})" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="text-yellow-600 hover:text-yellow-800 mr-3" onclick="editStudent(${student.id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-800" onclick="deleteStudent(${student.id}, '${student.first_name} ${student.last_name}')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                    
                    tbody.innerHTML = html;
                    // Re-apply filters after refresh
                    filterStudents();
                }
            } catch (error) {
                console.error('Error refreshing student table:', error);
            }
        }

        // Function to view student details
        function viewStudent(studentId) {
            // You can implement a detailed view modal here
            console.log('View student:', studentId);
            alert('View student details for ID: ' + studentId);
        }

        // Function to edit student
        async function editStudent(studentId) {
            try {
                const response = await fetch(`manage_roles.php?action=get_student&id=${studentId}`);
                const data = await response.json();
                
                if (data.success && data.student) {
                    const student = data.student;
                    
                    // Populate the modal with student data
                    document.getElementById('studentModalTitle').textContent = 'Edit Student';
                    document.getElementById('stuId').value = student.student_id;
                    document.getElementById('stuName').value = student.first_name;
                    document.getElementById('stuLastName').value = student.last_name;
                    document.getElementById('stuEmail').value = student.email;
                    document.getElementById('stuGrade').value = student.grade || '';
                    document.getElementById('stuSection').value = student.section || '';
                    
                    // Store student ID for update
                    document.getElementById('studentForm').dataset.studentId = studentId;
                    
                    // Show modal
                    document.getElementById('studentModal').classList.remove('hidden');
                } else {
                    showNotification('Error loading student data', 'error');
                }
            } catch (error) {
                console.error('Error loading student:', error);
                showNotification('Error loading student data', 'error');
            }
        }

        // Function to delete student
        // Function to delete student
        async function deleteStudent(studentId, studentName) {
            // Decode the student name (it's encoded in the PHP)
            const decodedName = decodeURIComponent(studentName);
            
            if (confirm(`Are you sure you want to delete student "${decodedName}"? This action cannot be undone.`)) {
                try {
                    // Show loading
                    showNotification('Deleting student...', 'info');
                    
                    const response = await fetch('manage_roles.php?action=delete_student&id=' + studentId);
                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification('Student deleted successfully', 'success');
                        
                        // Remove the row from the table directly without refreshing the whole table
                        const row = document.querySelector(`tr[data-id="${studentId}"]`);
                        if (row) {
                            row.remove();
                        }
                        
                        // Update the "Showing X to Y of Z students" message
                        updateStudentCount();
                    } else {
                        showNotification('Error deleting student: ' + (data.message || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    console.error('Error deleting student:', error);
                    showNotification('Error deleting student: ' + error.message, 'error');
                }
            }
        }

        // Function to update student count
        function updateStudentCount() {
            const rows = document.querySelectorAll('#studentsTbody tr:not([style*="display: none"])');
            const totalRows = document.querySelectorAll('#studentsTbody tr').length;
            const visibleCount = rows.length;
            
            const showingElement = document.querySelector('.flex.items-center.justify-between.mt-4 .text-sm.text-gray-600');
            if (showingElement) {
                showingElement.textContent = `Showing 1 to ${visibleCount} of ${totalRows} students`;
            }
        }
                // Add Student Modal Functions
                    window.openAddStudentModal = function() {
                        document.getElementById('studentModalTitle').textContent = 'Add New Student';
                        document.getElementById('studentForm').reset();
                        document.getElementById('studentModal').classList.remove('hidden');
                    };

                    window.closeStudentModal = function() {
                        document.getElementById('studentModal').classList.add('hidden');
                        document.getElementById('studentForm').reset();
                        delete document.getElementById('studentForm').dataset.studentId;
                        document.getElementById('studentModalTitle').textContent = 'Add New Student';
                    };

                    // Update the saveStudent function to handle both add and edit
        window.saveStudent = async function(e) {
            e.preventDefault();
            
            const form = document.getElementById('studentForm');
            const studentId = form.dataset.studentId; // For edit mode
            const isEdit = !!studentId;
            
            // Collect form data
            const formData = {
                student_id: document.getElementById('stuId').value,
                first_name: document.getElementById('stuName').value,
                last_name: document.getElementById('stuLastName').value,
                email: document.getElementById('stuEmail').value,
                grade: document.getElementById('stuGrade').value,
                section: document.getElementById('stuSection').value
            };
            
            // Validation
            if (!formData.student_id || !formData.first_name || !formData.last_name || !formData.email) {
                alert("Please fill in all required fields");
                return false;
            }
            
            // Show loading
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            submitBtn.disabled = true;
            
            try {
                const fd = new FormData();
                
                if (isEdit) {
                    fd.append('action', 'update_student');
                    fd.append('id', studentId);
                } else {
                    fd.append('action', 'add_student');
                }
                
                fd.append('student_id', formData.student_id);
                fd.append('first_name', formData.first_name);
                fd.append('last_name', formData.last_name);
                fd.append('email', formData.email);
                fd.append('grade', formData.grade);
                fd.append('section', formData.section);
                
                const response = await fetch('manage_roles.php', {
                    method: 'POST',
                    body: fd
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeStudentModal();
                    refreshStudentTable(); // Refresh the table with new data
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error("Error saving student:", error);
                showNotification('Error: ' + error.message, 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
            
            return false;
        };

        // Roles UI behaviour + client-side / AJAX integration
        (function(){
            const createMenu = document.getElementById('createRoleMenu');
            const createMenuBtn = document.getElementById('createRoleMenuBtn');
            const createBtn = document.getElementById('createRoleBtn');
            if (createMenuBtn) createMenuBtn.addEventListener('click', e => { e.stopPropagation(); createMenu.classList.toggle('hidden'); });
            document.addEventListener('click', ()=> createMenu && createMenu.classList.add('hidden'));
            if (createBtn) createBtn.addEventListener('click', openAddRoleModal);

            window.applyRoleTemplate = function(){
                const sel = document.getElementById('roleTemplateSelect');
                if (!sel || !sel.value) return;
                const [name, perms] = sel.value.split('|');
                document.getElementById('addRoleName').value = name;
                document.getElementById('addPermissions').value = perms;
                openAddRoleModal();
                createMenu && createMenu.classList.add('hidden');
            }

            // modals
            window.openAddRoleModal = () => document.getElementById('addRoleModal').classList.remove('hidden');
            window.closeAddRoleModal = () => { 
                document.getElementById('addRoleForm').reset(); 
                document.getElementById('addRoleModal').classList.add('hidden');
                
                // ======= ADD THIS: Reset the button state =======
                const saveBtn = document.querySelector('#addRoleForm button[type="submit"]');
                if (saveBtn) {
                    saveBtn.innerHTML = 'Save';
                    saveBtn.disabled = false;
                }
                // ======= END OF ADDITION =======
            };
            window.openEditRoleModal = () => document.getElementById('editRoleModal').classList.remove('hidden');
            window.closeEditRoleModal = () => { document.getElementById('editRoleForm').reset(); document.getElementById('editRoleModal').classList.add('hidden'); };

            // in-memory fallback roles (used if backend missing)
            let rolesCache = [
                { id: 1, role_name: 'Administrator', permissions: 'manage_users,settings,analytics', description: 'Full access' },
                { id: 2, role_name: 'Moderator', permissions: 'read,update,ban', description: 'Content moderation' },
                { id: 3, role_name: 'User', permissions: 'read', description: 'Regular user' }
            ];

            async function loadRolesFromServer(){
                try {
                    const res = await fetch('manage_roles.php?action=get_roles');
                    const json = await res.json();
                    if (json.success && Array.isArray(json.roles)) {
                        rolesCache = json.roles;
                    }
                } catch (e) {
                    // ignore — use fallback rolesCache
                }
            }

            // Render Roles List 
            window.renderRoles = function(){
                const tbody = document.getElementById('rolesTbody');
                const q = (document.getElementById('roleSearch')?.value || '').toLowerCase();
                const filter = (document.getElementById('roleFilter')?.value || '').toLowerCase();

                const list = rolesCache.filter(r => {
                    if (q && !(r.role_name.toLowerCase().includes(q) || (r.description||'').toLowerCase().includes(q) || (r.permissions||'').toLowerCase().includes(q))) return false;
                    if (filter) {
                        const perms = (r.permissions||'').toLowerCase().split(',').map(s=>s.trim());
                        if (!perms.includes(filter)) return false;
                    }
                    return true;
                });

                if (list.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No roles found.</td></tr>';
                    return;
                }

                tbody.innerHTML = list.map((r, idx) => {
                    const perms = (r.permissions || '').split(',').map(p => `<span class="inline-block text-xs mr-1 mb-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700">${escapeHtml(p)}</span>`).join('');
                    const desc = r.description ? escapeHtml(r.description) : '<span class="text-xs text-gray-400">—</span>';
                    return `<tr>
                        <td class="px-4 py-3">${idx+1}</td>
                        <td class="px-4 py-3 font-medium">${escapeHtml(r.role_name)}</td>
                        <td class="px-4 py-3">${perms}</td>
                        <td class="px-4 py-3">${desc}</td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-yellow-600 mr-3" onclick="onEditRole(${r.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="text-red-600" onclick="onDeleteRole(${r.id}, '${escapeJs(r.role_name)}')" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>`;
                }).join('');
            };

            function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
            function escapeJs(s){ return String(s||'').replace(/['"\\]/g,'\\$&'); }

            // Try loading from server; always render afterwards (fallback keeps UI usable)
            async function initRoles(){
                await loadRolesFromServer();
                renderRoles();
            }
            // Replace the window.saveRole function in your dashboard code with this improved version:
                        // Add this function to handle permission checkbox changes for ADD modal
            function updateSelectedPermissions() {
                const checkboxes = document.querySelectorAll('#addRoleModal .permission-checkbox:checked');
                const selectedTags = document.getElementById('selectedTags');
                const permissionsInput = document.getElementById('permissionsValue');
                
                // Update display
                if (checkboxes.length === 0) {
                    selectedTags.innerHTML = '<span class="text-sm text-gray-500">No permissions selected</span>';
                } else {
                    selectedTags.innerHTML = '';
                    const values = [];
                    
                    checkboxes.forEach(checkbox => {
                        values.push(checkbox.value);
                        const tag = document.createElement('span');
                        tag.className = 'inline-block text-xs px-2 py-1 rounded-full bg-green-100 text-green-800';
                        tag.textContent = checkbox.value;
                        selectedTags.appendChild(tag);
                    });
                    
                    // Update hidden input with comma-separated values
                    permissionsInput.value = values.join(',');
                }
            }

            // Add this function for EDIT modal
            function updateEditSelectedPermissions() {
                const checkboxes = document.querySelectorAll('#editRoleModal .permission-checkbox:checked');
                const selectedTags = document.getElementById('editSelectedTags');
                const permissionsInput = document.getElementById('editPermissions');
                
                if (checkboxes.length === 0) {
                    selectedTags.innerHTML = '<span class="text-sm text-gray-500">No permissions selected</span>';
                } else {
                    selectedTags.innerHTML = '';
                    const values = [];
                    
                    checkboxes.forEach(checkbox => {
                        values.push(checkbox.value);
                        const tag = document.createElement('span');
                        tag.className = 'inline-block text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800';
                        tag.textContent = checkbox.value;
                        selectedTags.appendChild(tag);
                    });
                    
                    permissionsInput.value = values.join(',');
                }
            }
            window.saveRole = async function(e) {
                    e.preventDefault();
                    
                    const name = document.getElementById('addRoleName').value.trim();
                    if (!name) { 
                        alert('Role name required'); 
                        return; 
                    }
                    
                    // Get permissions from the hidden input
                    const permissions = document.getElementById('permissionsValue').value.trim();
                    const description = document.getElementById('addRoleDescription').value.trim();

                    // Show loading state
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    submitBtn.disabled = true;

                    try {
                        // First try server save
                        const fd = new FormData();
                        fd.append('action', 'add');
                        fd.append('role_name', name);
                        fd.append('permissions', permissions);
                        fd.append('description', description);
                        
                        const res = await fetch('manage_roles.php', { method: 'POST', body: fd });
                        const json = await res.json();
                        
                        if (json.success) {
                            // Server save successful
                            showNotification('Role created successfully!', 'success');
                            
                            // Reload roles from server
                            await loadRolesFromServer();
                            renderRoles();
                            
                            // Close modal and reset form
                            closeAddRoleModal();
                            
                            // ======= ADD THIS: Reset button state =======
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            // ======= END OF ADDITION =======
                        } else {
                            // Server save failed
                            throw new Error(json.message || 'Failed to create role');
                        }
                    } catch(error) {
                        // Show error to user
                        showNotification(error.message || 'Error creating role. Please try again.', 'error');
                        
                        // ======= ADD THIS: Restore button state on error =======
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        // ======= END OF ADDITION =======
                        
                        // Don't close the modal
                        return;
                    }
                };
            window.onEditRole = async function(id){
                            // try server fetch, fallback to local
                            try {
                                const res = await fetch('manage_roles.php?action=get&id='+encodeURIComponent(id));
                                const json = await res.json();
                                if (json.success && json.role) {
                                    fillEditForm(json.role);
                                    openEditRoleModal();
                                    return;
                                }
                            } catch (e) {}
                            const r = rolesCache.find(x => x.id === id);
                            if (!r) { alert('Role not found'); return; }
                            fillEditForm(r);
                            openEditRoleModal();
                        };

                        function fillEditForm(r){
                document.getElementById('editRoleId').value = r.id;
                document.getElementById('editRoleName').value = r.role_name || '';
                document.getElementById('editPermissions').value = r.permissions || '';
                document.getElementById('editRoleDescription').value = r.description || '';
                
                // ======= ADD THIS: Check the checkboxes based on permissions =======
                const permissionsArray = (r.permissions || '').split(',').map(p => p.trim());
                const checkboxes = document.querySelectorAll('#editRoleModal .permission-checkbox');
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = permissionsArray.includes(checkbox.value);
                });
                
                // Update the visual tags display
                updateEditSelectedPermissions();
                // ======= END OF ADDITION =======
            }

                        window.updateRole = async function(e){
                e.preventDefault();
                const id = parseInt(document.getElementById('editRoleId').value, 10);
                if (!id) { alert('Invalid role'); return; }
                const role_name = document.getElementById('editRoleName').value.trim();
                const permissions = document.getElementById('editPermissions').value.trim(); // Make sure this ID exists
                const description = document.getElementById('editRoleDescription').value.trim();

                // Show loading state
                const submitBtn = e.target.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
                submitBtn.disabled = true;

                try {
                    // Attempt server update
                    const fd = new FormData();
                    fd.append('action','edit');
                    fd.append('id', id);
                    fd.append('role_name', role_name);
                    fd.append('permissions', permissions);
                    fd.append('description', description);
                    
                    const res = await fetch('manage_roles.php', { method:'POST', body: fd });
                    const json = await res.json();
                    
                    if (json.success) {
                        showNotification('Role updated successfully!', 'success');
                        await loadRolesFromServer();
                        renderRoles();
                        closeEditRoleModal();
                        
                        // Reset button
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    } else {
                        throw new Error(json.message || 'Failed to update role');
                    }
                } catch(e) {
                    showNotification('Error updating role: ' + e.message, 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            };

            window.onDeleteRole = async function(id, name){
                if (!confirm(`Delete role "${name}"? This action cannot be undone.`)) return;
                
                try {
                    // Attempt server delete FIRST
                    const res = await fetch('manage_roles.php?action=delete&id='+encodeURIComponent(id));
                    const json = await res.json();
                    
                    if (json.success) {
                        // Server delete successful - update local cache
                        showNotification('Role deleted successfully!', 'success');
                        
                        // Reload from server to ensure consistency
                        await loadRolesFromServer();
                        renderRoles();
                    } else {
                        // Server delete failed
                        showNotification('Delete failed: ' + json.message, 'error');
                        console.warn('Server delete failed:', json.message);
                        
                        // Don't remove from local cache if server failed
                    }
                } catch(error) {
                    showNotification('Error deleting role: ' + error.message, 'error');
                    console.error('Delete error:', error);
                }
            };

            // init on DOM ready
            document.addEventListener('DOMContentLoaded', function() {
                initRoles();
                
                // Add event listeners to checkboxes in add modal
                const checkboxes = document.querySelectorAll('.permission-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateSelectedPermissions);
                });
                
                // Add event listeners to checkboxes in edit modal
                const editCheckboxes = document.querySelectorAll('#editRoleModal .permission-checkbox');
                editCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateEditSelectedPermissions);
                });
            });
            // document.addEventListener('DOMContentLoaded', initRoles);
        })();

        // Add this to your JavaScript section
document.addEventListener('DOMContentLoaded', function() {
    // Make the profile image clickable
    const profileImageTrigger = document.getElementById('profileImageTrigger');
    const profileImageInput = document.getElementById('profileImage');
    
    if (profileImageTrigger && profileImageInput) {
        profileImageTrigger.addEventListener('click', function() {
            profileImageInput.click();
        });
    }
});

function handleProfileImageChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file type
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!validTypes.includes(file.type)) {
        alert('Please select a valid image file (JPEG, PNG, GIF)');
        return;
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Image size should be less than 5MB');
        return;
    }
    
    // Show preview immediately
    const reader = new FileReader();
    reader.onload = function(e) {
        const profileImg = document.getElementById('profileImageTrigger');
        profileImg.src = e.target.result;
        
        // Show upload indicator
        const cameraIcon = document.querySelector('.relative.group .fa-camera');
        if (cameraIcon) {
            cameraIcon.classList.remove('fa-camera');
            cameraIcon.classList.add('fa-spinner', 'fa-spin');
        }
    };
    reader.readAsDataURL(file);
    
    // Upload via AJAX
    const formData = new FormData();
    formData.append('profileImage', file);
    formData.append('ajax_upload', 'true');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Reset camera icon
        const cameraIcon = document.querySelector('.relative.group .fa-spinner');
        if (cameraIcon) {
            cameraIcon.classList.remove('fa-spinner', 'fa-spin');
            cameraIcon.classList.add('fa-camera');
        }
        
        // Show success message
        showNotification('Profile picture updated successfully!', 'success');
        
        // DON'T force refresh - keep the preview image
        // The preview is already showing the uploaded image
    })
    .catch(error => {
        console.error('Upload error:', error);
        
        // Reset camera icon
        const cameraIcon = document.querySelector('.relative.group .fa-spinner');
        if (cameraIcon) {
            cameraIcon.classList.remove('fa-spinner', 'fa-spin');
            cameraIcon.classList.add('fa-camera');
        }
        
        showNotification('Upload failed. Please try again.', 'error');
    });
}   
    </script>

</body>

</html>

