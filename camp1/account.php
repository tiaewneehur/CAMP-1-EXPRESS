<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$u = require_login();
if ($u['role'] === 'admin') redirect(isset($_GET['tn']) ? 'admin_shipment.php?tn=' . urlencode((string)$_GET['tn']) : 'admin.php');

$tn = strtoupper(trim((string)($_GET['tn'] ?? '')));

/* ---------- detail ---------- */
if ($tn !== '') {
    $s = q('SELECT * FROM shipments WHERE tn = ? AND user_id = ?', [$tn, $u['id']])->fetch();
    if (!$s) { flash(t('not_found'), 'err'); redirect('account.php'); }
    $dispatched = stage_index($s['status']) >= stage_index('dispatched');
    $edit = isset($_GET['edit']) || !$s['choice_carrier'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$dispatched) {
        $car = post('carrier');
        $name = post('name'); $phone = post('phone'); $addr = post('address');
        if (!isset(CARRIERS[$car])) { flash(t('need_carrier'), 'err'); }
        elseif ($car !== 'pickup' && ($name === '' || $phone === '' || $addr === '')) { flash(t('need_addr'), 'err'); }
        else {
            q('UPDATE shipments SET choice_carrier = ?, choice_name = ?, choice_phone = ?, choice_address = ?, choice_at = ?, updated_at = ? WHERE id = ?',
              [$car, mb_substr($name, 0, 120), mb_substr($phone, 0, 40), mb_substr($addr, 0, 1000), now(), now(), $s['id']]);
            flash(t('saved'));
            redirect('account.php?tn=' . urlencode($s['tn']));
        }
        redirect('account.php?tn=' . urlencode($s['tn']) . '&edit=1');
    }

    $events = q('SELECT * FROM events WHERE shipment_id = ? ORDER BY happened_at DESC, id DESC', [$s['id']])->fetchAll();
    $idx = stage_index($s['status']);
    $pct = (int)round($idx / (count(STAGES) - 1) * 100);
    $ph = stage_phase($s['status']);
    $order = array_keys(PHASES);
    $curPh = $ph === 'done' ? 4 : array_search($ph, $order, true);

    page_head($s['tn'], 'account');
    ?>
<main class="wrap page">
  <p><a href="account.php" class="muted"><?= e(t('back')) ?></a></p>
  <div class="card">
    <div class="ship-top">
      <div><div class="muted small"><?= e(t('tn')) ?></div><div class="tn mono"><?= e($s['tn']) ?></div><div style="margin-top:6px"><?= status_pill($s['status']) ?></div></div>
      <div class="right"><div class="muted small"><?= e(t('eta')) ?></div><div class="big"><?= e(fmt_dt($s['eta'], false)) ?></div><div class="muted small"><?= e(t('updated')) ?>: <?= e(fmt_dt($s['updated_at'])) ?></div></div>
    </div>
    <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
    <div class="phases">
      <?php $i = 0; foreach (PHASES as $k => $p): $cls = $i < $curPh ? 'done' : ($i === $curPh ? 'now' : ''); ?>
        <div class="phase <?= $cls ?>"><div class="ph-ic"><?= icon($p['icon']) ?></div><b><?= e(L($p)) ?></b><span><?= $i < $curPh ? '✓' : ($i === $curPh ? e(stage_label($s['status'])) : '') ?></span></div>
      <?php $i++; endforeach; ?>
    </div>
    <div class="kv">
      <div><span><?= e(t('item')) ?></span><b><?= e($s['item'] ?: '—') ?></b></div>
      <div><span><?= e(t('weight')) ?></span><b><?= $s['weight_kg'] !== null ? e(rtrim(rtrim((string)$s['weight_kg'], '0'), '.') . ' ' . t('kg')) : '—' ?><?= $s['boxes'] ? ' · ' . (int)$s['boxes'] . ' ' . e(t('boxes')) : '' ?></b></div>
      <div><span><?= e(t('vessel')) ?></span><b><?= e($s['vessel'] ?: '—') ?></b></div>
      <div><span><?= e(t('container')) ?></span><b class="mono"><?= e($s['container'] ?: '—') ?></b></div>
    </div>
  </div>

  <div class="two">
    <div class="card"><div class="hd"><h3><?= e(t('history')) ?></h3></div><div class="bd">
      <ul class="tl">
      <?php foreach ($events as $ev): ?>
        <li><span class="dot"></span><div><b><?= e(stage_label($ev['status'])) ?></b><div class="meta"><?= e(fmt_dt($ev['happened_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div><?php if ($ev['note']): ?><div class="nt"><?= nl2br(e($ev['note'])) ?></div><?php endif; ?></div></li>
      <?php endforeach; ?>
      </ul>
    </div></div>

    <div>
    <?php if ($dispatched): ?>
      <div class="card"><div class="hd"><h3><?= e(t('dom_h')) ?></h3><?= status_pill($s['status']) ?></div><div class="bd">
        <div class="muted small"><?= e(t('dom_carrier')) ?></div>
        <div class="big"><?= e(carrier_label($s['dom_carrier'] ?: $s['choice_carrier']) ?: '—') ?></div>
        <div class="muted small" style="margin-top:12px"><?= e(t('dom_tn')) ?></div>
        <div class="row-g"><span class="mono big" id="domtn"><?= e($s['dom_tracking'] ?: '—') ?></span>
          <?php if ($s['dom_tracking']): ?><button class="btn btn-ghost btn-sm" type="button" data-copy="domtn" data-done="<?= e(t('copied')) ?>"><?= e(t('copy')) ?></button><?php endif; ?></div>
      </div></div>
    <?php elseif (!$edit): ?>
      <div class="card"><div class="hd"><h3><?= e(t('choose_h')) ?></h3></div><div class="bd">
        <div class="chosen"><div class="muted small"><?= e(t('chosen')) ?></div><div class="big"><?= e(carrier_label($s['choice_carrier'])) ?></div>
          <?php if ($s['choice_carrier'] !== 'pickup'): ?><div style="margin-top:6px"><?= e($s['choice_name']) ?> · <?= e($s['choice_phone']) ?><br><?= nl2br(e($s['choice_address'])) ?></div><?php endif; ?>
          <div class="muted small" style="margin-top:8px"><?= e(t('chosen_note')) ?></div></div>
        <a class="btn btn-ghost btn-sm" style="margin-top:12px" href="account.php?tn=<?= urlencode($s['tn']) ?>&edit=1"><?= e(t('change')) ?></a>
      </div></div>
    <?php else: ?>
      <form class="card" method="post"><?= csrf_field() ?>
        <div class="hd"><h3><?= e(t('choose_h')) ?></h3></div><div class="bd">
        <p class="muted small" style="margin-top:0"><?= e(t('choose_sub')) ?></p>
        <div class="copt">
          <?php foreach (CARRIERS as $k => $c): ?>
            <label><input type="radio" name="carrier" value="<?= e($k) ?>" <?= $s['choice_carrier'] === $k ? 'checked' : '' ?> required><span><?= e(L($c)) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="stack addr">
          <div class="grid2"><label><span><?= e(t('recv_name')) ?></span><input class="in" name="name" value="<?= e($s['choice_name'] ?: $u['name']) ?>"></label>
          <label><span><?= e(t('recv_phone')) ?></span><input class="in" name="phone" value="<?= e($s['choice_phone'] ?: $u['phone']) ?>"></label></div>
          <label><span><?= e(t('recv_addr')) ?></span><textarea class="in" name="address" rows="3"><?= e($s['choice_address']) ?></textarea></label>
        </div>
        <div class="row-g" style="margin-top:12px"><button class="btn btn-orange"><?= e(t('save_choice')) ?></button>
          <?php if ($s['choice_carrier']): ?><a class="btn btn-ghost" href="account.php?tn=<?= urlencode($s['tn']) ?>"><?= e(t('cancel')) ?></a><?php endif; ?></div>
      </div></form>
      <script>(function(){var f=document.querySelector('.copt'),a=document.querySelector('.addr');function s(){var c=f.querySelector('input:checked');a.style.display=c&&c.value==='pickup'?'none':''}f.addEventListener('change',s);s()})();</script>
    <?php endif; ?>
    </div>
  </div>
</main>
<?php
    page_foot();
    exit;
}

/* ---------- list ---------- */
$rows = q('SELECT * FROM shipments WHERE user_id = ? ORDER BY updated_at DESC', [$u['id']])->fetchAll();
$active = array_filter($rows, fn($r) => $r['status'] !== 'delivered');
$done = array_filter($rows, fn($r) => $r['status'] === 'delivered');

function parcel_card(array $s): string {
    $idx = stage_index($s['status']); $pct = (int)round($idx / (count(STAGES) - 1) * 100);
    $carrier = $s['dom_carrier'] ?: $s['choice_carrier'];
    $need = $s['status'] === 'th_warehouse' && !$s['choice_carrier'];
    return '<a class="card parcel" href="account.php?tn=' . urlencode($s['tn']) . '">'
        . '<div class="parcel-top"><span class="mono tn-s">' . e($s['tn']) . '</span>' . status_pill($s['status']) . '</div>'
        . '<div class="parcel-item">' . e($s['item'] ?: '—') . '</div>'
        . '<div class="bar sm"><i style="width:' . $pct . '%"></i></div>'
        . '<div class="parcel-meta"><span>' . icon('clock') . ' ' . e(t('eta')) . ': ' . e(fmt_dt($s['eta'], false)) . '</span>'
        . ($carrier ? '<span>' . icon('truck') . ' ' . e(carrier_label($carrier)) . '</span>' : '')
        . ($need ? '<span class="flag">' . e(t('choose_h')) . '</span>' : '')
        . '</div></a>';
}

page_head(t('nav_account'), 'account');
?>
<main class="wrap page">
  <div class="page-title">
    <div><h1><?= e(t('acc_hi', $u['name'] ?: $u['username'])) ?></h1><p class="muted"><?= e(t('acc_sub')) ?></p></div>
  </div>
  <?php if (!$rows): ?>
    <div class="card empty"><?= icon('box') ?><p><?= e(t('acc_empty')) ?></p></div>
  <?php else: ?>
    <?php if ($active): ?><h2 class="h3"><?= e(t('acc_active')) ?> (<?= count($active) ?>)</h2><div class="parcels"><?php foreach ($active as $s) echo parcel_card($s); ?></div><?php endif; ?>
    <?php if ($done): ?><h2 class="h3" style="margin-top:28px"><?= e(t('acc_done')) ?> (<?= count($done) ?>)</h2><div class="parcels"><?php foreach ($done as $s) echo parcel_card($s); ?></div><?php endif; ?>
  <?php endif; ?>
</main>
<?php page_foot();
