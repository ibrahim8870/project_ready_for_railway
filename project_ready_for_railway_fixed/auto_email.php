<?php
// --- Security Check ---
if (!isset($_GET['secret_key']) || $_GET['secret_key'] !== 'MySecretCronKey123') {
    // If opened directly in the browser or if the key is wrong
    if (php_sapi_name() !== 'cli') {
        die('Access Denied!');
    }
}
// --- End Security Check ---

// Database Connection
include 'db.php';

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

// Fetch all user email addresses from the database
$user_emails = [];
$sql_users = "SELECT email FROM users WHERE email IS NOT NULL AND email != ''";
$result_users = $conn->query($sql_users);
if ($result_users->num_rows > 0) {
    while($row = $result_users->fetch_assoc()) {
        $user_emails[] = $row['email'];
    }
}

if (empty($user_emails)) {
    echo "No user email found.\n";
    exit;
}

// Searching for items expiring within 3 days
$three_days_later = date('Y-m-d', strtotime('+3 days'));
$today = date('Y-m-d');

$sql_items = "SELECT item_name, expiry_date FROM items WHERE expiry_date BETWEEN '$today' AND '$three_days_later' ORDER BY expiry_date ASC";
$result_items = $conn->query($sql_items);

if ($result_items->num_rows > 0) {
    $email_body = "<h1>Upcoming Expiry Item List</h1>";
    $email_body .= "<p>The following items will expire within the next 3 days:</p>";
    $email_body .= "<table border='1' cellpadding='10' cellspacing='0'><tr><th>Item Name</th><th>Expiry Date</th></tr>";

    while($row = $result_items->fetch_assoc()) {
        $email_body .= "<tr><td>" . htmlspecialchars($row['item_name']) . "</td><td>" . date("d M, Y", strtotime($row['expiry_date'])) . "</td></tr>";
    }
    $email_body .= "</table>";
    $email_body .= "<p>Please take immediate action.</p>";

    // Email sending process
    $mail = new PHPMailer(true);
    try {
        // Server Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'YOUR_GMAIL_ADDRESS@gmail.com'; // Enter your Gmail address
        $mail->Password   = 'YOUR_APP_PASSWORD';          // Enter your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom('YOUR_GMAIL_ADDRESS@gmail.com', 'Café Expiry Reminder Pro');
        foreach ($user_emails as $email) {
            $mail->addAddress($email);
        }

        // Subject
        $mail->isHTML(true);
        $mail->Subject = 'Urgent Notification: Items are about to expire!';
        $mail->Body    = $email_body;
        $mail->AltBody = strip_tags($email_body);

        $mail->send();
        echo 'Email sent successfully.';
    } catch (Exception $e) {
        echo "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
} else {
    echo "No items are expiring within the next 3 days.";
}

$conn->close();
?>
