<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_message = $_POST['message'] ?? '';
if (empty($user_message)) {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit();
}

// 1. Fetch API Settings
$api_settings_query = "SELECT * FROM settings WHERE setting_key IN ('gemini_api_key', 'deepseek_api_key', 'active_ai_model')";
$api_settings_result = $conn->query($api_settings_query);
$settings = [];
if ($api_settings_result) {
    while ($row = $api_settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$active_model = $settings['active_ai_model'] ?? 'gemini';
$gemini_key = $settings['gemini_api_key'] ?? '';
$deepseek_key = $settings['deepseek_api_key'] ?? '';

// 2. Fetch Website/Database Data (Context)
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$items_sql = "SELECT name, category, quantity, expiry_date FROM items";
if ($user_role !== 'admin') {
    $items_sql .= " WHERE added_by = " . intval($user_id);
}
$items_result = $conn->query($items_sql);
$items_data = [];
if ($items_result) {
    while ($row = $items_result->fetch_assoc()) {
        $items_data[] = "Item: {$row['name']}, Category: {$row['category']}, Qty: {$row['quantity']}, Expiry: {$row['expiry_date']}";
    }
}

$context = "You are an assistant for 'Café Expiry Reminder Pro'. Here is the current inventory data:\n" . (empty($items_data) ? "No items found." : implode("\n", $items_data));
$context .= "\n\nUser asked: " . $user_message;
$context .= "\nPlease answer in Bengali if the user asks in Bengali.";

// 3. Call AI API
if ($active_model === 'gemini' && !empty($gemini_key)) {
    // Using v1 instead of v1beta for better stability, and trying gemini-pro
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=" . $gemini_key;
    $data = [
        "contents" => [
            ["parts" => [["text" => $context]]]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(['success' => true, 'reply' => $result['candidates'][0]['content']['parts'][0]['text']]);
    } else {
        // Fallback to v1beta if v1 fails, but with gemini-1.5-flash
        $url_beta = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $gemini_key;
        $ch = curl_init($url_beta);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response_beta = curl_exec($ch);
        curl_close($ch);
        $result_beta = json_decode($response_beta, true);
        
        if (isset($result_beta['candidates'][0]['content']['parts'][0]['text'])) {
            echo json_encode(['success' => true, 'reply' => $result_beta['candidates'][0]['content']['parts'][0]['text']]);
        } else {
            $error_msg = $result['error']['message'] ?? $result_beta['error']['message'] ?? 'Unknown Gemini API error';
            echo json_encode(['success' => false, 'error' => 'Gemini API Error: ' . $error_msg]);
        }
    }
} elseif ($active_model === 'deepseek' && !empty($deepseek_key)) {
    $url = "https://api.deepseek.com/v1/chat/completions";
    $data = [
        "model" => "deepseek-chat",
        "messages" => [
            ["role" => "system", "content" => "You are a helpful assistant for an expiry reminder system. Answer in Bengali if the user asks in Bengali."],
            ["role" => "user", "content" => $context]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $deepseek_key
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo json_encode(['success' => true, 'reply' => $result['choices'][0]['message']['content']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'DeepSeek API Error: ' . ($result['error']['message'] ?? 'Unknown error')]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'AI Model not configured or API key missing.']);
}
