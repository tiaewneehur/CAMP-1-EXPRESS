<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$me = require_admin();

function bucket_of(array $s): string {
    if ($s['status'] === 'th_warehouse') return 'wait';
    $ph = stage_phase($s['status']);
    return $ph === 'done' ? 'done' : ($ph === 'dom' ? 'th' : $ph);
}

$all = q('SELECT s.*, u.name AS cname, u.username AS cuser FROM shipments s JOIN users u ON u.id = s.user_id ORDER BY s.updated_at DESC')->fetchAll();
$counts = ['all' => count($all), 'jp' => 0, 'sea' => 0, 'th' => 0, 'wait' => 0, 'done' => 0];
foreach ($all as $s) $counts[bucket_of($s)]++;

$f = $_GET['f'] ?? 'all'; if (!isset($counts[$f])) $f = 'all';
$qq = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$rows = array_filter($all, function ($s) use ($f, $qq) {
    if ($f !== 'all' && bucket_of($s) !== $f) return false;
    if ($qq === '') return true;
    return str_contains(mb_strtolower(implode(' ', [$s['tn'], $s['cname'], $s['cuser'], $s['container'], $s['item'], $s['vessel'], $s['dom_tracking']])), $qq);
});

page_head(t('adm_h'), 'admin');
?>
<main class="wrap page">
  <div class="page-title">
    <h1><?= e(t('adm_h')) ?></h1>
    <a class="btn btn-orange" href="admin_shipment.php"><?= e(t('new_ship')) ?></a>
  </div>
  <div class="kpis">
    <?php foreach ($counts as $k => $n): ?>
      <a class="card kpi <?= $f === $k ? 'sel' : '' ?>" href="?f=<?= e($k) ?>&q=<?= urlencode($qq) ?>"><span><?= e(t('k_' . $k)) ?></span><b><?= $n ?></b></a>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <form class="tools" method="get">
      <input type="hidden" name="f" value="<?= e($f) ?>">
      <input class="in" name="q" value="<?= e($qq) ?>" placeholder="<?= e(t('search_ph')) ?>">
      <button class="btn btn-dark btn-sm"><?= icon('search') ?> <?= e(t('search')) ?></button>
    </form>
    <div class="tblwrap"><table class="tbl">
      <thead><tr><th><?= e(t('tn')) ?></th><th><?= e(t('col_cust')) ?></th><th><?= e(t('status')) ?></th><th><?= e(t('col_carrier')) ?></th><th><?= e(t('col_upd')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $s):
        $carrier = $s['dom_carrier'] ?: $s['choice_carrier'];
        $needs = $s['status'] === 'th_warehouse' && $s['choice_carrier'] && !$s['dom_carrier']; ?>
        <tr onclick="location='admin_shipment.php?tn=<?= urlencode($s['tn']) ?>'" class="row">
          <td><a class="mono" href="admin_shipment.php?tn=<?= urlencode($s['tn']) ?>"><b><?= e($s['tn']) ?></b></a></td>
          <td><b><?= e($s['cname']) ?></b> <span class="muted small">@<?= e($s['cuser']) ?></span><div class="muted small"><?= e($s['item']) ?></div></td>
          <td><?= status_pill($s['status']) ?></td>
          <td><?= $carrier ? e(carrier_label($carrier)) : '<span class="muted">—</span>' ?><?= $needs ? ' <span class="flag">' . e(t('new')) . '</span>' : '' ?><?= $s['dom_tracking'] ? '<div class="muted small mono">' . e($s['dom_tracking']) . '</div>' : '' ?></td>
          <td class="muted small"><?= e(fmt_dt($s['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (!$rows): ?><div class="empty"><?= e(t('no_rows')) ?></div><?php endif; ?>
  </div>
</main>
<?php page_foot();
