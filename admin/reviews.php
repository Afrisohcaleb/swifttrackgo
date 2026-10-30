<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin('admin');

$db = getDbConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $reviewId = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');

    if (!$reviewId || !in_array($action, ['approve', 'hide', 'delete'], true)) {
        $errors[] = 'Select a valid review action.';
    } else {
        try {
            if ($action === 'delete') {
                $stmt = $db->prepare('DELETE FROM reviews WHERE id = :id');
                $stmt->execute(['id' => $reviewId]);
                if ($stmt->rowCount() > 0) {
                    recordAudit($db, 'review_deleted', 'review', $reviewId);
                    flash('success', 'Review deleted.');
                } else {
                    $errors[] = 'Review not found.';
                }
            } else {
                $active = $action === 'approve' ? 1 : 0;
                $stmt = $action === 'approve'
                    ? $db->prepare('UPDATE reviews SET is_active = :active WHERE id = :id AND is_sample = 0')
                    : $db->prepare('UPDATE reviews SET is_active = 0 WHERE id = :id');
                $stmt->execute($action === 'approve' ? ['active' => $active, 'id' => $reviewId] : ['id' => $reviewId]);
                if ($stmt->rowCount() > 0) {
                    recordAudit($db, $action === 'approve' ? 'review_approved' : 'review_hidden', 'review', $reviewId);
                    flash('success', $action === 'approve' ? 'Review approved and published.' : 'Review hidden from the public site.');
                } else {
                    $check = $db->prepare('SELECT id, is_sample FROM reviews WHERE id = :id');
                    $check->execute(['id' => $reviewId]);
                    $existingReview = $check->fetch();
                    if (!$existingReview) {
                        $errors[] = 'Review not found.';
                    } elseif ($action === 'approve' && (int)$existingReview['is_sample'] === 1) {
                        $errors[] = 'Sample reviews cannot be published as customer feedback. Delete or hide them, then approve a real submitted review.';
                    } else {
                        flash('success', 'Review status is unchanged.');
                    }
                }
            }

            if (!$errors) {
                redirect('admin/reviews.php');
            }
        } catch (PDOException $exception) {
            error_log('SwiftTrackGo review moderation failed: ' . $exception->getMessage());
            $errors[] = 'The review could not be updated. Please try again.';
        }
    }
}

$reviews = $db->query(
    'SELECT id, reviewer_name, reviewer_location, rating, review_text, reviewer_image_path, is_active, is_sample, created_at
     FROM reviews ORDER BY is_active ASC, created_at DESC LIMIT 200'
)->fetchAll();

$pageTitle = 'Review moderation';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>Review moderation</h1>
<p class="muted">Visitor reviews are private until an administrator approves them.</p>
<?php foreach ($errors as $error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<?php if (!$reviews): ?>
    <div class="card">No reviews have been submitted yet.</div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($reviews as $review): ?>
            <article class="card">
                <div style="display:flex;justify-content:space-between;gap:.75rem;align-items:start">
                    <div><h2 style="font-size:1.15rem;margin:0"><?= e($review['reviewer_name']) ?></h2>
                        <?php if (!empty($review['reviewer_image_path'])): ?><img class="review-avatar" src="<?= e(url($review['reviewer_image_path'])) ?>" alt="Photo submitted by <?= e($review['reviewer_name']) ?>" loading="lazy"><?php endif; ?>
                        <?php if ($review['reviewer_location'] !== ''): ?><small class="muted"><?= e($review['reviewer_location']) ?></small><?php endif; ?>
                    </div>
                    <span class="badge <?= (int)$review['is_sample'] === 1 ? 'badge-warning' : ((int)$review['is_active'] === 1 ? 'badge-success' : 'badge-warning') ?>"><?= (int)$review['is_sample'] === 1 ? 'Demo sample' : ((int)$review['is_active'] === 1 ? 'Published' : 'Pending') ?></span>
                </div>
                <p class="review-stars" aria-label="Rating: <?= (int)$review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?></p>
                <p><?= nl2br(e($review['review_text'])) ?></p>
                <small class="muted">Submitted <?= e(date('M j, Y', strtotime($review['created_at']))) ?></small>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                        <input type="hidden" name="action" value="<?= (int)$review['is_active'] === 1 ? 'hide' : 'approve' ?>">
                        <button class="button button-small" type="submit"><?= (int)$review['is_sample'] === 1 ? 'Hide sample' : ((int)$review['is_active'] === 1 ? 'Hide' : 'Approve') ?></button>
                    </form>
                    <form method="post" onsubmit="return confirm('Permanently delete this review?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="button button-small button-secondary" type="submit">Delete</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
