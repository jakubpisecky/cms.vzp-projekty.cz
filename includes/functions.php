<?php
/**
 * Vytvoří slug z textu (odstraní diakritiku a převede mezery na pomlčky)
 */
function slugify(string $text): string {
    $map = [
        'ě'=>'e','š'=>'s','č'=>'c','ř'=>'r','ž'=>'z','ý'=>'y','á'=>'a','í'=>'i','é'=>'e','ú'=>'u','ů'=>'u','ó'=>'o',
        'Ě'=>'E','Š'=>'S','Č'=>'C','Ř'=>'R','Ž'=>'Z','Ý'=>'Y','Á'=>'A','Í'=>'I','É'=>'E','Ú'=>'U','Ů'=>'U','Ó'=>'O'
    ];
    $text = strtr($text, $map);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header("Location: ../dashboard.php?error=no-permission");
        exit;
    }
}

/**
 * Zapíše akci do logů
 */
function logAction(string $action): void {
    global $conn;
    $adminId = $_SESSION['admin_id'] ?? null;

    $stmt = $conn->prepare("INSERT INTO logs (admin_id, action, created_at) VALUES (?,?,NOW())");
    $stmt->bind_param("is", $adminId, $action);
    $stmt->execute();
}

function formatDateTimeCz(?string $datetime): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    return date('j. n. Y H:i', strtotime($datetime));
}
function formatDateCz(?string $datetime): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    return date('j. n. Y', strtotime($datetime));
}

require_once __DIR__ . '/permissions.php';

/**
 * Vyžaduje oprávnění na danou sekci – jinak přístup zakáže
 */
function requirePermission(string $section): void {
    if (!hasPermission($section)) {
        header("HTTP/1.1 403 Forbidden");
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="utf-8">
            <title>Přístup odepřen</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container text-center mt-5">
                <div class="alert alert-danger shadow-sm p-4">
                    <h1 class="display-5 mb-3">🚫 Přístup odepřen</h1>
                    <p class="mb-4">Na tuto stránku nemáte oprávnění.</p>
                    <a href="/dashboard.php" class="btn btn-primary">⏪ Zpět na Dashboard</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'    => $_SESSION['admin_id'] ?? null,
        'email' => $_SESSION['admin_email'] ?? null,
        'role'  => $_SESSION['role'] ?? 'user'
    ];
}
// Klíč = hodnota ukládaná do DB (pages.template), Hodnota = popisek v UI
$PAGE_TEMPLATES = [
    'home'      => 'Homepage',
    'articles'  => 'Výpis článků',
    'page'      => 'Obecná stránka',
    'galleries' => 'Fotogalerie',
    'contact'   => 'Kontaktní formulář',
];

// Pomocná validace; když přijde neznámá šablona, spadne na "page"
function normalizePageTemplate(string $tpl, array $allowed): string {
    return array_key_exists($tpl, $allowed) ? $tpl : 'page';
}

// Otočí podle EXIF (jen u JPEG), vrátí GD image resource
function gd_load_with_orientation(string $path) {
    if (!is_file($path)) {
        // nic nevypisuj na výstup, ať nepadá redirect; log stačí do error_log
        error_log("gd_load_with_orientation: file not found: " . $path);
        return null;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $img = @imagecreatefromjpeg($path);
        if (!$img) return null;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (!empty($exif['Orientation'])) {
                switch ((int)$exif['Orientation']) {
                    case 3: $img = imagerotate($img, 180, 0); break;
                    case 6: $img = imagerotate($img, -90, 0); break;
                    case 8: $img = imagerotate($img,  90, 0); break;
                }
            }
        }
        return $img;
    }
    if ($ext === 'png')  return @imagecreatefrompng($path) ?: null;
    if ($ext === 'gif')  return @imagecreatefromgif($path) ?: null;
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path) ?: null;

    $blob = @file_get_contents($path);
    return $blob ? (@imagecreatefromstring($blob) ?: null) : null;
}


