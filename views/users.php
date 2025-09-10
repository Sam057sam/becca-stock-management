<?php
require_role(['Admin']);
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

// Create / Update user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'Staff');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE name=? LIMIT 1');
    $stmtRole->execute([$role]);
    $roleId = (int)($stmtRole->fetchColumn() ?: 3);
    if ($id) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role_id=?, is_active=?, password_hash=? WHERE id=?');
            $stmt->execute([$name,$email,$roleId,$is_active,$hash,$id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role_id=?, is_active=? WHERE id=?');
            $stmt->execute([$name,$email,$roleId,$is_active,$id]);
        }
    } else {
        $hash = password_hash($password ?: 'password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role_id,is_active) VALUES (?,?,?,?,?)');
        $stmt->execute([$name,$email,$hash,$roleId,$is_active]);
    }
    redirect_to_page('users');
    exit;
}

// Delete
if (($_GET['action'] ?? '') === 'delete') {
    csrf_verify_get();
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
    redirect_to_page('users');
    exit;
}

$roles = $pdo->query('SELECT name FROM roles ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query('SELECT u.*, r.name as role FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id DESC')->fetchAll();
?>

<h2>Users</h2>

<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
  <label>Name<input type="text" name="name" required></label>
  <label>Email<input type="email" name="email" required></label>
  <label>Role
    <select name="role">
      <?php foreach ($roles as $r): ?>
        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Password<input type="password" name="password" placeholder="Leave blank to keep"></label>
  <label class="checkbox"><input type="checkbox" name="is_active" checked> Active</label>
  <button type="submit">Save User</button>
  <a href="?page=users" class="btn-secondary">Clear</a>
  <p class="muted">Tip: Admin can create Managers and Staff.</p>
  </form>

<table>
  <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u['name']) ?></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><?= htmlspecialchars($u['role']) ?></td>
      <td><?= $u['is_active']? 'Active':'Inactive' ?></td>
      <td>
        <a class="danger" href="?page=users&action=delete&id=<?= $u['id'] ?>&amp;_token=<?= csrf_token() ?>" onclick="return confirm('Delete user?')">Delete</a>
      </td>
    </tr>
  <?php endforeach; if (!$users): ?>
    <tr><td colspan="5" class="muted">No users yet</td></tr>
  <?php endif; ?>
</table>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
