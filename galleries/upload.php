<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('galleries');

$galleryId = intval($_GET['gallery_id'] ?? 0);

// Ověření galerie
$stmt = $conn->prepare("SELECT * FROM galleries WHERE id = ?");
$stmt->bind_param("i", $galleryId);
$stmt->execute();
$gallery = $stmt->get_result()->fetch_assoc();

if (!$galleryId || !$gallery) {
    http_response_code(400);
    echo "Galerie neexistuje.";
    exit;
}

// Cesty
$uploadDir = "../uploads/galleries/{$galleryId}/";
$thumbsDir = $uploadDir . "thumbs/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0777, true);
}

// Nastavení
$allowedExt = ['jpg','jpeg','png','gif','webp'];
$maxW = 1600;   // max šířka pro originál
$maxH = 1200;   // max výška pro originál
$thumbSize = 480; // velikost čtvercového náhledu

// ------- Pomocné funkce -------

function imageCreateFromExt(string $path, string $ext) {
    switch ($ext) {
        case 'jpg':
        case 'jpeg': return @imagecreatefromjpeg($path);
        case 'png':  return @imagecreatefrompng($path);
        case 'gif':  return @imagecreatefromgif($path);
        case 'webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        default:     return false;
    }
}

function imageSaveToExt($img, string $path, string $ext, int $quality = 85): bool {
    switch ($ext) {
        case 'jpg':
        case 'jpeg': return imagejpeg($img, $path, $quality);
        case 'png':  return imagepng($img, $path, 6);
        case 'gif':  return imagegif($img, $path);
        case 'webp':
            if (function_exists('imagewebp')) {
                return imagewebp($img, $path, $quality);
            }
            return false;
        default: return false;
    }
}

/**
 * Vytvoří plátno s transparentním pozadím pro PNG/WEBP (jinak obyčejné).
 */
