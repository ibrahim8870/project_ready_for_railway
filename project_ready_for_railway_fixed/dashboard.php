<?php
session_start();

require 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];

// --- Separate calculation for Admin and User ---
$today = date('Y-m-d');

function get_count($conn, $condition = "", $params = [], $types = "") {
    global $user_id, $user_role;
    
    $sql = "SELECT COUNT(*) as total FROM items";
    
    $final_conditions = [];
    $final_params = [];
    $final_types = '';

    // added_by filter will not be applied for admin, unless they filter a specific user
    if ($user_role !== 'admin') {
        $final_conditions[] = "added_by = ?";
        array_push($final_params, $user_id);
        $final_types .= 'i';
    }

    if (!empty($condition)) {
        $final_conditions[] = $condition;
        $final_params = array_merge($final_params, $params);
        $final_types .= $types;
    }

    if (count($final_conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $final_conditions);
    }

    $stmt = $conn->prepare($sql);
    if (!empty($final_types)) {
        $stmt->bind_param($final_types, ...$final_params);
    }
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $count;
}

// Calculations are done using the new function
$total_items_result = get_count($conn);
$expired_today_result = get_count($conn, "expiry_date = ?", [$today], "s");
$already_expired_result = get_count($conn, "expiry_date < ?", [$today], "s");

$seven_days_later = date('Y-m-d', strtotime('+7 days'));
$expiring_soon_result = get_count($conn, "expiry_date > ? AND expiry_date <= ?", [$today, $seven_days_later], "ss");

$thirty_days_later = date('Y-m-d', strtotime('+30 days'));
$expiring_in_30_days_result = get_count($conn, "expiry_date > ? AND expiry_date <= ?", [$today, $thirty_days_later], "ss");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Café Expiry Reminder Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4">
        
        <div class="dashboard-header">
            <h1 style="color: #FF8C00; font-size: 2.5rem; font-weight: bold; margin: 0; font-family: 'Arial Black', sans-serif;">Dunkin Donut</h1>
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="text-muted mb-0">
                <?php if ($user_role === 'admin'): ?>
                    Overview of all item statuses
                <?php else: ?>
                    Overview of your item statuses
                <?php endif; ?>
            </p>
        </div>
        <div class="text-center mb-4 d-flex justify-content-center align-items-center gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#barcodeSearchModal">
                <i class="fas fa-barcode"></i> Search by Barcode
            </button>
	            <div class="dropdown">
	                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
	                    <i class="fas fa-ellipsis-v"></i> More
	                </button>
	                <ul class="dropdown-menu dropdown-menu-end">
	                    <li><a class="dropdown-item" href="new_alerts.php"><i class="fas fa-clock"></i> Secondary Shelf Life</a></li>
	                    <li><hr class="dropdown-divider"></li>
	                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
	                </ul>
	            </div>
        </div>

        <!-- Status Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4">
                <a href="view_items.php?filter=all" class="stat-card-link">
                    <div class="stat-card">
                        <h5>Total Items</h5>
                        <p class="display-6 text-dark"><?php echo $total_items_result; ?></p>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="view_items.php?filter=expired_today" class="stat-card-link">
                    <div class="stat-card">
                        <h5>Expiring Today</h5>
                        <p class="display-6 text-danger"><?php echo $expired_today_result; ?></p>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="view_items.php?filter=already_expired" class="stat-card-link">
                    <div class="stat-card">
                        <h5>Already Expired</h5>
                        <p class="display-6 text-danger"><?php echo $already_expired_result; ?></p>
                    </div>
                </a>
            </div>
	            <div class="col-6 col-md-6">
	                <a href="view_items.php?filter=expiring_soon" class="stat-card-link">
	                    <div class="stat-card">
	                        <h5>Expiring in 7 Days</h5>
	                        <p class="display-6 text-warning"><?php echo $expiring_soon_result; ?></p>
	                    </div>
	                </a>
	            </div>
	            <div class="col-12 col-md-6">
	                <a href="view_items.php?filter=expiring_in_30_days" class="stat-card-link">
	                    <div class="stat-card">
	                        <h5>Expiring in 30 Days</h5>
	                        <p class="display-6 text-warning"><?php echo $expiring_in_30_days_result; ?></p>
	                    </div>
	                </a>
	            </div>
        </div>

        <!-- Menu Items -->
        <div class="d-grid gap-3">
            <a href="add_item.php" class="main-menu-item">
                <div class="icon-wrapper"><i class="fas fa-plus"></i></div>
                <div class="menu-text"><h4>Add Item</h4><p>Add new item to the system</p></div>
            </a>
	            <a href="view_items.php" class="main-menu-item">
	                <div class="icon-wrapper"><i class="fas fa-list-alt"></i></div>
	                <div class="menu-text"><h4>Item List</h4><p>View list of all items</p></div>
	            </a>

            <a href="send_reminders.php" class="main-menu-item">
                <div class="icon-wrapper"><i class="fab fa-whatsapp"></i></div>
                <div class="menu-text"><h4>Send Reminders</h4><p>Send expiry list on WhatsApp</p></div>
            </a>
<a href="add_contact.php" class="main-menu-item">
	                <div class="icon-wrapper"><i class="fas fa-user-plus"></i></div>
	                <div class="menu-text"><h4>Add Contact</h4><p>New number for sending reminders</p></div>
	            </a>
	            <a href="ai_chat.php" class="main-menu-item">
	                <div class="icon-wrapper" style="background-color: #6f42c1;"><i class="fas fa-robot"></i></div>
	                <div class="menu-text"><h4>AI Chat Assistant</h4><p>Chat with your inventory data</p></div>
	            </a>
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
	                <a href="manage_users.php" class="main-menu-item">
	                    <div class="icon-wrapper" style="background-color: var(--status-warning);"><i class="fas fa-users-cog"></i></div>
	                    <div class="menu-text"><h4>User Management</h4><p>Change user roles</p></div>
	                </a>
	                <a href="settings.php" class="main-menu-item">
	                    <div class="icon-wrapper" style="background-color: #6c757d;"><i class="fas fa-cog"></i></div>
	                    <div class="menu-text"><h4>AI Settings</h4><p>Configure API Keys</p></div>
	                </a>
	            <?php endif; ?>
        </div>
    </div>

    <!-- Barcode Search Modal -->
    <div class="modal fade" id="barcodeSearchModal" tabindex="-1" aria-labelledby="barcodeSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="barcodeSearchModalLabel"><i class="fas fa-barcode"></i> Search Item by Barcode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg" id="barcodeInput" placeholder="Scan barcode here..." autofocus>
                    </div>
                    <div id="barcodeResult">
                        <p class="text-center text-muted">Waiting for barcode scan...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================== -->
    <!--            *** Highly Important Section ***         -->
    <!-- ================================================== -->
    <!-- Bootstrap JS and Custom Script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <!-- ================================================== -->

</body>
</html>
