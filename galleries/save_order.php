<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');

header('Content-Type: application/json; charset=utf-8');

// povolíme jen POST (když přijde něco jiného, ať víš proč se nic neděje)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// načti JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// validace payloadu
if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

// transakce (když něco selže, nic se neuloží napůl)
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("UPDATE gallery_photos SET sort_order = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // očekáváme [{id, sort_order}], fallback na pořadí v poli
    foreach ($data as $i => $item) {
        if (!isset($item['id'])) continue;
        $id = (int)$item['id'];
        $sort_order = isset($item['sort_order']) ? (int)$item['sort_order'] : ($i + 1);

        $stmt->bind_param("ii", $sort_order, $id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed for id '.$id.': '.$stmt->error);
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
