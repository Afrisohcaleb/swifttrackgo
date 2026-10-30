<?php
/**
 * Chat API Endpoint for SwiftTrackGo Live Chat System
 *
 * Handles all chat operations:
 * - Starting new conversations (visitor name + first message)
 * - Sending subsequent messages
 * - Fetching conversation history
 *
 * Uses vanilla PHP with PDO for database operations
 * Follows existing SwiftTrackGo patterns (CSRF, error handling, etc.)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Initialize database connection
$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

/**
 * Handle GET requests for fetching messages
 *
 * @param action - Should be 'messages'
 * @param conversation_id - Required ID of the conversation
 */
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'status') {
        $settings = getSiteSettingsSafe();
        echo json_encode([
            'enabled' => ($settings['livechat_enabled'] ?? '0') === '1',
            'online' => ($settings['livechat_enabled'] ?? '0') === '1'
                && ($settings['livechat_online'] ?? '0') === '1',
        ]);
        exit;
    }

    if ($action === 'messages') {
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
        $sessionToken = (string)($_GET['session'] ?? '');

        if ($conversationId < 1 || !preg_match('/^[a-f0-9]{32}$/', $sessionToken)) {
            echo json_encode(['error' => 'Invalid conversation credentials']);
            http_response_code(400);
            exit;
        }

        try {
            $conversation = $db->prepare(
                'SELECT id FROM chat_conversations WHERE id = :id AND visitor_session = :session LIMIT 1'
            );
            $conversation->execute(['id' => $conversationId, 'session' => $sessionToken]);
            if (!$conversation->fetch()) {
                echo json_encode(['error' => 'Conversation not found']);
                http_response_code(404);
                exit;
            }

            $stmt = $db->prepare('SELECT message, sender_type, created_at FROM chat_messages WHERE conversation_id = ? ORDER BY created_at ASC');
            $stmt->execute([$conversationId]);
            $messages = $stmt->fetchAll();

            $settings = getSiteSettingsSafe();
            $online = ($settings['livechat_enabled'] ?? '0') === '1'
                && ($settings['livechat_online'] ?? '0') === '1';

            echo json_encode([
                'messages' => $messages,
                'online' => $online
            ]);

            exit;

        } catch (PDOException $e) {
            error_log('Chat messages fetch error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to fetch messages']);
            http_response_code(500);
            exit;
        }
    }

    echo json_encode(['error' => 'Unknown action']);
    http_response_code(400);
    exit;
}

/**
 * Handle POST requests for starting conversations and sending messages
 */
if ($method === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf) || !hash_equals(csrfToken(), $csrf)) {
        echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
        http_response_code(403);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        $chatSettings = getSiteSettingsSafe();
        if (($chatSettings['livechat_enabled'] ?? '0') !== '1') {
            echo json_encode(['error' => 'Live chat is currently unavailable.']);
            http_response_code(503);
            exit;
        }

        // Start new conversation
        $name = trim((string)($_POST['name'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        // Validate input
        if (empty($name) || empty($message) || mb_strlen($name) > 100 || mb_strlen($message) > 2000) {
            echo json_encode(['error' => 'Name and message are required']);
            http_response_code(400);
            exit;
        }

        // Sanitize inputs
        $name = mb_substr($name, 0, 100);
        $message = mb_substr($message, 0, 2000);

        try {
            // Generate session ID for visitor tracking
            $sessionId = bin2hex(random_bytes(16));

            // Insert new conversation
            $stmt = $db->prepare('INSERT INTO chat_conversations (visitor_name, visitor_session, status) VALUES (?, ?, ?)');
            $stmt->execute([$name, $sessionId, 'active']);
            $conversationId = (int)$db->lastInsertId();

            // Insert first message
            $stmt = $db->prepare('INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, ?, ?)');
            $stmt->execute([$conversationId, 'visitor', $message]);

            echo json_encode([
                'conversation_id' => $conversationId,
                'session' => $sessionId,
                'message' => $message
            ]);

            exit;

        } catch (PDOException $e) {
            error_log('Chat start error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to start conversation']);
            http_response_code(500);
            exit;
        }

    } elseif ($action === 'send') {
        // Send message to existing conversation
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $sessionToken = (string)($_POST['session'] ?? '');
        $message = trim((string)($_POST['message'] ?? ''));

        if ($conversationId < 1 || !preg_match('/^[a-f0-9]{32}$/', $sessionToken) || empty($message)) {
            echo json_encode(['error' => 'Invalid conversation credentials or empty message']);
            http_response_code(400);
            exit;
        }

        $message = mb_substr($message, 0, 2000);

        try {
            // Verify conversation exists and is active
            $checkStmt = $db->prepare(
                'SELECT id FROM chat_conversations
                 WHERE id = :id AND visitor_session = :session AND status = :status'
            );
            $checkStmt->execute(['id' => $conversationId, 'session' => $sessionToken, 'status' => 'active']);

            if (!$checkStmt->fetch()) {
                echo json_encode(['error' => 'Conversation not found or closed']);
                http_response_code(404);
                exit;
            }

            // Insert message
            $stmt = $db->prepare('INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, ?, ?)');
            $stmt->execute([$conversationId, 'visitor', $message]);

            // Update conversation timestamp
            $db->prepare('UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')
               ->execute([$conversationId]);

            echo json_encode([
                'success' => true,
                'message' => $message,
                'conversation_id' => $conversationId
            ]);

            exit;

        } catch (PDOException $e) {
            error_log('Chat send error: ' . $e->getMessage());
            echo json_encode(['error' => 'Failed to send message']);
            http_response_code(500);
            exit;
        }
    }

    echo json_encode(['error' => 'Unknown action']);
    http_response_code(400);
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
