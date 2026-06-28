<?php
$imagesList = array_filter(array_map('trim', explode(';', $images ?? '')));
?>

<div class="mb-3">
    <label class="form-label">Nadpis</label>
    <input type="text" name="title" class="form-control" value="<?= e($title) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Podnadpis</label>
    <input type="text" name="subtitle" class="form-control" value="<?= e($subtitle) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Obsah</label>
    <textarea name="content" class="form-control editor" rows="8"><?= e($content) ?></textarea>
</div>

<hr>

<h5 class="mb-3">Obrázky</h5>

<input type="hidden" name="images" id="block_images" value="<?= e($images) ?>">

<div class="mb-3">
    <label class="form-label">Vybraný obrázek</label>

    <div class="input-group">
        <input type="text"
               id="tmp_block_image"
               class="form-control"
               placeholder="Nejdříve vyberte obrázek"
               readonly>

        <button type="button"
                class="btn btn-outline-secondary open-picker"
                data-bs-toggle="modal"
                data-bs-target="#imagePickerModal"
                data-iframe-src="/pictures/list.php?picker=thumb"
                data-target-input="#tmp_block_image"
                data-target-preview="#tmp-block-image-preview">
            Vybrat...
        </button>

        <button type="button"
                class="btn btn-primary"
                onclick="addSelectedBlockImage()">
            Přidat do seznamu
        </button>
    </div>

    <div class="mt-2" id="tmp-block-image-preview" style="display:none;">
        <img src=""
             alt="Náhled"
             class="img-fluid mt-2 rounded border"
             style="max-height:150px;">
    </div>
</div>

<div id="images-list" class="row g-3 mb-3">
    <?php foreach ($imagesList as $img): ?>
        <div class="col-md-3 image-item" data-url="<?= e($img) ?>">
            <div class="border rounded p-2 bg-light">
                <img src="<?= e($img) ?>"
                     class="img-fluid rounded mb-2"
                     style="height:120px;object-fit:cover;width:100%;">
                <button type="button" class="btn btn-sm btn-danger w-100 remove-image">
                    Odebrat
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<hr>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Text tlačítka</label>

        <input type="text"
               name="button_text"
               class="form-control"
               value="<?= e($button_text ?? '') ?>">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Odkaz tlačítka</label>

        <input type="text"
               name="button_url"
               class="form-control"
               placeholder="/kontakt"
               value="<?= e($button_url ?? '') ?>">
    </div>
</div>

<script>
(function() {
    const input = document.getElementById('block_images');
    const list = document.getElementById('images-list');

    window.addSelectedBlockImage = function() {
        const tmpInput = document.getElementById('tmp_block_image');
        const url = tmpInput ? tmpInput.value.trim() : '';

        if (!url) {
            alert('Nejdříve vyberte obrázek.');
            return;
        }

        let images = input.value ? input.value.split(';').filter(Boolean) : [];

        if (!images.includes(url)) {
            images.push(url);
        }

        input.value = images.join(';');

        tmpInput.value = '';

        const tmpPreview = document.getElementById('tmp-block-image-preview');
        const tmpImg = tmpPreview ? tmpPreview.querySelector('img') : null;

        if (tmpImg) {
            tmpImg.src = '';
        }

        if (tmpPreview) {
            tmpPreview.style.display = 'none';
        }

        renderImages();
    };

    function renderImages() {
        const images = input.value ? input.value.split(';').filter(Boolean) : [];

        list.innerHTML = '';

        images.forEach(function(url) {
            const col = document.createElement('div');
            col.className = 'col-md-3 image-item';
            col.dataset.url = url;

            col.innerHTML = `
                <div class="border rounded p-2 bg-light">
                    <img src="${url}" class="img-fluid rounded mb-2" style="height:120px;object-fit:cover;width:100%;">
                    <button type="button" class="btn btn-sm btn-danger w-100 remove-image">
                        Odebrat
                    </button>
                </div>
            `;

            list.appendChild(col);
        });
    }

    list.addEventListener('click', function(e) {
        const btn = e.target.closest('.remove-image');

        if (!btn) {
            return;
        }

        const item = btn.closest('.image-item');
        const url = item.dataset.url;

        let images = input.value ? input.value.split(';').filter(Boolean) : [];

        images = images.filter(function(img) {
            return img !== url;
        });

        input.value = images.join(';');

        renderImages();
    });
})();
</script>