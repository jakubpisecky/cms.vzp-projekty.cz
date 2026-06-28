<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM mailing_recipients WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: recipients.php");
exit;