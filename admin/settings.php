<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin('admin');

$db = getDbConnection();
$settings = getSiteSettingsSafe();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $updates = [
        'company_name' => trim((string)($_POST['company_name'] ?? '')),
        'company_tagline' => trim((string)($_POST['company_tagline'] ?? '')),
        'company_email' => trim((string)($_POST['company_email'] ?? '')),
        'company_telephone' => trim((string)($_POST['company_telephone'] ?? '')),
        'company_address' => trim((string)($_POST['company_address'] ?? '')),
        'theme_primary' => trim((string)($_POST['theme_primary'] ?? '')),
        'theme_secondary' => trim((string)($_POST['theme_secondary'] ?? '')),
        'theme_accent' => trim((string)($_POST['theme_accent'] ?? '')),
        'livechat_enabled' => isset($_POST['livechat_enabled']) ? '1' : '0',
    ];

    foreach ($updates as $key => $value) {
        if ($key === 'company_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Company email must be a valid address or empty.';
        }
        if (in_array($key, ['theme_primary', 'theme_secondary', 'theme_accent'], true) && $value !== '' && !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value)) {
            $errors[] = 'Theme colors must be valid hex codes.';
        }
    }

    $brandFields = ['logo_path', 'dashboard_logo_path', 'favicon_path', 'hero_background_path', 'page_background_path'];
    foreach ($brandFields as $field) {
        if (!empty($_FILES[$field]['name'])) {
            $destination = $field === 'favicon_path' ? 'assets/logos' : ($field === 'dashboard_logo_path' ? 'assets/logos' : ($field === 'hero_background_path' || $field === 'page_background_path' ? 'assets/backgrounds' : 'assets/logos'));
            $uploaded = uploadBrandAsset($_FILES[$field], $destination);
            if ($uploaded === null) {
                $errors[] = 'The uploaded asset for ' . str_replace('_path', '', $field) . ' is invalid or too large.';
            } else {
                $updates[$field] = $uploaded;
            }
        }
    }

    if (!$errors) {
        saveSiteSettings($db, $updates);
        $settings = getSiteSettingsSafe();
        flash('success', 'Branding and site settings updated.');
        redirect('admin/settings.php');
    }
}

$pageTitle = 'Website settings';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>Website settings</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <div class="form-grid">
        <div class="field"><label for="company_name">Company name</label><input id="company_name" name="company_name" value="<?= e($settings['company_name']) ?>"></div>
        <div class="field"><label for="company_tagline">Tagline</label><input id="company_tagline" name="company_tagline" value="<?= e($settings['company_tagline']) ?>"></div>
        <div class="field"><label for="company_email">Email</label><input id="company_email" name="company_email" type="email" value="<?= e($settings['company_email']) ?>"></div>
        <div class="field"><label for="company_telephone">Telephone</label><input id="company_telephone" name="company_telephone" value="<?= e($settings['company_telephone']) ?>"></div>
        <div class="field field-full"><label for="company_address">Address</label><textarea id="company_address" name="company_address" rows="3"><?= e($settings['company_address']) ?></textarea></div>
        <div class="field"><label for="theme_primary">Primary color</label><input id="theme_primary" name="theme_primary" type="color" value="<?= e($settings['theme_primary']) ?>"></div>
        <div class="field"><label for="theme_secondary">Secondary color</label><input id="theme_secondary" name="theme_secondary" type="color" value="<?= e($settings['theme_secondary']) ?>"></div>
        <div class="field"><label for="theme_accent">Accent color</label><input id="theme_accent" name="theme_accent" type="color" value="<?= e($settings['theme_accent']) ?>"></div>
        <div class="field"><label for="logo_path">Primary logo image</label><input id="logo_path" type="file" name="logo_path" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="field"><label for="dashboard_logo_path">Dashboard logo image</label><input id="dashboard_logo_path" type="file" name="dashboard_logo_path" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="field"><label for="favicon_path">Favicon</label><input id="favicon_path" type="file" name="favicon_path" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="field"><label for="hero_background_path">Hero background</label><input id="hero_background_path" type="file" name="hero_background_path" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="field"><label for="page_background_path">Page background</label><input id="page_background_path" type="file" name="page_background_path" accept="image/png,image/jpeg,image/gif,image/webp"></div>
        <div class="field field-full">
            <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer">
                <input type="checkbox" name="livechat_enabled" value="1" <?= ($settings['livechat_enabled'] ?? '0') === '1' ? 'checked' : '' ?> style="width:auto">
                Enable live chat widget on public pages
            </label>
            <small style="display:block;color:var(--muted);margin-top:.3rem">Shows a floating chat bubble in the bottom-right corner. Manage conversations from <a href="<?= e(url('admin/chat.php')) ?>">Admin → Live Chat</a>.</small>
        </div>
    </div>
    <button class="button" type="submit">Save settings</button>
</form>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
