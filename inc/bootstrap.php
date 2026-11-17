<?php
declare(strict_types=1);

/* ---------- config ---------- */
$cfgFile = dirname(__DIR__) . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('Missing config.php — copy config.sample.php to config.php and fill in your database details.');
}
$CONFIG = require $cfgFile;
date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Bangkok');

function cfg(string $k, $default = null) { global $CONFIG; return $CONFIG[$k] ?? $default; }

/* ---------- session ---------- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('c1sess');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ---------- database ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    if (cfg('db_driver') === 'sqlite') {
        $pdo = new PDO('sqlite:' . cfg('sqlite_path'), null, null, $opt);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', cfg('db_host'), cfg('db_name'));
        $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), $opt);
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function now(): string { return date('Y-m-d H:i:s'); }
function tables_exist(): bool {
    try { q('SELECT 1 FROM users LIMIT 1'); return true; } catch (Throwable $e) { return false; }
}

/* ---------- language ---------- */
require __DIR__ . '/lang.php';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['th', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
function lang(): string { return $_SESSION['lang'] ?? cfg('default_lang', 'th'); }
function t(string $k, ...$args): string {
    global $LANG;
    $s = $LANG[lang()][$k] ?? $LANG['th'][$k] ?? $k;
    return $args ? vsprintf($s, $args) : $s;
}
function L($v): string { return is_array($v) ? ($v[lang()] ?? $v['th']) : (string)$v; }

/* ---------- helpers ---------- */
function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function lang_url(string $l): string {
    $qs = $_GET; $qs['lang'] = $l;
    return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') . '?' . http_build_query($qs);
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400); exit('Invalid form token. Please go back and try again.');
    }
}
csrf_check();

