<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: list.php?error=bad_id"); exit; }

$conn->begin_transaction();

try {
    $delPhotos = $conn->prepare("DELETE FROM gallery_photos WHERE gallery_id=?");
    $delPhotos->bind_param("i", $id);
    $delPhotos->execute();
    $delPhotos->close();

    // (B) smaž galerii (u CASCADE tím z DB zmizí i fotky)
    $delGal = $conn->prepare("DELETE FROM galleries WHERE id=?");
    $delGal->bind_param("i", $id);
    $delGal->execute();
    $affected = $delGal->affected_rows;
    $delGal->close();

    if ($affected <= 0) { throw new Exception("Galerie nenalezena."); }

    $conn->commit();

} catch (Throwable $e) {
    $conn->rollback();
    header("Location: list.php?error=delete_failed");
    exit;
}

// (C) úklid souborů na disku – až po commit
$base = realpath(__DIR__ . "/../uploads");               // .../admin/uploads
$galDir = $base . "/galleries/" . $id;

if ($base && strpos($galDir, $base) === 0 && is_dir($galDir)) {
    rrmdir($galDir);  // smaže i thumbs/
}

header("Location: list.php?success=deleted");
exit;

/** Bezpečné rekurzivní smazání složky */
function rrmdir(string $dir): void {
    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
}
