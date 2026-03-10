<?php
session_start();

require 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Searching for upcoming expiry items (within 7 days)
$today = date('Y-m-d');
$seven_days_later = date('Y-m-d', strtotime('+7 days'));

// Solution: Correct column 'name' is used instead of 'item_name'
$sql_items = "SELECT name, expiry_date FROM items WHERE expiry_date BETWEEN '$today' AND '$seven_days_later' ORDER BY expiry_date ASC";
$result_items = $conn->query($sql_items);

$reminder_message = "Upcoming Expiry Item List:\n\n";
if ($result_items->num_rows > 0) {
    while ($row = $result_items->fetch_assoc()) {
        $expiry_date_formatted = date("d M, Y", strtotime($row['expiry_date']));
        // Solution: Here too, 'name' is used instead of 'item_name'
        $reminder_message .= "• Item Name: " . $row['name'] . "\n";
        $reminder_message .= "  Expiry Date: " . $expiry_date_formatted . "\n\n";
    }
} else {
    $reminder_message = "Great! No items are expiring within the next 7 days.";
}
$encoded_message = urlencode($reminder_message);

// Fetching contact list from the database
$sql_contacts = "SELECT id, name, whatsapp_number FROM contacts ORDER BY name ASC";
$result_contacts = $conn->query($sql_contacts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send WhatsApp Reminder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4" style="max-width: 600px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Send Reminder</h1>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Go Back</a>
        </div>

        <div class="card bg-light border-0 mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Reminder Content</h5>
                <p class="card-text" style="white-space: pre-wrap;"><?php echo htmlspecialchars($reminder_message); ?></p>
            </div>
        </div>

        <h5 class="mb-3">Who do you want to send it to?</h5>

        <?php if ($result_contacts->num_rows > 0): ?>
            <div class="list-group">
                <?php while ($contact = $result_contacts->fetch_assoc()):
                    $whatsapp_link = "https://api.whatsapp.com/send?phone=" . htmlspecialchars($contact['whatsapp_number']) . "&text=" . $encoded_message;
                ?>
                    <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="d-block"><?php echo htmlspecialchars($contact['name']); ?></strong>
                            <small class="text-muted"><?php echo htmlspecialchars($contact['whatsapp_number']); ?></small>
                        </div>
                        <i class="fab fa-whatsapp text-success fa-2x"></i>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                No contacts found. Please first <a href="add_contact.php" class="alert-link">Add Contact</a>.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
