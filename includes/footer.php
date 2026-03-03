 </main>
  </div><!-- /.admin-wrap -->

<footer class="text-center py-4 bg-white border-top">
    <small>© <?= date('Y') ?> My Admin CMS</small>
</footer>
<!-- Dropzone JS -->
<script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.js"></script>

<script>
// Dropzone neautodetekovat (ručně inicializujeme)
Dropzone.autoDiscover = false;

(function(){
  const galleryId = <?= (int)$galleryId ?>;
  const csrf = "<?= htmlspecialchars($csrf, ENT_QUOTES) ?>";

  const dz = new Dropzone("#dzUpload", {
    url: "upload_chunk.php?gallery_id=" + galleryId,
    method: "post",
    headers: { "X-CSRF-Token": csrf },        // CSRF (server bude ověřovat)
    paramName: "file",                         // název pole souboru
    acceptedFiles: "image/*",
    maxFilesize: 256,                          // 256 MB per file (klidně uprav)
    parallelUploads: 2,
    uploadMultiple: false,

    // Chunking
    chunking: true,
    forceChunking: true,
    chunkSize: 2 * 1024 * 1024,                // 2 MB kousky
    retryChunks: true,
    retryChunksLimit: 3,

    // UI
    addRemoveLinks: true,
    dictRemoveFile: "Zrušit",
    dictCancelUpload: "Zrušit nahrávání",
    dictCancelUploadConfirmation: "Zrušit nahrávání tohoto souboru?",
    dictDefaultMessage: "Přetáhněte sem soubory nebo klikněte",

    // thumbnails děláme sami až po uložení (rychlejší init)
    createImageThumbnails: false,
    timeout: 0
  });

  const totalWrap = document.getElementById('dzTotalProgressWrap');
  const totalBar  = document.getElementById('dzTotalProgress');

  dz.on("processing", () => {
    if (totalWrap) totalWrap.style.display = 'block';
  });

  dz.on("totaluploadprogress", (progress) => {
    if (totalBar) totalBar.style.width = (progress|0) + "%";
  });

  dz.on("queuecomplete", () => {
    // malá prodleva a progress schovat
    setTimeout(() => {
      if (totalWrap) totalWrap.style.display = 'none';
      if (totalBar) totalBar.style.width = "0%";
    }, 600);
  });

  // Po dokončení souboru – server vrátí JSON s daty nové fotky
  dz.on("success", (file, response) => {
    try {
      const data = (typeof response === "string") ? JSON.parse(response) : response;
      if (!data || !data.ok) {
        console.error(data);
        return;
      }
      // Přidej kartičku do gridu bez reloadu
      appendPhotoCard(data.photo);
      // volitelně: visual feedback na kartičce v dropzóně
      file.previewElement?.classList?.add("dz-success");
    } catch (e) {
      console.error("Invalid JSON response", e, response);
    }
  });

  dz.on("error", (file, errorMessage) => {
    console.error("Upload error:", errorMessage);
  });

  function appendPhotoCard(p){
    if (!p) return;
    const list = document.getElementById('photo-list');
    const title = (p.title || '').trim();
    const titleEsc = escapeHtml(title || 'Klikni pro přidání titulku');
    const altEsc   = escapeHtml(title || 'Fotka');

    const col = document.createElement('div');
    col.className = 'col photo-item';
    col.setAttribute('data-id', p.id);

    col.innerHTML = `
      <div class="card h-100 d-flex flex-column shadow-sm">
        <img src="${escapeAttr(p.url)}" class="card-img-top img-fluid" alt="${altEsc}"
             loading="lazy" style="cursor: zoom-in"
             data-bs-toggle="modal" data-bs-target="#imageModal" data-img-src="${escapeAttr(p.url)}">
        <div class="card-body p-2 text-center">
          <div class="editable-title form-control-plaintext py-1"
               contenteditable="true"
               data-id="${p.id}"
               onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}"
               onblur="updateTitle(this)"
               title="Klikni a uprav titulek">${titleEsc}</div>
          <small class="text-muted d-block">${escapeHtml(p.uploaded_at || '')}</small>
        </div>
        <div class="card-footer text-center p-2 mt-auto">
          <a href="delete_photo.php?gid=${galleryId}&id=${p.id}"
             class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Opravdu smazat fotku?')">
            <i class="bi bi-trash"></i> Smazat
          </a>
        </div>
      </div>
    `;
    list?.prepend(col); // nové fotky nahoru
  }

  function escapeHtml(s){
    return (s||'').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  }
  function escapeAttr(s){ return escapeHtml(s); }
})();
</script>


<!-- JS knihovny -->
<script src="../js/functions.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../js/tinymce/tinymce.min.js"></script>

<!-- Modal pro TinyMCE výběr obrázků -->
<div class="modal fade tinymce-modal" id="editorImageModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Vložit obrázek</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="height: 500px;">
        <iframe src="" id="editorImageIframe" style="border:0;width:100%;height:100%;"></iframe>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="imagePickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Knihovna obrázků</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
      </div>
      <div class="modal-body p-0">
        <iframe src="/pictures/list.php?picker=thumb" style="width:100%;height:70vh;border:0" loading="lazy"></iframe>
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    // Select2
    $('.select2').select2({ placeholder: "Vyberte položky", width: '100%' });

