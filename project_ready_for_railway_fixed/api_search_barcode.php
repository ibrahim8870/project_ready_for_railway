<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$barcode = $input['barcode'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // User role

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Barcode not provided.']);
    exit;
}

// --- Main change here ---
$sql = "SELECT name, category, quantity, expiry_date, image 
        FROM items 
        WHERE barcode = ?";
$params = [$barcode];
$types = 's';

// If not admin, search only for their own items
if ($user_role !== 'admin') {
    $sql .= " AND added_by = ?";
    $types .= 'i';
    array_push($params, $user_id);
}

$sql .= " ORDER BY expiry_date ASC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $item = $result->fetch_assoc();
    
    // Calculate days until expiry
    $today = new DateTime();
    $expiry_dt = new DateTime($item['expiry_date']);
    $diff = (int)$today->diff($expiry_dt)->format("%r%a");
    $item['days_until_expiry'] = $diff;
    
    echo json_encode(['success' => true, 'item' => $item]);
} else {
    // Separate message for admin and regular user
    if ($user_role === 'admin') {
        echo json_encode(['success' => false, 'message' => 'No product found with this barcode in the entire database.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No product found with this barcode in your list.']);
    }
}

$stmt->close();
$conn->close();
?>
