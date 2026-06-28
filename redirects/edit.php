<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('redirects');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neplatné ID přesměrování.");
}

function normalizeRedirectPath(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }

    if ($url[0] !== '/') {
        $url = '/' . $url;
    }

    return $url;
}

$stmt = $conn->prepare("
    SELECT *
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

$source_url = $redirect['source_url'] ?? '';
$target_url = $redirect['target_url'] ?? '';
$status_code = (int)($redirect['status_code'] ?? 301);
$is_active = (int)($redirect['is_active'] ?? 1);
$note = $redirect['note'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $source_url = normalizeRedirectPath($_POST['source_url'] ?? '');
    $target_url = normalizeRedirectPath($_POST['target_url'] ?? '');
    $status_code = (int)($_POST['status_code'] ?? 301);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $note = trim($_POST['note'] ?? '');

    if ($source_url === '') {
        $error = "Zdrojová URL je povinná.";
    } elseif ($target_url === '') {
        $error = "Cílová URL je povinná.";
    } elseif ($source_url === $target_url) {
        $error = "Zdrojová a cílová URL nesmí být stejná.";
    } elseif (!in_array($status_code, [301, 302], true)) {
        $error = "Neplatný typ přesměrování.";
    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM redirects
            WHERE source_url = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $source_url, $id);
        $stmt->execute();

        $exists = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($exists) {

            $error = "Tato zdrojová URL už existuje.";

        } else {

            $stmt = $conn->prepare("
                UPDATE redirects
                SET
                    source_url = ?,
                    target_url = ?,
                    status_code = ?,
                    is_active = ?,
                    note = ?
                WHERE id = ?
            ");

            if (!$stmt) {

                $error = "Nepodařilo se připravit SQL dotaz: " . $conn->error;

            } else {

                $stmt->bind_param(
                    "ssiisi",
                    $source_url,
                    $target_url,
                    $status_code,
                    $is_active,
                    $note,
                    $id
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    if (function_exists('logAction')) {
                        logAction("Upraveno přesměrování ID {$id}: {$source_url} → {$target_url}");
                    }

                    header("Location: list.php?updated=1");
                    exit;

                } else {

                    $error = "Nepodařilo se uložit změny: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Upravit přesměrování</h2>
                    <p class="text-muted mb-0"><?= htmlspecialchars($source_url) ?></p>
                </div>

                <div>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">

                    <form method="post">

                        <div class="mb-3">
                            <label for="source_url" class="form-label">
                                Zdrojová URL <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="source_url"
                                   id="source_url"
                                   class="form-control"
                                   placeholder="/pokus"
                                   value="<?= htmlspecialchars($source_url) ?>"
                                   required>

                            <div class="form-text">
                                Adresa, kterou návštěvník zadá.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="target_url" class="form-label">
                                Cílová URL <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="target_url"
                                   id="target_url"
                                   class="form-control"
                                   placeholder="/aktuality/pokusna-aktualita"
                                   value="<?= htmlspecialchars($target_url) ?>"
                                   required>

                            <div class="form-text">
                                Interní nebo externí URL.
                            </div>
                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label for="status_code" class="form-label">
                                    Typ přesměrování
                                </label>

                                <select name="status_code"
                                        id="status_code"
                                        class="form-select">

                                    <option value="301" <?= $status_code === 301 ? 'selected' : '' ?>>
                                        301 – trvalé
                                    </option>

                                    <option value="302" <?= $status_code === 302 ? 'selected' : '' ?>>
                                        302 – dočasné
                                    </option>

                                </select>
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-end">

                                <div class="form-check">
                                    <input type="checkbox"
                                           name="is_active"
                                           id="is_active"
                                           class="form-check-input"
                                           value="1"
                                           <?= $is_active === 1 ? 'checked' : '' ?>>

                                    <label for="is_active" class="form-check-label">
                                        Aktivní přesměrování
                                    </label>
                                </div>

                            </div>

                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">
                                Poznámka
                            </label>

                            <textarea name="note"
                                      id="note"
                                      rows="4"
                                      class="form-control"
                                      placeholder="Interní poznámka..."><?= htmlspecialchars($note) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">

                            <a href="list.php"
                               class="btn btn-outline-secondary">
                                Zrušit
                            </a>

                            <button type="submit"
                                    class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Uložit změny
                            </button>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>