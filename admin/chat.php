<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin(null);

$db = getDbConnection();

// Mark conversation as online/offline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    $action = $_POST['action'];

    if ($action === 'send_reply') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));
        if ($conversationId > 0 && $message !== '') {
            $stmt = $db->prepare(
                'INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (:cid, :type, :msg)'
            );
            $stmt->execute(['cid' => $conversationId, 'type' => 'agent', 'msg' => $message]);
            $db->prepare('UPDATE chat_conversations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
               ->execute(['active', $conversationId]);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'close') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        if ($conversationId > 0) {
            $db->prepare('UPDATE chat_conversations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
               ->execute(['closed', $conversationId]);
        }
        flash('success', 'Conversation closed.');
        redirect('admin/chat.php');
    }

    if ($action === 'toggle_online') {
        $current = getSiteSettingsSafe()['livechat_online'] ?? '0';
        saveSiteSettings($db, ['livechat_online' => $current === '1' ? '0' : '1']);
        flash('success', $current === '1' ? 'Support is now offline.' : 'Support is now online.');
        redirect('admin/chat.php');
    }
}

// Load conversations
$conversations = $db->query(
    'SELECT c.*, (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_type = "visitor" AND m.is_read = 0) AS unread_count
     FROM chat_conversations c WHERE c.status != "closed" ORDER BY c.updated_at DESC'
)->fetchAll();

$selectedId = (int)($_GET['id'] ?? 0);
$messages = [];
$selectedConvo = null;
if ($selectedId > 0) {
    $selectedConvo = $db->prepare('SELECT * FROM chat_conversations WHERE id = ?');
    $selectedConvo->execute([$selectedId]);
    $selectedConvo = $selectedConvo->fetch();
    if ($selectedConvo) {
        $msgs = $db->prepare('SELECT * FROM chat_messages WHERE conversation_id = ? ORDER BY created_at ASC');
        $msgs->execute([$selectedId]);
        $messages = $msgs->fetchAll();
        // Mark visitor messages as read
        $db->prepare('UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = "visitor"')
           ->execute([$selectedId]);
    }
}

$isOnline = (getSiteSettingsSafe()['livechat_online'] ?? '0') === '1';

$pageTitle = 'Live Chat';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
    <h1 style="margin:0">Live Chat</h1>
    <form method="post" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="toggle_online">
        <button type="submit" class="button button-small <?= $isOnline ? '' : 'button-secondary' ?>">
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $isOnline ? '#22c55e' : '#94a3b8' ?>;margin-right:.4rem"></span>
            <?= $isOnline ? 'Online — Go Offline' : 'Offline — Go Online' ?>
        </button>
    </form>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:1rem;min-height:500px">
    <!-- Conversation list -->
    <div class="table-wrap" style="border-radius:12px;overflow-y:auto;max-height:600px">
        <?php if (empty($conversations)): ?>
            <p style="padding:1.5rem;color:var(--muted);text-align:center">No active conversations</p>
        <?php else: ?>
            <?php foreach ($conversations as $c): ?>
                <a href="<?= e(url('admin/chat.php?id=' . $c['id'])) ?>" style="display:block;padding:.85rem;border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink);<?= $selectedId === (int)$c['id'] ? 'background:var(--paper);' : '' ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <strong style="font-size:.95rem"><?= e($c['visitor_name']) ?></strong>
                        <?php if ($c['unread_count'] > 0): ?>
                            <span style="display:inline-grid;place-items:center;min-width:20px;height:20px;border-radius:10px;background:var(--brand);color:white;font-size:.75rem;font-weight:700;padding:0 5px"><?= (int)$c['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                    <small style="color:var(--muted)"><?= e(date('M j, g:ia', strtotime($c['updated_at']))) ?></small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Chat panel -->
    <div class="table-wrap" style="border-radius:12px;display:flex;flex-direction:column">
        <?php if ($selectedConvo): ?>
            <div style="padding:.85rem 1rem;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
                <div>
                    <strong><?= e($selectedConvo['visitor_name']) ?></strong>
                    <small style="color:var(--muted);margin-left:.5rem">Started <?= e(date('M j, Y g:ia', strtotime($selectedConvo['created_at']))) ?></small>
                </div>
                <form method="post" style="margin:0">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="conversation_id" value="<?= (int)$selectedConvo['id'] ?>">
                    <button type="submit" class="button button-small button-secondary">Close</button>
                </form>
            </div>
            <div id="chat-messages" style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.6rem;max-height:400px">
                <?php foreach ($messages as $msg): ?>
                    <div style="max-width:75%;<?= $msg['sender_type'] === 'agent' ? 'align-self:flex-end;background:var(--brand);color:white;' : 'align-self:flex-start;background:var(--paper);' ?>padding:.65rem .85rem;border-radius:12px;border-bottom-<?= $msg['sender_type'] === 'agent' ? 'right' : 'left' ?>-radius:3px">
                        <div style="font-size:.93rem"><?= e($msg['message']) ?></div>
                        <small style="opacity:.65;font-size:.75rem"><?= e(date('g:ia', strtotime($msg['created_at']))) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" id="reply-form" style="padding:.75rem;border-top:1px solid var(--line);display:flex;gap:.5rem">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="send_reply">
                <input type="hidden" name="conversation_id" value="<?= (int)$selectedConvo['id'] ?>">
                <input name="message" placeholder="Type your reply…" autocomplete="off" style="flex:1" required>
                <button type="submit" class="button button-small">Send</button>
            </form>
            <script>
            (function(){
                var el = document.getElementById('chat-messages');
                if (el) el.scrollTop = el.scrollHeight;
                var form = document.getElementById('reply-form');
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    var fd = new FormData(form);
                    fetch('<?= e(url('admin/chat.php')) ?>', { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(){
                        var input = form.querySelector('input[name="message"]');
                        var msg = input.value;
                        input.value = '';
                        var div = document.createElement('div');
                        div.style.cssText = 'max-width:75%;align-self:flex-end;background:var(--brand);color:white;padding:.65rem .85rem;border-radius:12px;border-bottom-right-radius:3px';
                        div.innerHTML = '<div style="font-size:.93rem">' + msg.replace(/</g,'&lt;') + '</div><small style="opacity:.65;font-size:.75rem">now</small>';
                        el.appendChild(div);
                        el.scrollTop = el.scrollHeight;
                    });
                });
                // Auto-refresh for new messages
                setInterval(function(){ location.reload(); }, 15000);
            })();
            </script>
        <?php else: ?>
            <div style="flex:1;display:grid;place-items:center;color:var(--muted);padding:2rem">
                <div style="text-align:center">
                    <div style="font-size:2.5rem;margin-bottom:.5rem">💬</div>
                    <p>Select a conversation to start replying</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
