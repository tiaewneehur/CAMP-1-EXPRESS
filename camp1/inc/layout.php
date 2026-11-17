<?php
function page_head(string $title, string $active = '', string $bodyClass = ''): void {
    $u = current_user();
    $site = cfg('site_name', 'Camp 1 Express');
    ?><!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ? "$title · $site" : $site) ?></title>
<meta name="description" content="<?= e(t('hero_lead')) ?>">
<link rel="icon" href="assets/logo-sm.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@500;600;700;800&family=IBM+Plex+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="nav">
  <div class="wrap nav-in">
    <a class="brand" href="index.php"><img src="assets/logo-sm.jpg" alt="" width="42" height="42"><span><b>CAMP 1</b> EXPRESS<small>クタホマ · JAPAN → THAILAND</small></span></a>
    <input type="checkbox" id="navtog" hidden><label for="navtog" class="burger" aria-label="menu"><i></i><i></i><i></i></label>
    <nav class="links">
      <?php if ($u && $u['role'] === 'admin'): ?>
        <a href="admin.php" class="<?= $active === 'admin' ? 'on' : '' ?>"><?= e(t('nav_shipments')) ?></a>
        <a href="admin_customers.php" class="<?= $active === 'customers' ? 'on' : '' ?>"><?= e(t('nav_customers')) ?></a>
        <a href="index.php" class="<?= $active === 'home' ? 'on' : '' ?>"><?= e(t('nav_home')) ?></a>
      <?php elseif ($u): ?>
        <a href="index.php" class="<?= $active === 'home' ? 'on' : '' ?>"><?= e(t('nav_home')) ?></a>
        <a href="account.php" class="<?= $active === 'account' ? 'on' : '' ?>"><?= e(t('nav_account')) ?></a>
      <?php else: ?>
        <a href="index.php#rates"><?= e(t('nav_rates')) ?></a>
        <a href="index.php#items"><?= e(t('nav_items')) ?></a>
        <a href="index.php#contact"><?= e(t('nav_contact')) ?></a>
      <?php endif; ?>
      <span class="lang"><a href="<?= e(lang_url('th')) ?>" class="<?= lang() === 'th' ? 'on' : '' ?>">TH</a><a href="<?= e(lang_url('en')) ?>" class="<?= lang() === 'en' ? 'on' : '' ?>">EN</a></span>
      <?php if ($u): ?>
        <span class="who"><?= icon('user') ?> <?= e($u['username']) ?>
          <span class="menu"><a href="change_password.php"><?= e(t('nav_password')) ?></a><a href="logout.php"><?= e(t('nav_logout')) ?></a></span>
        </span>
      <?php else: ?>
        <a class="btn btn-orange btn-sm" href="login.php"><?= icon('lock') ?> <?= e(t('nav_login')) ?></a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php
    $fl = flash();
    if ($fl) {
        echo '<div class="wrap flashes">';
        foreach ($fl as [$type, $msg]) echo '<div class="flash flash-' . e($type) . '">' . nl2br(e($msg)) . '</div>';
        echo '</div>';
    }
}

function page_foot(): void { ?>
<footer class="foot">
  <div class="wrap foot-in">
    <div class="foot-brand"><img src="assets/logo-sm.jpg" alt="" width="48" height="48"><div><b>CAMP 1 EXPRESS</b><br><span><?= e(t('slogan')) ?></span></div></div>
    <div class="foot-ct">
      <span><?= icon('phone') ?> <?= e(cfg('contact_phone')) ?></span>
      <span><?= icon('chat') ?> LINE <?= e(cfg('contact_line')) ?></span>
    </div>
    <div class="foot-copy">© <?= date('Y') ?> Camp 1 Express · <?= e(t('footer')) ?></div>
  </div>
</footer>
<script>
document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){
  var el=document.getElementById(b.dataset.copy);var txt=el.value!==undefined?el.value:el.textContent;
  (navigator.clipboard?navigator.clipboard.writeText(txt):Promise.reject()).then(function(){b.textContent=b.dataset.done||'✓'}).catch(function(){if(el.select){el.select();document.execCommand('copy');b.textContent=b.dataset.done||'✓'}});
})});
document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(ev){if(!confirm(f.dataset.confirm))ev.preventDefault()})});
</script>
</body>
</html>
<?php }
