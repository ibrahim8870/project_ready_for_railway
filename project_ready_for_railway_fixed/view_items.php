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
$search = $_GET['search'] ?? '';
$filter_category_name = $_GET['category'] ?? '';
$filter = $_GET['filter'] ?? '';
$page_title = "Item List";

// Fetching categories for filter dropdown
$categories_result_filter = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// --- Main change here: Separate query for Admin and User ---
$sql = "SELECT i.id, i.name, i.category, i.quantity, i.expiry_date, i.barcode, i.image, u.username as added_by_username 
        FROM items i 
        JOIN users u ON i.added_by = u.id";

$conditions = [];
$params = [];
$types = '';

// If the user is not 'admin', only their own items will be shown
if ($user_role !== 'admin') {
    $conditions[] = "i.added_by = ?";
    $types .= 'i';
    array_push($params, $user_id);
}

// Filtering logic (same as before)
if ($filter == 'expired_today') { $conditions[] = "i.expiry_date = ?"; $types .= 's'; array_push($params, $today); $page_title = "Items Expiring Today List"; }
elseif ($filter == 'already_expired') { $conditions[] = "i.expiry_date < ?"; $types .= 's'; array_push($params, $today); $page_title = "Already Expired Items List"; }
elseif ($filter == 'expiring_soon') { $seven_days_later = date('Y-m-d', strtotime('+7 days')); $conditions[] = "i.expiry_date > ? AND i.expiry_date <= ?"; $types .= 'ss'; array_push($params, $today, $seven_days_later); $page_title = "Expiring in the next 7 days"; }
elseif ($filter == 'expiring_in_30_days') { $thirty_days_later = date('Y-m-d', strtotime('+30 days')); $conditions[] = "i.expiry_date > ? AND i.expiry_date <= ?"; $types .= 'ss'; array_push($params, $today, $thirty_days_later); $page_title = "Expiring in the next 30 days"; }

if (!empty($search)) { $conditions[] = "(i.name LIKE ? OR i.barcode LIKE ?)"; $types .= 'ss'; $search_param = "%{$search}%"; array_push($params, $search_param, $search_param); }
if (!empty($filter_category_name)) { $conditions[] = "i.category = ?"; $types .= 's'; array_push($params, $filter_category_name); }

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY i.expiry_date ASC";

$stmt = $conn->prepare($sql);

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4" style="max-width: 900px;"> <!-- Container size increased for admin -->
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0"><?php echo htmlspecialchars($page_title); ?></h2>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Go Back</a>
        </header>

        <form method="get" action="view_items.php" class="mb-4 p-3 bg-light rounded shadow-sm">
            <?php if (!empty($filter)): ?><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"><?php endif; ?>
            <div class="row g-2">
                <div class="col-md-5"><input type="text" name="search" class="form-control" placeholder="Name or Barcode" value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="col-md-4">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php 
                            if ($categories_result_filter->num_rows > 0) {
                                mysqli_data_seek($categories_result_filter, 0);
                                while ($cat = $categories_result_filter->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php if ($filter_category_name == $cat['name']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php 
                                endwhile; 
                            }
                        ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                    <a href="view_items.php<?php if($filter) echo '?filter='.$filter; ?>" class="btn btn-secondary" title="Reset"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
	                        <th>Days Remaining</th>
                        <th>Status</th>
                        <?php if ($user_role === 'admin'): ?>
                            <th>Added By</th> <!-- New column: for Admin -->
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                $expiry_date_dt = new DateTime($row['expiry_date']);
                                $today_dt = new DateTime($today);
                                $diff = (int)$today_dt->diff($expiry_date_dt)->format("%r%a");
                                $status_class = ''; $status_text = '';
                                if ($diff < 0) { $status_class = 'table-danger'; $status_text = 'Expired'; }
                                elseif ($diff == 0) { $status_class = 'table-danger'; $status_text = 'Expiring Today'; }
                                elseif ($diff <= 7) { $status_class = 'table-warning'; $status_text = ($diff) . ' days remaining'; }
                                else { $status_class = 'table-success'; $status_text = 'OK'; }
                            ?>
                            <tr class="<?php echo $status_class; ?>">
                                <td>
                                    <?php if (!empty($row['image']) && file_exists("uploads/" . $row['image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="item-image">
                                    <?php else: ?>
                                        <div class="item-image bg-light d-flex align-items-center justify-content-center"><i class="fas fa-camera text-muted"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                <td><?php echo date("d M, Y", strtotime($row['expiry_date'])); ?></td>
	                                <td>
	                                    <?php 
	                                        if ($diff < 0) {
	                                            echo '<span class="badge bg-danger text-dark">Expired</span>';
	                                        } elseif ($diff == 0) {
	                                            echo '<span class="badge bg-danger text-dark">Today</span>';
	                                        } else {
	                                            echo '<span class="badge bg-success text-dark">' . $diff . ' days</span>';
	                                        }
	                                    ?>
	                                </td>
                                <td><?php echo $status_text; ?></td>
                                
                                <?php if ($user_role === 'admin'): ?>
                                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['added_by_username']); ?></span></td>
                                <?php endif; ?>

                                <td>
                                    <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="Edit"><i class="fas fa-edit"></i></a>
                                    
                                    <?php if ($user_role === 'admin'): ?>
                                        <a href="delete_item.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this item?');"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo ($user_role === 'admin') ? '9' : '8'; ?>" class="text-center p-4">No items found for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
