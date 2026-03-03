<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('banners');

header('Content-Type: application/json; charset=utf-8');

$id  = (int)($_POST['id'] ?? 0);
$dir = $_POST['dir'] ?? '';

if ($id <= 0 || !in_array($dir, ['up','down'], true)) {
  echo json_encode(['ok' => false, 'msg' => 'Bad input']); exit;
}

try {
  $conn->begin_transaction();

  // aktuální záznam
  $stmt = $conn->prepare("SELECT id, sort_order FROM banners WHERE id=? FOR UPDATE");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $cur = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$cur) { $conn->rollback(); echo json_encode(['ok'=>false, 'msg'=>'Not found']); exit; }

  $curOrd = (int)$cur['sort_order'];

  // soused nahoru/dolů
  if ($dir === 'up') {
    $stmt = $conn->prepare("
      SELECT id, sort_order FROM banners
      WHERE sort_order < ?
      ORDER BY sort_order DESC, id DESC
      LIMIT 1 FOR UPDATE
    ");
  } else {
    $stmt = $conn->prepare("
      SELECT id, sort_order FROM banners
      WHERE sort_order > ?
      ORDER BY sort_order ASC, id ASC
      LIMIT 1 FOR UPDATE
    ");
  }
  $stmt->bind_param("i", $curOrd);
  $stmt->execute();
  $nbr = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // není soused -> nic se nemění
  if (!$nbr) { $conn->commit(); echo json_encode(['ok'=>true]); exit; }

  $nbrId  = (int)$nbr['id'];
  $nbrOrd = (int)$nbr['sort_order'];

  // prohoď hodnoty
  $u = $conn->prepare("UPDATE banners SET sort_order=? WHERE id=?");
  $u->bind_param("ii", $nbrOrd, $id);
  $u->execute();
  $u->bind_param("ii", $curOrd, $nbrId);
  $u->execute();
  $u->close();

  $conn->commit();

  // volitelné: znormalizuj na 10,20,30…
  $res = $conn->query("SELECT id FROM banners ORDER BY sort_order ASC, id ASC");
  $order = 10;
  $upd = $conn->prepare("UPDATE banners SET sort_order=? WHERE id=?");
  while ($row = $res->fetch_assoc()) {
    $iid = (int)$row['id'];
    $upd->bind_param("ii", $order, $iid);
    $upd->execute();
    $order += 10;
  }
  $upd->close();

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if ($conn->errno) { $conn->rollback(); }
  echo json_encode(['ok'=>false, 'msg'=>'DB error']); // nechceme HTML výpis chyby
}
