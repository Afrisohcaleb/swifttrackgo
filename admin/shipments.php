<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();
$db = getDbConnection();
$errors = [];
$trackingNumber = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $senderName = trim((string)($_POST['sender_name'] ?? ''));
    $senderEmail = trim((string)($_POST['sender_email'] ?? ''));
    $senderTelephone = trim((string)($_POST['sender_telephone'] ?? ''));
    $senderAddress = trim((string)($_POST['sender_address'] ?? ''));
    $recipientName = trim((string)($_POST['recipient_name'] ?? ''));
    $recipientEmail = trim((string)($_POST['recipient_email'] ?? ''));
    $recipientTelephone = trim((string)($_POST['recipient_telephone'] ?? ''));
    $recipientAddress = trim((string)($_POST['recipient_address'] ?? ''));
    $origin = trim((string)($_POST['origin'] ?? ''));
    $destination = trim((string)($_POST['destination'] ?? ''));
    $service = trim((string)($_POST['service_type'] ?? ''));
    $location = trim((string)($_POST['event_location'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $estimated = trim((string)($_POST['estimated_delivery_at'] ?? ''));
    if ($senderName === '' || mb_strlen($senderName) > 160) $errors[] = 'Sender name is required (up to 160 characters).';
    if ($recipientName === '' || mb_strlen($recipientName) > 160) $errors[] = 'Recipient name is required (up to 160 characters).';
    foreach (['Sender' => $senderEmail, 'Recipient' => $recipientEmail] as $label => $emailValue) {
        if ($emailValue !== '' && (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || mb_strlen($emailValue) > 190)) $errors[] = "$label email address is invalid.";
    }
    foreach (['Sender telephone' => $senderTelephone, 'Recipient telephone' => $recipientTelephone] as $label => $telephone) {
        if (mb_strlen($telephone) > 40) $errors[] = "$label must be 40 characters or fewer.";
    }
    foreach (['Sender address' => $senderAddress, 'Recipient address' => $recipientAddress] as $label => $address) {
        if (mb_strlen($address) > 2000) $errors[] = "$label must be 2,000 characters or fewer.";
    }
    foreach (['Origin' => $origin, 'Destination' => $destination, 'Service type' => $service, 'Event location' => $location] as $label => $value) {
        if ($value === '' || mb_strlen($value) > 190) $errors[] = "$label is required (up to 190 characters).";
    }
    if ($description === '' || mb_strlen($description) > 5000) $errors[] = 'An initial event description is required (up to 5,000 characters).';
    if ($estimated !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimated)) $errors[] = 'Estimated delivery date must be a valid date.';
    if (!$errors) {
        try {
            $db->beginTransaction();
            $customer = $db->prepare('INSERT INTO customers (name, email, telephone, address) VALUES (:name, :email, :telephone, :address)');
            $customer->execute([
                'name' => $senderName, 'email' => $senderEmail !== '' ? $senderEmail : null,
                'telephone' => $senderTelephone !== '' ? $senderTelephone : null,
                'address' => $senderAddress !== '' ? $senderAddress : null,
            ]);
            $senderId = (int)$db->lastInsertId();
            $customer->execute([
                'name' => $recipientName, 'email' => $recipientEmail !== '' ? $recipientEmail : null,
                'telephone' => $recipientTelephone !== '' ? $recipientTelephone : null,
                'address' => $recipientAddress !== '' ? $recipientAddress : null,
            ]);
            $recipientId = (int)$db->lastInsertId();

            $trackingNumber = 'STG-' . strtoupper(bin2hex(random_bytes(5)));
            $insert = $db->prepare(
                'INSERT INTO shipments (tracking_number, sender_customer_id, recipient_customer_id, origin, destination,
                    service_type, current_status, estimated_delivery_at, created_by)
                 VALUES (:tracking_number, :sender, :recipient, :origin, :destination, :service, :status, :estimated, :created_by)'
            );
            $insert->execute([
                'tracking_number' => $trackingNumber,
                'sender' => $senderId,
                'recipient' => $recipientId,
                'origin' => $origin,
                'destination' => $destination,
                'service' => $service,
                'status' => 'Shipment Created',
                'estimated' => $estimated !== '' ? $estimated : null,
                'created_by' => $_SESSION['user']['id'],
            ]);
            $shipmentId = (int)$db->lastInsertId();
            $event = $db->prepare(
                'INSERT INTO tracking_events (shipment_id, status, location, description, event_at, created_by)
                 VALUES (:shipment_id, :status, :location, :description, NOW(), :created_by)'
            );
            $event->execute([
                'shipment_id' => $shipmentId, 'status' => 'Shipment Created', 'location' => $location,
                'description' => $description, 'created_by' => $_SESSION['user']['id'],
            ]);
            recordAudit($db, 'shipment_created', 'shipment', $shipmentId);
            $db->commit();
            flash('success', "Shipment created. Tracking number: $trackingNumber");
            redirect('admin/shipment.php?id=' . $shipmentId);
        } catch (PDOException $exception) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('SwiftTrackGo shipment creation failed: ' . $exception->getMessage());
            $errors[] = 'The shipment could not be created. Check the database and form values, then try again.';
        }
    }
}
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$sql = 'SELECT id, tracking_number, current_status, origin, destination, created_at FROM shipments WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (tracking_number LIKE :tracking_search OR origin LIKE :origin_search OR destination LIKE :destination_search)';
    $params['tracking_search'] = '%' . $search . '%';
    $params['origin_search'] = '%' . $search . '%';
    $params['destination_search'] = '%' . $search . '%';
}
if (in_array($statusFilter, SHIPMENT_STATUSES, true)) {
    $sql .= ' AND current_status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$shipments = $stmt->fetchAll();
$pageTitle = 'Shipments';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>Shipment management</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<section class="card" style="margin:1.5rem 0">
    <h2 style="font-size:1.5rem">Create shipment</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-grid">
            <div class="field"><label for="sender_name">Sender name</label><input id="sender_name" name="sender_name" required maxlength="160" value="<?= e($_POST['sender_name'] ?? '') ?>"></div>
            <div class="field"><label for="sender_email">Sender email (optional)</label><input id="sender_email" name="sender_email" type="email" maxlength="190" value="<?= e($_POST['sender_email'] ?? '') ?>"></div>
            <div class="field"><label for="sender_telephone">Sender telephone (optional)</label><input id="sender_telephone" name="sender_telephone" maxlength="40" value="<?= e($_POST['sender_telephone'] ?? '') ?>"></div>
            <div class="field"><label for="sender_address">Sender address (optional)</label><input id="sender_address" name="sender_address" maxlength="2000" value="<?= e($_POST['sender_address'] ?? '') ?>"></div>
            <div class="field"><label for="recipient_name">Recipient name</label><input id="recipient_name" name="recipient_name" required maxlength="160" value="<?= e($_POST['recipient_name'] ?? '') ?>"></div>
            <div class="field"><label for="recipient_email">Recipient email (optional)</label><input id="recipient_email" name="recipient_email" type="email" maxlength="190" value="<?= e($_POST['recipient_email'] ?? '') ?>"></div>
            <div class="field"><label for="recipient_telephone">Recipient telephone (optional)</label><input id="recipient_telephone" name="recipient_telephone" maxlength="40" value="<?= e($_POST['recipient_telephone'] ?? '') ?>"></div>
            <div class="field"><label for="recipient_address">Recipient address (optional)</label><input id="recipient_address" name="recipient_address" maxlength="2000" value="<?= e($_POST['recipient_address'] ?? '') ?>"></div>
            <div class="field"><label for="origin">Origin</label><input id="origin" name="origin" required maxlength="190" value="<?= e($_POST['origin'] ?? '') ?>"></div>
            <div class="field"><label for="destination">Destination</label><input id="destination" name="destination" required maxlength="190" value="<?= e($_POST['destination'] ?? '') ?>"></div>
            <div class="field"><label for="service_type">Service type</label><select id="service_type" name="service_type" required><?php foreach (['Domestic Delivery','International Shipping','Express Delivery','Freight Services'] as $service): ?><option <?= ($_POST['service_type'] ?? '') === $service ? 'selected' : '' ?>><?= e($service) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="estimated_delivery_at">Estimated delivery date (optional)</label><input id="estimated_delivery_at" name="estimated_delivery_at" type="date" value="<?= e($_POST['estimated_delivery_at'] ?? '') ?>"></div>
            <div class="field"><label for="event_location">Initial event location</label><input id="event_location" name="event_location" required maxlength="190" value="<?= e($_POST['event_location'] ?? '') ?>"></div>
            <div class="field"><label for="description">Initial event description</label><input id="description" name="description" required maxlength="5000" value="<?= e($_POST['description'] ?? '') ?>"></div>
        </div>
        <button class="button" type="submit">Create shipment</button>
    </form>
</section>
<h2>Shipments</h2>
<form method="get" class="input-row" style="margin:1rem 0">
    <label class="honeypot" for="search">Search tracking or route</label><input id="search" name="search" placeholder="Search tracking number or route" value="<?= e($search) ?>">
    <select name="status" aria-label="Filter by status"><option value="">All statuses</option><?php foreach (SHIPMENT_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select>
    <button class="button" type="submit">Filter</button>
</form>
<div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Status</th><th>Origin</th><th>Destination</th><th>Created</th><th></th></tr></thead><tbody>
<?php foreach ($shipments as $shipment): ?><tr><td><?= e($shipment['tracking_number']) ?></td><td><span class="badge badge-<?= e(trackingStatusClass($shipment['current_status'])) ?>"><?= e($shipment['current_status']) ?></span></td><td><?= e($shipment['origin']) ?></td><td><?= e($shipment['destination']) ?></td><td><?= e(date('M j, Y', strtotime($shipment['created_at']))) ?></td><td><a href="<?= e(url('admin/shipment.php?id=' . $shipment['id'])) ?>">Manage</a></td></tr><?php endforeach; ?>
<?php if (!$shipments): ?><tr><td colspan="6">No matching shipments.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
