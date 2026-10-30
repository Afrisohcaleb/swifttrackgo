<?php
require_once dirname(__DIR__) . '/includes/functions.php';
if (!empty($_SESSION['user'])) redirect('admin/index.php');
$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $attempts = (int)($_SESSION['login_attempts'] ?? 0);
    if ($attempts >= 10) {
        $error = 'Too many sign-in attempts. Wait a while and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Email or password is incorrect.';
    } else {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT id, name, email, password_hash, role, is_active, failed_login_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            $locked = $user && $user['locked_until'] !== null && strtotime($user['locked_until']) > time();
            if (!$user || !$user['is_active'] || $locked || !password_verify($password, $user['password_hash'])) {
                if ($user && !$locked) {
                    $failures = (int)$user['failed_login_attempts'] + 1;
                    $lockUntil = $failures >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                    $update = $db->prepare('UPDATE users SET failed_login_attempts = :failures, locked_until = :locked_until WHERE id = :id');
                    $update->execute(['failures' => $lockUntil ? 0 : $failures, 'locked_until' => $lockUntil, 'id' => $user['id']]);
                }
                $_SESSION['login_attempts'] = $attempts + 1;
                $error = 'Email or password is incorrect.';
            } else {
                $update = $db->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
                $update->execute(['id' => $user['id']]);
                session_regenerate_id(true);
                unset($user['password_hash'], $user['failed_login_attempts'], $user['locked_until'], $user['is_active']);
                $_SESSION['user'] = $user;
                unset($_SESSION['login_attempts']);
                recordAudit($db, 'admin_login', 'user', (int)$user['id']);
                redirect('admin/index.php');
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('SwiftTrackGo admin login failed: ' . $exception->getMessage());
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}
$pageTitle = 'Administrator sign in';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<section class="section" style="max-width:520px;margin:auto">
    <div class="card">
        <h1 style="font-size:2rem">Administrator sign in</h1>
        <p class="muted">Authorized staff only.</p>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="username" value="<?= e($email) ?>"></div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
            <button class="button" type="submit">Sign in</button>
        </form>
    </div>
</section>
</main></body></html>
