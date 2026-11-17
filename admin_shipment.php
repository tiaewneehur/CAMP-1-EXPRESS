<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
$me = require_admin();

const ORIGINS = ['Osaka', 'Tokyo', 'Nagoya', 'Yokohama', 'Kobe', 'Fukuoka', 'Sapporo', 'Hiroshima'];
$customers = q("SELECT id, username, name FROM users WHERE role = 'customer' ORDER BY name")->fetchAll();
$tn = strtoupper(trim((string)($_GET['tn'] ?? '')));
$s = $tn !== '' ? q('SELECT * FROM shipments WHERE tn = ?', [$tn])->fetch() : null;
if ($tn !== '' && !$s) { flash(t('not_found'), 'err'); redirect('admin.php'); }

function num_or_null(string $v) { return $v === '' ? null : (float)$v; }
function date_or_null(string $v) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null; }
function dt_from_input(string $v): string {
    $ts = strtotime($v);
    if (!$ts || date('Y-m-d H:i', $ts) === date('Y-m-d H:i')) return now(); // untouched default -> exact time now
    return date('Y-m-d H:i:s', $ts);
}
function cust_exists(int $id): bool { return (bool)q("SELECT 1 FROM users WHERE id = ? AND role = 'customer'", [$id])->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('act');

    if ($act === 'create') {
        $cid = (int)post('user_id');
        $newTn = strtoupper(preg_replace('/\s+/', '', post('tn')));
        $back = 'admin_shipment.php?customer=' . $cid;
        if (!cust_exists($cid)) { flash(t('need_customer'), 'err'); redirect($back); }
        if ($newTn !== '' && !preg_match('/^[A-Z0-9-]{4,40}$/', $newTn)) { flash(t('bad_tn'), 'err'); redirect($back); }
        if ($newTn !== '' && q('SELECT 1 FROM shipments WHERE tn = ?', [$newTn])->fetch()) { flash(t('tn_exists'), 'err'); redirect($back); }
        $newTn = $newTn ?: gen_tn();
        $origin = in_array(post('origin'), ORIGINS, true) ? post('origin') . ', Japan' : 'Japan';
        $status = isset(STAGES[post('status')]) ? post('status') : 'created';
        $ts = now();
        db()->beginTransaction();
        q('INSERT INTO shipments (tn, user_id, item, weight_kg, boxes, origin, vessel, container, eta, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
          [$newTn, $cid, mb_substr(post('item'), 0, 255), num_or_null(post('weight')), post('boxes') === '' ? null : (int)post('boxes'),
           $origin, '', '', date_or_null(post('eta')), $status, $ts, $ts]);
        $sid = (int)db()->lastInsertId();
        q('INSERT INTO events (shipment_id, status, happened_at, location, note, created_at) VALUES (?,?,?,?,?,?)', [$sid, 'created', $ts, $origin, '', $ts]);
        if ($status !== 'created') {
            q('INSERT INTO events (shipment_id, status, happened_at, location, note, created_at) VALUES (?,?,?,?,?,?)', [$sid, $status, $ts, default_location($status, $origin), '', $ts]);
        }
        db()->commit();
        flash(t('ship_created') . ': ' . $newTn);
        redirect('admin_shipment.php?tn=' . urlencode($newTn));
    }

    if (!$s) redirect('admin.php');
    $self = 'admin_shipment.php?tn=' . urlencode($s['tn']);

    switch ($act) {
        case 'event':
            $st = post('status');
            if (isset(STAGES[$st])) {
                q('INSERT INTO events (shipment_id, status, happened_at, location, note, created_at) VALUES (?,?,?,?,?,?)',
                  [$s['id'], $st, dt_from_input(post('at')), mb_substr(post('location') ?: default_location($st, $s['origin']), 0, 160), mb_substr(post('note'), 0, 500), now()]);
                recompute_status((int)$s['id']);
                flash(t('saved'));
            }
            break;
        case 'del_event':
            $n = (int)q('SELECT COUNT(*) AS c FROM events WHERE shipment_id = ?', [$s['id']])->fetch()['c'];
            if ($n > 1) {
                q('DELETE FROM events WHERE id = ? AND shipment_id = ?', [(int)post('event_id'), $s['id']]);
                recompute_status((int)$s['id']);
                flash(t('deleted'));
            }
            break;
        case 'info':
            $cid = (int)post('user_id');
            if (!cust_exists($cid)) $cid = (int)$s['user_id'];
            q('UPDATE shipments SET user_id = ?, item = ?, weight_kg = ?, boxes = ?, eta = ?, vessel = ?, container = ?, updated_at = ? WHERE id = ?',
              [$cid, mb_substr(post('item'), 0, 255), num_or_null(post('weight')), post('boxes') === '' ? null : (int)post('boxes'),
               date_or_null(post('eta')), mb_substr(post('vessel'), 0, 120), strtoupper(mb_substr(post('container'), 0, 40)), now(), $s['id']]);
            flash(t('saved'));
            break;
        case 'dom':
            $car = post('carrier');
            q('UPDATE shipments SET dom_carrier = ?, dom_tracking = ?, updated_at = ? WHERE id = ?',
              [isset(CARRIERS[$car]) ? $car : '', mb_substr(post('dom_tracking'), 0, 80), now(), $s['id']]);
            flash(t('saved'));
            break;
        case 'delete':
            q('DELETE FROM events WHERE shipment_id = ?', [$s['id']]);
            q('DELETE FROM shipments WHERE id = ?', [$s['id']]);
            flash(t('deleted'));
            redirect('admin.php');
    }
    redirect($self);
}

