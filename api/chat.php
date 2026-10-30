<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function chatJsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    chatJsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $db = getDbConnection();

    if ($method === 'GET') {
        $action = (string)($_GET['action'] ?? '');
        if ($action === 'status') {
            $settings = getSiteSettingsSafe();
            $enabled = ($settings['livechat_enabled'] ?? '0') === '1';
            chatJsonResponse([
                'enabled' => $enabled,
                'online' => $enabled && ($settings['livechat_online'] ?? '0') === '1',
            ]);
        }

        if ($action !== 'messages') {
            chatJsonResponse(['error' => 'Unknown action'], 400);
        }

        $trackingNumber = strtoupper(trim((string)($_GET['tracking_number'] ?? '')));
        if ($trackingNumber !== '' && strlen($trackingNumber) <= 40) {
            $conversation = $db->prepare(
                'SELECT c.id
                 FROM chat_conversations c
                 INNER JOIN shipments s ON s.id = c.shipment_id
                 WHERE s.tracking_number = :tracking_number
                 LIMIT 1'
            );
            $conversation->execute(['tracking_number' => $trackingNumber]);
            $conversationId = $conversation->fetchColumn();
            if (!$conversationId) {
                chatJsonResponse(['error' => 'No chat was found for this tracking number.'], 404);
            }
        } else {
            $conversationId = (int)($_GET['conversation_id'] ?? 0);
            $sessionToken = (string)($_GET['session'] ?? '');
            if ($conversationId < 1 || !preg_match('/^[a-f0-9]{32}$/', $sessionToken)) {
                chatJsonResponse(['error' => 'Invalid conversation credentials.'], 400);
            }

            $legacyConversation = $db->prepare(
                'SELECT id FROM chat_conversations
                 WHERE id = :id AND visitor_session = :session AND shipment_id IS NULL
                 LIMIT 1'
            );
            $legacyConversation->execute(['id' => $conversationId, 'session' => $sessionToken]);
            if (!$legacyConversation->fetch()) {
                chatJsonResponse(['error' => 'Conversation not found.'], 404);
            }
        }

        $stmt = $db->prepare(
            'SELECT message, sender_type, created_at
             FROM chat_messages WHERE conversation_id = ?
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([(int)$conversationId]);
        $settings = getSiteSettingsSafe();
        $enabled = ($settings['livechat_enabled'] ?? '0') === '1';
        chatJsonResponse([
            'messages' => $stmt->fetchAll(),
            'online' => $enabled && ($settings['livechat_online'] ?? '0') === '1',
        ]);
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf) || !hash_equals(csrfToken(), $csrf)) {
        chatJsonResponse(['error' => 'Your session expired. Refresh the page and try again.'], 403);
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'start') {
        $settings = getSiteSettingsSafe();
        if (($settings['livechat_enabled'] ?? '0') !== '1') {
            chatJsonResponse(['error' => 'Live chat is currently unavailable.'], 503);
        }

        $trackingNumber = strtoupper(trim((string)($_POST['tracking_number'] ?? '')));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($trackingNumber === '' || strlen($trackingNumber) > 40
            || !preg_match('/^[A-Z0-9-]+$/', $trackingNumber)
            || mb_strlen($message) > 2000) {
            chatJsonResponse(['error' => 'Enter a valid tracking number and a message of at most 2,000 characters.'], 400);
        }

        $shipment = $db->prepare('SELECT id FROM shipments WHERE tracking_number = :tracking_number LIMIT 1');
        $shipment->execute(['tracking_number' => $trackingNumber]);
        $shipmentId = $shipment->fetchColumn();
        if (!$shipmentId) {
            chatJsonResponse(['error' => 'We could not find a shipment with that tracking number.'], 404);
        }

        $db->beginTransaction();
        $existing = $db->prepare(
            'SELECT id FROM chat_conversations WHERE shipment_id = ? LIMIT 1 FOR UPDATE'
        );
        $existing->execute([(int)$shipmentId]);
        $conversationId = $existing->fetchColumn();
        if ($conversationId) {
            $conversationId = (int)$conversationId;
            $db->prepare(
                'UPDATE chat_conversations
                 SET status = :status, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute(['status' => 'active', 'id' => $conversationId]);
        } elseif ($message === '') {
            $db->rollBack();
            chatJsonResponse(['error' => 'Send a message to start a chat for this shipment.'], 400);
        } else {
            $sessionId = bin2hex(random_bytes(16));
            $insert = $db->prepare(
                'INSERT INTO chat_conversations (shipment_id, visitor_name, visitor_session, status)
                 VALUES (:shipment_id, :tracking_number, :visitor_session, :status)
                 ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    status = VALUES(status),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $insert->execute([
                'shipment_id' => (int)$shipmentId,
                'tracking_number' => $trackingNumber,
                'visitor_session' => $sessionId,
                'status' => 'active',
            ]);
            $conversationId = (int)$db->lastInsertId();
        }

        if ($message !== '') {
            $db->prepare(
                'INSERT INTO chat_messages (conversation_id, sender_type, message)
                 VALUES (?, ?, ?)'
            )->execute([$conversationId, 'visitor', mb_substr($message, 0, 2000)]);
            $db->prepare('UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')
               ->execute([$conversationId]);
        }
        $db->commit();

        chatJsonResponse([
            'conversation_id' => $conversationId,
            'tracking_number' => $trackingNumber,
        ]);
    }

    if ($action === 'send') {
        $trackingNumber = strtoupper(trim((string)($_POST['tracking_number'] ?? '')));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($trackingNumber === '' || strlen($trackingNumber) > 40
            || !preg_match('/^[A-Z0-9-]+$/', $trackingNumber)
            || $message === '' || mb_strlen($message) > 2000) {
            chatJsonResponse(['error' => 'Enter a valid tracking number and message.'], 400);
        }

        $conversation = $db->prepare(
            'SELECT c.id
             FROM chat_conversations c
             INNER JOIN shipments s ON s.id = c.shipment_id
             WHERE s.tracking_number = :tracking_number
             LIMIT 1'
        );
        $conversation->execute(['tracking_number' => $trackingNumber]);
        $conversationId = $conversation->fetchColumn();
        if (!$conversationId) {
            chatJsonResponse(['error' => 'No active chat was found for this tracking number. Reopen the chat to continue.'], 404);
        }

        $db->beginTransaction();
        $db->prepare(
            'INSERT INTO chat_messages (conversation_id, sender_type, message)
             VALUES (?, ?, ?)'
        )->execute([(int)$conversationId, 'visitor', mb_substr($message, 0, 2000)]);
        $db->prepare(
            'UPDATE chat_conversations
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['status' => 'active', 'id' => (int)$conversationId]);
        $db->commit();

        chatJsonResponse(['success' => true, 'conversation_id' => (int)$conversationId]);
    }

    chatJsonResponse(['error' => 'Unknown action.'], 400);
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('SwiftTrackGo chat request failed: ' . $exception->getMessage());
    chatJsonResponse(['error' => 'The chat service is temporarily unavailable. Please try again later.'], 500);
}
