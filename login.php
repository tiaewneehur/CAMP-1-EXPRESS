<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (!tables_exist()) redirect('install.php');
if ($u = current_user()) redirect($u['role'] === 'admin' ? 'admin.php' : 'account.php');

const MAX_ATTEMPTS = 5;
const LOCK_MINUTES = 15;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = (string)($_POST['password'] ?? '');
    $row = q('SELECT * FROM users WHERE username = ?', [$username])->fetch();

    if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
        $error = t('login_locked', (int)ceil((strtotime($row['locked_until']) - time()) / 60));
    } elseif ($row && (int)$row['active'] === 1 && password_verify($password, $row['password_hash'])) {
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        q('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = ? WHERE id = ?', [now(), $row['id']]);
        login_user($row);
        if ((int)$row['must_change'] === 1) redirect('change_password.php');
        redirect($row['role'] === 'admin' ? 'admin.php' : 'account.php');
    } else {
        if ($row) {
            $fails = (int)$row['failed_attempts'] + 1;
            $lock = $fails >= MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null;
            q('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?', [$lock ? 0 : $fails, $lock, $row['id']]);
            if ($lock) { $error = t('login_locked', LOCK_MINUTES); }
        } else {
            password_verify($password, '$2y$10$fsSi.GcvNfyRenDFDAei0OxRkf/2FMnh9nhjy9qJ2S8eMLGjNZ3q.'); // similar timing for unknown users
        }
        $error = $error ?: t('login_fail');
    }
}

page_head(t('login_h'), '', 'auth-page');
?>
<main class="auth">
  <div class="auth-card">
    <img src="assets/logo.jpg" alt="Camp 1 Express" class="auth-logo" width="140" height="140">
    <h1><?= e(t('login_h')) ?></h1>
    <p class="muted"><?= e(t('login_sub')) ?></p>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack" autocomplete="on">
      <?= csrf_field() ?>
      <label><span><?= e(t('username')) ?></span><input class="in" name="username" autocomplete="username" required autofocus value="<?= e(post('username')) ?>"></label>
      <label><span><?= e(t('password')) ?></span><input class="in" type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn btn-orange btn-block"><?= e(t('login_btn')) ?></button>
    </form>
    <p class="muted small" style="margin-top:18px"><?= e(t('login_help', cfg('contact_line'))) ?></p>
  </div>
</main>
<?php page_foot();
