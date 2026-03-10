<?php
session_start();
require 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Getting information from session
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // User role

$today = date('Y-m-d');
$base_date = $_GET['base_date'] ?? $today; // Use selected date or today's date as default
$page_title = "Secondary Shelf Life";

// Fetching categories for the quick-add form
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quick_add_alert'])) {
	    $name = trim($_POST['name']);
	    $category_name = trim($_POST['category_name']); 
	    $quantity = trim($_POST['quantity']);
	    $alert_type = $_POST['alert_type'];
	    $barcode = trim($_POST['barcode'] ?? ''); // New barcode field
	    $base_date_quick_add = trim($_POST['base_date_quick_add']); // New base date for quick add
	    $purchase_date = date('Y-m-d'); // Use today's date as purchase date for quick add

		    $expiry_date = NULL;
		    $alert_date = NULL;
		    $alert_group_value = NULL;
		
			    if ($alert_type == '7_day_alert') {
			        // 7-day alert: Expiry Date is 7 days from the selected base date
			        $expiry_date = date('Y-m-d', strtotime('+7 days', strtotime($base_date_quick_add)));
			        $alert_group_value = '7_day_alert';
			    } elseif ($alert_type == '10_day_alert') {
			        // 10-day alert: Expiry Date is 10 days from the selected base date
			        $expiry_date = date('Y-m-d', strtotime('+10 days', strtotime($base_date_quick_add)));
			        $alert_group_value = '10_day_alert';
			    } elseif ($alert_type == '30_day_post_alert') {
		        // 30-day alert: Expiry Date is 30 days from the selected base date
		        $expiry_date = date('Y-m-d', strtotime('+30 days', strtotime($base_date_quick_add)));
		        $alert_group_value = '30_day_post_alert';
			    }
		
			    if (!empty($name) && !empty($category_name) && !empty($quantity) && ($expiry_date || $alert_date)) {
		        // Try to find primary item by barcode
		        $primary_item_id = NULL;
		        $opened_date = $base_date_quick_add; // Opened date is the base date
		        
		        if (!empty($barcode)) {
		            $lookup_stmt = $conn->prepare("SELECT id FROM items WHERE barcode = ? LIMIT 1");
		            $lookup_stmt->bind_param("s", $barcode);
		            $lookup_stmt->execute();
		            $lookup_result = $lookup_stmt->get_result();
		            if ($lookup_row = $lookup_result->fetch_assoc()) {
		                $primary_item_id = $lookup_row['id'];
		            }
		            $lookup_stmt->close();
		        }
		        
		        $stmt = $conn->prepare("INSERT INTO secondary_shelf_items (primary_item_id, name, category, quantity, purchase_date, opened_date, expiry_date, alert_date, alert_group, barcode, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		        $stmt->bind_param("isisssssssi", $primary_item_id, $name, $category_name, $quantity, $purchase_date, $opened_date, $expiry_date, $alert_date, $alert_group_value, $barcode, $user_id);

        if ($stmt->execute()) {
	            $message = '<div class="alert alert-success">Item added successfully for ' . ($alert_type == '7_day_alert' ? '7-Day Expiry Alert' : ($alert_type == '10_day_alert' ? '10-Day Expiry Alert' : '30-Day Post Alert')) . '.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-danger">Error: All required fields must be filled.</div>';
    }
}

// --- Function to get count for a specific alert ---
function get_alert_count($conn, $alert_type, $user_id, $user_role, $base_date) {
    $sql = "SELECT COUNT(*) as total FROM secondary_shelf_items i";
    
    $conditions = [];
    $params = [];
    $types = '';

    if ($user_role !== 'admin') {
        $conditions[] = "i.added_by = ?";
        $types .= 'i';
        array_push($params, $user_id);
    }

	    // Show ALL items for each alert group (no date filtering)
	    if ($alert_type == 'expiring_in_7_days') {
	        $conditions[] = "i.alert_group = '7_day_alert'";
	    } elseif ($alert_type == 'expiring_in_10_days') {
	        $conditions[] = "i.alert_group = '10_day_alert'";
	    } elseif ($alert_type == 'thirty_days_post_alert') {
	        $conditions[] = "i.alert_group = '30_day_post_alert'";
	    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $count;
}

// --- Function to fetch items for a specific alert ---
function fetch_alert_items($conn, $alert_type, $user_id, $user_role, $base_date) {
    $sql = "SELECT i.id, i.name, i.category, i.quantity, i.expiry_date, i.alert_date, i.opened_date, i.barcode, i.image, u.username as added_by_username, 
                   p.id as primary_id, p.name as primary_name, p.expiry_date as primary_expiry_date
            FROM secondary_shelf_items i 
            JOIN users u ON i.added_by = u.id
            LEFT JOIN items p ON i.primary_item_id = p.id";
    
    $conditions = [];
    $params = [];
    $types = '';

    // If the user is not 'admin', only their own items will be shown
    if ($user_role !== 'admin') {
        $conditions[] = "i.added_by = ?";
        $types .= 'i';
        array_push($params, $user_id);
    }

	    // Show ALL items for each alert group (no date filtering)
	    if ($alert_type == 'expiring_in_7_days') {
	        $conditions[] = "i.alert_group = '7_day_alert'";
	    } elseif ($alert_type == 'expiring_in_10_days') {
	        $conditions[] = "i.alert_group = '10_day_alert'";
	    } elseif ($alert_type == 'thirty_days_post_alert') {
	        $conditions[] = "i.alert_group = '30_day_post_alert'";
	    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY i.expiry_date ASC";

    $stmt = $conn->prepare($sql);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// Calculate counts for the dashboard view
$count_7_days = get_alert_count($conn, 'expiring_in_7_days', $user_id, $user_role, $base_date);
$count_10_days = get_alert_count($conn, 'expiring_in_10_days', $user_id, $user_role, $base_date);
$count_30_days_post = get_alert_count($conn, 'thirty_days_post_alert', $user_id, $user_role, $base_date);

// Calculate total count across all alert groups
$total_count = $count_7_days + $count_10_days + $count_30_days_post;

// Fetch all three lists
$result_7_days = fetch_alert_items($conn, 'expiring_in_7_days', $user_id, $user_role, $base_date);
$result_10_days = fetch_alert_items($conn, 'expiring_in_10_days', $user_id, $user_role, $base_date);
$result_30_days = fetch_alert_items($conn, 'thirty_days_post_alert', $user_id, $user_role, $base_date);

// Helper function to render the table
function render_item_table_inline($result, $user_role, $today) {
    $html = '<div class="table-responsive mt-3">
                <table class="table table-bordered table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Countdown</th>
                            <th>Alert Status</th>';
    if ($user_role === 'admin') {
        $html .= '<th>Added By</th>';
    }
    $html .= '
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expiry_date_dt = new DateTime($row['expiry_date']);
            $today_dt = new DateTime($today);
            $diff = (int)$today_dt->diff($expiry_date_dt)->format("%r%a");
            $status_class = ''; 
            $status_text = '';

            if ($diff < 0) { $status_class = 'table-danger'; $status_text = 'Expired'; }
            elseif ($diff == 0) { $status_class = 'table-danger'; $status_text = 'Expiring Today'; }
            elseif ($diff <= 7) { $status_class = 'table-warning'; $status_text = ($diff) . ' days remaining'; }
            else { $status_class = 'table-success'; $status_text = 'OK'; }

	            // Special handling for 30 Days Post Alert
	            if ($row['alert_date'] !== null) {
	                $alert_date_dt = new DateTime($row['alert_date']);
	                $days_since_alert = (int)$alert_date_dt->diff($today_dt)->format("%r%a");
	                $status_text = $days_since_alert . ' days since alert';
	                if ($days_since_alert >= 30) {
	                    $status_class = 'table-info';
	                } else {
	                    $status_class = 'table-success';
	                }
	            }

            // CHECK PRIMARY EXPIRY STATUS FIRST
            $primary_conflict = false;
            $primary_warning = '';
            if (!empty($row['primary_expiry_date'])) {
                $primary_expiry_dt = new DateTime($row['primary_expiry_date']);
                $primary_diff = (int)$today_dt->diff($primary_expiry_dt)->format("%r%a");
                
                // Check if primary expires BEFORE secondary
                if ($primary_diff < $diff) {
                    $primary_conflict = true;
                    
                    if ($primary_diff <= 0) {
                        // Primary already expired
                        $primary_warning = '<div class="alert alert-danger mb-0 py-1 px-2"><small><i class="fas fa-exclamation-triangle"></i> <strong>PRIMARY EXPIRED!</strong> Primary shelf life ended on ' . date('d M, Y', strtotime($row['primary_expiry_date'])) . '. This item should NOT be used.</small></div>';
                    } else {
                        // Primary will expire before secondary
                        $primary_warning = '<div class="alert alert-warning mb-0 py-1 px-2"><small><i class="fas fa-exclamation-triangle"></i> <strong>EXPIRY CONFLICT!</strong> Primary shelf life ends on ' . date('d M, Y', strtotime($row['primary_expiry_date'])) . ' (' . $primary_diff . ' days), before secondary expires. Item will become unusable after primary expiry.</small></div>';
                    }
                }
            }
            
            // Determine alert status based on alert group
            $needs_alert = false;
            $alert_icon = '';
            
            if ($row['alert_group'] == '7_day_alert' || $row['alert_group'] == '10_day_alert') {
                // Alert if 2 days or less remaining
                if ($diff <= 2) {
                    $needs_alert = true;
                    $alert_icon = '<i class="fas fa-bell text-danger"></i> ';
                }
            } elseif ($row['alert_group'] == '30_day_post_alert') {
                // Alert if 5 days or less remaining
                if ($diff <= 5) {
                    $needs_alert = true;
                    $alert_icon = '<i class="fas fa-bell text-danger"></i> ';
                }
            }
            
            // Format countdown display
            $countdown_text = '';
            if ($diff < 0) {
                $countdown_text = '<span class="badge bg-danger">' . abs($diff) . ' দিন আগে এক্সপায়ার</span>';
            } elseif ($diff == 0) {
                $countdown_text = '<span class="badge bg-danger">আজই এক্সপায়ার</span>';
            } elseif ($diff == 1) {
                $countdown_text = '<span class="badge bg-warning text-dark">১ দিন বাকি</span>';
            } elseif ($diff <= 5) {
                $countdown_text = '<span class="badge bg-info text-dark">' . $diff . ' দিন বাকি</span>';
            } else {
                $countdown_text = '<span class="badge bg-success">' . $diff . ' দিন বাকি</span>';
            }
            
            // Add alert status indicator
            $alert_status = $needs_alert ? '<span class="badge bg-danger"><i class="fas fa-bell"></i> Alert!</span>' : '<span class="text-muted">-</span>';
            
            // Add row highlighting for items needing attention
            $row_class = $status_class;
            if ($primary_conflict) {
                // Override everything if primary conflict exists
                if (!empty($row['primary_expiry_date'])) {
                    $primary_expiry_dt = new DateTime($row['primary_expiry_date']);
                    $primary_diff = (int)$today_dt->diff($primary_expiry_dt)->format("%r%a");
                    if ($primary_diff <= 0) {
                        // Primary expired - RED
                        $row_class = 'table-danger border-start border-danger border-5';
                        $alert_icon = '<i class="fas fa-ban text-danger"></i> ';
                    } else {
                        // Primary will expire before secondary - YELLOW/WARNING
                        $row_class = 'table-warning border-start border-warning border-5';
                        $alert_icon = '<i class="fas fa-exclamation-triangle text-warning"></i> ';
                    }
                }
            } elseif ($needs_alert) {
                $row_class .= ' table-warning border-start border-danger border-3';
            }
            
            $html .= '<tr class="' . $row_class . '">
                        <td>' . $alert_icon . htmlspecialchars($row['name']);
            if ($primary_warning) {
                $html .= '<br>' . $primary_warning;
            }
            $html .= '</td>
                        <td>' . htmlspecialchars($row['category']) . '</td>
                        <td>' . htmlspecialchars($row['quantity']) . '</td>
                        <td>' . date('d M, Y', strtotime($row['expiry_date'])) . '</td>
                        <td>' . $countdown_text . '</td>
                        <td>' . $alert_status . '</td>';
            if ($user_role === 'admin') {
                $html .= '<td>' . htmlspecialchars($row['added_by_username']) . '</td>';
            }
            $html .= '<td>
                        <a href="edit_secondary_item.php?id=' . $row['id'] . '" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <a href="delete_secondary_item.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure?\');"><i class="fas fa-trash"></i></a>
                      </td>
                    </tr>';
        }
    } else {
        $colspan = $user_role === 'admin' ? 8 : 7;
        $html .= '<tr><td colspan="' . $colspan . '" class="text-center text-muted">No items found</td></tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Café Expiry Reminder Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .table-info { background-color: #cff4fc !important; }
        .accordion-card {
            margin-bottom: 1rem;
        }
        .card-header-custom {
            cursor: pointer;
            padding: 20px;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-top: 4px solid;
        }
        .card-header-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .card-header-custom h5 {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 8px;
        }
        .card-header-custom .count-display {
            font-weight: 700;
            font-size: 2rem;
            margin: 0;
            padding: 5px 15px;
            border-radius: 8px;
            display: inline-block;
        }
        .card-7days .card-header-custom {
            border-top-color: var(--status-danger);
        }
        .card-7days .count-display {
            background-color: var(--status-danger);
            color: #000;
        }
        .card-10days .card-header-custom {
            border-top-color: var(--status-warning);
        }
        .card-10days .count-display {
            background-color: var(--status-warning);
            color: #000;
        }
        .card-30days .card-header-custom {
            border-top-color: #00BCD4;
        }
        .card-30days .count-display {
            background-color: #00BCD4;
            color: #000;
        }
        .card-total .card-header-custom {
            border-top-color: var(--primary-brand-color);
        }
        .card-total .count-display {
            background-color: var(--primary-brand-color);
            color: #fff;
        }
        .card-body-collapsible {
            padding: 0 20px 20px 20px;
        }
        .floating-add-btn-inline {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background-color: var(--primary-brand-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(109, 76, 65, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            z-index: 10;
        }
        .floating-add-btn-inline:hover {
            background-color: var(--accent-color);
            transform: scale(1.1) rotate(90deg);
        }
        .card-content-wrapper {
            position: relative;
            padding-bottom: 60px;
        }
        
        /* Smart Suggestion Box Styles */
        .smart-suggestion-box {
            background: linear-gradient(135deg, #FFF3CD 0%, #FFE69C 100%);
            border: 2px solid #FFC107;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            display: none;
        }
        .smart-suggestion-box.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .suggestion-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .suggestion-header i {
            font-size: 24px;
            color: #FF8C00;
            margin-right: 10px;
        }
        .suggestion-header h6 {
            margin: 0;
            color: #856404;
            font-weight: bold;
        }
        .suggestion-content {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .suggestion-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        .suggestion-row:last-child {
            margin-bottom: 0;
        }
        .suggestion-label {
            font-weight: 600;
            color: #495057;
        }
        .suggestion-value {
            color: #212529;
        }
        .suggestion-value.highlight {
            color: #DC3545;
            font-weight: bold;
        }
        .suggestion-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .btn-adjust {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-adjust:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
            color: white;
        }
        .btn-keep {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-keep:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
            color: white;
        }
        .primary-info-box {
            background: #E7F3FF;
            border-left: 4px solid #0D6EFD;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            display: none;
        }
        .primary-info-box.show {
            display: block;
        }
        .primary-info-box .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .primary-info-box .info-row:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        
        <div class="dashboard-header">
            <h1 style="color: #FF8C00; font-size: 2.5rem; font-weight: bold; margin: 0; font-family: 'Arial Black', sans-serif;">Dunkin Donut</h1>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p class="text-muted mb-0">
                <?php if ($user_role === 'admin'): ?>
                    Manage secondary shelf life alerts for all items
                <?php else: ?>
                    Manage your secondary shelf life alerts
                <?php endif; ?>
            </p>
        </div>
        
        <div class="text-center mb-4">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Go Back</a>
        </div>

        <?php if(!empty($message)) echo $message; ?>

        <!-- Accordion Cards -->
        <div class="accordion" id="alertsAccordion">
            
            <!-- Total Items Card -->
            <div class="accordion-card card-total">
                <div class="card">
                    <div class="card-header-custom" data-bs-toggle="collapse" data-bs-target="#collapseTotal">
                        <div>
                            <h5>Total Items</h5>
                            <p class="count-display mb-0"><?php echo $total_count; ?></p>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div id="collapseTotal" class="collapse" data-bs-parent="#alertsAccordion">
                        <div class="card-body-collapsible">
                            <div class="card-content-wrapper">
                                <?php 
                                // Fetch all items from all groups
                                $sql_all = "SELECT i.*, u.username as added_by_username 
                                            FROM secondary_shelf_items i 
                                            LEFT JOIN users u ON i.added_by = u.id 
                                            WHERE 1=1";
                                
                                if ($user_role !== 'admin') {
                                    $sql_all .= " AND i.added_by = ?";
                                }
                                
                                $sql_all .= " ORDER BY i.expiry_date ASC";
                                
                                $stmt_all = $conn->prepare($sql_all);
                                if ($user_role !== 'admin') {
                                    $stmt_all->bind_param("i", $user_id);
                                }
                                $stmt_all->execute();
                                $result_all = $stmt_all->get_result();
                                
                                echo render_item_table_inline($result_all, $user_role, $today);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 7 Days Card -->
            <div class="accordion-card card-7days">
                <div class="card">
                    <div class="card-header-custom" data-bs-toggle="collapse" data-bs-target="#collapse7Days">
                        <div>
                            <h5>Expiring in 7 Days</h5>
                            <p class="count-display mb-0"><?php echo $count_7_days; ?></p>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div id="collapse7Days" class="collapse" data-bs-parent="#alertsAccordion">
                        <div class="card-body-collapsible">
                            <div class="card-content-wrapper">
                                <?php echo render_item_table_inline($result_7_days, $user_role, $today); ?>
                                <button class="floating-add-btn-inline" onclick="openQuickAdd('7_day_alert')" title="Add Item">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 10 Days Card -->
            <div class="accordion-card card-10days">
                <div class="card">
                    <div class="card-header-custom" data-bs-toggle="collapse" data-bs-target="#collapse10Days">
                        <div>
                            <h5>Expiring in 10 Days</h5>
                            <p class="count-display mb-0"><?php echo $count_10_days; ?></p>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div id="collapse10Days" class="collapse" data-bs-parent="#alertsAccordion">
                        <div class="card-body-collapsible">
                            <div class="card-content-wrapper">
                                <?php echo render_item_table_inline($result_10_days, $user_role, $today); ?>
                                <button class="floating-add-btn-inline" onclick="openQuickAdd('10_day_alert')" title="Add Item">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 30 Days Post Alert Card -->
            <div class="accordion-card card-30days">
                <div class="card">
                    <div class="card-header-custom" data-bs-toggle="collapse" data-bs-target="#collapse30Days">
                        <div>
                            <h5>30 Days Post Alert</h5>
                            <p class="count-display mb-0"><?php echo $count_30_days_post; ?></p>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div id="collapse30Days" class="collapse" data-bs-parent="#alertsAccordion">
                        <div class="card-body-collapsible">
                            <div class="card-content-wrapper">
                                <?php echo render_item_table_inline($result_30_days, $user_role, $today); ?>
                                <button class="floating-add-btn-inline" onclick="openQuickAdd('30_day_post_alert')" title="Add Item">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
	
	    </div>

	    <!-- Quick Add Modal -->
	    <div class="modal fade" id="quickAddModal" tabindex="-1" aria-labelledby="quickAddModalLabel" aria-hidden="true">
	        <div class="modal-dialog modal-dialog-centered">
	            <div class="modal-content">
	                <div class="modal-header">
	                    <h5 class="modal-title" id="quickAddModalLabel"><i class="fas fa-plus"></i> Quick Add Item for Alert</h5>
	                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	                </div>
	                <div class="modal-body">
	                    <form action="new_alerts.php" method="post">
	                        <input type="hidden" name="quick_add_alert" value="1">
	                        <div class="mb-3">
	                            <label for="base_date_quick_add" class="form-label">Base Date for Alert Calculation</label>
	                            <input type="date" class="form-control" id="base_date_quick_add" name="base_date_quick_add" value="<?php echo date('Y-m-d'); ?>" required onchange="checkExpiryConflict()">
	                        </div>
                        <div class="mb-3">
                            <label for="barcode" class="form-label">Barcode (Scan or Enter)</label>
                            <input type="text" class="form-control" id="barcode" name="barcode" autofocus onchange="lookupBarcode(this.value)">
                        </div>
                        
                        <!-- Primary Item Info Box -->
                        <div class="primary-info-box" id="primaryInfoBox">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <i class="fas fa-check-circle" style="color: #0D6EFD; margin-right: 8px;"></i>
                                <strong>Primary Item Found</strong>
                            </div>
                            <div class="info-row">
                                <span>Name:</span>
                                <span id="primaryName">-</span>
                            </div>
                            <div class="info-row">
                                <span>Expiry Date:</span>
                                <span id="primaryExpiry">-</span>
                            </div>
                            <div class="info-row">
                                <span>Days Until Expiry:</span>
                                <span id="primaryDays">-</span>
                            </div>
                        </div>
                        
                        <!-- Smart Suggestion Box -->
                        <div class="smart-suggestion-box" id="smartSuggestionBox">
                            <div class="suggestion-header">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h6>EXPIRY CONFLICT DETECTED!</h6>
                            </div>
                            <div class="suggestion-content">
                                <div class="suggestion-row">
                                    <span class="suggestion-label">Primary Expiry:</span>
                                    <span class="suggestion-value highlight" id="suggestionPrimaryDays">-</span>
                                </div>
                                <div class="suggestion-row">
                                    <span class="suggestion-label">Your Selected Alert:</span>
                                    <span class="suggestion-value" id="suggestionSecondaryDays">-</span>
                                </div>
                                <div class="suggestion-row">
                                    <span class="suggestion-label">💡 Recommended:</span>
                                    <span class="suggestion-value highlight" id="suggestionRecommended">-</span>
                                </div>
                            </div>
                            <div class="suggestion-buttons">
                                <button type="button" class="btn-adjust" onclick="applyRecommendation()">
                                    <i class="fas fa-magic"></i> Use <span id="btnRecommendedDays">-</span> days
                                </button>
                                <button type="button" class="btn-keep" onclick="keepOriginal()">
                                    <i class="fas fa-hand-paper"></i> Keep <span id="btnOriginalDays">-</span> days
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alert_type" class="form-label">Select Alert Type</label>
	                            <select class="form-select" id="alert_type" name="alert_type" required onchange="checkExpiryConflict()">
	                                <option value="">Select...</option>
	                                <option value="7_day_alert">7 Day Expiry Alert (Expires 7 days from Base Date)</option>
	                                <option value="10_day_alert">10 Day Expiry Alert (Expires 10 days from Base Date)</option>
	                                <option value="30_day_post_alert">30 Day Post Alert (Alert Date is Base Date)</option>
	                            </select>
	                        </div>
	                        <div class="mb-3">
	                            <label for="name" class="form-label">Item Name</label>
	                            <input type="text" class="form-control" id="name" name="name" required>
	                        </div>
	                        <div class="mb-3">
	                            <label for="category_name" class="form-label">Category</label>
	                            <select class="form-select" id="category_name" name="category_name" required>
	                                <option value="">Select Category</option>
	                                <?php 
	                                    if ($categories_result->num_rows > 0) {
	                                        mysqli_data_seek($categories_result, 0);
	                                        while($category = $categories_result->fetch_assoc()): 
	                                ?>
	                                    <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
	                                <?php 
	                                        endwhile; 
	                                    }
	                                ?>
	                            </select>
	                        </div>
	                        <div class="mb-3">
	                            <label for="quantity" class="form-label">Quantity</label>
	                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
	                        </div>
	                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Add Item</button>
	                    </form>
	                </div>
	            </div>
	        </div>
	    </div>

	    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	    <script>
	        let primaryItemData = null;
	        
	        function lookupBarcode(barcode) {
	            if (barcode.length > 0) {
	                fetch('api_search_barcode.php', {
	                    method: 'POST',
	                    headers: {
	                        'Content-Type': 'application/json'
	                    },
	                    body: JSON.stringify({ barcode: barcode })
	                })
	                .then(response => response.json())
	                .then(data => {
	                    if (data.success) {
	                        primaryItemData = data.item;
	                        
	                        // Fill form fields
	                        document.getElementById('name').value = data.item.name;
	                        document.getElementById('category_name').value = data.item.category;
	                        document.getElementById('quantity').value = 1;
	                        
	                        // Show primary info box
	                        document.getElementById('primaryInfoBox').classList.add('show');
	                        document.getElementById('primaryName').textContent = data.item.name;
	                        document.getElementById('primaryExpiry').textContent = formatDate(data.item.expiry_date);
	                        document.getElementById('primaryDays').textContent = data.item.days_until_expiry + ' days';
	                        
	                        // Check for conflicts
	                        checkExpiryConflict();
	                    } else {
	                        // Clear fields if not found
	                        primaryItemData = null;
	                        document.getElementById('name').value = '';
	                        document.getElementById('category_name').value = '';
	                        document.getElementById('quantity').value = 1;
	                        document.getElementById('primaryInfoBox').classList.remove('show');
	                        document.getElementById('smartSuggestionBox').classList.remove('show');
	                    }
	                })
	                .catch(error => console.error('Error:', error));
	            } else {
	                primaryItemData = null;
	                document.getElementById('primaryInfoBox').classList.remove('show');
	                document.getElementById('smartSuggestionBox').classList.remove('show');
	            }
	        }
	        
	        function checkExpiryConflict() {
	            if (!primaryItemData) {
	                document.getElementById('smartSuggestionBox').classList.remove('show');
	                return;
	            }
	            
	            const alertType = document.getElementById('alert_type').value;
	            const baseDate = document.getElementById('base_date_quick_add').value;
	            
	            if (!alertType || !baseDate) {
	                document.getElementById('smartSuggestionBox').classList.remove('show');
	                return;
	            }
	            
	            let secondaryDays = 0;
	            if (alertType === '7_day_alert') secondaryDays = 7;
	            else if (alertType === '10_day_alert') secondaryDays = 10;
	            else if (alertType === '30_day_post_alert') secondaryDays = 30;
	            
	            const primaryDays = primaryItemData.days_until_expiry;
	            
	            // Show conflict if primary expires before secondary
	            if (primaryDays < secondaryDays && primaryDays >= 0) {
	                showSmartSuggestion(primaryDays, secondaryDays);
	            } else {
	                document.getElementById('smartSuggestionBox').classList.remove('show');
	            }
	        }
	        
	        function showSmartSuggestion(primaryDays, secondaryDays) {
	            document.getElementById('suggestionPrimaryDays').textContent = primaryDays + ' days';
	            document.getElementById('suggestionSecondaryDays').textContent = secondaryDays + ' days';
	            document.getElementById('suggestionRecommended').textContent = primaryDays + ' days (matching primary expiry)';
	            document.getElementById('btnRecommendedDays').textContent = primaryDays;
	            document.getElementById('btnOriginalDays').textContent = secondaryDays;
	            
	            document.getElementById('smartSuggestionBox').classList.add('show');
	        }
	        
	        function applyRecommendation() {
	            const primaryDays = primaryItemData.days_until_expiry;
	            
	            // Adjust alert type based on recommended days
	            let newAlertType = '';
	            if (primaryDays <= 7) newAlertType = '7_day_alert';
	            else if (primaryDays <= 10) newAlertType = '10_day_alert';
	            else newAlertType = '30_day_post_alert';
	            
	            document.getElementById('alert_type').value = newAlertType;
	            document.getElementById('smartSuggestionBox').classList.remove('show');
	            
	            // Show success message
	            alert('✅ Alert adjusted to ' + primaryDays + ' days to match primary expiry!');
	        }
	        
	        function keepOriginal() {
	            document.getElementById('smartSuggestionBox').classList.remove('show');
	            alert('⚠️ Warning: Primary item will expire before the selected alert period!');
	        }
	        
	        function formatDate(dateString) {
	            const date = new Date(dateString);
	            const options = { day: '2-digit', month: 'short', year: 'numeric' };
	            return date.toLocaleDateString('en-GB', options);
	        }

            function openQuickAdd(alertType) {
                document.getElementById('alert_type').value = alertType;
                var quickAddModal = new bootstrap.Modal(document.getElementById('quickAddModal'));
                quickAddModal.show();
                return false;
            }
	    </script>
	</body>
	</html>