// === TinyMCE konfigurace ===
tinymce.init({ 
  selector: 'textarea.editor',
  language: 'cs',
  language_url: '/js/tinymce/langs/cs.js',
  license_key: 'gpl',
  promotion: false,  
  branding: false, 

  plugins: 'link image lists advlist autolink code table autoresize ' +
           'searchreplace visualblocks fullscreen preview anchor charmap ' +
           'insertdatetime help wordcount quickbars',

  toolbar: [
    'undo redo | blocks | bold italic underline strikethrough | ' +
    'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
    'link image | table | hr charmap | removeformat | searchreplace preview fullscreen | code',
    'tabledelete tableprops tablerowprops tablecellprops | ' +
    'tableinsertrowbefore tableinsertrowafter tabledeleterow | ' +
    'tableinsertcolbefore tableinsertcolafter tabledeletecol'
  ].join('\n'),

  menubar: 'file edit view insert format table',
  toolbar_mode: 'sliding',

  block_formats: 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Nadpis 5=h5;Nadpis 6=h6;Předformátovaný=pre;Citace=blockquote',

  quickbars_selection_toolbar: 'bold italic underline | blocks | quicklink h2 h3 blockquote',
  quickbars_insert_toolbar: 'image table hr',

  // české formáty pro datum/čas
  insertdatetime_dateformat: '%d. %m. %Y',
  insertdatetime_timeformat: '%H:%M:%S',
  insertdatetime_formats: [
    '%H:%M', '%H:%M:%S',
    '%d.%m.%Y', '%d. %m. %Y',
    '%d. %m. %Y %H:%M',
    '%d.%m.%Y %H:%M:%S'
  ],

  file_picker_types: 'image file',

  file_picker_callback: function (callback, value, meta) {
    window.tinymceActiveCallback = callback;

    const iframe  = document.getElementById('editorImageIframe');
    const modalEl = document.getElementById('editorImageModal');
    const titleEl = modalEl ? modalEl.querySelector('.modal-title') : null;

    if (meta.filetype === 'image') {
      iframe.src = '../pictures/list.php?picker=tinymce';
      if (titleEl) titleEl.textContent = 'Vložit obrázek';
    } else {
      iframe.src = '../documents/list.php?picker=tinymce';
      if (titleEl) titleEl.textContent = 'Vložit dokument';
    }

    window._editorImageModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    window._editorImageModal.show();
  },

  link_title: true,
  image_title: true,
  image_caption: true,

  // ponechej jen tyhle tři – nejsou deprecated
  autoresize_bottom_margin: 20,
  min_height: 500,
  max_height: 1000
});






    // === PŘÍJEM ZPRÁVY Z pictures/list.php (iframe) ===
    window.addEventListener('message', function (e) {
        // pokud máš knihovnu na jiné doméně, tuhle kontrolu odkomentuj / uprav
        // if (e.origin !== window.location.origin) return;

        const data = e.data || {};
        if (data.mceAction === 'chooseImage' && data.url) {
            if (typeof window.tinymceActiveCallback === 'function') {
                // vyplní pole „Zdroj“ (a alt) v dialogu TinyMCE
                window.tinymceActiveCallback(data.url, { alt: data.alt || '' });
                window.tinymceActiveCallback = null;
            }
            // zavřít modal a vyčistit iframe
            const modalEl = document.getElementById('editorImageModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
            document.getElementById('editorImageIframe').src = 'about:blank';
        }
    }, false);

    // Backdrop třída – lépe po "shown" (ať existuje)
    document.getElementById('editorImageModal').addEventListener('shown.bs.modal', function () {
        const bd = document.querySelector('.modal-backdrop');
        if (bd) bd.classList.add('tinymce-backdrop');
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    const el = document.getElementById('photo-list');
    new Sortable(el, {
        animation: 150,
        onEnd: function (evt) {
            const order = [];
            document.querySelectorAll('#photo-list .photo-item').forEach((el, index) => {
                order.push({
                    id: el.dataset.id,
                    position: index + 1
                });
            });

            fetch('save_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(order)
            });
        }
    });
</script>

<script>
function updateTitle(el) {
  const id = el.dataset.id;
  let newTitle = el.textContent.trim();

  if (!newTitle) {
    el.textContent = 'Klikni pro přidání titulku';
    el.classList.add('empty');
    newTitle = '';
  } else {
    el.classList.remove('empty');
  }

  fetch('update_title.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
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
(function () {
  let targetInput = '#logo';
  let targetPreview = '#logo-preview';

  // Tlačítko si předá cílové selektory (můžeš tak mít na stránce víc pickerů)
  document.querySelectorAll('.open-picker').forEach(btn => {
    btn.addEventListener('click', function () {
      targetInput = this.dataset.targetInput || '#logo';
      targetPreview = this.dataset.targetPreview || '#logo-preview';
    });
  });

  // Volá se z iframe /pictures/list.php po kliknutí na obrázek
  window.filePickerCallback = function (url) {
    const input = document.querySelector(targetInput);
    const previewWrap = document.querySelector(targetPreview);
    const img = previewWrap ? previewWrap.querySelector('img') : null;

    if (input) input.value = url;
    if (img) img.src = url;
    if (previewWrap) previewWrap.style.display = 'block';

    // Zavřít modal
    const modalEl = document.getElementById('imagePickerModal');
    if (modalEl) {
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();
    }
  };
})();
</script>

<script>
function togglePwd(id) {
  const input = document.getElementById(id);
  if (!input) return;

  if (input.type === "password") {
    input.type = "text";
    event.target.textContent = "Skrýt";
  } else {
    input.type = "password";
    event.target.textContent = "Zobrazit";
  }
}
</script>
