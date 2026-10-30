<?php
declare(strict_types=1);

$remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$passwordHash = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? null;
    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 4096) {
        $error = 'Enter a password between 8 and 4,096 bytes.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $password = '';
        if ($passwordHash === false) {
            http_response_code(500);
            $error = 'Could not create a password hash.';
        }
    }
}

function hashPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local Password Hash Utility</title>
</head>
<body>
    <main>
        <h1>Local Password Hash Utility</h1>
        <p>This page works only from this computer. The password is not saved.</p>
        <form method="post" autocomplete="off">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="8" maxlength="4096" required autofocus>
            <button type="submit">Generate hash</button>
        </form>
        <?php if ($error !== null): ?>
            <p role="alert"><?= hashPageEscape($error) ?></p>
        <?php elseif ($passwordHash !== null): ?>
            <p>Copy this hash into the database password_hash field:</p>
            <textarea rows="4" cols="90" readonly><?= hashPageEscape($passwordHash) ?></textarea>
        <?php endif; ?>
    </main>
</body>
</html>
