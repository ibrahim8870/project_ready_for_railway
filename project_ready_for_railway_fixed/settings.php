<?php
session_start();
require 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $gemini_key = trim($_POST['gemini_api_key']);
    $deepseek_key = trim($_POST['deepseek_api_key']);
    $active_model = $_POST['active_ai_model'];

    $settings = [
        'gemini_api_key' => $gemini_key,
        'deepseek_api_key' => $deepseek_key,
        'active_ai_model' => $active_model
    ];

    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
        $stmt->close();
    }
    $message = '<div class="alert alert-success">Settings updated successfully!</div>';
}

// Fetch current settings
$current_settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Settings - Café Expiry Reminder Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-4" style="max-width: 600px;">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0"><i class="fas fa-cog"></i> AI Settings</h2>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </header>

        <?php echo $message; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="settings.php" method="post">
                    <div class="mb-3">
                        <label for="gemini_api_key" class="form-label">Google Gemini API Key</label>
                        <input type="password" class="form-control" id="gemini_api_key" name="gemini_api_key" value="<?php echo htmlspecialchars($current_settings['gemini_api_key'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="deepseek_api_key" class="form-label">DeepSeek API Key</label>
                        <input type="password" class="form-control" id="deepseek_api_key" name="deepseek_api_key" value="<?php echo htmlspecialchars($current_settings['deepseek_api_key'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="active_ai_model" class="form-label">Active AI Model</label>
                        <select class="form-select" id="active_ai_model" name="active_ai_model">
                            <option value="gemini" <?php echo ($current_settings['active_ai_model'] ?? '') === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                            <option value="deepseek" <?php echo ($current_settings['active_ai_model'] ?? '') === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
