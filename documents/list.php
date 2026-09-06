<?php 
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('documents');

$picker  = $_GET['picker'] ?? ''; // '', 'tinymce'
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = $picker ? 20 : 20;

$created = isset($_GET['created']);
$deleted = isset($_GET['deleted']);

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(title LIKE ? OR filename LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// počet
$sqlCount = "SELECT COUNT(*) AS c FROM documents {$whereSql}";
$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalDocuments = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalDocuments / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$paramsUrl = $_GET;
unset($paramsUrl['page']);
$base = http_build_query($paramsUrl);
$base = $base ? ('?' . $base . '&') : '?';

// data
$sql = "
    SELECT id, title, filename, uploaded_at
    FROM documents
    {$whereSql}
    ORDER BY uploaded_at DESC, id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if (!$picker) {
    include "../includes/header.php";
} else {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <title>Knihovna dokumentů</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/assets/css/admin.css">

        <script>
        function selectDocument(url, text) {
            if (window.parent && typeof window.parent.tinymceActiveCallback === 'function') {
                let selected = '';

                try {
                    selected = window.parent.tinymce?.activeEditor?.selection?.getContent({ format: 'text' }) || '';
                } catch (e) {}

                const linkText = selected || (text || '');

                window.parent.tinymceActiveCallback(url, {
                    text: linkText,
                    title: text || linkText
                });

                if (window.parent._editorImageModal) {
                    window.parent._editorImageModal.hide();
                }

                return;
            }

            alert('Nepodařilo se předat dokument do editoru.');
        }
        </script>
    </head>
    <body class="bg-light">
    <?php
}
?>

<div class="container-fluid py-4">

    <?php if (!$picker): ?>
        <div class="admin-page-header-content mb-4">
            <div>
                <h2 class="mb-1">
                    Dokumenty <small class="text-muted">(<?= (int)$totalDocuments ?>)</small>
                </h2>
                <p class="text-muted mb-0">Správa nahraných dokumentů</p>
            </div>

            <div class="responsive-actions">
                <a href="add.php" class="btn btn-success">
                    <i class="bi bi-upload me-1"></i> Nahrát dokument
                </a>
            </div>
        </div>
    <?php else: ?>
        <h4 class="mb-3">
            Vyberte dokument
            <small class="text-muted">(<?= (int)$totalDocuments ?>)</small>
        </h4>
    <?php endif; ?>

    <?php if (!$picker && $created): ?>
        <div class="alert alert-success">Dokument byl uložen.</div>
    <?php endif; ?>

    <?php if (!$picker && $deleted): ?>
        <div class="alert alert-success">Dokument byl smazán.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <?php if ($picker): ?>
                    <input type="hidden" name="picker" value="<?= e($picker) ?>">
                <?php endif; ?>

                <div class="col-lg-7">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle názvu nebo názvu souboru..."
                           value="<?= e($q) ?>">
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Hledat
                    </button>
                </div>

                <?php if ($q !== ''): ?>
                    <div class="col-auto">
                        <a href="list.php<?= $picker ? '?picker=' . urlencode($picker) : '' ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Zrušit
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 bg-white">
                <thead class="table-light">
                    <tr>
                        <th width="70" class="text-center">ID</th>
                        <th>Název</th>
                        <th>Soubor</th>
                        <th width="170">Datum nahrání</th>
                        <th width="130" class="text-center">Akce</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($doc = $result->fetch_assoc()): ?>
                        <?php
                            $id = (int)$doc['id'];
                            $titleRaw = $doc['title'] ?? '';
                            $filenameRaw = $doc['filename'] ?? '';

                            $title = e($titleRaw);
                            $filename = e($filenameRaw);

                            $url = "/uploads/documents/" . rawurlencode($filenameRaw);
                        ?>

                        <tr>
                            <td class="text-center"><?= $id ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= $title !== '' ? $title : '<span class="text-muted">Bez názvu</span>' ?>
                                </div>
                            </td>

                            <td>
                                <?php if ($filenameRaw !== ''): ?>
                                    <a href="<?= e($url) ?>" target="_blank" rel="noopener">
                                        <?= $filename ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= e(formatDateCz($doc['uploaded_at'])) ?>
                            </td>

                            <td class="text-center text-nowrap">
                                <?php if ($picker): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-primary"
                                            onclick="selectDocument('<?= e($url) ?>','<?= htmlspecialchars($titleRaw, ENT_QUOTES) ?>')">
                                        Vybrat
                                    </button>
                                <?php else: ?>
                                    <a href="delete.php?id=<?= $id ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Opravdu smazat dokument?');"
                                       title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Žádné dokumenty nebyly nalezeny.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (function_exists('renderPagination')): ?>
        <?php renderPagination($totalPages, $page, $base); ?>
    <?php elseif ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $base ?>page=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php
if (!$picker) {
    include "../includes/footer.php";
} else {
    echo "</body></html>";
}
?>