<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = require_login();
$first = (int)$u['must_change'] === 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $new2 = (string)($_POST['new2'] ?? '');
    if (!password_verify($cur, $u['password_hash'])) $error = t('cp_bad_cur');
    elseif (mb_strlen($new) < 8) $error = t('cp_short');
    elseif ($new !== $new2) $error = t('cp_mismatch');
    elseif (hash_equals($cur, $new)) $error = t('cp_same');
    else {
        // bump session_ver: logs out every other device using the old password
        q('UPDATE users SET password_hash = ?, must_change = 0, session_ver = session_ver + 1 WHERE id = ?',
          [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        $fresh = q('SELECT * FROM users WHERE id = ?', [$u['id']])->fetch();
        login_user($fresh);
        flash(t('cp_ok'));
        redirect($fresh['role'] === 'admin' ? 'admin.php' : 'account.php');
    }
}

page_head(t('cp_h'), '', 'auth-page');
?>
<main class="auth">
  <div class="auth-card">
    <div class="auth-ic"><?= icon('lock') ?></div>
    <h1><?= e(t('cp_h')) ?></h1>
    <?php if ($first): ?><div class="flash flash-info"><?= e(t('cp_first')) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="text" name="username" value="<?= e($u['username']) ?>" autocomplete="username" hidden>
      <label><span><?= e(t('cp_cur')) ?></span><input class="in" type="password" name="current" autocomplete="current-password" required></label>
      <label><span><?= e(t('cp_new')) ?> <em class="muted">(<?= e(t('cp_rule')) ?>)</em></span><input class="in" type="password" name="new" minlength="8" autocomplete="new-password" required></label>
      <label><span><?= e(t('cp_new2')) ?></span><input class="in" type="password" name="new2" minlength="8" autocomplete="new-password" required></label>
      <button class="btn btn-orange btn-block"><?= e(t('cp_btn')) ?></button>
    </form>
    <?php if (!$first): ?><p style="margin-top:14px"><a href="<?= $u['role'] === 'admin' ? 'admin.php' : 'account.php' ?>"><?= e(t('back')) ?></a></p><?php endif; ?>
  </div>
</main>
<?php page_foot();
