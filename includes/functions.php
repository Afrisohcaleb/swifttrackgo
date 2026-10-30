<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_URL . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrfToken(), $submitted)) {
        http_response_code(403);
        exit('The form expired. Go back, refresh the page, and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($message) ? $message : null;
}

function requireAdmin(?string $role = null): void
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) {
        flash('warning', 'Please sign in to continue.');
        redirect('admin/login.php');
    }

    if ($role !== null && ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}

function recordAudit(PDO $db, string $action, ?string $recordType = null, ?int $recordId = null): void
{
    $stmt = $db->prepare(
        'INSERT INTO audit_logs (user_id, action, record_type, record_id, ip_address)
         VALUES (:user_id, :action, :record_type, :record_id, :ip)'
    );
    $stmt->execute([
        'user_id' => $_SESSION['user']['id'] ?? null,
        'action' => $action,
        'record_type' => $recordType,
        'record_id' => $recordId,
        'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
    ]);
}

function trackingStatusClass(string $status): string
{
    return match ($status) {
        'Delivered' => 'success',
        'Delayed', 'Exception' => 'danger',
        'Cancelled' => 'dark',
        'In Transit', 'Out for Delivery', 'Delivery Attempted' => 'warning',
        default => 'primary',
    };
}

function trackingStatusIcon(string $status): string
{
    return match ($status) {
        'Delivered' => '✓',
        'Delayed', 'Exception' => '!',
        'Cancelled' => '×',
        'Out for Delivery', 'Delivery Attempted' => '↗',
        default => '•',
    };
}

function loadShipment(PDO $db, string $trackingNumber): ?array
{
    $stmt = $db->prepare(
        'SELECT id, tracking_number, origin, destination, service_type, current_status,
                created_at, estimated_delivery_at
         FROM shipments WHERE tracking_number = :tracking_number LIMIT 1'
    );
    $stmt->execute(['tracking_number' => strtoupper(trim($trackingNumber))]);
    $shipment = $stmt->fetch();
    if (!$shipment) {
        return null;
    }

    $events = $db->prepare(
        'SELECT status, location, description, event_at
         FROM tracking_events WHERE shipment_id = :shipment_id
         ORDER BY event_at DESC, id DESC'
    );
    $events->execute(['shipment_id' => $shipment['id']]);
    $shipment['events'] = $events->fetchAll();

    return $shipment;
}

function getSiteSettings(PDO $db): array
{
    $stmt = $db->query('SELECT setting_key, setting_value FROM site_settings');
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['setting_key']] = $row['setting_value'];
    }

    return array_merge([
        'company_name' => APP_NAME,
        'company_tagline' => 'Shipping, without the guesswork',
        'company_email' => '',
        'company_telephone' => '',
        'company_address' => '',
        'theme_primary' => '#087f8c',
        'theme_secondary' => '#122d3d',
        'theme_accent' => '#f1a43c',
        'logo_path' => '',
        'dashboard_logo_path' => '',
        'favicon_path' => '',
        'hero_background_path' => '',
        'page_background_path' => '',
        'livechat_enabled' => '1',
        'livechat_online' => '0',
    ], $result);
}

function getSiteSettingsSafe(): array
{
    try {
        return getSiteSettings(getDbConnection());
    } catch (Throwable $exception) {
        error_log('SwiftTrackGo site settings unavailable: ' . $exception->getMessage());

        return [
            'company_name' => APP_NAME,
            'company_tagline' => 'Shipping, without the guesswork',
            'company_email' => '',
            'company_telephone' => '',
            'company_address' => '',
            'theme_primary' => '#087f8c',
            'theme_secondary' => '#122d3d',
            'theme_accent' => '#f1a43c',
            'logo_path' => '',
            'dashboard_logo_path' => '',
            'favicon_path' => '',
            'hero_background_path' => '',
            'page_background_path' => '',
            'livechat_enabled' => '1',
            'livechat_online' => '0',
        ];
    }
}

function saveSiteSettings(PDO $db, array $settings): void
{
    $stmt = $db->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($settings as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => (string)$value]);
    }
}

function normalizeSettingKey(string $key): string
{
    $key = trim($key);
    return preg_replace('/[^a-z0-9_]+/', '_', strtolower($key)) ?: 'site_setting';
}

function uploadBrandAsset(array $file, string $destinationDir): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if (($file['size'] ?? 0) > UPLOAD_MAX_SIZE || @getimagesize($file['tmp_name']) === false) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return null;
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => null,
    };
    if ($extension === null) {
        return null;
    }

    $relativeDir = trim(str_replace('\\', '/', $destinationDir), '/');
    if (!in_array($relativeDir, ['assets/logos', 'assets/backgrounds', 'assets/images'], true)) {
        return null;
    }
    $targetDir = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return null;
    }

    $filename = 'brand_' . bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $targetDir . DIRECTORY_SEPARATOR . $filename)) {
        return null;
    }

    return $relativeDir . '/' . $filename;
}

function deleteUploadedAsset(?string $relativePath): bool
{
    if ($relativePath === null || $relativePath === '') {
        return true;
    }

    $normalized = str_replace('\\', '/', $relativePath);
    if (!preg_match('#^assets/images/brand_[a-f0-9]{16}\.(?:jpg|png|gif|webp)$#', $normalized)) {
        return false;
    }

    $path = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    return !is_file($path) || unlink($path);
}
