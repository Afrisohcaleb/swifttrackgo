<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();
try {
    $db = getDbConnection();
    $total = (int)$db->query('SELECT COUNT(*) FROM shipments')->fetchColumn();
    $inTransit = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE current_status IN ('Picked Up','In Transit','Arrived at Facility','Customs Clearance','Out for Delivery')")->fetchColumn();
    $delivered = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE current_status = 'Delivered'")->fetchColumn();
    $attention = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE current_status IN ('Delayed','Exception','Delivery Attempted')")->fetchColumn();
    $recent = $db->query(
        'SELECT s.id, s.tracking_number, s.current_status, s.destination, s.created_at
         FROM shipments s ORDER BY s.created_at DESC LIMIT 8'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('SwiftTrackGo dashboard query failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Dashboard is temporarily unavailable. Check the database setup and try again.');
} catch (RuntimeException $exception) {
    http_response_code(503);
    exit(e($exception->getMessage()));
}
$pageTitle = 'Dashboard';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>Dashboard</h1>
<p class="muted">Welcome, <?= e($_SESSION['user']['name']) ?>.</p>
<div class="stats">
    <div class="stat"><span class="muted">All shipments</span><strong><?= $total ?></strong></div>
    <div class="stat"><span class="muted">In progress</span><strong><?= $inTransit ?></strong></div>
    <div class="stat"><span class="muted">Delivered</span><strong><?= $delivered ?></strong></div>
    <div class="stat"><span class="muted">Needs attention</span><strong><?= $attention ?></strong></div>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;margin:2rem 0 1rem"><h2 style="margin:0">Recent shipments</h2><a class="button" href="<?= e(url('admin/shipments.php')) ?>">Manage shipments</a></div>
<div class="table-wrap"><table><thead><tr><th>Tracking number</th><th>Status</th><th>Destination</th><th>Created</th><th></th></tr></thead><tbody>
<?php foreach ($recent as $shipment): ?><tr>
    <td><?= e($shipment['tracking_number']) ?></td><td><span class="badge badge-<?= e(trackingStatusClass($shipment['current_status'])) ?>"><?= e($shipment['current_status']) ?></span></td>
    <td><?= e($shipment['destination']) ?></td><td><?= e(date('M j, Y', strtotime($shipment['created_at']))) ?></td><td><a href="<?= e(url('admin/shipment.php?id=' . $shipment['id'])) ?>">View</a></td>
</tr><?php endforeach; ?>
<?php if (!$recent): ?><tr><td colspan="5">No shipments have been created yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
