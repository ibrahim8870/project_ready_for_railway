<?php
// register.php
require 'config.php';
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 5) {
        $error = "Password must be at least 5 characters long.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                $error = "This username is already taken.";
            } else {
                $sql_insert = "INSERT INTO users (username, password) VALUES (?, ?)";
                
                if ($stmt_insert = $conn->prepare($sql_insert)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_insert->bind_param("ss", $username, $hashed_password);
                    
                    if ($stmt_insert->execute()) {
                        $success = "Registration successful! You are being redirected to the login page...";
                        header("refresh:3;url=login.php");
                    } else {
                        $error = "Something went wrong. Please try again.";
                    }
                    $stmt_insert->close();
                }
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - Café Expiry Reminder Pro</title>
    <!-- Bootstrap CSS Link (Important) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Your Custom CSS File -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h1 style="color: #FF8C00; font-size: 2.5rem; font-weight: bold; margin: 10px 0; font-family: 'Arial Black', sans-serif; text-align: center;">Dunkin Donut</h1>
            <h2>Create New Account</h2>
            
            <?php if($error): ?>
                <p class="mt-3" style="color: var(--status-danger);"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if($success): ?>
                <p class="mt-3" style="color: var(--status-success);"><?php echo $success; ?></p>
            <?php endif; ?>

            <form action="register.php" method="post" class="mt-4">
                <div class="mb-3">
                    <input type="text" class="form-control" name="username" placeholder="New Username" required>
                </div>
                <div class="mb-3">
                    <input type="password" class="form-control" name="password" placeholder="Password (at least 5 characters)" required>
                </div>
                <div class="mb-3">
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            <div class="extra-links mt-4">
                <p class="text-muted">Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
