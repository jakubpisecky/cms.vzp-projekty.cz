<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('forms');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM forms
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$form) {
    header("Location: list.php");
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT id
        FROM form_submissions
        WHERE form_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    $submissionIds = [];

    while ($row = $res->fetch_assoc()) {
        $submissionIds[] = (int)$row['id'];
    }

    $stmt->close();

    if (!empty($submissionIds)) {
        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $types = str_repeat('i', count($submissionIds));

        $stmt = $conn->prepare("
            DELETE FROM form_submission_values
            WHERE submission_id IN ($placeholders)
        ");
        $stmt->bind_param($types, ...$submissionIds);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("
        DELETE FROM form_submissions
        WHERE form_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        DELETE FROM form_fields
        WHERE form_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        DELETE FROM forms
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    logAction("Smazán formulář '{$form['name']}'");

    header("Location: list.php?deleted=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    header("Location: list.php?delete_error=1");
    exit;
}