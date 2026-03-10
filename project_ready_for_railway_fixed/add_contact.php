<?php
session_start();

// Solution: 'config.php' is used instead of 'db.php'
require 'config.php';

// Solution: Session is checked with $_SESSION['loggedin']
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $number = trim($_POST['whatsapp_number']);

    if (!empty($name) && !empty($number)) {
        if (preg_match('/^[0-9+]+$/', $number) && strlen($number) > 8) {
            // লাইন ১৬: এখন $conn ভেরিয়েবলটি null থাকবে না
            $stmt = $conn->prepare("INSERT INTO contacts (name, whatsapp_number) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $number);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Contact added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-danger'>Invalid number. Please use only digits (0-9) and '+' sign, and the number must be of correct length.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Please fill in both Name and Number.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Contact</title>
    <!-- Solution: Stylesheet link added -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4" style="max-width: 600px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><i class="fas fa-user-plus"></i> Add New Contact</h1>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
        
        <?php echo $message; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="add_contact.php" method="post" class="p-0 shadow-none">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="whatsapp_number" class="form-label">WhatsApp Number (including country code)</label>
                        <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" placeholder="e.g.: 8801712345678" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
