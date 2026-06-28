<?php
$frontendBase = rtrim(setting('frontend_url'), '/');

$iconNames = [
    'alarm',
    'apartment',
    'arrow-down-circle',
    'arrow-left-circle',
    'arrow-right-circle',
    'arrow-up-circle',
    'bicycle',
    'bold',
    'book',
    'bookmark',
    'briefcase',
    'bubble',
    'bug',
    'bullhorn',
    'bus',
    'calendar-full',
    'camera-video',
    'car',
    'cart',
    'chart-bars',
    'checkmark-circle',
    'chevron-down',
    'chevron-left',
    'chevron-right',
    'chevron-up',
    'clock',
    'cloud',
    'code',
    'coffee-cup',
    'cog',
    'construction',
    'crop',
    'cross-circle',
    'diamond',
    'dice',
    'download',
    'earth',
    'enter-down',
    'envelope',
    'exit-up',
    'eye',
    'file-add',
    'file-empty',
    'film-play',
    'flag',
    'frame-contract',
    'gift',
    'heart',
    'highlight',
    'home',
    'hourglass',
    'inbox',
    'indent-increase',
    'keyboard',
    'laptop',
    'layers',
    'leaf',
    'license',
    'link',
    'list',
    'location',
    'lock',
    'magic-wand',
    'magnifier',
    'map',
    'menu',
    'mic',
    'moon',
    'music-note',
    'mustache',
    'neutral',
    'paperclip',
    'paw',
    'pencil',
    'phone',
    'picture',
    'pie-chart',
    'pilcrow',
    'plus-circle',
    'pointer-down',
    'power-switch',
    'printer',
    'pushpin',
    'question-circle',
    'rocket',
    'sad',
    'screen',
    'select',
    'shirt',
    'smartphone',
    'smile',
    'sort-alpha-asc',
    'spell-check',
    'star',
    'store',
    'sun',
    'sync',
    'tablet',
    'tag',
    'text-align-center',
    'text-align-justify',
    'text-align-left',
    'text-align-right',
    'thumbs-up',
    'train',
    'trash',
    'underline',
    'upload',
    'user',
    'users',
    'volume-high',
    'wheelchair',
];

$iconOptions = [];

foreach ($iconNames as $name) {

    $path = '/public/themes/ezy/vendor/linear-icons/' . $name . '.svg';

    $label = ucfirst(str_replace('-', ' ', $name));

    $iconOptions[$path] = $label;
}
?>

<?php for ($i = 1; $i <= 3; $i++): ?>

    <?php
    $iconVar   = 'col' . $i . '_icon';
    $kickerVar = 'col' . $i . '_kicker';
    $titleVar  = 'col' . $i . '_title';
    $textVar   = 'col' . $i . '_text';

    $iconValue   = $$iconVar ?? '';
    $kickerValue = $$kickerVar ?? '';
    $titleValue  = $$titleVar ?? '';
    $textValue   = $$textVar ?? '';
    ?>

    <div class="card border mb-4">

        <div class="card-header bg-light">
            <strong>Sloupec <?= $i ?></strong>
        </div>

        <div class="card-body">

            <div class="mb-3">

                <label class="form-label">Ikona</label>

                <select
                    name="col<?= $i ?>_icon"
                    class="form-select select2-icon"
                    data-preview="#icon-preview-<?= $i ?>">

                    <option value="">— Bez ikony —</option>

                    <?php foreach ($iconOptions as $iconPath => $iconLabel): ?>

                        <option
                            value="<?= e($iconPath) ?>"
                            <?= $iconValue === $iconPath ? 'selected' : '' ?>>

                            <?= e($iconLabel) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <div class="mt-3">

                    <img
                        id="icon-preview-<?= $i ?>"
                        src="<?= !empty($iconValue) ? e($frontendBase . $iconValue) : '' ?>"
                        alt=""
                        style="
                            width:48px;
                            height:48px;
                            <?= empty($iconValue) ? 'display:none;' : '' ?>
                        ">

                </div>

            </div>

            <div class="mb-3">
                <label class="form-label">Horní titulek</label>

                <input
                    type="text"
                    name="col<?= $i ?>_kicker"
                    class="form-control"
                    value="<?= e($kickerValue) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Nadpis</label>

                <input
                    type="text"
                    name="col<?= $i ?>_title"
                    class="form-control"
                    value="<?= e($titleValue) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Text</label>

                <textarea
                    name="col<?= $i ?>_text"
                    class="form-control"
                    rows="4"><?= e($textValue) ?></textarea>
            </div>

        </div>

    </div>

<?php endfor; ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {

        if (!window.jQuery || typeof jQuery.fn.select2 !== 'function') {
            console.warn('Select2 není dostupný.');
            return;
        }

        jQuery('.select2-icon').each(function () {
            const el = jQuery(this);

            if (el.hasClass('select2-hidden-accessible')) {
                el.select2('destroy');
            }

            el.select2({
                width: '100%',
                placeholder: 'Vyberte ikonu',
                allowClear: true,
                templateResult: formatIcon,
                templateSelection: formatIcon,
                escapeMarkup: function(markup) {
                    return markup;
                }
            });
        });

        jQuery('.select2-icon').on('change', function () {
            const preview = document.querySelector(this.dataset.preview);
            const value = this.value;

            if (!preview) return;

            if (!value) {
                preview.style.display = 'none';
                preview.src = '';
                return;
            }

            preview.src = '<?= e($frontendBase) ?>' + value;
            preview.style.display = 'inline-block';
        });

    }, 300);
});
function formatIcon(option) {

    if (!option.id) {
        return option.text;
    }

    const iconUrl = '<?= e($frontendBase) ?>' + option.id;

    return $(`
        <div style="display:flex;align-items:center;gap:10px;">
            <img src="${iconUrl}"
                 style="width:22px;height:22px;">
            <span>${option.text}</span>
        </div>
    `);
}
</script>