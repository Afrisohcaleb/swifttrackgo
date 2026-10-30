<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Contact us';
$errors = [];
$values = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if (!empty($_POST['website'] ?? '')) {
        $errors[] = 'Unable to submit this request.';
    }
    if ($values['name'] === '' || mb_strlen($values['name']) > 160) $errors[] = 'Enter your name (up to 160 characters).';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190) $errors[] = 'Enter a valid email address.';
    if ($values['subject'] === '' || mb_strlen($values['subject']) > 190) $errors[] = 'Enter a subject (up to 190 characters).';
    if ($values['message'] === '' || mb_strlen($values['message']) > 5000) $errors[] = 'Enter a message (up to 5,000 characters).';
    $lastSent = (int)($_SESSION['contact_last_sent'] ?? 0);
    if (time() - $lastSent < 30) $errors[] = 'Please wait before sending another message.';
    if (!$errors) {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$values['name'], $values['email'], $values['subject'], $values['message']]);
            $_SESSION['contact_last_sent'] = time();
            flash('success', 'Thank you. Your message has been received.');
            redirect('contact.php');
        } catch (PDOException $exception) {
            error_log('SwiftTrackGo contact submission failed: ' . $exception->getMessage());
            $errors[] = 'We could not send your message right now. Please try again later.';
        } catch (RuntimeException $exception) {
            error_log('SwiftTrackGo contact service unavailable: ' . $exception->getMessage());
            $errors[] = 'We could not send your message right now. Please try again later.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-intro"><div class="container"><span class="eyebrow">Get in touch</span><h1>How can we help?</h1><p class="lead">Send our team a message and we will follow up.</p></div></section>
<section class="section"><div class="container split" style="align-items:start">
    <form method="post" class="card">
        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="honeypot" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="160" value="<?= e($values['name']) ?>"></div>
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190" value="<?= e($values['email']) ?>"></div>
        <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" required maxlength="190" value="<?= e($values['subject']) ?>"></div>
        <div class="field"><label for="message">Message</label><textarea id="message" name="message" rows="6" required maxlength="5000"><?= e($values['message']) ?></textarea></div>
        <button class="button" type="submit">Send message</button>
    </form>
    <aside><h2>Contact details</h2><p class="muted">Add your verified company email, telephone, and address in the site settings when the settings editor is implemented. Do not publish placeholder contact information.</p></aside>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
