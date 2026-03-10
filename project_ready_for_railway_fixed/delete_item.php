<?php
session_start();
require 'config.php';

// --- New Security Check ---
// If the user is not logged in or their role is not 'admin'
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || $_SESSION['role'] !== 'admin') {
    // Then they will be redirected to the dashboard and no action will be taken
    header("location: dashboard.php");
    exit;
}
// --- End Security Check ---

$item_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id']; // Although admin is deleting, added_by can be checked

if (!$item_id) {
    header("location: view_items.php");
    exit;
}

// First, fetch the item information and image name from the database
// Admin can delete any user's item, so added_by is not being checked
$stmt = $conn->prepare("SELECT image FROM items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if ($item) {
    // Delete the item from the database
    $delete_stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $delete_stmt->bind_param("i", $item_id);
    
    if ($delete_stmt->execute()) {
        // If the item has an image, delete the image from the server as well
        if (!empty($item['image']) && file_exists("uploads/" . $item['image'])) {
            unlink("uploads/" . $item['image']);
        }
    }
    $delete_stmt->close();
}

// Redirect the user back to the item list page
header("location: view_items.php");
exit;
?>
