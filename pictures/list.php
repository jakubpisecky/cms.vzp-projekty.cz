<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

$picker = $_GET['picker'] ?? ''; // '', 'thumb', 'tinymce'
$totalPictures = (int)$conn->query("SELECT COUNT(*) FROM pictures")->fetch_row()[0]; // ← přidáno
$result = $conn->query("SELECT * FROM pictures ORDER BY uploaded_at DESC");

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
        /**
         * Jediná funkce pro výběr. Podle režimu:
         *  - tinymce: pošle zprávu do TinyMCE dialogu (vyplní pole "Zdroj")
         *  - thumb:   zavolá parent.filePickerCallback(url) pro náhledové obrázky
         */
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
    <body class="bg-light p-3">
    <?php
}
?>

<div class="container py-4">
    <?php if (!$picker): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Knihovna obrázků <small class="text-muted">(<?= $totalPictures ?>)</small></h2>
            <a href="add.php" class="btn btn-success">
                <i class="bi bi-upload me-1"></i> Nahrát
            </a>
        </div>
    <?php else: ?>
        <h5 class="mb-3">
            <?php if ($picker === 'tinymce'): ?>
                Vyberte obrázek do TinyMCE (vyplní pole „Zdroj“ v dialogu)
            <?php else: ?>
                Vyberte náhledový obrázek
            <?php endif; ?>
        </h5>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4">
        <?php while ($picture = $result->fetch_assoc()):
    $id          = (int)$picture['id'];
    $filenameRaw = $picture['filename']; // pro práci se jménem bez HTML escapů
    $filename    = htmlspecialchars($filenameRaw, ENT_QUOTES);
    $titleRaw    = $picture['title'] ?: $picture['filename'];
    $title       = htmlspecialchars($titleRaw, ENT_QUOTES);

    $baseUrl = '/uploads/pictures/';
    $baseFs  = rtrim($_SERVER['DOCUMENT_ROOT'] . $baseUrl, '/');
    $nameNoExt = pathinfo($filenameRaw, PATHINFO_FILENAME);

    // Varianty na FS (preferuj webp)
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

    // Co budeme zobrazovat v gridu
    $gridSrc = htmlspecialchars($thumbUrl ?: $mediumUrl ?: $origUrl, ENT_QUOTES);

    // Co pošleme ven při výběru do pickerů
$pickerTargetForTinymce = htmlspecialchars($origUrl, ENT_QUOTES);
$pickerTargetForThumb   = htmlspecialchars($origUrl, ENT_QUOTES);


    // Co dáme do modalu (větší obrázek)
    $modalSrc = htmlspecialchars($mediumUrl ?: $origUrl, ENT_QUOTES);
?>

        <div class="col">
            <div class="card h-100 d-flex flex-column shadow-sm photo-item" data-id="<?= $id ?>">
                <img
    src="<?= $gridSrc ?>"
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
    <?php endif; ?>
>

                <div class="card-body p-2 text-center">
                    <?php if (!$picker): ?>
                        <div
                            class="editable-title form-control-plaintext py-1"
                            contenteditable="true"
                            data-id="<?= $id ?>"
                            onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
                            onblur="updatePicTitle(this)"
                            title="Klikni a uprav titulek"
                            ><?= htmlspecialchars($titleRaw) ?></div>
                    <?php else: ?>
                        <small class="d-block"><?= $title ?></small>
                    <?php endif; ?>
                    <small class="text-muted d-block"><?= formatDateCz($picture['uploaded_at']) ?></small>
                </div>

                <?php if (!$picker): ?>
                    <div class="card-footer text-center p-2 mt-auto">
                        <a href="delete.php?id=<?= $id ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Opravdu smazat obrázek?')">
                            <i class="bi bi-trash"></i> Smazat
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php if (!$picker): ?>
<!-- Modal pro zvětšení obrázku -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark text-white border-0">
      <div class="modal-body p-0">
        <img src="" id="modalImage" class="img-fluid w-100 rounded" alt="Náhled obrázku">
      </div>
      <div class="modal-footer justify-content-end bg-dark">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Zavřít</button>
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
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('imageModal');
  if (!modal) return; // na stránkách bez modálu se nic nespustí

  modal.addEventListener('show.bs.modal', function (e) {
    const trigger = e.relatedTarget; // element, na který se kliklo (tvé <img>)
    const url = trigger?.getAttribute('data-img-src') || trigger?.src || '';
    const alt = trigger?.getAttribute('alt') || 'Náhled obrázku';
    const img = modal.querySelector('#modalImage');
    if (img) { img.src = url; img.alt = alt; }
  });

  modal.addEventListener('hidden.bs.modal', function () {
    const img = modal.querySelector('#modalImage');
    if (img) img.src = ''; // uvolní paměť u velkých fotek
  });
});
</script>

<?php endif; ?>

<style>
/* Stejný vzhled jako u galerie */
.card-img-top { object-fit: cover; height: 220px; }

/* Inline edit vizuál + badge jako u galerie */
.editable-title {
  min-height: 28px;
  outline: none;
  border-radius: .5rem;
}
.editable-title:focus {
  background: rgba(13,110,253,.08);
  box-shadow: inset 0 0 0 1px rgba(13,110,253,.4);
}
.editable-title.empty { color: #6c757d; font-style: italic; }

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
}
.save-badge.show { opacity: 1; transform: translateY(0); }
</style>

<?php
if (!$picker) {
    include "../includes/footer.php";
} else {
    echo "</body></html>";
}
?>