/* ---------- auth ---------- */
function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $row = q('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']])->fetch();
        if ($row && (int)$row['session_ver'] === (int)($_SESSION['sver'] ?? -1)) $u = $row;
        else { unset($_SESSION['uid'], $_SESSION['sver']); }
    }
    return $u;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { redirect('login.php'); }
    $page = basename($_SERVER['SCRIPT_NAME']);
    if ((int)$u['must_change'] === 1 && $page !== 'change_password.php' && $page !== 'logout.php') {
        redirect('change_password.php');
    }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); redirect('account.php'); }
    return $u;
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['sver'] = (int)$u['session_ver'];
}
function gen_password(int $len = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $p = ''; for ($i = 0; $i < $len; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}
function valid_username(string $u): bool { return (bool)preg_match('/^[A-Za-z0-9._-]{3,40}$/', $u); }

/* ---------- reference data ---------- */
const STAGES = [
    'created'      => ['ph' => 'jp',  'th' => 'รับข้อมูลสินค้า',              'en' => 'Shipment registered'],
    'jp_warehouse' => ['ph' => 'jp',  'th' => 'ถึงโกดังญี่ปุ่น',              'en' => 'Arrived at Japan warehouse'],
    'jp_truck'     => ['ph' => 'jp',  'th' => 'ขนส่งด้วยรถบรรทุกไปท่าเรือ',    'en' => 'Trucking to Japan port'],
    'jp_port'      => ['ph' => 'jp',  'th' => 'ถึงท่าเรือญี่ปุ่น / รอขึ้นเรือ',  'en' => 'At Japan port / awaiting loading'],
    'at_sea'       => ['ph' => 'sea', 'th' => 'อยู่ระหว่างขนส่งทางเรือ',        'en' => 'In transit by sea'],
    'th_port'      => ['ph' => 'th',  'th' => 'ถึงท่าเรือไทย',                 'en' => 'Arrived at Thai port'],
    'customs'      => ['ph' => 'th',  'th' => 'อยู่ระหว่างพิธีการศุลกากร',       'en' => 'Customs clearance'],
    'th_warehouse' => ['ph' => 'th',  'th' => 'ถึงโกดังไทย — รอเลือกขนส่ง',    'en' => 'At Thai warehouse — choose carrier'],
    'dispatched'   => ['ph' => 'dom', 'th' => 'ส่งต่อให้ขนส่งในประเทศแล้ว',    'en' => 'Handed to domestic carrier'],
    'delivered'    => ['ph' => 'done','th' => 'จัดส่งสำเร็จ',                 'en' => 'Delivered'],
];
const PHASES = [
    'jp'  => ['icon' => 'truck', 'th' => 'ญี่ปุ่น (รถบรรทุก)', 'en' => 'Japan (truck)'],
    'sea' => ['icon' => 'ship',  'th' => 'ขนส่งทางเรือ',      'en' => 'Ocean freight'],
    'th'  => ['icon' => 'doc',   'th' => 'ถึงไทย / ศุลกากร',  'en' => 'Thailand / customs'],
    'dom' => ['icon' => 'home',  'th' => 'จัดส่งถึงมือคุณ',    'en' => 'Last-mile delivery'],
];
const CARRIERS = [
    'kex'      => ['th' => 'KEX Express (Kerry)', 'en' => 'KEX Express (Kerry)'],
    'flash'    => ['th' => 'Flash Express', 'en' => 'Flash Express'],
    'jt'       => ['th' => 'J&T Express', 'en' => 'J&T Express'],
    'thaipost' => ['th' => 'ไปรษณีย์ไทย (EMS)', 'en' => 'Thailand Post (EMS)'],
    'ninjavan' => ['th' => 'Ninja Van', 'en' => 'Ninja Van'],
    'best'     => ['th' => 'BEST Express', 'en' => 'BEST Express'],
    'lalamove' => ['th' => 'Lalamove (กทม./ปริมณฑล)', 'en' => 'Lalamove (Bangkok area)'],
    'pickup'   => ['th' => 'รับเองที่โกดัง', 'en' => 'Self pick-up at warehouse'],
];
function stage_label(string $k): string { return isset(STAGES[$k]) ? L(STAGES[$k]) : $k; }
function stage_phase(string $k): string { return STAGES[$k]['ph'] ?? 'jp'; }
function stage_index(string $k): int { $i = array_search($k, array_keys(STAGES), true); return $i === false ? 0 : $i; }
function carrier_label(?string $k): string { return $k && isset(CARRIERS[$k]) ? L(CARRIERS[$k]) : (string)$k; }
function status_pill(string $k): string { return '<span class="pill p-' . e(stage_phase($k)) . '">' . e(stage_label($k)) . '</span>'; }
function fmt_dt(?string $s, bool $time = true): string {
    if (!$s) return '—';
    $ts = strtotime($s); if (!$ts) return e($s);
    if (lang() === 'th') {
        $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $out = date('j', $ts) . ' ' . $m[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543);
    } else {
        $out = date('j M Y', $ts);
    }
    return $time ? $out . ' ' . date('H:i', $ts) : $out;
}
function gen_tn(): string {
    do { $tn = 'C1' . date('ym') . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT); }
    while (q('SELECT 1 FROM shipments WHERE tn = ?', [$tn])->fetch());
    return $tn;
}
function recompute_status(int $shipId): void {
    $last = q('SELECT status FROM events WHERE shipment_id = ? ORDER BY happened_at DESC, id DESC LIMIT 1', [$shipId])->fetch();
    q('UPDATE shipments SET status = ?, updated_at = ? WHERE id = ?', [$last['status'] ?? 'created', now(), $shipId]);
}
function default_location(string $st, string $origin): string {
    $city = trim(explode(',', $origin)[0] ?: 'Osaka');
    return [
        'created' => $origin, 'jp_warehouse' => $origin, 'jp_truck' => $origin, 'jp_port' => $city . ' Port, Japan',
        'at_sea' => 'Ocean freight', 'th_port' => 'Laem Chabang, Chonburi', 'customs' => 'Thai Customs',
        'th_warehouse' => 'Camp 1 Express Warehouse, Thailand', 'dispatched' => 'Thailand', 'delivered' => '',
    ][$st] ?? '';
}

function icon(string $k): string {
    $p = [
        'truck' => '<path d="M1 4h13v11H1z"/><path d="M14 8h4l4 4v3h-8z"/><circle cx="5.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/>',
        'ship'  => '<path d="M2 20c2 1.3 4 1.3 6 0s4-1.3 6 0 4 1.3 6 0"/><path d="M4 17l-1-5h18l-2 5"/><path d="M6 12V7h12v5"/><path d="M12 4v3"/>',
        'doc'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
        'home'  => '<path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'box'   => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
        'cart'  => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L21 7H6"/>',
        'search'=> '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'yen'   => '<path d="M6 3l6 8 6-8"/><path d="M12 11v10"/><path d="M7 13h10"/><path d="M7 17h10"/>',
        'lock'  => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'chat'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    ][$k] ?? '';
    return '<svg class="ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
