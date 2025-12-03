<?php
// Start the session
session_start();

// Destroy the session to log out the user
session_destroy();

// Clear session variables to remove any session data
unset($_SESSION['logged_in']);
unset($_SESSION['email']);
unset($_SESSION['user_id']);

// Redirect to login page after logout
header("Location: login_page.php");
exit(); // Make sure no further code is executed after the redirect
?>
