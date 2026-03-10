<?php
// api_check_expiry.php
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['expiredToday' => 0]);
    exit;
}

$today_str = date('Y-m-d');

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE expiry_date = ?");
$stmt->bind_param("s", $today_str);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['expiredToday' => (int)$result['count']]);

$stmt->close();
$conn->close();
?>
	