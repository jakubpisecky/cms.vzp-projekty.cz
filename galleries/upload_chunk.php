<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');


// ---- Parametry ----
$galleryId = (int)($_GET['gallery_id'] ?? 0);
if ($galleryId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Neplatná galerie']);
    exit;
}

// Ověření existence galerie
$stmt = $conn->prepare("SELECT id, title FROM galleries WHERE id=?");
$stmt->bind_param("i", $galleryId);
$stmt->execute();
$gal = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$gal) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Galerie neexistuje']);
    exit;
}

// Dropzone chunk params
$chunkIndex  = isset($_POST['dzchunkindex']) ? (int)$_POST['dzchunkindex'] : 0;
$totalChunks = isset($_POST['dztotalchunkcount']) ? (int)$_POST['dztotalchunkcount'] : 1;
$uuid        = $_POST['dzuuid'] ?? bin2hex(random_bytes(12));

// Soubory/cesty
$baseDir   = realpath(__DIR__ . "/../uploads");
$galPath   = $baseDir . "/galleries/" . $galleryId;
$tmpPath   = $baseDir . "/tmp/" . $uuid;

if (!is_dir($galPath)) @mkdir($galPath, 0777, true);
if (!is_dir($tmpPath)) @mkdir($tmpPath, 0777, true);

// Přijatý chunk
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Soubor se nepodařilo přijmout']);
    exit;
}

$tmpName = $_FILES['file']['tmp_name'];

// Bezpečná validace typu (podle obsahu)
$info = @getimagesize($tmpName);
if (!$info || empty($info['mime']) || stripos($info['mime'], 'image/') !== 0) {
    @unlink($tmpName);
    http_response_code(415);
    echo json_encode(['ok' => false, 'error' => 'Nepodporovaný typ souboru']);
    exit;
}

// Ulož chunk
$chunkFile = $tmpPath . "/chunk_" . str_pad((string)$chunkIndex, 5, "0", STR_PAD_LEFT);
if (!@move_uploaded_file($tmpName, $chunkFile)) {
    // některé SAPI neumožní move_uploaded_file u chunků → fallback copy
    @copy($tmpName, $chunkFile);
    @unlink($tmpName);
}

$done = ($chunkIndex + 1) >= $totalChunks;

if (!$done) {
    // stále se nahrává – vrať OK (Dropzone si vyžádá další chunk)
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(['ok' => true, 'partial' => true]);
    exit;
}

