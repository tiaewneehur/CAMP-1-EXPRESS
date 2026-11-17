<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
if (!tables_exist()) redirect('install.php');

$u = current_user();
$rate = (float)cfg('rate_yen', 0.235);
$perKg = (float)cfg('ship_per_kg', 140);
$per100 = (float)cfg('ship_per_100g', 14);
$days = (int)cfg('days_to_receive', 30);
$lineUrl = cfg('contact_line_url');

page_head('', 'home', 'home');
?>
<section class="hero">
  <div class="rays" aria-hidden="true"></div>
  <div class="wrap hero-in">
    <div class="hero-txt">
      <div class="tag"><?= e(t('hero_tag')) ?></div>
      <h1><?= t('hero_h1') ?></h1>
      <p class="lead"><?= e(t('hero_lead')) ?></p>
      <div class="row-g">
        <?php if ($u): ?>
          <a class="btn btn-orange btn-lg" href="<?= $u['role'] === 'admin' ? 'admin.php' : 'account.php' ?>"><?= icon('box') ?> <?= e($u['role'] === 'admin' ? t('nav_admin') : t('nav_account')) ?></a>
        <?php else: ?>
          <a class="btn btn-orange btn-lg" href="login.php"><?= icon('lock') ?> <?= e(t('hero_cta')) ?></a>
        <?php endif; ?>
        <?php if ($lineUrl): ?><a class="btn btn-outline btn-lg" href="<?= e($lineUrl) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> <?= e(t('hero_cta2')) ?></a><?php endif; ?>
      </div>
      <div class="chips">
        <div class="chip"><b><?= e(rtrim(rtrim(number_format($rate, 3), '0'), '.')) ?></b><span><?= e(t('chip_rate')) ?></span></div>
        <div class="chip"><b>฿<?= e(number_format($perKg)) ?></b><span><?= e(t('chip_ship')) ?></span></div>
        <div class="chip"><b>฿<?= e(number_format($per100)) ?></b><span><?= e(t('chip_100g')) ?></span></div>
        <div class="chip"><b><?= $days ?></b><span><?= e(t('chip_days')) ?></span></div>
      </div>
    </div>
    <div class="hero-art">
      <img src="assets/logo.jpg" alt="Camp 1 Express" width="420" height="420">
      <div class="badge b1"><?= icon('check') ?> <?= e(t('chip_nofee')) ?></div>
      <div class="badge b2"><?= icon('ship') ?> <?= e(t('chip_weekly')) ?></div>
    </div>
  </div>
  <div class="stores wrap"><span>Amazon.co.jp</span><span>Yahoo! Auctions</span><span>Mercari</span><span>Rakuten</span><span>+ <?= lang() === 'th' ? 'ทุกเว็บญี่ปุ่น' : 'any Japanese site' ?></span></div>
</section>

<section class="blk">
  <div class="wrap">
    <div class="eyebrow"><?= e(t('svc_eb')) ?></div>
    <h2 class="h2"><?= e(t('svc_h')) ?></h2>
    <div class="services">
      <div class="card svc"><div class="sic"><?= icon('cart') ?></div><h3><?= e(t('s1h')) ?></h3><p><?= e(t('s1p')) ?></p></div>
      <div class="card svc"><div class="sic"><?= icon('search') ?></div><h3><?= e(t('s2h')) ?></h3><p><?= e(t('s2p')) ?></p></div>
      <div class="card svc"><div class="sic"><?= icon('ship') ?></div><h3><?= e(t('s3h')) ?></h3><p><?= e(t('s3p')) ?></p></div>
      <div class="card svc"><div class="sic"><?= icon('truck') ?></div><h3><?= e(t('s4h')) ?></h3><p><?= e(t('s4p')) ?></p></div>
    </div>
  </div>
</section>

