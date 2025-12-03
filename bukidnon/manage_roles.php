<?php
// manage_roles.php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include('db_config.php');

// Set response header to JSON
header('Content-Type: application/json');

// Handle different actions
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_roles':
        getRoles();
        break;
    case 'get':
        getRole();
        break;
    case 'add':
        addRole();
        break;
    case 'edit':
        editRole();
        break;
    case 'delete':
        deleteRole();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Add this after your other switch cases in manage_roles.php or create new file
if ($action === 'add_student') {
    addStudent();
} elseif ($action === 'get_students') {
    getStudents();
} elseif ($action === 'update_student') {
    updateStudent();
} elseif ($action === 'delete_student') {
    deleteStudent();
}
function deleteStudent() {
    global $pdo;
    
    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        exit();
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // First delete related profile images
        $sqlDeleteImages = "DELETE FROM profile_images WHERE user_id = ?";
        $stmtDeleteImages = $pdo->prepare($sqlDeleteImages);
        $stmtDeleteImages->execute([$id]);
        
        // Then delete the student
        $sql = "DELETE FROM students WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        if ($stmt->rowCount() > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }
        
    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error deleting student: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to delete student: ' . $e->getMessage()]);
        exit();
    }
}
function addStudent() {
    global $pdo;
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    $required = ['first_name', 'last_name', 'email', 'student_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            exit(); // Add exit() to stop script execution
        }
    }
    
    try {
        // Check if student ID or email already exists
        $checkSql = "SELECT id FROM students WHERE student_id = ? OR email = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$_POST['student_id'], $_POST['email']]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student ID or Email already exists']);
            exit(); // Add exit()
        }
        
        $sql = "INSERT INTO students (
            student_id, first_name, last_name, email, 
            grade, section
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['student_id'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['grade'] ?? '',
            $_POST['section'] ?? ''
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Student added successfully',
            'id' => $pdo->lastInsertId()
        ]);
        exit(); // Add exit()
    } catch (PDOException $e) {
        error_log("Error adding student: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add student']);
        exit(); // Add exit()
    }
}

function getRoles() {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM roles ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'roles' => $roles
        ]);
    } catch (PDOException $e) {
        error_log("Error fetching roles: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch roles',
            'debug' => $e->getMessage()
        ]);
    }
}

function getRole() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Role ID required']);
        return;
    }
    
    try {
        $sql = "SELECT * FROM roles WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role) {
            echo json_encode([
                'success' => true,
                'role' => $role
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Role not found'
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error fetching role: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch role'
        ]);
    }
}

function addRole() {
    global $pdo;
    
    $role_name = $_POST['role_name'] ?? '';
    $permissions = $_POST['permissions'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($role_name)) {
        echo json_encode(['success' => false, 'message' => 'Role name is required']);
        return;
    }
    
    try {
        // Check if role already exists
        $checkSql = "SELECT id FROM roles WHERE role_name = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$role_name]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Role already exists']);
            return;
        }
        
        // Insert new role
        // $sql = "INSERT INTO roles (role_name, permissions, description, created_at) 
        //         VALUES (?, ?, ?, NOW())";
        
        $sql = "INSERT INTO roles (role_name, permissions, created_at) 
                VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        // $stmt->execute([$role_name, $permissions, $description]);
        $stmt->execute([$role_name, $permissions]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Role created successfully',
            'id' => $newId
        ]);
    } catch (PDOException $e) {
        error_log("Error adding role: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create role'
        ]);
    }
}

function editRole() {
    global $pdo;
    
    $id = $_POST['id'] ?? 0;
    $role_name = $_POST['role_name'] ?? '';
    $permissions = $_POST['permissions'] ?? '';
    
    // Remove description since column doesn't exist
    // $description = $_POST['description'] ?? '';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Role ID required']);
        return;
    }
    
    if (empty($role_name)) {
        echo json_encode(['success' => false, 'message' => 'Role name is required']);
        return;
    }
    
    try {
        // Check if role name conflicts with another role
        $checkSql = "SELECT id FROM roles WHERE role_name = ? AND id != ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$role_name, $id]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Role name already exists']);
            return;
        }
        
        // Fixed SQL: Removed description column
        $sql = "UPDATE roles 
                SET role_name = ?, permissions = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$role_name, $permissions, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Role updated successfully'
        ]);
    } catch (PDOException $e) {
        error_log("Error editing role: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update role: ' . $e->getMessage()
        ]);
    }
}

function deleteRole() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Role ID required']);
        return;
    }
    
    try {
        // No need to check for user assignments if role_id column doesn't exist
        // Directly delete the role
        
        $sql = "DELETE FROM roles WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Role not found or already deleted'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Error deleting role: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete role: ' . $e->getMessage()
        ]);
    }
}
?>