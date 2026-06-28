<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('team');

$id  = intval($_GET['id'] ?? 0);
$dir = $_GET['dir'] ?? '';

if ($id <= 0 || !in_array($dir, ['up', 'down'], true)) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, sort_order
    FROM team_members
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    header("Location: list.php");
    exit;
}

$currentOrder = (int)$current['sort_order'];

if ($dir === 'up') {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM team_members
        WHERE sort_order < ?
        ORDER BY sort_order DESC, id DESC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM team_members
        WHERE sort_order > ?
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
}

$stmt->bind_param("i", $currentOrder);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($other) {
    $otherId = (int)$other['id'];
    $otherOrder = (int)$other['sort_order'];

    $stmt = $conn->prepare("
        UPDATE team_members
        SET sort_order = ?
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $otherOrder, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE team_members
        SET sort_order = ?
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $currentOrder, $otherId);
    $stmt->execute();
    $stmt->close();
}

header("Location: list.php");
exit;