function prepareTransparentCanvas(int $w, int $h, string $ext) {
    $dst = imagecreatetruecolor($w, $h);
    if (in_array($ext, ['png','webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
    }
    return $dst;
}

/**
 * Proporční zmenšení do boxu (bez ořezu, bez upscalování pokud $allowUpscale=false).
 */
function resizeIntoBox(string $srcPath, string $destPath, string $ext, int $maxW, int $maxH, bool $allowUpscale = false, int $quality = 85): bool {
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    [$origW, $origH] = $info;

    if (!$allowUpscale && $origW <= $maxW && $origH <= $maxH) {
        // není třeba měnit
        if ($srcPath === $destPath) return true;
        return copy($srcPath, $destPath);
    }

    $ratio = min($maxW / $origW, $maxH / $origH);
    $newW = max(1, (int) floor($origW * $ratio));
    $newH = max(1, (int) floor($origH * $ratio));

    $src = imageCreateFromExt($srcPath, $ext);
    if (!$src) return false;

    $dst = prepareTransparentCanvas($newW, $newH, $ext);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    $ok = imageSaveToExt($dst, $destPath, $ext, $quality);

    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

/**
 * Čtvercový náhled s center-cropem.
 * 1) Spočítá scale tak, aby kratší strana byla >= target (bez upscalu pokud $allowUpscale=false).
 * 2) Změní velikost.
 * 3) Ořízne střed na target x target (pokud je obrázek menší a upscalování je vypnuto, ořeže na menší dostupný čtverec).
 */
function makeSquareThumb(string $srcPath, string $destPath, string $ext, int $target, bool $allowUpscale = false, int $quality = 85): bool {
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    [$origW, $origH] = $info;
    $shorter = min($origW, $origH);

    // Výpočet scale – bez upscalu zůstane <=1, pokud kratší strana < target
    $scale = $target / $shorter;
    if (!$allowUpscale && $shorter < $target) {
        $scale = 1.0; // neškálujeme nahoru
    }

    $newW = max(1, (int) floor($origW * $scale));
    $newH = max(1, (int) floor($origH * $scale));

    $src = imageCreateFromExt($srcPath, $ext);
    if (!$src) return false;

    // Nejprve resample na nové rozměry (nebo 1:1)
    $resized = prepareTransparentCanvas($newW, $newH, $ext);
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    // Následně crop středu do čtverce o velikosti $cropSize (<= $target pokud neproběhl upscale)
    $cropSize = min($target, min($newW, $newH));
    $cropX = (int) floor(($newW - $cropSize) / 2);
    $cropY = (int) floor(($newH - $cropSize) / 2);

    $thumb = prepareTransparentCanvas($cropSize, $cropSize, $ext);
    imagecopy($thumb, $resized, 0, 0, $cropX, $cropY, $cropSize, $cropSize);

    $ok = imageSaveToExt($thumb, $destPath, $ext, $quality);

    imagedestroy($src);
    imagedestroy($resized);
    imagedestroy($thumb);
    return $ok;
}

/**
 * Autorotace JPEG dle EXIF (pokud je k dispozici).
 */
function maybeAutorotateJpeg(string $path, string $ext): void {
    if (!in_array($ext, ['jpg','jpeg'])) return;
    if (!function_exists('exif_read_data')) return;

    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) return;

    $orientation = (int)$exif['Orientation'];
    if ($orientation === 1) return;

    $img = @imagecreatefromjpeg($path);
    if (!$img) return;

    switch ($orientation) {
        case 3: $img = imagerotate($img, 180, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0);  break;
        default: // nic
    }
    imagejpeg($img, $path, 90);
    imagedestroy($img);
}

// ------- Upload & zpracování -------

foreach ($_FILES['photos']['name'] as $index => $name) {
    if ($_FILES['photos']['error'][$index] !== UPLOAD_ERR_OK) {
        continue;
    }

    $tmpName = $_FILES['photos']['tmp_name'][$index];

    // Ověření MIME přes getimagesize (bezpečnější než samotná přípona)
    $info = @getimagesize($tmpName);
    if (!$info || empty($info['mime']) || stripos($info['mime'], 'image/') !== 0) {
        continue;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        // Zkusit odvodit z MIME
        $mime = strtolower($info['mime']);
        if ($mime === 'image/jpeg') $ext = 'jpg';
        elseif ($mime === 'image/png') $ext = 'png';
        elseif ($mime === 'image/gif') $ext = 'gif';
        elseif ($mime === 'image/webp') $ext = 'webp';
        else continue;
    }

    $baseName = pathinfo($name, PATHINFO_FILENAME);
    $slug = slugify($baseName);
    if ($slug === '') $slug = 'image';

    $filename = $slug . '.' . $ext;
    $target   = $uploadDir . $filename;

    // Unikátní název
    $i = 1;
    while (file_exists($target)) {
        $filename = $slug . '-' . $i . '.' . $ext;
        $target   = $uploadDir . $filename;
        $i++;
    }

    // Přesun surového souboru
    if (!move_uploaded_file($tmpName, $target)) {
        continue;
    }

    // Autorotace JPEG (pokud je třeba)
    maybeAutorotateJpeg($target, $ext);

    // Zmenšení originálu do max 1600x1200 (bez upscalu)
    resizeIntoBox($target, $target, $ext, $maxW, $maxH, false, 85);

    // Vygenerování ČTVERCOVÉHO náhledu do thumbs/{filename} (center-crop, bez upscalu)
    $thumbPath = $thumbsDir . $filename;
    makeSquareThumb($target, $thumbPath, $ext, $thumbSize, false, 85);

    // Získání pořadí
    $res = $conn->query("SELECT MAX(sort_order) AS max_sort FROM gallery_photos WHERE gallery_id = " . intval($galleryId));
    $sort = intval(($res && $res->num_rows) ? ($res->fetch_assoc()['max_sort'] ?? 0) : 0) + 1;

    // Uložení do DB
    $stmt = $conn->prepare("INSERT INTO gallery_photos (gallery_id, filename, title, sort_order) VALUES (?, ?, ?, ?)");
    $title = $baseName; // výchozí titulek podle názvu souboru
    $stmt->bind_param("issi", $galleryId, $filename, $title, $sort);
    $stmt->execute();
}

header("Location: photos.php?gallery_id=" . $galleryId);
exit;
