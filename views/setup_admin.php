<?php
start_session();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? 'admin@example.com');
    $name = trim($_POST['name'] ?? 'Administrator');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if ($password && $password === $confirm) {
        $pdo = get_db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try {
            // ensure admin role exists
            $roleId = (int)$pdo->query("SELECT id FROM roles WHERE name='Admin'")->fetchColumn();
            // upsert user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $id = $stmt->fetchColumn();
            if ($id) {
                $stmt = $pdo->prepare("UPDATE users SET name=?, password_hash=?, role_id=?, is_active=1 WHERE id=?");
                $stmt->execute([$name, $hash, $roleId, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role_id,is_active) VALUES (?,?,?,?,1)");
                $stmt->execute([$name,$email,$hash,$roleId]);
            }
            $pdo->commit();
            $msg = 'Admin credentials saved. You can now login.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $msg = 'Setup failed: ' . $e->getMessage();
        }
    } else {
        $msg = 'Passwords do not match.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup Admin - Becca</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
  <div class="login-card">
    <h1>First-time Setup</h1>
    <p>Create your Admin account.</p>
    <?php if ($msg): ?><div class="note"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Admin Name</label>
      <input type="text" name="name" required value="Administrator">
      <label>Admin Email</label>
      <input type="email" name="email" required value="admin@example.com">
      <label>New Password</label>
      <input type="password" name="password" required>
      <label>Confirm Password</label>
      <input type="password" name="confirm" required>
      <button type="submit">Save Admin</button>
      <a class="btn-secondary" href="<?= page_url('login') ?>">Back to Login</a>
    </form>
  </div>
</body>
</html>
