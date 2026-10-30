<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About us';
require __DIR__ . '/includes/header.php';
?>
<section class="page-intro"><div class="container"><span class="eyebrow">About <?= e(APP_NAME) ?></span><h1>Make every delivery easier to follow.</h1><p class="lead">We believe shipping should come with clear information, dependable service, and a straightforward way to stay informed.</p></div></section>
<section class="section"><div class="container split">
    <div><span class="eyebrow">Our purpose</span><h2>Useful service built around transparency.</h2><p class="muted">SwiftTrackGo is a customizable shipment-tracking platform starter. Replace this text with your approved company history, mission, and operating details before launch.</p></div>
    <div class="card"><h3>Our values</h3><ul><li>Clear and timely communication</li><li>Respect for customer privacy</li><li>Reliable service at every handoff</li><li>Continuous improvement through feedback</li></ul></div>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
