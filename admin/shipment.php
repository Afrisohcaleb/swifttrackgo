<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('A valid shipment id is required.');
}
$db = getDbConnection();
$shipmentQuery = $db->prepare(
    'SELECT s.*, sender.name AS sender_name, sender.email AS sender_email, sender.telephone AS sender_telephone, sender.address AS sender_address,
            recipient.name AS recipient_name, recipient.email AS recipient_email, recipient.telephone AS recipient_telephone, recipient.address AS recipient_address
     FROM shipments s JOIN customers sender ON sender.id = s.sender_customer_id
     JOIN customers recipient ON recipient.id = s.recipient_customer_id WHERE s.id = :id'
);
$shipmentQuery->execute(['id' => $id]);
$shipment = $shipmentQuery->fetch();
if (!$shipment) {
    http_response_code(404);
    exit('Shipment not found.');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $status = (string)($_POST['status'] ?? '');
    $location = trim((string)($_POST['location'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $eventTimeInput = trim((string)($_POST['event_at'] ?? ''));
    $eventTime = DateTime::createFromFormat('Y-m-d\TH:i', $eventTimeInput);
    if (!in_array($status, SHIPMENT_STATUSES, true)) $errors[] = 'Select a valid shipment status.';
    if ($location === '' || mb_strlen($location) > 190) $errors[] = 'Enter a location (up to 190 characters).';
    if ($description === '' || mb_strlen($description) > 5000) $errors[] = 'Enter an event description (up to 5,000 characters).';
    if (!$eventTime || $eventTime->format('Y-m-d\TH:i') !== $eventTimeInput) $errors[] = 'Enter a valid event date and time.';
    if (!$errors) {
        try {
            $db->beginTransaction();
            $update = $db->prepare('UPDATE shipments SET current_status = :status WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $id]);
            $event = $db->prepare(
                'INSERT INTO tracking_events (shipment_id, status, location, description, event_at, created_by)
                 VALUES (:shipment_id, :status, :location, :description, :event_at, :created_by)'
            );
            $event->execute([
                'shipment_id' => $id, 'status' => $status, 'location' => $location,
                'description' => $description, 'event_at' => $eventTime->format('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user']['id'],
            ]);
            recordAudit($db, 'shipment_status_updated', 'shipment', (int)$id);
            $db->commit();
            flash('success', 'Shipment status and tracking event updated.');
            redirect('admin/shipment.php?id=' . $id);
        } catch (PDOException $exception) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('SwiftTrackGo shipment status update failed: ' . $exception->getMessage());
            $errors[] = 'The tracking event could not be saved. Please try again.';
        }
    }
}
$eventQuery = $db->prepare('SELECT status, location, description, event_at FROM tracking_events WHERE shipment_id = :id ORDER BY event_at DESC, id DESC');
$eventQuery->execute(['id' => $id]);
$events = $eventQuery->fetchAll();
$pageTitle = 'Shipment ' . $shipment['tracking_number'];
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<p><a href="<?= e(url('admin/shipments.php')) ?>">&larr; Back to shipments</a></p>
<h1><?= e($shipment['tracking_number']) ?></h1>
<p><span class="badge badge-<?= e(trackingStatusClass($shipment['current_status'])) ?>"><?= e($shipment['current_status']) ?></span></p>
<div class="grid-3">
    <section class="card"><h2 style="font-size:1.35rem">Route</h2><p><strong>Origin:</strong> <?= e($shipment['origin']) ?><br><strong>Destination:</strong> <?= e($shipment['destination']) ?><br><strong>Service:</strong> <?= e($shipment['service_type']) ?><br><strong>Estimated:</strong> <?= $shipment['estimated_delivery_at'] ? e($shipment['estimated_delivery_at']) : 'Not available' ?></p></section>
    <section class="card"><h2 style="font-size:1.35rem">Sender (private)</h2><p><?= e($shipment['sender_name']) ?><br><?= e($shipment['sender_email']) ?><br><?= e($shipment['sender_telephone']) ?><br><?= nl2br(e($shipment['sender_address'])) ?></p></section>
    <section class="card"><h2 style="font-size:1.35rem">Recipient (private)</h2><p><?= e($shipment['recipient_name']) ?><br><?= e($shipment['recipient_email']) ?><br><?= e($shipment['recipient_telephone']) ?><br><?= nl2br(e($shipment['recipient_address'])) ?></p></section>
</div>
<section class="card" style="margin:1.5rem 0">
    <h2 style="font-size:1.5rem">Add tracking event</h2>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-grid">
            <div class="field"><label for="status">New status</label><select id="status" name="status" required><?php foreach (SHIPMENT_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $shipment['current_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="location">Location</label><input id="location" name="location" required maxlength="190" value="<?= e($_POST['location'] ?? '') ?>"></div>
            <div class="field"><label for="event_at">Event date and time</label><input id="event_at" name="event_at" type="datetime-local" required value="<?= e($_POST['event_at'] ?? date('Y-m-d\TH:i')) ?>"></div>
            <div class="field"><label for="description">Description</label><input id="description" name="description" required maxlength="5000" value="<?= e($_POST['description'] ?? '') ?>"></div>
        </div>
        <button class="button" type="submit">Save event</button>
    </form>
</section>
<h2>Tracking history</h2>
<?php if ($events): ?><ol class="timeline"><?php foreach ($events as $event): ?><li><strong><?= e($event['status']) ?></strong> <span class="badge"><?= e($event['location']) ?></span><br><time><?= e(date('M j, Y \a\t g:i A', strtotime($event['event_at']))) ?></time><p><?= nl2br(e($event['description'])) ?></p></li><?php endforeach; ?></ol><?php else: ?><p>No tracking history has been recorded.</p><?php endif; ?>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
