<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('redirects');

$source_url = '';
$target_url = '';
$status_code = 301;
$is_active = 1;
$note = '';
$error = '';

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
            LIMIT 1
        ");
        $stmt->bind_param("s", $source_url);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = "Tato zdrojová URL už v přesměrování existuje.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO redirects
                (source_url, target_url, status_code, is_active, note)
                VALUES (?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $error = "Nepodařilo se připravit SQL dotaz: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "ssiis",
                    $source_url,
                    $target_url,
                    $status_code,
                    $is_active,
                    $note
                );

                if ($stmt->execute()) {
                    $newId = (int)$stmt->insert_id;
                    $stmt->close();

                    if (function_exists('logAction')) {
                        logAction("Vytvořeno přesměrování ID {$newId}: {$source_url} → {$target_url}");
                    }

                    header("Location: list.php?created=1");
                    exit;
                } else {
                    $error = "Nepodařilo se uložit přesměrování: " . $stmt->error;
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
                    <h2 class="mb-1">Nové přesměrování</h2>
                    <p class="text-muted mb-0">Vytvoření URL aliasu nebo přesměrování</p>
                </div>
                <div>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post">

                        <div class="mb-3">
                            <label for="source_url" class="form-label">Zdrojová URL <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="source_url"
                                   id="source_url"
                                   class="form-control"
                                   placeholder="/pokus"
                                   value="<?= htmlspecialchars($source_url) ?>"
                                   required>
                            <div class="form-text">
                                Adresa, kterou uživatel zadá. Např. <code>/pokus</code>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="target_url" class="form-label">Cílová URL <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="target_url"
                                   id="target_url"
                                   class="form-control"
                                   placeholder="/aktuality/pokusna-aktualita"
                                   value="<?= htmlspecialchars($target_url) ?>"
                                   required>
                            <div class="form-text">
                                Kam se má návštěvník přesměrovat. Může být interní URL nebo externí adresa.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status_code" class="form-label">Typ přesměrování</label>
                                <select name="status_code" id="status_code" class="form-select">
                                    <option value="301" <?= (int)$status_code === 301 ? 'selected' : '' ?>>301 – trvalé</option>
                                    <option value="302" <?= (int)$status_code === 302 ? 'selected' : '' ?>>302 – dočasné</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox"
                                           name="is_active"
                                           id="is_active"
                                           class="form-check-input"
                                           value="1"
                                           <?= (int)$is_active === 1 ? 'checked' : '' ?>>
                                    <label for="is_active" class="form-check-label">
                                        Aktivní přesměrování
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">Poznámka</label>
                            <textarea name="note"
                                      id="note"
                                      rows="4"
                                      class="form-control"
                                      placeholder="Volitelná interní poznámka..."><?= htmlspecialchars($note) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="list.php" class="btn btn-outline-secondary">Zrušit</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Uložit přesměrování
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>