// ===== kompletace chunků do jednoho souboru =====
$finalTmp = $tmpPath . "/assembled.bin";
$fp = @fopen($finalTmp, "wb");
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Chyba při kompletaci']);
    exit;
}
for ($i = 0; $i < $totalChunks; $i++) {
    $cf = $tmpPath . "/chunk_" . str_pad((string)$i, 5, "0", STR_PAD_LEFT);
    $in = @fopen($cf, "rb");
    if (!$in) { fclose($fp); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Chybějící chunk']); exit; }
    stream_copy_to_stream($in, $fp);
    fclose($in);
}
fclose($fp);

// Rozhodni koncovku z MIME
$ext = 'jpg';
$mime = strtolower($info['mime']);
if ($mime === 'image/png')  $ext = 'png';
elseif ($mime === 'image/gif')  $ext = 'gif';
elseif ($mime === 'image/webp') $ext = 'webp';
else $ext = 'jpg'; // default

// Jméno souboru (bezpečně)
$galleryTitle = trim((string)($gal['title'] ?? ''));
$slug = slugify($galleryTitle) ?: 'galerie';
$filename = $slug . "." . $ext;  // případné kolize ošetří cyklus níže

// Unikátní název
$targetPath = $galPath . "/" . $filename;
$i = 1;
while (file_exists($targetPath)) {
    $filename = $slug . "-" . $i . "." . $ext;
    $targetPath = $galPath . "/" . $filename;
    $i++;
}

// Přesuň kompletovaný do cíle
@rename($finalTmp, $targetPath);

// úklid chunků
$files = @glob($tmpPath . "/chunk_*");
if ($files) foreach ($files as $f) @unlink($f);
@rmdir($tmpPath);

// ===== post-processing (autorotate, resize, thumb) =====
maybeAutorotateJpeg($targetPath, $ext);
resizeIntoBox($targetPath, $targetPath, $ext, 1600, 1200, false, 85);

// čtvercový náhled do thumbs/
$thumbs = $galPath . "/thumbs";
if (!is_dir($thumbs)) @mkdir($thumbs, 0777, true);
$thumbPath = $thumbs . "/" . $filename;
makeSquareThumb($targetPath, $thumbPath, $ext, 480, false, 85);

// ===== ulož do DB =====
$res = $conn->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_photos WHERE gallery_id = " . (int)$galleryId);
list($maxSort) = $res->fetch_row();
$sort = (int)$maxSort + 1;

$title = $galleryTitle;
$ins = $conn->prepare("INSERT INTO gallery_photos (gallery_id, filename, title, sort_order, uploaded_at) VALUES (?,?,?,?, NOW())");
$ins->bind_param("issi", $galleryId, $filename, $title, $sort);
$ins->execute();
$newId = $ins->insert_id;
$ins->close();

// URL pro frontend (stejná konvence jako jinde)
$relUrl = "/uploads/galleries/$galleryId/" . rawurlencode($filename);

// hotovo
header("Content-Type: application/json; charset=utf-8");
echo json_encode([
  'ok' => true,
  'photo' => [
    'id' => (int)$newId,
    'url' => $relUrl,
    'title' => $title,
    'uploaded_at' => date('d.m.Y H:i')
  ]
]);
exit;

/* ===== Pomocné funkce – stejné jako v upload.php, jen zkopírované sem ===== */
function imageCreateFromExt(string $path, string $ext) {
    switch (strtolower($ext)) {
        case 'jpg': case 'jpeg': return @imagecreatefromjpeg($path);
        case 'png':  return @imagecreatefrompng($path);
        case 'gif':  return @imagecreatefromgif($path);
        case 'webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    } return false;
}
function imageSaveToExt($img, string $path, string $ext, int $quality = 85): bool {
    switch (strtolower($ext)) {
        case 'jpg': case 'jpeg': return imagejpeg($img, $path, $quality);
        case 'png':  return imagepng($img, $path, 6);
        case 'gif':  return imagegif($img, $path);
        case 'webp': return function_exists('imagewebp') ? imagewebp($img, $path, $quality) : false;
    } return false;
}
function prepareTransparentCanvas(int $w, int $h, string $ext) {
    $dst = imagecreatetruecolor($w, $h);
    if (in_array(strtolower($ext), ['png','webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
    }
    return $dst;
}
function resizeIntoBox(string $srcPath, string $destPath, string $ext, int $maxW, int $maxH, bool $allowUpscale = false, int $quality = 85): bool {
    $info = @getimagesize($srcPath); if (!$info) return false;
    [$ow,$oh] = $info;
    if (!$allowUpscale && $ow <= $maxW && $oh <= $maxH) {
        return ($srcPath === $destPath) ? true : copy($srcPath, $destPath);
    }
    $ratio = min($maxW/$ow, $maxH/$oh);
    $nw = max(1,(int)floor($ow*$ratio)); $nh = max(1,(int)floor($oh*$ratio));
    $src = imageCreateFromExt($srcPath, $ext); if (!$src) return false;
    $dst = prepareTransparentCanvas($nw,$nh,$ext);
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$ow,$oh);
    $ok = imageSaveToExt($dst,$destPath,$ext,$quality);
    imagedestroy($src); imagedestroy($dst); return $ok;
}
function makeSquareThumb(string $srcPath, string $destPath, string $ext, int $target, bool $allowUpscale=false, int $quality=85): bool {
    $info = @getimagesize($srcPath); if (!$info) return false;
    [$ow,$oh] = $info; $shorter = min($ow,$oh);
    $scale = $target / $shorter; if (!$allowUpscale && $shorter < $target) $scale = 1.0;
    $nw = max(1,(int)floor($ow*$scale)); $nh = max(1,(int)floor($oh*$scale));
    $src = imageCreateFromExt($srcPath,$ext); if (!$src) return false;
    $resized = prepareTransparentCanvas($nw,$nh,$ext);
    imagecopyresampled($resized,$src,0,0,0,0,$nw,$nh,$ow,$oh);
    $cropSize = min($target, min($nw,$nh));
    $cx = (int)floor(($nw - $cropSize)/2); $cy = (int)floor(($nh - $cropSize)/2);
    $thumb = prepareTransparentCanvas($cropSize,$cropSize,$ext);
    imagecopy($thumb,$resized,0,0,$cx,$cy,$cropSize,$cropSize);
    $ok = imageSaveToExt($thumb,$destPath,$ext,$quality);
    imagedestroy($src); imagedestroy($resized); imagedestroy($thumb); return $ok;
}
function maybeAutorotateJpeg(string $path, string $ext): void {
    if (!in_array(strtolower($ext), ['jpg','jpeg'])) return;
    if (!function_exists('exif_read_data')) return;
    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) return;
    $orientation = (int)$exif['Orientation']; if ($orientation === 1) return;
    $img = @imagecreatefromjpeg($path); if (!$img) return;
    switch ($orientation) {
        case 3: $img = imagerotate($img, 180, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0);  break;
    }
    imagejpeg($img, $path, 90);
    imagedestroy($img);
}
