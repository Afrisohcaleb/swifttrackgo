<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin('admin');

$db = getDbConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'staff');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Name is required and must be 120 characters or fewer.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Passwords must be at least 8 characters long.';
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Select a valid role.';
    }

    if (!$errors) {
        try {
            $stmt = $db->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active)
                 VALUES (:name, :email, :password_hash, :role, :is_active)'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'is_active' => $isActive,
            ]);
            recordAudit($db, 'user_created', 'user', (int)$db->lastInsertId());
            flash('success', 'User account created.');
            redirect('admin/users.php');
        } catch (PDOException $exception) {
            error_log('SwiftTrackGo user creation failed: ' . $exception->getMessage());
            $errors[] = 'The user could not be created. The email may already be in use.';
        }
    }
}

$users = $db->query(
    'SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 100'
)->fetchAll();

$pageTitle = 'Users';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>User management</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<section class="card" style="margin:1.5rem 0">
    <h2 style="font-size:1.5rem">Create user</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-grid">
            <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="120"></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190"></div>
            <div class="field"><label for="password">Password (8+ chars)</label><input id="password" name="password" type="password" required minlength="8"></div>
            <div class="field"><label for="role">Role</label><select id="role" name="role"><option value="staff">Staff</option><option value="admin">Administrator</option></select></div>
            <div class="field"><label><input type="checkbox" name="is_active" checked> Active account</label></div>
        </div>
        <button class="button" type="submit">Create user</button>
    </form>
</section>
<h2>Accounts</h2>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr>
    <td><?= e($user['name']) ?></td>
    <td><?= e($user['email']) ?></td>
    <td><?= e($user['role']) ?></td>
    <td><?= (int)$user['is_active'] === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Disabled</span>' ?></td>
    <td><?= e(date('M j, Y', strtotime($user['created_at']))) ?></td>
</tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="5">No user accounts yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
