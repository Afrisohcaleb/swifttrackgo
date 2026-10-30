<?php
require_once dirname(__DIR__) . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verifyCsrf();
if (!empty($_SESSION['user']['id'])) {
    try {
        recordAudit(getDbConnection(), 'admin_logout', 'user', (int)$_SESSION['user']['id']);
    } catch (PDOException $exception) {
        error_log('SwiftTrackGo logout audit failed: ' . $exception->getMessage());
    } catch (RuntimeException $exception) {
        error_log('SwiftTrackGo logout audit unavailable: ' . $exception->getMessage());
    }
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
redirect('admin/login.php');
