<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM gallery_photos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$photo) {
    header("Location: list.php");
    exit;
}

// Cesta k souboru
$galleryId = (int)$photo['gallery_id'];
$filepath  = "../../uploads/galleries/$galleryId/" . $photo['filename'];

if (file_exists($filepath)) {
    unlink($filepath);
}

// Smazání záznamu z databáze
$stmt = $conn->prepare("DELETE FROM gallery_photos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Log akce
$title = $photo['title'] ?? '';
$filename = $photo['filename'] ?? '';
if ($title) {
    logAction("Smazána fotografie '{$title}' (soubor: {$filename}) z galerie ID {$galleryId}");
} else {
    logAction("Smazána fotografie '{$filename}' z galerie ID {$galleryId}");
}

// Přesměrování zpět do galerie
header("Location: photos.php?gallery_id=$galleryId&deleted=1");
exit;
