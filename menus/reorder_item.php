<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('menus');
header('Content-Type: application/json; charset=utf-8');

$id    = (int)($_POST['id'] ?? 0);
$dir   = $_POST['dir'] ?? '';
$dayId = (int)($_POST['day_id'] ?? 0);

if (!$id || !$dayId || !in_array($dir, ['up','down'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'Bad input']); exit;
}

try {
  $conn->begin_transaction();

  $stmt = $conn->prepare("SELECT id, section_id, sort_order FROM menu_items WHERE id=? AND day_id=? FOR UPDATE");
  $stmt->bind_param("ii",$id,$dayId); $stmt->execute();
  $cur = $stmt->get_result()->fetch_assoc(); $stmt->close();
  if (!$cur) { $conn->rollback(); echo json_encode(['ok'=>false,'msg'=>'Not found']); exit; }

  $sec = (int)$cur['section_id']; $ord=(int)$cur['sort_order'];

  if ($dir==='up') {
    $nbr = $conn->prepare("SELECT id, sort_order FROM menu_items WHERE day_id=? AND section_id=? AND sort_order < ? ORDER BY sort_order DESC, id DESC LIMIT 1 FOR UPDATE");
  } else {
    $nbr = $conn->prepare("SELECT id, sort_order FROM menu_items WHERE day_id=? AND section_id=? AND sort_order > ? ORDER BY sort_order ASC, id ASC LIMIT 1 FOR UPDATE");
  }
  $nbr->bind_param("iii",$dayId,$sec,$ord); $nbr->execute();
  $n = $nbr->get_result()->fetch_assoc(); $nbr->close();

  if (!$n) { $conn->commit(); echo json_encode(['ok'=>true]); exit; }

  $u = $conn->prepare("UPDATE menu_items SET sort_order=? WHERE id=?");
  $u->bind_param("ii", $n['sort_order'], $id); $u->execute();
  $u->bind_param("ii", $ord, $n['id']);      $u->execute(); $u->close();

  // normalizace celé sekce
  $res = $conn->query("SELECT id FROM menu_items WHERE day_id={$dayId} AND section_id={$sec} ORDER BY sort_order ASC, id ASC");
  $o=10; $nu=$conn->prepare("UPDATE menu_items SET sort_order=? WHERE id=?");
  while ($row=$res->fetch_assoc()){ $nu->bind_param("ii",$o,$row['id']); $nu->execute(); $o+=10; }
  $nu->close();

  $conn->commit();
  echo json_encode(['ok'=>true]);
} catch(Throwable $e){
  if ($conn->errno) $conn->rollback();
  echo json_encode(['ok'=>false,'msg'=>'DB error']);
}
