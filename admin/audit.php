<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$db = getDbConnection();
$logs = $db->query(
    'SELECT a.id, a.action, a.record_type, a.record_id, a.created_at, u.name AS user_name
     FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC LIMIT 100'
)->fetchAll();

$pageTitle = 'Audit log';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>Audit log</h1>
<div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr>
    <td><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
    <td><?= e($log['user_name'] ?? 'System') ?></td>
    <td><?= e($log['action']) ?></td>
    <td><?= e($log['record_type'] ?? '-') ?>#<?= (int)($log['record_id'] ?? 0) ?></td>
</tr><?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="4">No audit activity has been recorded yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
