<?php
include('db_config.php'); // Include database connection

// Fetch all images from the database
$query = "SELECT * FROM profile_images";
$stmt = $pdo->query($query);

// Display images
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<div>';
    // Display the image using the path stored in the database
    echo '<img src="' . $row['image_path'] . '" alt="Uploaded Image" style="width:100px; height:auto;">';
    echo '<p>Uploaded on: ' . $row['upload_date'] . '</p>';
    echo '</div>';
}
?>
