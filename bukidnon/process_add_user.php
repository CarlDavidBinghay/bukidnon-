<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db_config.php';

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role_id = intval($_POST['role_id'] ?? 0);

if ($username === '' || $email === '' || $password === '' || $role_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

try {
    // check existing username or email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit();
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // adjust columns if your users table uses different names
    $sql = "INSERT INTO users (username, email, password, role_id, created_at) VALUES (?, ?, ?, ?, NOW())";
    $ins = $pdo->prepare($sql);
    $ins->execute([$username, $email, $passwordHash, $role_id]);

    echo json_encode(['success' => true, 'message' => 'User created successfully']);
    exit();
} catch (PDOException $e) {
    error_log('Add user error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}
?>