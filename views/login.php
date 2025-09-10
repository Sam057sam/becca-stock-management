<?php
require_once __DIR__ . '/../includes/security.php';

start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (is_account_locked($email)) {
        $error = 'This account is temporarily locked. Please try again later.';
    } else {
        if (auth_login($email, $password)) {
            redirect_to_page('dashboard');
            exit;
        }
        $error = 'Invalid email or password';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Becca</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
  <div class="login-card">
    <h1>Becca Stock Management</h1>
    <?php if (is_setup_required()): ?>
      <div class="note">First run detected. Please go to <a href="<?= page_url('setup') ?>">Setup Admin</a> to set the admin password.</div>
    <?php endif; ?>
    <?php if (!empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>