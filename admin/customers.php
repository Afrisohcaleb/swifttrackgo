<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$db = getDbConnection();
$search = trim((string)($_GET['search'] ?? ''));
$sql = 'SELECT id, name, email, telephone, address, created_at FROM customers WHERE 1 = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE :search OR email LIKE :search OR telephone LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customers';
require dirname(__DIR__) . '/includes/admin_header.php';
?>
<h1>Customer management</h1>
<form method="get" class="input-row" style="margin:1rem 0">
    <input name="search" placeholder="Search customer name, email, or phone" value="<?= e($search) ?>">
    <button class="button" type="submit">Search</button>
</form>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Telephone</th><th>Address</th><th>Created</th></tr></thead><tbody>
<?php foreach ($customers as $customer): ?><tr>
    <td><?= e($customer['name']) ?></td>
    <td><?= e($customer['email']) ?></td>
    <td><?= e($customer['telephone']) ?></td>
    <td><?= e($customer['address']) ?></td>
    <td><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
</tr><?php endforeach; ?>
<?php if (!$customers): ?><tr><td colspan="5">No customer records match that search.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
