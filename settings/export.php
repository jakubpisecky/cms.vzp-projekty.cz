<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('settings');

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="settings-export-'.date('Ymd-His').'.json"');

$res = $conn->query("SELECT `key`,`value` FROM settings ORDER BY `key` ASC");
$out = [];
while ($row = $res->fetch_assoc()) { $out[$row['key']] = $row['value']; }
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
