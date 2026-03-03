<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('visits');
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("SELECT name, email FROM visits WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name, $email);
    $stmt->fetch();
    $stmt->close();

    if ($name) {
        $stmt = $conn->prepare("DELETE FROM visits WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        logAction("Smazána návštěva '$name' <$email>");
    }
}

header("Location: list.php");
exit;