// Poměrné zmenšení na max šířku/výšku, vrátí [dst,width,height]
function gd_resize_fit($srcImg, int $maxW, int $maxH) {
    $sw = imagesx($srcImg); $sh = imagesy($srcImg);
    $ratio = min($maxW / $sw, $maxH / $sh, 1); // nikdy nezvětšovat
    $dw = (int)round($sw * $ratio);
    $dh = (int)round($sh * $ratio);
    $dst = imagecreatetruecolor($dw, $dh);
    imagecopyresampled($dst, $srcImg, 0,0,0,0, $dw,$dh, $sw,$sh);
    return [$dst, $dw, $dh];
}

// Vytvoří čtvercový ořez ze středu
function gd_crop_square($srcImg, int $size) {
    $sw = imagesx($srcImg); $sh = imagesy($srcImg);
    $side = min($sw, $sh);
    $sx = (int)(($sw - $side)/2);
    $sy = (int)(($sh - $side)/2);
    $tmp = imagecreatetruecolor($size, $size);
    imagecopyresampled($tmp, $srcImg, 0,0, $sx,$sy, $size,$size, $side,$side);
    return [$tmp, $size, $size];
}

function save_jpeg($img, string $path, int $quality = 82): bool {
    imageinterlace($img, true); // progressive
    return imagejpeg($img, $path, $quality);
}

function save_webp_if_supported($img, string $path, int $quality = 82): ?string {
    if (!function_exists('imagewebp')) return null;

    // když je přípona, přepiš ji na .webp; jinak ji přidej
    $webpPath = preg_match('/\.\w+$/', $path) ? preg_replace('/\.\w+$/', '.webp', $path)
                                              : $path . '.webp';

    return imagewebp($img, $webpPath, $quality) ? $webpPath : null;
}


/**
 * Vytvoří varianty pro nahraný soubor.
 * @return array ['thumb'=>['path'=>..., 'w'=>..,'h'=>..], 'medium'=>...]
 */
function image_make_variants(string $absPath, string $publicPathNoExt, string $ext, array $opts = []): array {
    $q = $opts['quality'] ?? 82;
    $src = gd_load_with_orientation($absPath);
    if (!$src) return [];

    $variants = [];

    // medium (max 1600x1600)
    [$medImg, $mw, $mh] = gd_resize_fit($src, 1600, 1600);
    $medPath = $publicPathNoExt . '_medium.' . $ext;
    save_jpeg($medImg, $_SERVER['DOCUMENT_ROOT'].$medPath, $q);
    $medWebp = save_webp_if_supported($medImg, $_SERVER['DOCUMENT_ROOT'].$medPath, $q);
    imagedestroy($medImg);
    $variants['medium'] = ['path'=>$medPath, 'w'=>$mw, 'h'=>$mh];
    if ($medWebp) $variants['medium_webp'] = ['path'=>preg_replace('/\.\w+$/', '.webp', $medPath), 'w'=>$mw, 'h'=>$mh];

    // thumb (300x300 crop)
    [$thImg, $tw, $th] = gd_crop_square($src, 300);
    $thPath = $publicPathNoExt . '_thumb.' . $ext;
    save_jpeg($thImg, $_SERVER['DOCUMENT_ROOT'].$thPath, $q);
    $thWebp = save_webp_if_supported($thImg, $_SERVER['DOCUMENT_ROOT'].$thPath, $q);
    imagedestroy($thImg);
    $variants['thumb'] = ['path'=>$thPath, 'w'=>$tw, 'h'=>$th];
    if ($thWebp) $variants['thumb_webp'] = ['path'=>preg_replace('/\.\w+$/', '.webp', $thPath), 'w'=>$tw, 'h'=>$th];

    imagedestroy($src);
    return $variants;
}

