<?php
session_start();
require 'config.php';

// Security Check - Only admin can delete
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || $_SESSION['role'] !== 'admin') {
    header("location: new_alerts.php");
    exit;
}

$item_id = $_GET['id'] ?? null;

if (!$item_id) {
    header("location: new_alerts.php");
    exit;
}

// Fetch the item information and image name from secondary_shelf_items table
$stmt = $conn->prepare("SELECT image FROM secondary_shelf_items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if ($item) {
    // Delete the item from the database
    $delete_stmt = $conn->prepare("DELETE FROM secondary_shelf_items WHERE id = ?");
    $delete_stmt->bind_param("i", $item_id);
    
    if ($delete_stmt->execute()) {
        // If the item has an image, delete the image from the server as well
        if (!empty($item['image']) && file_exists("uploads/" . $item['image'])) {
            unlink("uploads/" . $item['image']);
        }
    }
    $delete_stmt->close();
}

// Redirect back to secondary shelf life page
header("location: new_alerts.php");
exit;
?>
