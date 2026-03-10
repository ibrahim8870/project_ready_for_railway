<?php
session_start();
require 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Fetching all categories from the database
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $category_name = trim($_POST['category_name']); 
    $quantity = trim($_POST['quantity']);
    $purchase_date = $_POST['purchase_date'];
    $expiry_date = $_POST['expiry_date'];
    $alert_date = $_POST['alert_date'] ?: NULL; // New field, use NULL if empty
    $barcode = trim($_POST['barcode']);
    $added_by = $_SESSION['user_id'];
    
    $image_name = null;

    // --- ডুপ্লিকেট আইটেম চেক ---
    $stmt_check = $conn->prepare("SELECT id FROM items WHERE barcode = ? AND category = ? AND expiry_date = ? AND added_by = ?");
    $stmt_check->bind_param("sssi", $barcode, $category_name, $expiry_date, $added_by);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0 && !empty($barcode)) {
        $message = '<div class="alert alert-danger">Error: This item (with the same barcode, category, and expiry date) is already added.</div>';
    } else {
        // Image upload handling
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/";
            $image_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($image_extension, $allowed_extensions)) {
                $image_name = "item_" . time() . "_" . bin2hex(random_bytes(8)) . "." . $image_extension;
                $target_file = $target_dir . $image_name;

                if ($_FILES["image"]["size"] <= 5000000) {
                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        $message = '<div class="alert alert-danger">Sorry, the file could not be uploaded.</div>';
                        $image_name = null;
                    }
                } else {
                    $message = '<div class="alert alert-danger">File size is too large (maximum 5MB).</div>';
                    $image_name = null;
                }
            } else {
                $message = '<div class="alert alert-danger">Only JPG, JPEG, PNG, GIF, WEBP format images are allowed.</div>';
                $image_name = null;
            }
        }

            if (empty($message)) {
                $stmt = $conn->prepare("INSERT INTO items (name, category, quantity, purchase_date, expiry_date, alert_date, barcode, image, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisssssi", $name, $category_name, $quantity, $purchase_date, $expiry_date, $alert_date, $barcode, $image_name, $added_by);

            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Item added successfully.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt->error) . '</div>';
            }
            $stmt->close();
        }
    }
    $stmt_check->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4" style="max-width: 600px;">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0"><i class="fas fa-plus"></i> Add New Item</h2>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </header>

        <?php if(!empty($message)) echo $message; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="add_item.php" method="post" enctype="multipart/form-data" class="p-0 shadow-none">
                    <div class="mb-3"><label for="name" class="form-label">Item Name</label><input type="text" class="form-control" id="name" name="name" required></div>
                    
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

                    <div class="mb-3"><label for="quantity" class="form-label">Quantity</label><input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required></div>
                    <div class="mb-3"><label for="purchase_date" class="form-label">Purchase Date</label><input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required></div>
	                    <div class="mb-3"><label for="expiry_date" class="form-label">Expiry Date</label><input type="date" class="form-control" id="expiry_date" name="expiry_date" required></div>
	                    <div class="mb-3"><label for="alert_date" class="form-label">Alert Date (For 30-Day Post-Alert)</label><input type="date" class="form-control" id="alert_date" name="alert_date"></div>
                    <div class="mb-3"><label for="barcode" class="form-label">Barcode (Optional)</label><input type="text" class="form-control" id="barcode" name="barcode"></div>
                    <div class="mb-3"><label for="image" class="form-label">Item Image (Optional)</label><input type="file" class="form-control" id="image" name="image" accept="image/*"></div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Item</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
