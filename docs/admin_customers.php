<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$me = require_admin();

function site_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/login.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('act');
    if ($act === 'create') {
        $username = post('username'); $name = post('name'); $pw = post('password') ?: gen_password();
        $role = post('role') === 'admin' ? 'admin' : 'customer';
        if (!valid_username($username)) flash(t('bad_username'), 'err');
        elseif ($name === '') flash(t('need_name'), 'err');
        elseif (mb_strlen($pw) < 8) flash(t('cp_short'), 'err');
        elseif (q('SELECT 1 FROM users WHERE username = ?', [$username])->fetch()) flash(t('user_exists'), 'err');
        else {
            q('INSERT INTO users (username, password_hash, role, name, phone, contact, must_change, created_at) VALUES (?,?,?,?,?,?,1,?)',
              [$username, password_hash($pw, PASSWORD_DEFAULT), $role, mb_substr($name, 0, 120), mb_substr(post('phone'), 0, 40), mb_substr(post('contact'), 0, 255), now()]);
            $_SESSION['cred'] = ['title' => t('cust_created'), 'user' => $username, 'pw' => $pw];
        }
        redirect('admin_customers.php');
    }
    $id = (int)post('id');
    $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if (!$target) redirect('admin_customers.php');
    if ($act === 'reset') {
        $pw = gen_password();
        q('UPDATE users SET password_hash = ?, must_change = 1, session_ver = session_ver + 1, failed_attempts = 0, locked_until = NULL WHERE id = ?',
          [password_hash($pw, PASSWORD_DEFAULT), $id]);
        $_SESSION['cred'] = ['title' => t('pw_reset_done'), 'user' => $target['username'], 'pw' => $pw];
    } elseif ($act === 'toggle' && $id !== (int)$me['id']) {
        q('UPDATE users SET active = 1 - active, session_ver = session_ver + 1 WHERE id = ?', [$id]);
        flash(t('saved'));
    } elseif ($act === 'edit') {
        $name = post('name');
        if ($name === '') { flash(t('need_name'), 'err'); redirect('admin_customers.php?edit=' . $id); }
        q('UPDATE users SET name = ?, phone = ?, contact = ? WHERE id = ?', [mb_substr($name, 0, 120), mb_substr(post('phone'), 0, 40), mb_substr(post('contact'), 0, 255), $id]);
        flash(t('saved'));
    }
    redirect('admin_customers.php');
}

