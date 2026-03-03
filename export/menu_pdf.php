<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');

/* ============================================================
   VSTUPNÍ PARAMETRY
   ============================================================ */
$mode       = $_POST['mode'] ?? ($_GET['week_of'] ?? null ? 'week' : 'day'); // day|week|days|items
$dayId      = (int)($_GET['id'] ?? $_POST['day_id'] ?? 0);
$weekOf     = $_GET['week_of'] ?? null;                       // YYYY-mm-dd (pondělí daného týdne stačí)
$itemIds    = array_map('intval', $_POST['item_id'] ?? []);   // vybrané položky
$dayIdsArr  = array_map('intval', $_POST['day_id'] ?? []);    // vybrané dny (více)
$withPrices = true;                                           // případně si přepni na POST checkbox

/* ============================================================
   NAČÍTACÍ HELPERY (DB)
   ============================================================ */
function load_day(mysqli $conn, int $id): ?array {
  $stmt = $conn->prepare("SELECT * FROM menu_days WHERE id=?");
  $stmt->bind_param("i",$id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}
function load_sections(mysqli $conn): array {
  $rows = [];
  $res = $conn->query("SELECT id, name FROM menu_sections ORDER BY sort_order ASC, id ASC");
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  return $rows;
}
function load_items_for_day(mysqli $conn, int $dayId): array {
  $rows = [];
  $stmt = $conn->prepare("SELECT * FROM menu_items WHERE day_id=? ORDER BY section_id ASC, sort_order ASC, id ASC");
  $stmt->bind_param("i",$dayId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();
  return $rows;
}
function load_items_by_ids(mysqli $conn, array $ids): array {
  if (!$ids) return [];
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $typ = str_repeat('i', count($ids));
  $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id IN ($in) ORDER BY day_id ASC, section_id ASC, sort_order ASC, id ASC");
  $stmt->bind_param($typ, ...$ids);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();
  return $rows;
}
function items_count_for_day(mysqli $conn, int $dayId): int {
  $stmt = $conn->prepare("SELECT COUNT(*) c FROM menu_items WHERE day_id=?");
  $stmt->bind_param("i",$dayId);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();
  return $c;
}
function section_map(array $sections): array {
  $m = [];
  foreach ($sections as $s) $m[(int)$s['id']] = $s['name'];
  return $m;
}

/* ============================================================
   DEBUG TEXTOVÝ VÝSTUP (volitelně ?debug=1)
   ============================================================ */
if (isset($_GET['debug'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "MODE = " . ($mode ?? '') . "\n";
  if (($mode ?? '') === 'day' && $dayId) {
    $d = load_day($conn, $dayId);
    echo "DAY id={$dayId} date=" . ($d['menu_date'] ?? '-') . "\n";
    $items = load_items_for_day($conn, $dayId);
    echo "items=" . count($items) . "\n";
    foreach ($items as $it) {
      echo " - [sec {$it['section_id']}] {$it['title']} price={$it['price']} allergens={$it['allergens']}\n";
    }
  } elseif (($mode ?? '') === 'week' && $weekOf) {
    [$start,$end] = week_range($weekOf);
    $st = $conn->prepare("SELECT id,menu_date FROM menu_days WHERE menu_date BETWEEN ? AND ? ORDER BY menu_date ASC");
    $st->bind_param("ss",$start,$end);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
      $cnt = items_count_for_day($conn, (int)$r['id']);
      echo $r['menu_date']." id={$r['id']} items={$cnt}\n";
    }
    $st->close();
  }
  exit;
}

/* ============================================================
   RENDER OBSAHU (tabulky položek)
   ============================================================ */
function render_items_grouped(array $sections, array $items, bool $withPrices): string {
  if (!$items) {
    return '<div style="color:#888;margin:4px 0 10px 0;">— nevaří se —</div>';
  }
  $secMap = section_map($sections);

  ob_start(); ?>
  <table width="100%" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
    <?php
      $currentSec = null;
      foreach ($items as $it):
        $secId = (int)$it['section_id'];
        if ($currentSec !== $secId):
          $currentSec = $secId; ?>
          <tr>
            <td colspan="3" class="section-title" style="font-weight:bold;border-bottom:1px solid #ccc;padding:6px 0 3px 0;">
              <?= e($secMap[$secId] ?? ('Sekce '.$secId)) ?>
            </td>
          </tr>
        <?php endif; ?>
        <tr>
          <td class="ttl"   style="width:70%;padding:3px 0;"><?= e($it['title']) ?></td>
          <td class="price" style="width:15%;padding:3px 0; text-align:right;">
            <?= $withPrices && $it['price']!==null ? e(number_format((float)$it['price'],2,',',' ')).' Kč' : '&nbsp;' ?>
          </td>
          <td class="al"    style="width:15%;padding:3px 0; text-align:right;"><?= $it['allergens'] ? e($it['allergens']) : '&nbsp;' ?></td>
        </tr>
    <?php endforeach; ?>
  </table>
  <?php
  return ob_get_clean();
}

function render_day_inner(mysqli $conn, int $dayId, bool $withPrices): string {
  $sections = load_sections($conn);
  $items    = load_items_for_day($conn, $dayId);
  return render_items_grouped($sections, $items, $withPrices);
}

function render_day_html(mysqli $conn, int $dayId, bool $withPrices): string {
  $day = load_day($conn, $dayId);
  if (!$day) return '<p>Den nenalezen.</p>';
  ob_start(); ?>
  <h2 style="margin:0 0 6px 0;"><?= e(date('j. n. Y', strtotime($day['menu_date']))) ?></h2>
  <?php if (!empty($day['note'])): ?>
    <div style="margin:0 0 8px 0; color:#666;"><?= e($day['note']) ?></div>
  <?php endif; ?>
  <?= render_day_inner($conn, $dayId, $withPrices) ?>
  <?php
  return ob_get_clean();
}

/* ============================================================
   ŠABLONY: DEN / TÝDEN (ODDĚLENÉ)
   ============================================================ */
function pdf_template_day(string $title, string $itemsHtml, string $logoUrl = '', string $siteTitle = '', string $siteUrl = ''): string {
  return '
  <html>
  <head>
    <meta charset="UTF-8">
    <style>
      /* --- Denní nabídka (portrait, větší písmo) --- */
      body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:13.5px; line-height:1.45; color:#111; }
      .wrap{ max-width:730px; margin:0 auto; }
      .header{ text-align:center; margin:8px 0 16px; }
      .logo{ height:70px; margin-bottom:6px; }
      .site{ font-size:18px; font-weight:600; }
      h1{ font-size:20px; margin:14px 0 10px; text-align:center; }
      .items{ margin-top:10px; }
      .section-title{ font-weight:700; border-bottom:1px solid #ccc; padding:6px 0 3px; margin-top:8px; }
      table{ width:100%; border-collapse:collapse; }
      td{ padding:3px 0; }
      .price,.al{ width:15%; text-align:right; white-space:nowrap; }
      .footer{ margin-top:24px; text-align:center; color:#666; font-size:11px; }
      .footer a{ color:#666; text-decoration:none; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="header">'
        . ($logoUrl ? '<img class="logo" src="'.e($logoUrl).'" alt="logo">' : '') .
        '<div class="site">'.e($siteTitle).'</div>
      </div>

      <h1>'. $title .'</h1>

      <div class="items">'. $itemsHtml .'</div>

      <div class="footer">'
        . ($siteUrl ? '<span>'.e($siteUrl).'</span>' : '') .
      '</div>
    </div>
  </body>
  </html>';
}

function pdf_template_week(string $title, string $gridHtml, string $logoUrl = '', string $siteTitle = '', string $siteUrl = ''): string {
  return '
  <html>
  <head>
    <meta charset="UTF-8">
    <style>
      /* --- Týdenní přehled (landscape, menší písmo, 2 sloupce) --- */
      body{ font-family: DejaVu Sans, Arial, sans-serif; color:#111; }
      .wrap{ margin:6px 14px; }
      .header{ text-align:center; margin:0 0 8px; }
      .logo{ height:50px; margin-bottom:4px; }
      .site{ font-size:14px; font-weight:600; }
      h1{ font-size:16px; margin:8px 0 6px; text-align:center; }

      .week-grid{ font-size:11px; }
      .daybox{
        display:inline-block; vertical-align:top;
        width:48%; margin:0 1% 8px 0; padding:6px 8px; border:1px solid #ddd; border-radius:6px;
        box-sizing:border-box; break-inside:avoid;
      }
      .dayhead{ font-weight:700; margin:0 0 4px 0; }
      .daynote{ color:#666; margin:0 0 6px 0; }
      table{ width:100%; border-collapse:collapse; }
      td{ padding:2px 0; }
      .price,.al{ width:15%; text-align:right; white-space:nowrap; }

      .footer{ margin-top:6px; text-align:center; color:#666; font-size:10.5px; }
      .footer a{ color:#666; text-decoration:none; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="header">'
        . ($logoUrl ? '<img class="logo" src="'.e($logoUrl).'" alt="logo">' : '') .
        '<div class="site">'.e($siteTitle).'</div>
      </div>

      <h1>'. $title .'</h1>

      <div class="week-grid">'. $gridHtml .'</div>

      <div class="footer">'
        . ($siteUrl ? '<span>'.e($siteUrl).'</span>' : '') .
      '</div>
    </div>
  </body>
  </html>';
}

/* ============================================================
   POSKLÁDÁNÍ OBSAHU DLE REŽIMU
   ============================================================ */
$html        = '';
$title       = 'Jídelní lístek';
$orientation = 'portrait'; // day default (portrait)

if ($mode === 'items' && $itemIds) {
  $secs  = load_sections($conn);
  $items = load_items_by_ids($conn, $itemIds);
  $byDay = [];
  foreach ($items as $it) $byDay[(int)$it['day_id']][] = $it;

  foreach ($byDay as $dId => $list) {
    $day = load_day($conn, $dId);
    $html .= '<div style="page-break-inside:avoid;">';
    $html .= '<h2 style="margin:0 0 6px 0;">' . e(date('j. n. Y', strtotime($day['menu_date']))) . ' — vybrané položky</h2>';
    $html .= render_items_grouped($secs, $list, $withPrices);
    $html .= '</div><div style="page-break-after:always;"></div>';
  }
  $title = 'Jídelní lístek – vybrané položky';

} elseif ($mode === 'week' && $weekOf) {
  [$start,$end] = week_range($weekOf);
  $stmt = $conn->prepare("SELECT id FROM menu_days WHERE menu_date BETWEEN ? AND ? ORDER BY menu_date ASC");
  $stmt->bind_param("ss",$start,$end);
  $stmt->execute();
  $res = $stmt->get_result();
  $ids = [];
  while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
  $stmt->close();

  $orientation = 'portrait'; // vejde se pohodlně na jednu A4
  if (!$ids) {
    $html = '<p>Pro zadaný týden nebyly nalezeny žádné dny.</p>';
  } else {
    $html .= '<div class="week-grid">';
    foreach ($ids as $dId) {
      $day = load_day($conn, $dId);
      $html .= '<div class="daybox">';
      $html .= '<div class="dayhead">'. e(date('j. n. Y', strtotime($day['menu_date']))) .'</div>';
      if (!empty($day['note'])) $html .= '<div class="daynote">'. e($day['note']) .'</div>';
      $html .= render_day_inner($conn, $dId, $withPrices);
      $html .= '</div>';
    }
    $html .= '</div>';
  }
  $title = 'Jídelní lístek – týden ' . e(date('j. n.', strtotime($start))) . '–' . e(date('j. n. Y', strtotime($end)));

} elseif ($mode === 'days' && $dayIdsArr) {
  $dayIdsArr = array_values(array_unique(array_filter($dayIdsArr)));
  foreach ($dayIdsArr as $i => $dId) {
    $html .= render_day_html($conn, $dId, true);
    if ($i < count($dayIdsArr)-1) $html .= '<div style="page-break-after:always;"></div>';
  }
  $title = 'Jídelní lístek – vybrané dny';

} else {
  // default: jeden den (portrait, větší písmo)
  if (!$dayId) { echo "Missing id"; exit; }
  $html  = render_day_html($conn, $dayId, $withPrices);
  $title = 'Jídelní lístek – ' . e(date('j. n. Y', strtotime(load_day($conn,$dayId)['menu_date'] ?? 'now')));
}

/* ============================================================
   HLAVIČKA / PATIČKA – logo + URL ze settings_admins
   ============================================================ */
$logoUrl   = media_url(settings_admin('site_logo_url',''));
$siteTitle = settings_admin('site_title','');
$siteUrl   = trim(settings_admin('site_url',''));
if ($siteUrl === '') {
  $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $siteUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/* ============================================================
   SLOŽENÍ FINÁLNÍHO HTML PODLE REŽIMU
   ============================================================ */
if ($mode === 'week') {
  $fullHtml = pdf_template_week($title, $html, $logoUrl, $siteTitle, $siteUrl);
} else {
  $fullHtml = pdf_template_day($title, $html, $logoUrl, $siteTitle, $siteUrl);
}

/* ============================================================
   DOMPDF (bez Composeru) + VÝSTUP
   ============================================================ */
$autoloadLoaded = false;
$candidates = [
  __DIR__ . '/../libs/dompdf/autoload.inc.php',
  __DIR__ . '/../vendor/dompdf/autoload.inc.php',
  __DIR__ . '/../../libs/dompdf/autoload.inc.php',
  $_SERVER['DOCUMENT_ROOT'] . '/libs/dompdf/autoload.inc.php',
  $_SERVER['DOCUMENT_ROOT'] . '/vendor/dompdf/autoload.inc.php',
];
foreach ($candidates as $path) {
  if (is_file($path)) { require_once $path; $autoloadLoaded = true; break; }
}
$dompdfAvailable = $autoloadLoaded && class_exists('\Dompdf\Dompdf');

if (!$dompdfAvailable) {
  // Fallback – pošli HTML do prohlížeče (pro ladění)
  header('Content-Type: text/html; charset=utf-8');
  echo $fullHtml;
  exit;
}

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('tempDir', __DIR__ . '/../tmp');

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($fullHtml);
$dompdf->setPaper('A4', $orientation); // portrait / landscape
$dompdf->render();
$dompdf->stream(($title ?? 'vystup') . ".pdf", ["Attachment" => false]);