// ../includes/functions.php
if (!function_exists('e')) {
  function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

// ==== lokální helpery pro stránkování (stejné jako u pages/list.php) ====
function buildBaseUrl(): string {
    $params = $_GET; unset($params['page']);
    $base = http_build_query($params);
    return $base ? ('?' . $base . '&') : '?';
}
function renderPagination(int $totalPages, int $page, string $base): void {
    if ($totalPages <= 1) return;

    $window = 2;
    $pages  = [1];
    $start  = max(2, $page - $window);
    $end    = min($totalPages - 1, $page + $window);
    if ($start > 2) $pages[] = '...';
    for ($i = $start; $i <= $end; $i++) $pages[] = $i;
    if ($end < $totalPages - 1) $pages[] = '...';
    if ($totalPages > 1) $pages[] = $totalPages;

    $plink = fn (int $p) => $base . 'page=' . $p;

    echo '<nav aria-label="Stránkování"><ul class="pagination justify-content-center mt-3">';
    echo '<li class="page-item '.($page===1?'disabled':'').'"><a class="page-link" href="'.($page===1?'#':$plink(1)).'">«</a></li>';
    echo '<li class="page-item '.($page===1?'disabled':'').'"><a class="page-link" href="'.($page===1?'#':$plink($page-1)).'">‹</a></li>';

    foreach ($pages as $p) {
        if ($p === '...') {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        } else {
            echo '<li class="page-item '.($p===$page?'active':'').'"><a class="page-link" href="'.$plink($p).'">'.$p.'</a></li>';
        }
    }

    echo '<li class="page-item '.($page===$totalPages?'disabled':'').'"><a class="page-link" href="'.($page===$totalPages?'#':$plink($page+1)).'">›</a></li>';
    echo '<li class="page-item '.($page===$totalPages?'disabled':'').'"><a class="page-link" href="'.($page===$totalPages?'#':$plink($totalPages)).'">»</a></li>';
    echo '</ul></nav>';
}

// Vrať [pondělí, pátek] pro libovolné datum (YYYY-mm-dd)
function week_range(string $date): array {
  $ts  = strtotime($date);
  $dow = (int)date('N', $ts); // 1=Mon … 7=Sun
  $mon = strtotime('-'.($dow-1).' days', $ts);
  $fri = strtotime('+'.(5-$dow).' days', $ts);  // pátek
  return [date('Y-m-d', $mon), date('Y-m-d', $fri)];
}


// --- Settings cache + helpers ---
// Jednorázově natáhne tabulku `settings` do paměti a vrací hodnoty dle klíče.
function settings_admin(string $key, mixed $default = null): mixed {
    // Když DB není k dispozici, vrať default (bez fatální chyby)
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return $default;
    }

    static $CACHE = null;
    if ($CACHE === null) {
        $CACHE = [];
        if ($res = $conn->query("SELECT `key`, `value` FROM settings")) {
            while ($r = $res->fetch_assoc()) {
                $CACHE[$r['key']] = $r['value'];
            }
        }
    }
    return array_key_exists($key, $CACHE) ? $CACHE[$key] : $default;
}

// Vyprázdnění cache po uložení v administraci nastavení
function settings_flush_cache(): void {
    // protože v setting() je cache ve static proměnné, použijeme trik s voláním anonymní funkce
    // nebo prostě přejmenuj (mírný hack): restart skriptu vyřeší vše; pro jistotu:
    if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
}

// Pohodlné čtení bool hodnot (1/0, true/false, yes/no…)
function setting_bool(string $key, bool $default = false): bool {
    $v = setting($key, null);
    if ($v === null) return $default;
    $v = strtolower(trim((string)$v));
    return in_array($v, ['1','true','yes','on','ano','y'], true);
}

// === URL helpers ===

// Absolutní URL k webu (schéma + host). Můžeš si případně číst i setting('site_base_url')
function site_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * media_url('/uploads/obrazek.jpg') -> 'https://domena.cz/uploads/obrazek.jpg'
 * Nechá být již absolutní URL (http/https) i protokol-relative URL (//cdn...).
 */
function media_url(string $path, bool $absolute = true): string {
    $path = trim($path);
    if ($path === '') return '';

    // už je to absolutní URL nebo //cdn
    if (preg_match('~^(?:https?:)?//~i', $path)) return $path;

    // zajisti počáteční lomítko
    if ($path[0] !== '/') $path = '/' . $path;

    return $absolute ? (site_url() . $path) : $path;
}


