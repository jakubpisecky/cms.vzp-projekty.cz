<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
requirePermission('galleries');


$galleryId = intval($_GET['gallery_id'] ?? 0);

// Základní kontrola parametru
if (!$galleryId) {
    header("Location: list.php");
    exit;
}

// Načteme galerii
$stmt = $conn->prepare("SELECT * FROM galleries WHERE id = ?");
$stmt->bind_param("i", $galleryId);
$stmt->execute();
$gallery = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Pokud galerie neexistuje, vrať na seznam
if (!$gallery) {
    header("Location: list.php");
    exit;
}

// Načteme fotky (seřazeno podle sort_order)
$stmt = $conn->prepare("SELECT * FROM gallery_photos WHERE gallery_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("i", $galleryId);
$stmt->execute();
$photos = $stmt->get_result();

include "../includes/header.php";
?>
<div class="container py-4">
    <h2 class="mb-4">Fotky v&nbsp;galerii: <?= htmlspecialchars($gallery['title']) ?></h2>

    <div class="mb-3 d-flex gap-2">
        <a href="list.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Zpět na seznam galerií
        </a>
    </div>

    <!-- DROP ZÓNA -->
    <!-- DROP ZÓNA (Dropzone + chunking) -->
<form id="dzUpload"
      class="dropzone border rounded p-0 bg-white mb-4"
      action="upload_chunk.php?gallery_id=<?= (int)$galleryId ?>">
  <div class="dz-message py-5 text-muted">
    <div class="mb-2"><i class="bi bi-cloud-arrow-up fs-1"></i></div>
    <div class="fw-semibold">Přetáhněte sem obrázky nebo klikněte pro výběr</div>
    <small class="d-block">Podporované: JPG, PNG, GIF, WEBP. Velké soubory se nahrávají po částech.</small>
  </div>

  <!-- Celkový progress -->
  <div class="px-3 pb-3" id="dzTotalProgressWrap" style="display:none;">
    <div class="progress" role="progressbar" aria-label="Celkový průběh">
      <div class="progress-bar" id="dzTotalProgress" style="width:0%"></div>
    </div>
  </div>
</form>


    <!-- NÁHLEDY -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4" id="photo-list">
        <?php while ($photo = $photos->fetch_assoc()):
            $pid      = (int)$photo['id'];
            $filename = htmlspecialchars($photo['filename'], ENT_QUOTES);
            $titleRaw = trim((string)($photo['title'] ?? ''));
            $titleOut = htmlspecialchars($titleRaw !== '' ? $titleRaw : 'Klikni pro přidání titulku', ENT_QUOTES);
            $altOut   = htmlspecialchars($titleRaw !== '' ? $titleRaw : 'Fotka', ENT_QUOTES);
            $imgUrl   = "/uploads/galleries/{$galleryId}/{$filename}";
        ?>
            <div class="col photo-item" data-id="<?= $pid ?>">
                <div class="card h-100 d-flex flex-column shadow-sm">
                    <img
                        src="<?= $imgUrl ?>"
                        class="card-img-top img-fluid"
                        alt="<?= $altOut ?>"
                        loading="lazy"
                        style="cursor: zoom-in"
                        data-bs-toggle="modal"
                        data-bs-target="#imageModal"
                        data-img-src="<?= $imgUrl ?>"
                    >

                    <div class="card-body p-2 text-center">
                        <div
                            class="editable-title form-control-plaintext py-1"
                            contenteditable="true"
                            data-id="<?= $pid ?>"
                            onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
                            onblur="updateTitle(this)"
                            title="Klikni a uprav titulek"
                        ><?= htmlspecialchars($titleRaw, ENT_QUOTES) ?></div>
                        <small class="text-muted d-block"><?= formatDateCz($photo['uploaded_at'] ?? $photo['created_at'] ?? '') ?></small>
                    </div>

                    <!-- Footer držíme u dna karty -->
                    <div class="card-footer text-center p-2 mt-auto">
                        <a href="delete_photo.php?gid=<?=
                            (int)$galleryId ?>&id=<?= $pid ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Opravdu smazat fotku?')">
                            <i class="bi bi-trash"></i> Smazat
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php // Modal pro zvětšení obrázku (stejný jako u pictures) ?>
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
document.addEventListener("DOMContentLoaded", function () {
    const imageModal = document.getElementById('imageModal');
    imageModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const imgSrc = trigger.getAttribute('data-img-src');
        const modalImg = imageModal.querySelector('#modalImage');
        modalImg.src = imgSrc;
    });

    // sjednocený placeholder pro prázdné titulky (funguje i s tvým kódem ve footeru)
    document.querySelectorAll('.editable-title').forEach(el => {
        if (!el.textContent.trim()) {
            el.textContent = 'Klikni pro přidání titulku';
            el.classList.add('empty');
        }
    });
});
</script>

