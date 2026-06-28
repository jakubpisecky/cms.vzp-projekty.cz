<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('pictures');

$picker     = $_GET['picker'] ?? '';
$q          = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = $picker ? 24 : 20;

$created = isset($_GET['created']);
$deleted = isset($_GET['deleted']);
$updated = isset($_GET['updated']);

$categories = [];
$resCat = $conn->query("
    SELECT id, name
    FROM picture_categories
    ORDER BY sort_order ASC, name ASC
");

if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
}

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "(p.title LIKE ? OR p.filename LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if ($categoryId > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryId;
    $types .= "i";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

$sqlCount = "
    SELECT COUNT(*) AS c
    FROM pictures p
    LEFT JOIN picture_categories c ON c.id = p.category_id
    {$whereSql}
";

$stmt = $conn->prepare($sqlCount);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalPictures = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalPictures / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$paramsUrl = $_GET;
unset($paramsUrl['page']);
$base = http_build_query($paramsUrl);
$base = $base ? ('?' . $base . '&') : '?';

$sql = "
    SELECT 
        p.*,
        c.name AS category_name
    FROM pictures p
    LEFT JOIN picture_categories c ON c.id = p.category_id
    {$whereSql}
    ORDER BY p.uploaded_at DESC, p.id DESC
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
        <meta charset="UTF-8">
        <title>Knihovna obrázků</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/assets/css/admin.css">

        <script>
        function selectPicture(url, title) {
            var mode = new URLSearchParams(location.search).get('picker') || '';

            if (mode === 'tinymce') {
                if (window.parent && window.parent.tinymce) {
                    window.parent.postMessage({
                        mceAction: 'chooseImage',
                        url: url,
                        alt: title || ''
                    }, '*');
                } else {
                    alert('TinyMCE dialog není dostupný.');
                }
                return;
            }

            if (window.parent && typeof window.parent.filePickerCallback === 'function') {
                window.parent.filePickerCallback(url, title || '');
            } else {
                alert('Nenalezen callback pro výběr obrázku.');
            }
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
                    Knihovna obrázků <small class="text-muted">(<?= (int)$totalPictures ?>)</small>
                </h2>
                <p class="text-muted mb-0">Správa nahraných obrázků</p>
            </div>

            <div class="responsive-actions">
                <a href="add.php" class="btn btn-success">
                    <i class="bi bi-upload me-1"></i> Nahrát obrázek
                </a>

                <a href="categories.php" class="btn btn-outline-secondary">
                    <i class="bi bi-tags me-1"></i> Kategorie obrázků
                </a>
            </div>
        </div>

        <?php if ($created): ?>
            <div class="alert alert-success">Obrázek byl nahrán.</div>
        <?php endif; ?>

        <?php if ($updated): ?>
            <div class="alert alert-success">Obrázek byl upraven.</div>
        <?php endif; ?>

        <?php if ($deleted): ?>
            <div class="alert alert-success">Obrázek byl smazán.</div>
        <?php endif; ?>

    <?php else: ?>
        <h5 class="mb-3">
            <?php if ($picker === 'tinymce'): ?>
                Vyberte obrázek do TinyMCE
            <?php else: ?>
                Vyberte náhledový obrázek
            <?php endif; ?>

            <small class="text-muted">(<?= (int)$totalPictures ?>)</small>
        </h5>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <?php if ($picker): ?>
                    <input type="hidden" name="picker" value="<?= e($picker) ?>">
                <?php endif; ?>

                <div class="col-lg-4">
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Hledat podle názvu nebo názvu souboru..."
                           value="<?= e($q) ?>">
                </div>

                <div class="col-lg-3">
                    <select name="category_id" class="form-select">
                        <option value="0">Všechny kategorie</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"
                                <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Hledat
                    </button>
                </div>

                <?php if ($q !== '' || $categoryId > 0): ?>
                    <div class="col-auto">
                        <a href="list.php<?= $picker ? '?picker=' . urlencode($picker) : '' ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Zrušit
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($result->num_rows === 0): ?>

        <div class="card shadow-sm border-0">
            <div class="card-body text-center text-muted p-4">
                Žádné obrázky nebyly nalezeny.
            </div>
        </div>

    <?php else: ?>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php while ($picture = $result->fetch_assoc()): ?>
                <?php
                    $id          = (int)$picture['id'];
                    $filenameRaw = $picture['filename'];
                    $titleRaw    = $picture['title'] ?: $picture['filename'];

                    $filename = htmlspecialchars($filenameRaw, ENT_QUOTES);
                    $title    = htmlspecialchars($titleRaw, ENT_QUOTES);

                    $baseUrl = '/uploads/pictures/';
                    $baseFs  = rtrim($_SERVER['DOCUMENT_ROOT'] . $baseUrl, '/');

                    $nameNoExt = pathinfo($filenameRaw, PATHINFO_FILENAME);

                    $thumbWebpFs = $baseFs . '/' . $nameNoExt . '_thumb.webp';
                    $thumbJpgFs  = $baseFs . '/' . $nameNoExt . '_thumb.jpg';
                    $medWebpFs   = $baseFs . '/' . $nameNoExt . '_medium.webp';
                    $medJpgFs    = $baseFs . '/' . $nameNoExt . '_medium.jpg';

                    $thumbUrl = null;

                    if (is_file($thumbWebpFs)) {
                        $thumbUrl = $baseUrl . $nameNoExt . '_thumb.webp';
                    } elseif (is_file($thumbJpgFs)) {
                        $thumbUrl = $baseUrl . $nameNoExt . '_thumb.jpg';
                    }

                    $mediumUrl = null;

                    if (is_file($medWebpFs)) {
                        $mediumUrl = $baseUrl . $nameNoExt . '_medium.webp';
                    } elseif (is_file($medJpgFs)) {
                        $mediumUrl = $baseUrl . $nameNoExt . '_medium.jpg';
                    }

                    $origUrl = $baseUrl . $filenameRaw;

                    $gridSrc  = htmlspecialchars($thumbUrl ?: $mediumUrl ?: $origUrl, ENT_QUOTES);
                    $modalSrc = htmlspecialchars($mediumUrl ?: $origUrl, ENT_QUOTES);

                    $pickerTargetForTinymce = htmlspecialchars($origUrl, ENT_QUOTES);
                    $pickerTargetForThumb   = htmlspecialchars($origUrl, ENT_QUOTES);
                ?>

                <div class="col">
                    <div class="card h-100 d-flex flex-column shadow-sm border-0 photo-item" data-id="<?= $id ?>">

                        <img src="<?= $gridSrc ?>"
                             class="card-img-top img-fluid"
                             alt="<?= $title ?>"
                             <?php if ($picker): ?>
                                 style="cursor:pointer"
                                 onclick="selectPicture(
                                     '<?= ($picker === 'tinymce') ? $pickerTargetForTinymce : $pickerTargetForThumb ?>',
                                     '<?= $title ?>'
                                 )"
                             <?php else: ?>
                                 data-bs-toggle="modal"
                                 data-bs-target="#imageModal"
                                 data-img-src="<?= $modalSrc ?>"
                                 style="cursor: zoom-in"
                             <?php endif; ?>>

                        <div class="card-body p-2 text-center position-relative">
                            <?php if (!$picker): ?>
                                <div class="editable-title form-control-plaintext py-1"
                                     contenteditable="true"
                                     data-id="<?= $id ?>"
                                     onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
                                     onblur="updatePicTitle(this)"
                                     title="Klikni a uprav titulek"><?= e($titleRaw) ?></div>
                            <?php else: ?>
                                <small class="d-block"><?= $title ?></small>
                            <?php endif; ?>

                            <small class="text-muted d-block">
                                <?= e(formatDateCz($picture['uploaded_at'])) ?>
                            </small>

                            <?php if (!$picker): ?>
                                <div class="text-muted small mt-1">
                                    <?= e($filenameRaw) ?>
                                </div>

                                <select class="form-select form-select-sm mt-2 picture-category-select"
                                        data-id="<?= $id ?>">
                                    <option value="0">Bez kategorie</option>

                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>"
                                            <?= (int)($picture['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <?php if (!$picker): ?>
                            <div class="card-footer text-center p-2 mt-auto bg-white">
                                <a href="delete.php?id=<?= $id ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Opravdu smazat obrázek?');">
                                    <i class="bi bi-trash"></i> Smazat
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endwhile; ?>
        </div>

    <?php endif; ?>

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

<?php if (!$picker): ?>
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-0">
            <div class="modal-body p-0">
                <img src="" id="modalImage" class="img-fluid w-100 rounded" alt="Náhled obrázku">
            </div>

            <div class="modal-footer justify-content-end bg-dark">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">
                    Zavřít
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function updatePicTitle(el) {
    const id = el.dataset.id;
    let newTitle = (el.textContent || '').trim();

    if (!newTitle) {
        el.textContent = 'Klikni pro přidání titulku';
        el.classList.add('empty');
        newTitle = '';
    } else {
        el.classList.remove('empty');
    }

    fetch('update_title.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: `id=${encodeURIComponent(id)}&title=${encodeURIComponent(newTitle)}`
    })
    .then(r => r.text())
    .then(t => {
        if (t.trim() === 'OK') {
            showSavedBadge(id);
        } else {
            alert('Chyba při ukládání titulku: ' + t);
        }
    })
    .catch(() => alert('Chyba spojení se serverem.'));
}

function updatePicCategory(select) {
    const id = select.dataset.id;
    const categoryId = select.value;

    select.disabled = true;

    fetch('update_category.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: `id=${encodeURIComponent(id)}&category_id=${encodeURIComponent(categoryId)}`
    })
    .then(r => r.text())
    .then(t => {
        if (t.trim() !== 'OK') {
            alert('Chyba při ukládání kategorie: ' + t);
        } else {
            showSavedBadge(id);
        }
    })
    .catch(() => alert('Chyba spojení se serverem.'))
    .finally(() => {
        select.disabled = false;
    });
}

