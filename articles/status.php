<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('articles');

$id = intval($_GET['id'] ?? 0);
$to = strtolower(trim($_GET['to'] ?? ''));

// povolené hodnoty
$allowed = ['published', 'draft'];
if (!in_array($to, $allowed, true)) {
    $to = ''; // když nepřijde validní "to", budeme status prostě přepínat
}

$where = isAdmin() ? "id=?" : "id=? AND author_id=" . intval($_SESSION['admin_id']);

// zjistit aktuální status a zároveň oprávnění k článku
$stmt = $conn->prepare("SELECT status FROM articles WHERE $where");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: list.php?error=not_found");
    exit;
}

// spočítat nový stav
$newStatus = $to !== '' ? $to : (($row['status'] === 'published') ? 'draft' : 'published');

// update
$stmt = $conn->prepare("UPDATE articles SET status=? WHERE id=?");
$stmt->bind_param("si", $newStatus, $id);
$stmt->execute();
$stmt->close();

// zpět na list s hláškou
header("Location: list.php?toggled=" . urlencode($newStatus));
exit;