/* ---------- NEW ---------- */
if (!$s) {
    $pre = (int)($_GET['customer'] ?? 0);
    page_head(t('ship_new_h'), 'admin');
    ?>
<main class="wrap page narrow">
  <p><a class="muted" href="admin.php"><?= e(t('back')) ?></a></p>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="create">
    <div class="hd"><h3><?= e(t('ship_new_h')) ?></h3></div>
    <div class="bd stack">
      <label><span><?= e(t('customer')) ?> *</span>
        <select class="in" name="user_id" required><option value=""><?= e(t('choose_customer')) ?></option>
        <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $pre === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (@<?= e($c['username']) ?>)</option><?php endforeach; ?>
        </select>
        <?php if (!$customers): ?><em class="muted small"><a href="admin_customers.php"><?= e(t('cust_new')) ?> →</a></em><?php endif; ?>
      </label>
      <label><span><?= e(t('tn')) ?></span><input class="in mono" name="tn" placeholder="<?= e(t('tn_auto')) ?>"></label>
      <label><span><?= e(t('item_desc')) ?></span><input class="in" name="item"></label>
      <div class="grid3">
        <label><span><?= e(t('weight_kg')) ?></span><input class="in" type="number" step="0.01" min="0" name="weight"></label>
        <label><span><?= e(t('boxes')) ?></span><input class="in" type="number" min="1" name="boxes" value="1"></label>
        <label><span><?= e(t('origin_city')) ?></span><select class="in" name="origin"><?php foreach (ORIGINS as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="grid2">
        <label><span><?= e(t('first_status')) ?></span><select class="in" name="status"><?php foreach (array_slice(STAGES, 0, 4, true) as $k => $st): ?><option value="<?= e($k) ?>"><?= e(L($st)) ?></option><?php endforeach; ?></select></label>
        <label><span><?= e(t('eta')) ?></span><input class="in" type="date" name="eta"></label>
      </div>
      <button class="btn btn-orange"><?= e(t('create')) ?></button>
    </div>
  </form>
</main>
<?php
    page_foot(); exit;
}

/* ---------- EDIT ---------- */
$events = q('SELECT * FROM events WHERE shipment_id = ? ORDER BY happened_at DESC, id DESC', [$s['id']])->fetchAll();
$owner = q('SELECT * FROM users WHERE id = ?', [$s['user_id']])->fetch();
$keys = array_keys(STAGES);
$next = $keys[min(stage_index($s['status']) + 1, count($keys) - 1)];
page_head($s['tn'], 'admin');
?>
<main class="wrap page">
  <p><a class="muted" href="admin.php"><?= e(t('back')) ?></a></p>
  <div class="page-title">
    <div><div class="muted small"><?= e(t('ship_edit_h')) ?></div><h1 class="mono"><span id="tnv"><?= e($s['tn']) ?></span> <button class="btn btn-ghost btn-sm" type="button" data-copy="tnv" data-done="<?= e(t('copied')) ?>"><?= e(t('copy')) ?></button></h1></div>
    <div class="right"><?= status_pill($s['status']) ?><div class="muted small" style="margin-top:6px"><?= e(t('customer')) ?>: <b><?= e($owner['name'] ?? '') ?></b> @<?= e($owner['username'] ?? '') ?></div></div>
  </div>

  <div class="two">
    <div class="stackx">
      <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="event">
        <div class="hd"><h3><?= e(t('add_event')) ?></h3></div>
        <div class="bd stack">
          <div class="grid2">
            <label><span><?= e(t('new_status')) ?></span><select class="in" name="status">
              <?php $i = 1; foreach (STAGES as $k => $st): ?><option value="<?= e($k) ?>" <?= $k === $next ? 'selected' : '' ?>><?= $i++ ?>. <?= e(L($st)) ?></option><?php endforeach; ?>
            </select></label>
            <label><span><?= e(t('when')) ?></span><input class="in" type="datetime-local" name="at" value="<?= date('Y-m-d\TH:i') ?>"></label>
          </div>
          <label><span><?= e(t('location')) ?></span><input class="in" name="location" placeholder="<?= e(default_location($next, $s['origin'])) ?>"></label>
          <label><span><?= e(t('note_l')) ?></span><input class="in" name="note"></label>
          <div><button class="btn btn-orange"><?= e(t('add_ev_btn')) ?></button></div>
        </div>
      </form>

      <div class="card"><div class="hd"><h3><?= e(t('history')) ?></h3></div><div class="bd">
        <ul class="tl">
        <?php foreach ($events as $ev): ?>
          <li><span class="dot"></span><div class="tl-row"><div><b><?= e(stage_label($ev['status'])) ?></b><div class="meta"><?= e(fmt_dt($ev['happened_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></div><?php if ($ev['note']): ?><div class="nt"><?= nl2br(e($ev['note'])) ?></div><?php endif; ?></div>
            <?php if (count($events) > 1): ?><form method="post" data-confirm="<?= e(t('del_ev')) ?>?"><?= csrf_field() ?><input type="hidden" name="act" value="del_event"><input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>"><button class="linkbtn"><?= e(t('del_ev')) ?></button></form><?php endif; ?>
          </div></li>
        <?php endforeach; ?>
        </ul>
      </div></div>
    </div>

    <div class="stackx">
      <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="dom">
        <div class="hd"><h3><?= e(t('dom_h')) ?></h3></div>
        <div class="bd stack">
          <?php if ($s['choice_carrier']): ?>
            <div class="req-box"><b><?= e(t('cust_req')) ?>:</b> <?= e(carrier_label($s['choice_carrier'])) ?>
              <?php if ($s['choice_carrier'] !== 'pickup'): ?><br><?= e($s['choice_name']) ?> · <?= e($s['choice_phone']) ?><br><?= nl2br(e($s['choice_address'])) ?><?php endif; ?>
              <div class="muted small"><?= e(fmt_dt($s['choice_at'])) ?></div></div>
          <?php else: ?><div class="muted"><?= e(t('no_req')) ?></div><?php endif; ?>
          <div class="grid2">
            <label><span><?= e(t('dom_carrier')) ?></span><select class="in" name="carrier"><option value="">—</option>
              <?php foreach (CARRIERS as $k => $c): ?><option value="<?= e($k) ?>" <?= ($s['dom_carrier'] ?: $s['choice_carrier']) === $k ? 'selected' : '' ?>><?= e(L($c)) ?></option><?php endforeach; ?>
            </select></label>
            <label><span><?= e(t('dom_tn')) ?></span><input class="in mono" name="dom_tracking" value="<?= e($s['dom_tracking']) ?>"></label>
          </div>
          <div><button class="btn btn-dark"><?= e(t('dom_set')) ?></button></div>
        </div>
      </form>

      <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="info">
        <div class="hd"><h3><?= e(t('ship_info')) ?></h3></div>
        <div class="bd stack">
          <label><span><?= e(t('move_customer')) ?></span><select class="in" name="user_id">
            <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$s['user_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (@<?= e($c['username']) ?>)</option><?php endforeach; ?>
          </select></label>
          <label><span><?= e(t('item_desc')) ?></span><input class="in" name="item" value="<?= e($s['item']) ?>"></label>
          <div class="grid3">
            <label><span><?= e(t('weight_kg')) ?></span><input class="in" type="number" step="0.01" min="0" name="weight" value="<?= e($s['weight_kg']) ?>"></label>
            <label><span><?= e(t('boxes')) ?></span><input class="in" type="number" min="1" name="boxes" value="<?= e($s['boxes']) ?>"></label>
            <label><span><?= e(t('eta')) ?></span><input class="in" type="date" name="eta" value="<?= e($s['eta']) ?>"></label>
          </div>
          <div class="grid2">
            <label><span><?= e(t('vessel')) ?></span><input class="in" name="vessel" value="<?= e($s['vessel']) ?>"></label>
            <label><span><?= e(t('container')) ?></span><input class="in mono" name="container" value="<?= e($s['container']) ?>"></label>
          </div>
          <div><button class="btn btn-dark"><?= e(t('save')) ?></button></div>
        </div>
      </form>

      <form method="post" data-confirm="<?= e(t('del_confirm')) ?>" style="text-align:center"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><button class="linkbtn"><?= e(t('del_ship')) ?></button></form>
    </div>
  </div>
</main>
<?php page_foot();
