<?php
$pageTitle = $pageTitle ?? 'Admin';
$flashMessage = takeFlash();
$settings = getSiteSettingsSafe();
$companyName = $settings['company_name'] ?: APP_NAME;
$primaryColor = $settings['theme_primary'] ?: '#087f8c';
$secondaryColor = $settings['theme_secondary'] ?: '#122d3d';
$accentColor = $settings['theme_accent'] ?: '#f1a43c';
$dashboardLogo = $settings['dashboard_logo_path'] ?: '';
$styleFile = BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css';
$styleVersion = is_file($styleFile) ? (string)filemtime($styleFile) : '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e($companyName) ?> Admin</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css') . '?v=' . $styleVersion) ?>">
    <style>
        :root { --brand: <?= e($primaryColor) ?>; --brand-dark: <?= e($secondaryColor) ?>; --accent: <?= e($accentColor) ?>; }
    </style>
</head>
<body class="admin-shell">
<header class="admin-top"><div class="container nav">
    <a class="brand" href="<?= e(url('admin/index.php')) ?>"><?php if ($dashboardLogo !== ''): ?><img src="<?= e(url($dashboardLogo)) ?>" alt="<?= e($companyName) ?> admin logo" style="height:32px;width:auto;max-width:130px;vertical-align:middle;"> <?php endif; ?><?= e($companyName) ?> <small>Admin</small></a>
    <nav class="nav-links" aria-label="Admin navigation">
        <a href="<?= e(url('admin/index.php')) ?>">Overview</a>
        <a href="<?= e(url('admin/shipments.php')) ?>">Shipments</a>
        <a href="<?= e(url('admin/customers.php')) ?>">Customers</a>
        <a href="<?= e(url('admin/users.php')) ?>">Users</a>
        <a href="<?= e(url('admin/chat.php')) ?>">Live Chat</a>
        <a href="<?= e(url('admin/reviews.php')) ?>">Reviews</a>
        <a href="<?= e(url('admin/settings.php')) ?>">Settings</a>
        <a href="<?= e(url('admin/audit.php')) ?>">Audit</a>
        <form action="<?= e(url('admin/logout.php')) ?>" method="post" style="margin:0"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="button button-small button-secondary" type="submit">Sign out</button></form>
    </nav>
</div></header>
<main class="container admin-content">
<?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>