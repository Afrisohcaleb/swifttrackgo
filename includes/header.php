<?php
$pageTitle = $pageTitle ?? APP_NAME;
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$flashMessage = takeFlash();
$settings = getSiteSettingsSafe();
$companyName = $settings['company_name'] ?: APP_NAME;
$primaryColor = $settings['theme_primary'] ?: '#087f8c';
$secondaryColor = $settings['theme_secondary'] ?: '#122d3d';
$accentColor = $settings['theme_accent'] ?: '#f1a43c';
$logoPath = $settings['logo_path'] ?: '';
$faviconPath = $settings['favicon_path'] ?: '';
$styleFile = BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css';
$styleVersion = is_file($styleFile) ? (string)filemtime($styleFile) : '1';
$pageBackgroundPath = $settings['page_background_path'] ?: '';
$pageBackgroundStyle = $pageBackgroundPath !== ''
    ? 'background-image:linear-gradient(rgba(255,255,255,.92),rgba(255,255,255,.92)),url(' . json_encode(url($pageBackgroundPath), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) . ');background-size:cover;background-position:center;background-attachment:fixed;'
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e($companyName) ?></title>
    <meta name="description" content="Track your shipment with <?= e($companyName) ?>.">
    <?php if ($faviconPath !== ''): ?><link rel="icon" href="<?= e(url($faviconPath)) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css') . '?v=' . $styleVersion) ?>">
    <style>
        :root { --brand: <?= e($primaryColor) ?>; --brand-dark: <?= e($secondaryColor) ?>; --accent: <?= e($accentColor) ?>; }
    </style>
</head>
<body<?= $pageBackgroundStyle !== '' ? ' style="' . e($pageBackgroundStyle) . '"' : '' ?>>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= e(url()) ?>"><?php if ($logoPath !== ''): ?><img src="<?= e(url($logoPath)) ?>" alt="<?= e($companyName) ?> logo" style="height:38px;width:auto;max-width:140px;vertical-align:middle;">
        <?php else: ?><span class="brand-mark" aria-hidden="true">S</span><?php endif; ?><?= e($companyName) ?></a>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="<?= e(url()) ?>" <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>Home</a>
            <a href="<?= e(url('track.php')) ?>" <?= $currentPage === 'track.php' ? 'aria-current="page"' : '' ?>>Track</a>
            <a href="<?= e(url('about.php')) ?>" <?= $currentPage === 'about.php' ? 'aria-current="page"' : '' ?>>About</a>
            <a href="<?= e(url('services.php')) ?>" <?= $currentPage === 'services.php' ? 'aria-current="page"' : '' ?>>Services</a>
            <a href="<?= e(url('contact.php')) ?>" <?= $currentPage === 'contact.php' ? 'aria-current="page"' : '' ?>>Contact</a>
        </nav>
    </div>
</header>
<main>
<?php if ($flashMessage): ?>
    <div class="container"><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div></div>
<?php endif; ?>
