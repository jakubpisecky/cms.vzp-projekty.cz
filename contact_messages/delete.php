<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('contact_messages');

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT name, email, subject 
        FROM contact_messages 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name, $email, $subject);
    $stmt->fetch();
    $stmt->close();

    if ($email || $name || $subject) {
        $stmt = $conn->prepare("
            DELETE FROM contact_messages 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        logAction("Smazána kontaktní zpráva '$subject' od '$email'");
    }
}

header("Location: index.php?deleted=1");
exit;