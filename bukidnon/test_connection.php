<?php
include('db_config.php');

if ($pdo) {
    echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724; margin: 20px;'>";
    echo "<h3>✓ Database Connection Successful!</h3>";
    echo "<p>Your database is connected and working properly.</p>";
    echo "</div>";
    
    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM users");
        $data = $result->fetch(PDO::FETCH_ASSOC);
        echo "<p style='margin-top: 15px; margin-left: 20px;'><strong>Users in database:</strong> " . $data['count'] . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red; margin: 20px;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    }
} else {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; margin: 20px;'>";
    echo "<h3>✗ Database Connection Failed!</h3>";
    echo "<p>Check db_config.php for connection details.</p>";
    echo "</div>";
}
?>