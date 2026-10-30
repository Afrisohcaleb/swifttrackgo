<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// Initialize database connection for reviews section
try {
    $db = getDbConnection();
} catch (Throwable $exception) {
    error_log('SwiftTrackGo homepage database connection unavailable: ' . $exception->getMessage());
    $db = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    verifyCsrf();
    $name = trim((string)($_POST['reviewer_name'] ?? ''));
    $location = trim((string)($_POST['reviewer_location'] ?? ''));
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $reviewText = trim((string)($_POST['review_text'] ?? ''));
    $reviewImagePath = null;

    if (!empty($_POST['website'] ?? '')) {
        flash('danger', 'Unable to submit your review.');
        redirect('');
    }
    if ($name === '' || mb_strlen($name) > 120 || mb_strlen($location) > 120
        || $rating === false || $rating === null || $rating < 1 || $rating > 5
        || mb_strlen($reviewText) < 10 || mb_strlen($reviewText) > 2000) {
        flash('danger', 'Please provide a name, a rating from 1 to 5, and a review between 10 and 2,000 characters.');
        redirect('');
    }
    if (time() - (int)($_SESSION['review_last_submitted'] ?? 0) < 60) {
        flash('warning', 'Please wait a minute before submitting another review.');
        redirect('');
    }
    if (isset($_FILES['reviewer_image']) && ($_FILES['reviewer_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $reviewImagePath = uploadBrandAsset($_FILES['reviewer_image'], 'assets/images');
        if ($reviewImagePath === null) {
            flash('danger', 'The photo must be a valid JPG, PNG, GIF, or WebP image under 2 MB.');
            redirect('');
        }
    }
    if (!$db instanceof PDO) {
        if ($reviewImagePath !== null) {
            if (!deleteUploadedAsset($reviewImagePath)) {
                error_log('SwiftTrackGo failed to remove an orphaned review photo: ' . $reviewImagePath);
            }
        }
        flash('danger', 'Reviews are temporarily unavailable. Please try again later.');
        redirect('');
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO reviews (reviewer_name, reviewer_location, rating, review_text, reviewer_image_path, is_active)
             VALUES (:name, :location, :rating, :review_text, :image_path, 0)'
        );
        $stmt->execute([
            'name' => $name,
            'location' => $location,
            'rating' => $rating,
            'review_text' => $reviewText,
            'image_path' => $reviewImagePath,
        ]);
        $_SESSION['review_last_submitted'] = time();
        flash('success', 'Thank you for sharing your review. It will appear after our team approves it.');
    } catch (PDOException $exception) {
        if ($reviewImagePath !== null) {
            if (!deleteUploadedAsset($reviewImagePath)) {
                error_log('SwiftTrackGo failed to remove an orphaned review photo: ' . $reviewImagePath);
            }
        }
        error_log('SwiftTrackGo review submission failed: ' . $exception->getMessage());
        flash('danger', 'Reviews are temporarily unavailable. Please try again later.');
    }
    redirect('');
}

$pageTitle = 'Shipment tracking made simple';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">Shipping, without the guesswork</span>
            <h1>Your delivery journey, clearly in view.</h1>
            <p class="lead">Get reliable updates at every milestone. Enter your tracking number to see the latest shipment status and journey history.</p>
            <a class="button button-secondary" href="<?= e(url('services.php')) ?>">Explore shipping services</a>
        </div>
        <form class="track-box" action="<?= e(url('track.php')) ?>" method="get">
            <h2 style="font-size:1.45rem;margin-top:0">Track a shipment</h2>
            <label for="tracking_number">Tracking number</label>
            <div class="input-row">
                <input id="tracking_number" name="tracking_number" type="text" required maxlength="40" autocomplete="off" placeholder="e.g. STG-8F3K2M9Q">
                <button class="button" type="submit">Track</button>
            </div>
            <p class="muted" style="font-size:.88rem;margin-bottom:0">Your tracking history is available any time.</p>
        </form>
    </div>
</section>
<section class="section">
    <div class="container">
        <div class="section-heading"><span class="eyebrow">What we deliver</span><h2>Shipping options for every journey</h2><p>Choose a service that fits your timeline, destination, and shipment size.</p></div>
        <div class="grid-4">
            <?php foreach (['Domestic Delivery' => 'Dependable delivery across your local network.', 'International Shipping' => 'Send parcels across borders with clear progress updates.', 'Express Delivery' => 'A faster option when timing matters most.', 'Freight Services' => 'Flexible support for larger and heavier shipments.'] as $title => $description): ?>
                <article class="card"><div class="card-icon" aria-hidden="true">→</div><h3><?= e($title) ?></h3><p class="muted"><?= e($description) ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="section section-soft">
    <div class="container split">
        <div><span class="eyebrow">Simple by design</span><h2>From pickup to delivery, keep up with every step.</h2><p class="muted">A clear tracking timeline helps you understand where your shipment is and what happens next.</p></div>
        <div class="steps">
            <div class="step"><h3>Get your tracking number</h3><p class="muted">The sender or our team shares your unique shipment reference.</p></div>
            <div class="step"><h3>Check shipment progress</h3><p class="muted">View shipment status, key locations, and timestamped events.</p></div>
            <div class="step"><h3>Receive your delivery</h3><p class="muted">See delivery confirmation when the shipment reaches its destination.</p></div>
        </div>
    </div>
</section>
<section class="section">
    <div class="container split">
        <div><span class="eyebrow">Why SwiftTrackGo</span><h2>Information you can act on.</h2></div>
        <div class="grid-3">
            <article class="card"><h3>Useful updates</h3><p class="muted">Shipment milestones are organized in an easy-to-follow timeline.</p></article>
            <article class="card"><h3>Private by default</h3><p class="muted">Public tracking shows shipment details, not personal customer records.</p></article>
            <article class="card"><h3>Support when needed</h3><p class="muted">Contact our team if a shipment needs attention.</p></article>
        </div>
    </div>
</section>
<section class="section section-soft" id="customer-reviews">
    <div class="container">
        <div class="section-heading"><span class="eyebrow">Customer reviews</span><h2>What our customers say</h2></div>
        <div class="reviews-grid">
            <?php
            $reviews = [];
            $reviewsUnavailable = false;
            if ($db instanceof PDO) {
                try {
                    $reviews = $db->query(
                        'SELECT reviewer_name, reviewer_location, rating, review_text, reviewer_image_path, is_sample
                         FROM reviews WHERE is_active = 1
                         ORDER BY is_sample ASC, sort_order, created_at DESC LIMIT 3'
                    )->fetchAll();
                } catch (PDOException $exception) {
                    error_log('SwiftTrackGo reviews are unavailable: ' . $exception->getMessage());
                    $reviewsUnavailable = true;
                }
            } else {
                $reviewsUnavailable = true;
            }
            foreach ($reviews as $review):
                $stars = str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']);
            ?>
            <article class="review-card">
                <?php if ((int)$review['is_sample'] === 1): ?><span class="badge badge-warning">Sample review — replace with real customer feedback</span><?php endif; ?>
                <div class="review-header">
                    <?php if (!empty($review['reviewer_image_path'])): ?>
                        <img class="review-avatar" src="<?= e(url($review['reviewer_image_path'])) ?>" alt="Photo of <?= e($review['reviewer_name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="review-avatar review-avatar-fallback" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($review['reviewer_name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <strong><?= e($review['reviewer_name']) ?></strong>
                    <?php if ($review['reviewer_location']): ?>
                        <small class="muted">• <?= e($review['reviewer_location']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="review-stars" aria-label="Rating: <?= $review['rating'] ?> out of 5 stars"><?= $stars ?></div>
                <p class="review-text">“<?= e($review['review_text']) ?>”</p>
            </article>
            <?php endforeach; ?>
            <?php if ($reviewsUnavailable): ?>
                <p class="muted">Customer reviews are not available right now.</p>
            <?php elseif (!$reviews): ?>
                <p class="muted">Customer reviews will appear here when available.</p>
            <?php endif; ?>
        </div>
        <div class="card" style="max-width:760px;margin:2rem auto 0">
            <h3>Share your experience</h3>
            <p class="muted">No email address is required. Reviews are checked by our team before they appear publicly.</p>
            <form method="post" enctype="multipart/form-data" action="<?= e(url('index.php')) ?>#customer-reviews">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="submit_review">
                <div class="honeypot" aria-hidden="true"><label for="review_website">Leave this field empty</label><input id="review_website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="form-grid">
                    <div class="field"><label for="reviewer_name">Name</label><input id="reviewer_name" name="reviewer_name" required maxlength="120"></div>
                    <div class="field"><label for="reviewer_location">Location (optional)</label><input id="reviewer_location" name="reviewer_location" maxlength="120"></div>
                    <div class="field"><label for="rating">Rating</label><select id="rating" name="rating" required><option value="">Choose rating</option><?php for ($stars = 5; $stars >= 1; $stars--): ?><option value="<?= $stars ?>"><?= $stars ?> star<?= $stars === 1 ? '' : 's' ?></option><?php endfor; ?></select></div>
                    <div class="field"><label for="reviewer_image">Your photo (optional)</label><input id="reviewer_image" name="reviewer_image" type="file" accept="image/jpeg,image/png,image/gif,image/webp"><small class="muted">Use your own photo. JPG, PNG, GIF, or WebP; up to 2 MB.</small></div>
                    <div class="field field-full"><label for="review_text">Your review</label><textarea id="review_text" name="review_text" rows="4" minlength="10" maxlength="2000" required></textarea></div>
                </div>
                <button class="button" type="submit">Submit review</button>
            </form>
        </div>
    </div>
</section>
<section class="section section-soft">
    <div class="container">
        <div class="section-heading"><span class="eyebrow">Frequently asked questions</span><h2>Quick answers</h2></div>
        <div class="grid-3">
            <article class="card"><h3>Where can I find my tracking number?</h3><p class="muted">Check the shipment confirmation or ask the person or business that sent your parcel.</p></article>
            <article class="card"><h3>Why is my tracking not changing?</h3><p class="muted">Updates appear when a shipment reaches a recorded milestone. Contact support if you need assistance.</p></article>
            <article class="card"><h3>What if my delivery is delayed?</h3><p class="muted">The tracking page displays the latest recorded status. Contact the team for next steps.</p></article>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
