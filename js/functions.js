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

  let targetInput = '#logo';
  let targetPreview = '#logo-preview';

  document.querySelectorAll('.open-picker').forEach(btn => {
    btn.addEventListener('click', function () {
      targetInput = this.dataset.targetInput || '#logo';
      targetPreview = this.dataset.targetPreview || '#logo-preview';
    });
  });
window.filePickerCallback = function (url) {

  const input = document.querySelector(targetInput);

  if (input && input.id === 'block_images' && typeof window.blockImagesAdd === 'function') {
    window.blockImagesAdd(url);

    const modalEl = document.getElementById('imagePickerModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();
    }

    return;
  }

  const previewWrap = document.querySelector(targetPreview);
  const img = previewWrap ? previewWrap.querySelector('img') : null;

  if (input) input.value = url;
  if (img) img.src = url;
  if (previewWrap) previewWrap.style.display = 'block';

  const modalEl = document.getElementById('imagePickerModal');
  if (modalEl && typeof bootstrap !== 'undefined') {
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.hide();
  }
};

function togglePwd(id, btn) {
  const input = document.getElementById(id);
  if (!input) return;

  if (input.type === "password") {
    input.type = "text";
    if (btn) btn.textContent = "Skrýt";
  } else {
    input.type = "password";
    if (btn) btn.textContent = "Zobrazit";
  }
}