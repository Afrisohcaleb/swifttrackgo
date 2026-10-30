<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/config/database.php';
$name = trim((string)(getenv('SWIFTTRACKGO_ADMIN_NAME') ?: ''));
$email = strtolower(trim((string)(getenv('SWIFTTRACKGO_ADMIN_EMAIL') ?: '')));
$password = (string)(getenv('SWIFTTRACKGO_ADMIN_PASSWORD') ?: '');
if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || strlen($password) < 8) {
    fwrite(STDERR, "Set SWIFTTRACKGO_ADMIN_NAME, SWIFTTRACKGO_ADMIN_EMAIL, and SWIFTTRACKGO_ADMIN_PASSWORD (at least 8 characters).\n");
    exit(1);
}
try {
    $db = getDbConnection();
    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)');
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
    fwrite(STDOUT, "Administrator account created for $email.\n");
} catch (PDOException $exception) {
    error_log('SwiftTrackGo administrator creation failed: ' . $exception->getMessage());
    fwrite(STDERR, "Administrator account could not be created. The email may already exist or the database is unavailable.\n");
    exit(1);
} finally {
    $password = '';
    putenv('SWIFTTRACKGO_ADMIN_PASSWORD');
}
