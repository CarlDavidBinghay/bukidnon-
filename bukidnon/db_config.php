<?php
$host = 'localhost';      // Database host, usually 'localhost'
$dbname = 'admin_dashboard';  // Your database name
$username = 'root';       // Default username for MySQL in XAMPP
$password = '';           // Default password for MySQL in XAMPP (usually empty)

// Try to connect to the database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Handle connection error
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>