$cred = $_SESSION['cred'] ?? null; unset($_SESSION['cred']);
$qq = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$users = q('SELECT u.*, (SELECT COUNT(*) FROM shipments s WHERE s.user_id = u.id) AS n,
                   (SELECT COUNT(*) FROM shipments s WHERE s.user_id = u.id AND s.status <> \'delivered\') AS n_active
            FROM users u ORDER BY u.role DESC, u.created_at DESC')->fetchAll();
if ($qq !== '') $users = array_filter($users, fn($u) => str_contains(mb_strtolower($u['username'] . ' ' . $u['name'] . ' ' . $u['phone'] . ' ' . $u['contact']), $qq));
$editId = (int)($_GET['edit'] ?? 0);

page_head(t('cust_h'), 'customers');
?>
<main class="wrap page">
  <div class="page-title"><h1><?= e(t('cust_h')) ?></h1></div>

  <?php if ($cred): $msg = sprintf(t('cred_msg'), site_url(), $cred['user'], $cred['pw']); ?>
    <div class="card cred">
      <b><?= e($cred['title']) ?></b>
      <textarea id="credmsg" class="in mono" rows="5" readonly><?= e($msg) ?></textarea>
      <button class="btn btn-orange btn-sm" type="button" data-copy="credmsg" data-done="<?= e(t('copied')) ?>"><?= e(t('copy')) ?></button>
    </div>
  <?php endif; ?>

  <div class="adm-grid">
    <form class="card" method="post">
      <?= csrf_field() ?><input type="hidden" name="act" value="create">
      <div class="hd"><h3><?= e(t('cust_new')) ?></h3></div>
      <div class="bd stack">
        <label><span><?= e(t('username')) ?> *</span><input class="in mono" name="username" required pattern="[A-Za-z0-9._\-]{3,40}" placeholder="somchai01"></label>
        <label><span><?= e(t('cust_name')) ?> *</span><input class="in" name="name" required></label>
        <div class="grid2"><label><span><?= e(t('cust_phone')) ?></span><input class="in" name="phone"></label>
        <label><span><?= e(t('role')) ?></span><select class="in" name="role"><option value="customer"><?= e(t('role_customer')) ?></option><option value="admin"><?= e(t('role_admin')) ?></option></select></label></div>
        <label><span><?= e(t('cust_contact')) ?></span><input class="in" name="contact"></label>
        <label><span><?= e(t('temp_pw')) ?></span><input class="in mono" name="password" minlength="8" placeholder="<?= e(t('temp_pw_hint')) ?>"></label>
        <button class="btn btn-orange"><?= e(t('create')) ?></button>
      </div>
    </form>

    <div class="card">
      <form class="tools" method="get"><input class="in" name="q" value="<?= e($qq) ?>" placeholder="<?= e(t('search')) ?>…"><button class="btn btn-dark btn-sm"><?= icon('search') ?></button></form>
      <div class="tblwrap"><table class="tbl">
        <thead><tr><th><?= e(t('username')) ?></th><th><?= e(t('cust_name')) ?></th><th><?= e(t('shipments')) ?></th><th><?= e(t('last_login')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <?php if ($editId === (int)$u['id']): ?>
          <tr><td colspan="5">
            <form method="post" class="inline-edit"><?= csrf_field() ?><input type="hidden" name="act" value="edit"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <b class="mono">@<?= e($u['username']) ?></b>
              <input class="in" name="name" value="<?= e($u['name']) ?>" placeholder="<?= e(t('cust_name')) ?>" required>
              <input class="in" name="phone" value="<?= e($u['phone']) ?>" placeholder="<?= e(t('cust_phone')) ?>">
              <input class="in" name="contact" value="<?= e($u['contact']) ?>" placeholder="<?= e(t('cust_contact')) ?>">
              <button class="btn btn-orange btn-sm"><?= e(t('save')) ?></button><a class="btn btn-ghost btn-sm" href="admin_customers.php"><?= e(t('cancel')) ?></a>
            </form>
          </td></tr>
          <?php else: ?>
          <tr>
            <td><b class="mono"><?= e($u['username']) ?></b>
              <?php if ($u['role'] === 'admin'): ?><span class="pill p-sea"><?= e(t('role_admin')) ?></span><?php endif; ?>
              <?php if ((int)$u['must_change'] === 1): ?><span class="pill p-th"><?= e(t('pw_pending')) ?></span><?php endif; ?>
              <?php if ((int)$u['active'] !== 1): ?><span class="pill p-off"><?= e(t('inactive')) ?></span><?php endif; ?></td>
            <td><a href="?edit=<?= (int)$u['id'] ?>"><?= e($u['name']) ?></a><div class="muted small"><?= e(trim($u['phone'] . ' ' . $u['contact'])) ?></div></td>
            <td><?php if ($u['role'] === 'customer'): ?><a href="admin.php?q=<?= urlencode($u['username']) ?>"><?= (int)$u['n'] ?></a><?= $u['n_active'] ? ' <span class="muted small">(' . (int)$u['n_active'] . ' ' . e(t('acc_active')) . ')</span>' : '' ?><?php else: ?>—<?php endif; ?></td>
            <td class="muted small"><?= $u['last_login'] ? e(fmt_dt($u['last_login'])) : e(t('never')) ?></td>
            <td class="actions">
              <?php if ($u['role'] === 'customer'): ?><a class="btn btn-dark btn-sm" href="admin_shipment.php?customer=<?= (int)$u['id'] ?>" title="<?= e(t('add_for')) ?>">+ <?= e(t('tn')) ?></a><?php endif; ?>
              <form method="post" data-confirm="<?= e(t('reset_pw')) ?> @<?= e($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="act" value="reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-ghost btn-sm"><?= e(t('reset_pw')) ?></button></form>
              <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-ghost btn-sm"><?= e((int)$u['active'] === 1 ? t('disable') : t('enable')) ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if (!$users): ?><div class="empty"><?= e(t('no_rows')) ?></div><?php endif; ?>
    </div>
  </div>
</main>
<?php page_foot();
