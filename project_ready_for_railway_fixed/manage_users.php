<?php
session_start();
require 'config.php';

// --- Security Check: Only Admin can access this page ---
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("location: dashboard.php");
    exit;
}

$message = '';
$current_user_id = $_SESSION['user_id']; // So they cannot change their own role

// --- Role Change Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id_to_change = $_POST['user_id'];
    $new_role = $_POST['new_role'];

    // Ensure admin is not changing their own role
    if ($user_id_to_change == $current_user_id) {
        $message = '<div class="alert alert-danger">You cannot change your own role.</div>';
    } 
    // Ensure the role is one of 'user' or 'admin'
    elseif ($new_role === 'user' || $new_role === 'admin') {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id_to_change);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">User role updated successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">An error occurred while updating the role.</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-warning">Invalid role.</div>';
    }
}

// Fetching list of all users from the database
$users_result = $conn->query("SELECT id, username, email, role FROM users ORDER BY username ASC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0"><i class="fas fa-users-cog"></i> User Management</h2>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </header>

        <?php if(!empty($message)) echo $message; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Current Role</th>
                                <th style="width: 200px;">Change Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result->num_rows > 0): ?>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-success">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php // Admin cannot change their own role ?>
                                            <?php if ($user['id'] != $current_user_id): ?>
                                                <form action="manage_users.php" method="post" class="d-flex gap-2">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <select name="new_role" class="form-select form-select-sm">
                                                        <option value="user" <?php if ($user['role'] === 'user') echo 'selected'; ?>>User</option>
                                                        <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                </form>
                                            <?php else: ?>
                                                <small class="text-muted">Cannot change self</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No user found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
