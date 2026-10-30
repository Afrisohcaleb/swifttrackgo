<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

$email = strtolower(trim((string)(getenv('SWIFTTRACKGO_ADMIN_EMAIL') ?: '')));
$password = (string)(getenv('SWIFTTRACKGO_ADMIN_PASSWORD') ?: '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    fwrite(STDERR, "Set SWIFTTRACKGO_ADMIN_EMAIL and SWIFTTRACKGO_ADMIN_PASSWORD (at least 8 characters).\n");
    exit(1);
}

try {
    $db = getDbConnection();
    $stmt = $db->prepare(
        'UPDATE users
         SET password_hash = :password_hash, failed_login_attempts = 0, locked_until = NULL
         WHERE email = :email AND role = :role'
    );
    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'email' => $email,
        'role' => 'admin',
    ]);

    if ($stmt->rowCount() === 0) {
        $check = $db->prepare('SELECT id FROM users WHERE email = :email AND role = :role LIMIT 1');
        $check->execute(['email' => $email, 'role' => 'admin']);
        if (!$check->fetch()) {
            fwrite(STDERR, "No administrator account was found for that email address.\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "Administrator password reset successfully for $email.\n");
} catch (PDOException $exception) {
    error_log('SwiftTrackGo administrator password reset failed: ' . $exception->getMessage());
    fwrite(STDERR, "The password could not be reset. Check the database configuration and try again.\n");
    exit(1);
} finally {
    $password = '';
    putenv('SWIFTTRACKGO_ADMIN_PASSWORD');
}
