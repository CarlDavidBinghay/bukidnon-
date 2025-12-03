<?php
// filepath: c:\xampp\htdocs\bukidnon\upload.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
include('db_config.php');

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User ID not found']);
    exit();
}

// -------------------------- new: get username and per-user folder --------------------------
$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

// If username is not in session, try to fetch from DB (adjust table/column if different)
if (empty($username)) {
    try {
        $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$user_id]);
        $urow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $username = $urow['username'] ?? null;
    } catch (PDOException $e) {
        error_log("Unable to fetch username: " . $e->getMessage());
    }
}

if (empty($username)) {
    // fallback if username missing
    $username = 'user' . $user_id;
}

// sanitize username for folder name: allow letters, numbers, dash and underscore; shorten to 30 chars
$sanitizedUsername = preg_replace('/[^A-Za-z0-9_-]/', '_', substr($username, 0, 30));
$userFolder = 'user_' . $user_id . '_' . $sanitizedUsername;
$baseUploadDir = 'uploads' . DIRECTORY_SEPARATOR . 'documents';
$userUploadDir = $baseUploadDir . DIRECTORY_SEPARATOR . $userFolder;
// -----------------------------------------------------------------------------------------

// Handle AJAX file uploads for documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {
    $response = [
        'success' => false,
        'files' => [],
        'message' => ''
    ];

    $files = $_FILES['documents'];
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    
    // Create user-specific documents directory if it doesn't exist
    if (!is_dir($userUploadDir)) {
        if (!mkdir($userUploadDir, 0755, true)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit();
        }
    }

    $uploadedCount = 0;
    $errors = [];

    // Check if files array is properly structured
    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']],
            'type' => [$files['type']]
        ];
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        $fileName = $files['name'][$i];
        $fileTmp = $files['tmp_name'][$i];
        $fileError = $files['error'][$i];
        $fileSize = $files['size'][$i];

        // Skip empty files
        if (empty($fileName)) {
            continue;
        }

        // Validate file error
        if ($fileError !== 0) {
            $errors[] = $fileName . ' - Upload error (code: ' . $fileError . ')';
            continue;
        }

        // Validate file extension
        $fileName_ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileName_ext, $allowed)) {
            $errors[] = $fileName . ' - Invalid file type (allowed: PDF, JPG, PNG, DOC, DOCX)';
            continue;
        }

        // Validate file size (10MB max)
        if ($fileSize > 10000000) {
            $errors[] = $fileName . ' - File too large (max 10MB)';
            continue;
        }

        // Validate file is actually uploaded
        if (!is_uploaded_file($fileTmp)) {
            $errors[] = $fileName . ' - Invalid upload';
            continue;
        }

        // Generate unique filename with user_id
        $timestamp = time();
        $randomStr = substr(md5(rand()), 0, 8);
        $newFileName = 'doc_' . $user_id . '_' . $timestamp . '_' . $randomStr . '.' . $fileName_ext;
        $uploadPath = $userUploadDir . DIRECTORY_SEPARATOR . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmp, $uploadPath)) {
            try {
                // Insert into database
                $sql = "INSERT INTO user_documents (user_id, document_name, document_path, file_size, upload_date) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, htmlspecialchars($fileName), $uploadPath, $fileSize]);

                $fileData = [
                    'name' => htmlspecialchars($fileName),
                    'path' => $uploadPath,
                    'size' => $fileSize,
                    'time' => date('Y-m-d H:i:s'),
                    'id' => $pdo->lastInsertId()
                ];

                // Add to response
                $response['files'][] = $fileData;
                $uploadedCount++;

            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                // Delete file if database insert fails
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                $errors[] = $fileName . ' - Database error';
            }
        } else {
            $errors[] = $fileName . ' - Failed to save file';
        }
    }

    // Build response message
    if ($uploadedCount > 0) {
        $response['success'] = true;
        $response['message'] = $uploadedCount . ' file(s) uploaded successfully! Saved to ' . $userFolder;
        if (!empty($errors)) {
            $response['message'] .= ' (' . count($errors) . ' file(s) failed)';
        }
    } else {
        $response['message'] = !empty($errors) ? 'Upload failed: ' . implode(', ', $errors) : 'No files were uploaded';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle file deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteFile') {
    header('Content-Type: application/json');
    
    if (isset($_POST['fileId'])) {
        $fileId = intval($_POST['fileId']);
        
        try {
            // Get file path from database
            $sql = "SELECT document_path FROM user_documents WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fileId, $user_id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($file) {
                $filePath = $file['document_path'];

                // Delete physical file
                if (file_exists($filePath)) {
                    if (!unlink($filePath)) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to delete file from server']);
                        exit();
                    }
                }

                // Delete from database
                $deleteSql = "DELETE FROM user_documents WHERE id = ? AND user_id = ?";
                $deleteStmt = $pdo->prepare($deleteSql);
                $deleteStmt->execute([$fileId, $user_id]);

                echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File not found']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            error_log("Database error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing file ID']);
    }
    exit();
}

// Get uploaded documents via AJAX (from database)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getFiles') {
    header('Content-Type: application/json');
    
    try {
        $sql = "SELECT id, document_name as name, document_path as path, file_size as size, upload_date as time FROM user_documents WHERE user_id = ? ORDER BY upload_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format file sizes
        foreach ($files as &$file) {
            $file['time'] = date('Y-m-d H:i:s', strtotime($file['time']));
        }

        echo json_encode([
            'success' => true,
            'files' => $files,
            'count' => count($files)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error', 'files' => [], 'count' => 0]);
    }
    exit();
}

// Download file
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'downloadFile') {
    // Note: do not send JSON headers here because we stream file
    if (isset($_GET['fileId'])) {
        $fileId = intval($_GET['fileId']);
        
        try {
            // Get file from database
            $sql = "SELECT document_name, document_path FROM user_documents WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fileId, $user_id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($file && file_exists($file['document_path'])) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file['document_name']) . '"');
                header('Content-Length: ' . filesize($file['document_path']));
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                
                readfile($file['document_path']);
                exit();
            } else {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'File not found']);
                exit();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            error_log("Database error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database error']);
            exit();
        }
    }
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request']);
?>