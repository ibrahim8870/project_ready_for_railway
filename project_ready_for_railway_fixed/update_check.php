<?php
// update_check.php
require 'config.php';
if (!isset($_SESSION['loggedin'])) {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['item_id'])) {
    $item_id = $_POST['item_id'];
    
    $stmt = $conn->prepare("UPDATE items SET last_checked_on = NOW() WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
}

header("location: weekly_check.php");
exit;
?>
	