<script>
(function () {
  // helper pro tvorbu elementů z HTML
  function h(strings, ...vals) {
    const t = document.createElement('template');
    t.innerHTML = strings.map((s,i)=> s + (vals[i] ?? '')).join('').trim();
    return t.content.firstElementChild;
  }

  // karta – shodný markup jako ve tvém výpisu
  function renderCard(p, gid) {
    const imgUrl = `${p.url}?v=${Date.now()}`;
    const delUrl = `delete_photo.php?gid=${gid}&id=${p.id}`;
    const title  = p.title || '';

    return h`
      <div class="col photo-item" data-id="${p.id}">
        <div class="card h-100 d-flex flex-column shadow-sm">
          <img
            src="${imgUrl}"
            class="card-img-top img-fluid"
            alt="${title || 'Fotka'}"
            loading="lazy"
            style="cursor: zoom-in"
            data-bs-toggle="modal"
            data-bs-target="#imageModal"
            data-img-src="${imgUrl}"
          >
          <div class="card-body p-2 text-center">
            <div
              class="editable-title form-control-plaintext py-1"
              contenteditable="true"
              data-id="${p.id}"
              onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
              onblur="updateTitle(this)"
              title="Klikni a uprav titulek"
            >${title}</div>
            <small class="text-muted d-block">${p.uploaded_at}</small>
          </div>
          <div class="card-footer text-center p-2 mt-auto">
            <a href="${delUrl}" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Opravdu smazat fotku?')">
              <i class="bi bi-trash"></i> Smazat
            </a>
          </div>
        </div>
      </div>`;
  }

  const DZ_SELECTOR = '#dzUpload';
  const GID = <?= (int)$galleryId ?>;
  const list = document.getElementById('photo-list');

  // čeká na instanci Dropzone; když se neobjeví, bezpečně ji založí
  function ensureDropzone(cb) {
    const el = document.querySelector(DZ_SELECTOR);
    if (!el) { console.error('Nenalezen formulář Dropzone:', DZ_SELECTOR); return; }

    if (el.dropzone) return cb(el.dropzone);

    let tries = 0;
    const timer = setInterval(() => {
      if (el.dropzone) {
        clearInterval(timer);
        cb(el.dropzone);
      } else if (++tries > 40) { // ~2s čekání
        clearInterval(timer);
        if (window.Dropzone) {
          try {
            // pokud by autodiscovery neběželo, založíme instanci sami
            if (Dropzone.autoDiscover) Dropzone.autoDiscover = false;
            const dz = new Dropzone(el, {
              url: el.getAttribute('action'),
              chunking: true,
              forceChunking: true,
              chunkSize: 2 * 1024 * 1024,
              parallelChunkUploads: true,
              retryChunks: true,
              retryChunksLimit: 3,
              acceptedFiles: 'image/*'
            });
            cb(dz);
          } catch (e) {
            console.error('Nešlo inicializovat Dropzone:', e);
          }
        }
      }
    }, 50);
  }

  function hook(dz) {
    if (dz._cmsHooked) return;
    dz._cmsHooked = true;

    dz.on('success', function(file, resp) {
      let r = resp;
      try { if (typeof r === 'string') r = JSON.parse(r); } catch(e) {
        console.error('JSON parse selhal:', resp); return;
      }
      if (!r || !r.ok || !r.photo) return;

      const card = renderCard(r.photo, GID);
      if (list) list.prepend(card);

      // placeholder pro prázdné titulky
      const titleEl = card.querySelector('.editable-title');
      if (titleEl && !titleEl.textContent.trim()) {
        titleEl.textContent = 'Klikni pro přidání titulku';
        titleEl.classList.add('empty');
      }

      dz.removeFile(file);
    });

    dz.on('error', function(file, msg) {
      console.error('Upload error:', msg);
      alert(typeof msg === 'string' ? msg : (msg?.error || 'Chyba při nahrávání.'));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ensureDropzone(hook));
  } else {
    ensureDropzone(hook);
  }
})();
</script>



<?php include "../includes/footer.php"; ?>
