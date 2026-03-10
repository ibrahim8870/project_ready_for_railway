<?php
session_start();
require 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Fetch API settings
$api_settings_query = "SELECT * FROM settings WHERE setting_key IN ('gemini_api_key', 'deepseek_api_key', 'active_ai_model')";
$api_settings_result = $conn->query($api_settings_query);
$settings = [];
if ($api_settings_result) {
    while ($row = $api_settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$active_model = $settings['active_ai_model'] ?? 'gemini';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Café Expiry Reminder Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --chat-bg: #f7f7f8;
            --user-msg-bg: #007bff;
            --ai-msg-bg: #ffffff;
            --text-main: #374151;
            --sidebar-bg: #202123;
        }
        body {
            background-color: var(--chat-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            background: #ffffff;
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }
        .message {
            display: flex;
            gap: 15px;
            max-width: 85%;
        }
        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        .avatar {
            width: 35px;
            height: 35px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .user .avatar {
            background: var(--user-msg-bg);
            color: white;
        }
        .ai .avatar {
            background: #10a37f;
            color: white;
        }
        .bubble {
            padding: 12px 16px;
            border-radius: 15px;
            font-size: 15px;
            line-height: 1.5;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .user .bubble {
            background: var(--user-msg-bg);
            color: white;
            border-bottom-right-radius: 2px;
        }
        .ai .bubble {
            background: var(--ai-msg-bg);
            color: var(--text-main);
            border-bottom-left-radius: 2px;
            border: 1px solid #e5e7eb;
        }
        .input-area {
            background: white;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .input-container {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }
        #user-input {
            width: 100%;
            padding: 12px 50px 12px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            outline: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            transition: border-color 0.3s;
        }
        #user-input:focus {
            border-color: var(--user-msg-bg);
        }
        .send-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.3s;
        }
        .send-btn:hover {
            color: var(--user-msg-bg);
        }
        .typing-indicator {
            font-style: italic;
            color: #6b7280;
            font-size: 13px;
            margin-top: 5px;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="chat-header">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-robot fa-lg text-primary"></i>
            <h5 class="mb-0">AI Assistant <small class="text-muted">(<?php echo ucfirst($active_model); ?>)</small></h5>
        </div>
        <div class="d-flex gap-2">
            <a href="settings.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-cog"></i> Settings</a>
            <a href="dashboard.php" class="btn btn-sm btn-primary"><i class="fas fa-home"></i> Home</a>
        </div>
    </div>

    <div id="chat-box" class="chat-container">
        <div class="message ai">
            <div class="avatar"><i class="fas fa-robot"></i></div>
            <div class="bubble">
                Hello! I am your AI assistant. I have access to your inventory data. How can I help you today?
            </div>
        </div>
    </div>

    <div class="input-area">
        <form id="chat-form" class="input-container">
            <input type="text" id="user-input" placeholder="Ask anything about your items..." autocomplete="off" required>
            <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
        </form>
        <div class="text-center mt-2">
            <small class="text-muted">AI can make mistakes. Check important info.</small>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            function appendMessage(role, text) {
                const icon = role === 'user' ? 'fa-user' : 'fa-robot';
                const messageHtml = `
                    <div class="message ${role}">
                        <div class="avatar"><i class="fas ${icon}"></i></div>
                        <div class="bubble">${text}</div>
                    </div>
                `;
                $('#chat-box').append(messageHtml);
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
            }

            $('#chat-form').on('submit', function(e) {
                e.preventDefault();
                const userInput = $('#user-input').val().trim();
                if (!userInput) return;

                appendMessage('user', userInput);
                $('#user-input').val('');

                const typingId = 'typing-' + Date.now();
                const typingHtml = `
                    <div id="${typingId}" class="message ai">
                        <div class="avatar"><i class="fas fa-robot"></i></div>
                        <div class="bubble"><span class="typing-indicator">Thinking...</span></div>
                    </div>
                `;
                $('#chat-box').append(typingHtml);
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);

                $.ajax({
                    url: 'api_ai_chat.php',
                    method: 'POST',
                    data: { message: userInput },
                    dataType: 'json',
                    success: function(response) {
                        $(`#${typingId}`).remove();
                        if (response.success) {
                            appendMessage('ai', response.reply.replace(/\n/g, '<br>'));
                        } else {
                            appendMessage('ai', `<span class="text-danger">Error: ${response.error}</span>`);
                        }
                    },
                    error: function() {
                        $(`#${typingId}`).remove();
                        appendMessage('ai', '<span class="text-danger">Error: Could not connect to the server.</span>');
                    }
                });
            });
        });
    </script>
</body>
</html>