<section class="blk dark">
  <div class="rays soft" aria-hidden="true"></div>
  <div class="wrap" style="position:relative">
    <div class="eyebrow"><?= e(t('how_eb')) ?></div>
    <h2 class="h2"><?= e(t('how_h')) ?></h2>
    <div class="steps">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <div class="step"><div class="n"><?= $i ?></div><div><h4><?= e(t("h{$i}h")) ?></h4><p><?= e(t("h{$i}p", (string)$days)) ?></p></div></div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<section class="blk" id="rates">
  <div class="wrap">
    <div class="eyebrow"><?= e(t('rates_eb')) ?></div>
    <h2 class="h2"><?= e(t('rates_h')) ?></h2>
    <div class="rates">
      <div class="rate-cards">
        <div class="card rate"><span><?= e(t('rate_yen_l')) ?></span><b><?= e(rtrim(rtrim(number_format($rate, 3), '0'), '.')) ?></b><em>¥1 = ฿<?= e(rtrim(rtrim(number_format($rate, 3), '0'), '.')) ?></em></div>
        <div class="card rate hot"><span><?= e(t('rate_kg_l')) ?></span><b>฿<?= e(number_format($perKg)) ?></b><em>/ kg</em></div>
        <div class="card rate"><span><?= e(t('rate_100g_l')) ?></span><b>฿<?= e(number_format($per100)) ?></b><em>/ 100 g</em></div>
      </div>
      <div class="card calc">
        <div class="hd"><h3><?= icon('yen') ?> <?= e(t('calc_h')) ?></h3></div>
        <div class="bd">
          <div class="grid2">
            <label><span><?= e(t('calc_price')) ?></span><input class="in" type="number" min="0" id="cy" placeholder="5000"></label>
            <label><span><?= e(t('calc_weight')) ?></span><input class="in" type="number" min="0" id="cw" placeholder="800"></label>
          </div>
          <div class="calc-out">
            <div><span><?= e(t('calc_goods')) ?></span><b id="og">฿0</b></div>
            <div><span><?= e(t('calc_ship')) ?></span><b id="os">฿0</b></div>
            <div class="tot"><span><?= e(t('calc_total')) ?></span><b id="ot">฿0</b></div>
          </div>
          <p class="muted small"><?= e(t('calc_note')) ?></p>
        </div>
      </div>
    </div>

    <h3 class="h3" style="margin-top:44px"><?= e(t('bulk_h')) ?></h3>
    <p class="muted"><?= e(t('bulk_p')) ?></p>
    <div class="bulk">
      <?php foreach ($BULK_PRICES as [$name, $price, $note]): ?>
        <div class="bulk-item"><div><b><?= e(L($name)) ?></b><?php if ($note): ?><small><?= e(L($note)) ?></small><?php endif; ?></div><span>฿<?= e($price) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="blk cream" id="items">
  <div class="wrap">
    <div class="eyebrow"><?= e(t('items_eb')) ?></div>
    <h2 class="h2"><?= e(t('items_h')) ?></h2>
    <div class="items">
      <?php foreach (['🔋' => 'it1', '👜' => 'it2', '🍘' => 'it3', '📚' => 'it4', '💄' => 'it5', '🌸' => 'it6', '🏍️' => 'it7', '🎸' => 'it8'] as $emo => $k): ?>
        <div class="item"><span class="emo"><?= $emo ?></span><?= e(t($k)) ?></div>
      <?php endforeach; ?>
    </div>
    <h3 class="h3" style="margin-top:44px"><?= e(t('gallery_h')) ?></h3>
    <div class="gallery">
      <figure><img src="assets/warehouse.jpg" alt="<?= e(t('gal1')) ?>" loading="lazy"><figcaption><?= e(t('gal1')) ?></figcaption></figure>
      <figure><img src="assets/container.jpg" alt="<?= e(t('gal2')) ?>" loading="lazy"><figcaption><?= e(t('gal2')) ?></figcaption></figure>
    </div>
  </div>
</section>

<section class="blk" id="track">
  <div class="wrap track-cta card">
    <div class="sic big"><?= icon('box') ?></div>
    <div><div class="eyebrow"><?= e(t('track_eb')) ?></div><h2 class="h2" style="margin-top:4px"><?= e(t('track_h')) ?></h2><p class="muted"><?= e(t('track_p')) ?></p></div>
    <a class="btn btn-orange btn-lg" href="<?= $u ? ($u['role'] === 'admin' ? 'admin.php' : 'account.php') : 'login.php' ?>"><?= e($u ? t('nav_account') : t('nav_login')) ?></a>
  </div>
</section>

<section class="blk dark" id="contact">
  <div class="rays soft" aria-hidden="true"></div>
  <div class="wrap contact" style="position:relative">
    <div>
      <div class="eyebrow"><?= e(t('ct_eb')) ?></div>
      <h2 class="h2"><?= e(t('ct_h')) ?></h2>
      <p class="lead-s"><?= e(t('slogan')) ?></p>
    </div>
    <div class="ct-list">
      <div class="ct"><?= icon('phone') ?><div><span><?= e(t('ct_phone')) ?></span><b><?= e(cfg('contact_phone')) ?></b></div></div>
      <div class="ct"><?= icon('chat') ?><div><span><?= e(t('ct_line')) ?></span><b><?php if ($lineUrl): ?><a href="<?= e($lineUrl) ?>" target="_blank" rel="noopener"><?= e(cfg('contact_line')) ?></a><?php else: ?><?= e(cfg('contact_line')) ?><?php endif; ?></b></div></div>
      <div class="ct"><?= icon('user') ?><div><span><?= e(t('ct_fb')) ?></span><b><?php if (cfg('contact_facebook_url')): ?><a href="<?= e(cfg('contact_facebook_url')) ?>" target="_blank" rel="noopener"><?= e(cfg('contact_facebook')) ?></a><?php else: ?><?= e(cfg('contact_facebook')) ?><?php endif; ?></b></div></div>
    </div>
  </div>
</section>

<script>
(function(){
  var R=<?= json_encode($rate) ?>,P=<?= json_encode($per100) ?>;
  var y=document.getElementById('cy'),w=document.getElementById('cw');
  function f(n){return '฿'+Math.round(n).toLocaleString()}
  function go(){var yen=+y.value||0,g=+w.value||0;var goods=yen*R;var ship=g>0?Math.max(1,Math.ceil(g/100))*P:0;
    document.getElementById('og').textContent=f(goods);document.getElementById('os').textContent=f(ship);document.getElementById('ot').textContent=f(goods+ship)}
  y.addEventListener('input',go);w.addEventListener('input',go);
})();
</script>
<?php page_foot();
