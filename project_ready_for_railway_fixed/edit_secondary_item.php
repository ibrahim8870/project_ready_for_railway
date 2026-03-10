<?php
session_start();
require 'config.php';

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) { header("location: login.php"); exit; }

$message = '';
$item_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if (!$item_id) { header("location: new_alerts.php"); exit; }

// Fetching categories
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// Fetching current item data from secondary_shelf_items table
$stmt = $conn->prepare("SELECT * FROM secondary_shelf_items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

// Security Check
if (!$item || ($user_role !== 'admin' && $item['added_by'] != $user_id)) {
    $_SESSION['error_message'] = "Item not found or you do not have permission to edit it.";
    header("location: new_alerts.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $category_name = trim($_POST['category_name']);
    $quantity = trim($_POST['quantity']);
    $purchase_date = $_POST['purchase_date'];
    $expiry_date = $_POST['expiry_date'];
    $alert_group = $_POST['alert_group'];
    $barcode = trim($_POST['barcode']);
    $current_image = $item['image'];

    // Image delete and upload logic
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        if (!empty($current_image) && file_exists("uploads/" . $current_image)) {
            unlink("uploads/" . $current_image);
        }
        $current_image = null;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        if (!empty($current_image) && file_exists("uploads/" . $current_image)) {
            unlink("uploads/" . $current_image);
        }
        
        $target_dir = "uploads/";
        $image_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $current_image = "item_" . time() . "_" . bin2hex(random_bytes(8)) . "." . $image_extension;
        $target_file = $target_dir . $current_image;
        
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $message = '<div class="alert alert-danger">Sorry, the new image could not be uploaded.</div>';
            $current_image = $item['image'];
        }
    }

    if (empty($message)) {
        // UPDATE Query for secondary_shelf_items table
        $sql_update = "UPDATE secondary_shelf_items SET name = ?, category = ?, quantity = ?, purchase_date = ?, expiry_date = ?, alert_group = ?, barcode = ?, image = ? WHERE id = ?";
        $types_update = "ssisssssi";
        $params_update = [$name, $category_name, $quantity, $purchase_date, $expiry_date, $alert_group, $barcode, $current_image, $item_id];

        // If the user is not admin, check added_by
        if ($user_role !== 'admin') {
            $sql_update .= " AND added_by = ?";
            $types_update .= "i";
            array_push($params_update, $user_id);
        }

        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param($types_update, ...$params_update);

        if ($stmt_update->execute()) {
            $message = '<div class="alert alert-success">Item updated successfully.</div>';
            // Reload data
            $stmt_reload = $conn->prepare("SELECT * FROM secondary_shelf_items WHERE id = ?"); 
            $stmt_reload->bind_param("i", $item_id); 
            $stmt_reload->execute(); 
            $item = $stmt_reload->get_result()->fetch_assoc(); 
            $stmt_reload->close();
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt_update->error . '</div>';
        }
        $stmt_update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Secondary Shelf Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4" style="max-width: 600px;">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0">Edit Secondary Shelf Item</h2>
            <a href="new_alerts.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Secondary Shelf Life</a>
        </header>

        <?php if(!empty($message)) echo $message; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="edit_secondary_item.php?id=<?php echo $item_id; ?>" method="post" enctype="multipart/form-data" class="p-0 shadow-none">
                    <div class="mb-3">
                        <label for="name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category</label>
                        <select class="form-select" id="category_name" name="category_name" required>
                            <option value="">Select Category</option>
                            <?php mysqli_data_seek($categories_result, 0); ?>
                            <?php while($category = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php if($item['category'] == $category['name']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="purchase_date" class="form-label">Purchase Date</label>
                        <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars($item['purchase_date']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars($item['expiry_date']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alert_group" class="form-label">Alert Group</label>
                        <select class="form-select" id="alert_group" name="alert_group" required>
                            <option value="7_day_alert" <?php if($item['alert_group'] == '7_day_alert') echo 'selected'; ?>>7 Day Alert</option>
                            <option value="10_day_alert" <?php if($item['alert_group'] == '10_day_alert') echo 'selected'; ?>>10 Day Alert</option>
                            <option value="30_day_post_alert" <?php if($item['alert_group'] == '30_day_post_alert') echo 'selected'; ?>>30 Day Post Alert</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="barcode" class="form-label">Barcode</label>
                        <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo htmlspecialchars($item['barcode']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div>
                            <?php if (!empty($item['image']) && file_exists('uploads/' . $item['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="item-image" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="delete_image" value="1" id="delete_image">
                                    <label class="form-check-label" for="delete_image">Delete Image</label>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No image available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Upload New Image (Optional)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Update</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
