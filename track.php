<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Track your shipment';
$trackingNumber = trim((string)($_GET['tracking_number'] ?? ''));
$shipment = null;
$lookupError = null;
if ($trackingNumber !== '') {
    if (!preg_match('/^[A-Za-z0-9-]{4,40}$/', $trackingNumber)) {
        $lookupError = 'Enter a valid tracking number using letters, numbers, or hyphens.';
    } else {
        try {
            $shipment = loadShipment(getDbConnection(), $trackingNumber);
            if ($shipment === null) {
                $lookupError = 'We could not find a shipment with that tracking number. Check it and try again.';
            }
        } catch (RuntimeException $exception) {
            $lookupError = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('SwiftTrackGo tracking lookup failed: ' . $exception->getMessage());
            $lookupError = 'Tracking is temporarily unavailable. Please try again later.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-intro"><div class="container"><span class="eyebrow">Shipment updates</span><h1>Track your shipment</h1><p class="lead">Enter your tracking number to view the latest status and event history.</p></div></section>
<section class="section"><div class="container">
    <form class="track-box" action="<?= e(url('track.php')) ?>" method="get" style="max-width:680px">
        <label for="tracking_number">Tracking number</label>
        <div class="input-row"><input id="tracking_number" name="tracking_number" value="<?= e($trackingNumber) ?>" maxlength="40" required autocomplete="off"><button class="button" type="submit">Track shipment</button></div>
    </form>
    <?php if ($lookupError): ?><div class="alert alert-danger" role="alert" style="max-width:680px"><?= e($lookupError) ?></div><?php endif; ?>
    <?php if ($shipment): ?>
        <div style="margin-top:2rem">
            <div class="split" style="align-items:start">
                <div><span class="eyebrow">Tracking number</span><h2><?= e($shipment['tracking_number']) ?></h2><span class="badge badge-<?= e(trackingStatusClass($shipment['current_status'])) ?>"><?= e($shipment['current_status']) ?></span></div>
                <div class="shipment-summary">
                    <div class="summary-item"><small>Origin</small><strong><?= e($shipment['origin']) ?></strong></div>
                    <div class="summary-item"><small>Destination</small><strong><?= e($shipment['destination']) ?></strong></div>
                    <div class="summary-item"><small>Service</small><strong><?= e($shipment['service_type']) ?></strong></div>
                    <div class="summary-item"><small>Created</small><strong><?= e(date('M j, Y', strtotime($shipment['created_at']))) ?></strong></div>
                    <div class="summary-item"><small>Estimated delivery</small><strong><?= $shipment['estimated_delivery_at'] ? e(date('M j, Y', strtotime($shipment['estimated_delivery_at']))) : 'Not available' ?></strong></div>
                    <div class="summary-item"><small>Current status</small><strong><?= e($shipment['current_status']) ?></strong></div>
                </div>
            </div>
            <?php if ($shipment['current_status'] === 'Delayed'): ?><div class="alert alert-warning">This shipment is delayed. Please contact the sender or support team if you need further assistance.</div><?php endif; ?>
            <?php if ($shipment['current_status'] === 'Cancelled'): ?><div class="alert alert-warning">This shipment has been cancelled. Its tracking history remains available.</div><?php endif; ?>
            <h2 style="margin-top:3rem">Tracking history</h2>
            <?php if ($shipment['events']): ?>
                <ol class="timeline">
                    <?php foreach ($shipment['events'] as $event): ?>
                        <li><strong><?= e($event['status']) ?></strong> <span class="badge"><?= e($event['location']) ?></span><br><time datetime="<?= e(date(DATE_ATOM, strtotime($event['event_at']))) ?>"><?= e(date('M j, Y \a\t g:i A', strtotime($event['event_at']))) ?></time><?php if ($event['description'] !== ''): ?><p><?= nl2br(e($event['description'])) ?></p><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p class="muted">No tracking events have been recorded yet.</p><?php endif; ?>
        </div>
    <?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
