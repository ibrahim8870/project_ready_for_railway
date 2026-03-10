<?php
// weekly_check.php
require 'config.php';
if (!isset($_SESSION['loggedin'])) {
    header("location: login.php");
    exit;
}

// Date 7 days ago
$seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

$sql = "SELECT id, name, category, last_checked_on FROM items WHERE last_checked_on < ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $seven_days_ago);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Check</title>},{find:
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Weekly Check</h2>
            <a href="dashboard.php" class="btn-back">Go Back</a>
        </header>
        <p>These items have not been checked in the last 7 days.</p>

        <div class="item-list">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="item status-warning"> <!-- হলুদ অ্যালার্ট -->
                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p>Category: <?php echo htmlspecialchars($row['category']); ?></p>
                        <p>Last Checked On: <?php echo date("d M, Y", strtotime($row['last_checked_on'])); ?></p>
                        <form method="post" action="update_check.php" style="display:inline;">
                            <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn-check">Checked</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>All items have been checked recently.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
