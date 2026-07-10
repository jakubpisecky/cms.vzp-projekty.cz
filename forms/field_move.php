<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$id     = intval($_GET['id'] ?? 0);
$formId = intval($_GET['form_id'] ?? 0);
$dir    = $_GET['dir'] ?? '';

if (
    $id <= 0
    || $formId <= 0
    || !in_array($dir, ['up', 'down'], true)
) {
    header("Location: list.php");
    exit;
}

/*
 * Načteme přesouvané pole.
 * Kontrolujeme zároveň, že skutečně patří danému formuláři.
 */
$stmt = $conn->prepare("
    SELECT id, label, sort_order
    FROM form_fields
    WHERE id = ?
      AND form_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $id, $formId);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    header("Location: fields.php?id=" . $formId);
    exit;
}

$currentOrder = (int)$current['sort_order'];

/*
 * Najdeme nejbližší pole nad nebo pod aktuálním polem.
 */
if ($dir === 'up') {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM form_fields
        WHERE form_id = ?
          AND (
                sort_order < ?
                OR (sort_order = ? AND id < ?)
              )
        ORDER BY sort_order DESC, id DESC
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, sort_order
        FROM form_fields
        WHERE form_id = ?
          AND (
                sort_order > ?
                OR (sort_order = ? AND id > ?)
              )
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
}

$stmt->bind_param(
    "iiii",
    $formId,
    $currentOrder,
    $currentOrder,
    $id
);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($other) {
    $otherId = (int)$other['id'];
    $otherOrder = (int)$other['sort_order'];

    /*
     * Když mají obě pole shodné pořadí, nejdřív pořadí normalizujeme.
     */
    if ($currentOrder === $otherOrder) {
        $stmt = $conn->prepare("
            SELECT id
            FROM form_fields
            WHERE form_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $result = $stmt->get_result();

        $newOrder = 10;

        $updateStmt = $conn->prepare("
            UPDATE form_fields
            SET sort_order = ?
            WHERE id = ?
              AND form_id = ?
            LIMIT 1
        ");

        while ($row = $result->fetch_assoc()) {
            $fieldId = (int)$row['id'];

            $updateStmt->bind_param(
                "iii",
                $newOrder,
                $fieldId,
                $formId
            );
            $updateStmt->execute();

            $newOrder += 10;
        }

        $updateStmt->close();
        $stmt->close();

        /*
         * Po normalizaci načteme pořadí obou polí znovu.
         */
        $stmt = $conn->prepare("
            SELECT id, sort_order
            FROM form_fields
            WHERE id IN (?, ?)
              AND form_id = ?
        ");
        $stmt->bind_param("iii", $id, $otherId, $formId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ((int)$row['id'] === $id) {
                $currentOrder = (int)$row['sort_order'];
            }

            if ((int)$row['id'] === $otherId) {
                $otherOrder = (int)$row['sort_order'];
            }
        }

        $stmt->close();
    }

    $conn->begin_transaction();

    try {
        /*
         * Dočasná hodnota zabrání problémům,
         * pokud by jednou bylo sort_order unikátní.
         */
        $temporaryOrder = -1;

        $stmt = $conn->prepare("
            UPDATE form_fields
            SET sort_order = ?
            WHERE id = ?
              AND form_id = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "iii",
            $temporaryOrder,
            $id,
            $formId
        );
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE form_fields
            SET sort_order = ?
            WHERE id = ?
              AND form_id = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "iii",
            $currentOrder,
            $otherId,
            $formId
        );
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE form_fields
            SET sort_order = ?
            WHERE id = ?
              AND form_id = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "iii",
            $otherOrder,
            $id,
            $formId
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        logAction(
            "Změněno pořadí pole '{$current['label']}' ve formuláři ID $formId"
        );

    } catch (Throwable $e) {
        $conn->rollback();

        error_log(
            'Form field move error: ' . $e->getMessage()
        );
    }
}

header("Location: fields.php?id=" . $formId);
exit;