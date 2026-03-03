<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('pictures');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: list.php?error=bad_id");
    exit;
}

// 1) Zjisti název souboru z DB
$stmt = $conn->prepare("SELECT filename FROM pictures WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($filename);
$stmt->fetch();
$stmt->close();

if (!$filename) {
    header("Location: list.php?error=not_found");
    exit;
}

// 2) Připrav cesty (absolutně vůči document rootu)
$docRoot      = rtrim($_SERVER['DOCUMENT_ROOT'] ?: realpath(__DIR__ . "/.."), '/');
$uploadDirAbs = $docRoot . "/uploads/pictures/";
$base         = pathinfo($filename, PATHINFO_FILENAME);

// 3) Kandidáti k odstranění – master + varianty (JPG-only) + staré WEBP
$paths = [
    $uploadDirAbs . $filename,            // master (aktuálně .jpg, historicky i jiná přípona)
    $uploadDirAbs . $base . '.jpg',       // master .jpg (pro případ převodu)
    $uploadDirAbs . $base . '.jpeg',
    $uploadDirAbs . $base . '.png',
    $uploadDirAbs . $base . '.gif',
    $uploadDirAbs . $base . '.webp',      // historicky

    $uploadDirAbs . $base . '_medium.jpg',
    $uploadDirAbs . $base . '_thumb.jpg',

    $uploadDirAbs . $base . '_medium.webp', // historicky
    $uploadDirAbs . $base . '_thumb.webp',  // historicky
];

// 4) Smaž fyzické soubory (jen uvnitř /uploads/pictures)
foreach (array_unique($paths) as $p) {
    if (strpos($p, $uploadDirAbs) === 0 && is_file($p)) {
        @unlink($p);
    }
}

// 5) Smaž záznam z DB a zaloguj
$stmt = $conn->prepare("DELETE FROM pictures WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

logAction("Smazán obrázek '{$filename}' (včetně variant)");

// 6) Zpět na seznam
header("Location: list.php?success=deleted");
exit;