function showSavedBadge(photoId) {
    const cardBody = document.querySelector(`.photo-item[data-id="${photoId}"] .card-body`);

    if (!cardBody) return;

    const badge = document.createElement('div');
    badge.className = 'save-badge';
    badge.textContent = 'Uloženo';
    cardBody.appendChild(badge);

    requestAnimationFrame(() => badge.classList.add('show'));
    setTimeout(() => badge.classList.remove('show'), 1200);
    setTimeout(() => badge.remove(), 1500);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.editable-title').forEach(el => {
        if (!el.textContent.trim()) {
            el.textContent = 'Klikni pro přidání titulku';
            el.classList.add('empty');
        }
    });

    document.querySelectorAll('.picture-category-select').forEach(select => {
        select.addEventListener('change', function () {
            updatePicCategory(this);
        });
    });

    const modal = document.getElementById('imageModal');

    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (e) {
        const trigger = e.relatedTarget;
        const url = trigger?.getAttribute('data-img-src') || trigger?.src || '';
        const alt = trigger?.getAttribute('alt') || 'Náhled obrázku';
        const img = modal.querySelector('#modalImage');

        if (img) {
            img.src = url;
            img.alt = alt;
        }
    });

    modal.addEventListener('hidden.bs.modal', function () {
        const img = modal.querySelector('#modalImage');

        if (img) {
            img.src = '';
        }
    });
});
</script>
<?php endif; ?>
<script>
function openMultiImagePicker() {
    const iframe = document.querySelector('#imagePickerModal iframe');

    window.multiImagePickerCallback = function(url) {
        if (typeof window.blockImagesAdd === 'function') {
            window.blockImagesAdd(url);
        }

        const modalEl = document.getElementById('imagePickerModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
        }
    };

    if (iframe) {
        iframe.src = '/pictures/list.php?picker=multi';
    }
}
</script>
<style>
.card-img-top {
    object-fit: cover;
    height: 220px;
}

.editable-title {
    min-height: 28px;
    outline: none;
    border-radius: .5rem;
}

.editable-title:focus {
    background: rgba(13,110,253,.08);
    box-shadow: inset 0 0 0 1px rgba(13,110,253,.4);
}

.editable-title.empty {
    color: #6c757d;
    font-style: italic;
}

.save-badge {
    position: absolute;
    right: 8px;
    top: 8px;
    background: #198754;
    color: #fff;
    padding: .15rem .4rem;
    border-radius: .4rem;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity .15s ease, transform .15s ease;
    font-size: .75rem;
    z-index: 2;
}

.save-badge.show {
    opacity: 1;
    transform: translateY(0);
}
</style>

<?php
if (!$picker) {
    include "../includes/footer.php";
} else {
    echo "</body></html>";
}
?>