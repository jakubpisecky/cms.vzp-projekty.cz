<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('redirects');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neplatné ID přesměrování.");
}

// načtení redirectu kvůli logu
$stmt = $conn->prepare("
    SELECT source_url, target_url
    FROM redirects
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$redirect = $result->fetch_assoc();

$stmt->close();

if (!$redirect) {
    die("Přesměrování nebylo nalezeno.");
}

// smazání
$stmt = $conn->prepare("
    DELETE FROM redirects
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Nepodařilo se připravit SQL dotaz.");
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    if (function_exists('logAction')) {
        logAction(
            "Smazáno přesměrování ID {$id}: " .
            ($redirect['source_url'] ?? '') .
            " → " .
            ($redirect['target_url'] ?? '')
        );
    }

    $stmt->close();

    header("Location: list.php?deleted=1");
    exit;

} else {

    $error = $stmt->error;
    $stmt->close();

    die("Nepodařilo se smazat přesměrování: " . $error);
}
?>