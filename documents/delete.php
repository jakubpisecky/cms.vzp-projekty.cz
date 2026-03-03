<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('documents');
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // Najdeme soubor
    $stmt = $conn->prepare("SELECT filename FROM documents WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($filename);
    $stmt->fetch();
    $stmt->close();

    // Smažeme fyzický soubor
    if ($filename && file_exists("../uploads/documents/" . $filename)) {
        unlink("../uploads/documents/" . $filename);
    }

    // Smažeme z DB
    $stmt = $conn->prepare("DELETE FROM documents WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
	
	logAction("Smazán dokument '$filename'");
}

header("Location: list.php?deleted=1");
exit;
