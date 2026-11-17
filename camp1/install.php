<?php
/*
 * ติดตั้งครั้งแรก: สร้างตารางฐานข้อมูล + บัญชีแอดมินคนแรก
 * หลังติดตั้งเสร็จ ควรลบไฟล์นี้ออกจากโฮสต์ (ถึงไม่ลบ ไฟล์นี้ก็จะไม่ยอมสร้างแอดมินซ้ำ)
 */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

function create_tables(): void {
    $sqlite = cfg('db_driver') === 'sqlite';
    $id   = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk   = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $dt   = $sqlite ? 'TEXT' : 'DATETIME';
    $stmts = [
        "CREATE TABLE IF NOT EXISTS users (
            id $id,
            username VARCHAR(40) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(16) NOT NULL DEFAULT 'customer',
            name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            contact VARCHAR(255) NOT NULL DEFAULT '',
            must_change TINYINT NOT NULL DEFAULT 1,
            active TINYINT NOT NULL DEFAULT 1,
            session_ver INT NOT NULL DEFAULT 1,
            failed_attempts INT NOT NULL DEFAULT 0,
            locked_until $dt NULL,
            last_login $dt NULL,
            created_at $dt NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS shipments (
            id $id,
            tn VARCHAR(40) NOT NULL UNIQUE,
            user_id $fk NOT NULL,
            item VARCHAR(255) NOT NULL DEFAULT '',
            weight_kg DECIMAL(10,2) NULL,
            boxes INT NULL,
            origin VARCHAR(120) NOT NULL DEFAULT '',
            vessel VARCHAR(120) NOT NULL DEFAULT '',
            container VARCHAR(40) NOT NULL DEFAULT '',
            eta DATE NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'created',
            choice_carrier VARCHAR(32) NOT NULL DEFAULT '',
            choice_name VARCHAR(120) NOT NULL DEFAULT '',
            choice_phone VARCHAR(40) NOT NULL DEFAULT '',
            choice_address TEXT NULL,
            choice_at $dt NULL,
            dom_carrier VARCHAR(32) NOT NULL DEFAULT '',
            dom_tracking VARCHAR(80) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            updated_at $dt NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )$tail",
        "CREATE TABLE IF NOT EXISTS events (
            id $id,
            shipment_id $fk NOT NULL,
            status VARCHAR(32) NOT NULL,
            happened_at $dt NOT NULL,
            location VARCHAR(160) NOT NULL DEFAULT '',
            note VARCHAR(500) NOT NULL DEFAULT '',
            created_at $dt NOT NULL,
            FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
        )$tail",
    ];
    foreach ($stmts as $s) db()->exec($s);
    // indexes
    $idx = [
        'CREATE INDEX idx_ship_user ON shipments(user_id)',
        'CREATE INDEX idx_ship_status ON shipments(status)',
        'CREATE INDEX idx_ev_ship ON events(shipment_id)',
    ];
    foreach ($idx as $s) { try { db()->exec($s); } catch (Throwable $e) { /* already exists */ } }
}

$error = '';
try {
    create_tables();
    $hasAdmin = (bool)q("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetch();
} catch (Throwable $e) {
    $hasAdmin = false;
    $error = 'Database error: ' . $e->getMessage();
}

if (!$error && !$hasAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = post('username'); $pw = (string)($_POST['password'] ?? ''); $name = post('name');
    if (!valid_username($user)) $error = t('bad_username');
    elseif (strlen($pw) < 8) $error = t('cp_short');
    else {
        q('INSERT INTO users (username, password_hash, role, name, must_change, created_at) VALUES (?,?,?,?,0,?)',
          [$user, password_hash($pw, PASSWORD_DEFAULT), 'admin', $name ?: $user, now()]);
        flash('Admin created. Please log in — and delete install.php from your server.');
        redirect('login.php');
    }
}

page_head('Install');
?>
<main class="wrap narrow">
  <div class="card pad" style="margin:40px 0">
    <h1 class="h2">Camp 1 Express — Install</h1>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($hasAdmin): ?>
      <p>✅ ติดตั้งเรียบร้อยแล้ว / Already installed.</p>
      <p class="muted">เพื่อความปลอดภัย กรุณาลบไฟล์ <code>install.php</code> ออกจากโฮสต์ · Please delete <code>install.php</code> from your server.</p>
      <a class="btn btn-orange" href="login.php"><?= e(t('nav_login')) ?></a>
    <?php elseif (!$error || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      <p class="muted">สร้างตารางฐานข้อมูลแล้ว ตั้งบัญชีแอดมินคนแรก · Tables are ready. Create the first admin account.</p>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <label><span><?= e(t('username')) ?></span><input class="in" name="username" required pattern="[A-Za-z0-9._-]{3,40}" value="<?= e(post('username')) ?>"></label>
        <label><span><?= e(t('cust_name')) ?></span><input class="in" name="name" value="<?= e(post('name')) ?>"></label>
        <label><span><?= e(t('password')) ?> (<?= e(t('cp_rule')) ?>)</span><input class="in" type="password" name="password" required minlength="8"></label>
        <button class="btn btn-orange"><?= e(t('create')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</main>
<?php page_foot();
