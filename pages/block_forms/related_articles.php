<?php
// /pages/block_forms/related_articles.php

$source_type = $block['source_type'] ?? 'manual';
if (!in_array($source_type, ['manual', 'category'], true)) {
    $source_type = 'manual';
}

$article_limit = (int)($block['article_limit'] ?? 3);
if ($article_limit <= 0) {
    $article_limit = 3;
}

$article_order = $block['article_order'] ?? 'newest';
if (!in_array($article_order, ['newest', 'oldest', 'random'], true)) {
    $article_order = 'newest';
}

$categorySelected = [];

if (!empty($block['category_ids'])) {
    $categorySelected = array_filter(array_map('intval', explode(';', $block['category_ids'])));
}

$relatedSelected = [];

// při editaci existujícího bloku načteme uložené články
if (!empty($block['id'])) {
    $stmt = $conn->prepare("
        SELECT article_id
        FROM article_block_related
        WHERE block_id = ?
        ORDER BY id ASC
    ");
    $stmt->bind_param("i", $block['id']);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $relatedSelected[] = (int)$row['article_id'];
    }

    $stmt->close();
}

// seznam dostupných článků
$relatedArticles = [];

$res = $conn->query("
    SELECT id, title, publish_date
    FROM articles
    WHERE status = 'published'
    ORDER BY publish_date DESC, id DESC
");

while ($row = $res->fetch_assoc()) {
    $relatedArticles[] = $row;
}

// seznam kategorií
$categories = [];

$res = $conn->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC, id ASC
");

while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}
?>

<div class="mb-3">
    <label class="form-label">Nadpis bloku</label>

    <input type="text"
           name="title"
           class="form-control"
           value="<?= e($title ?: 'Související články') ?>">
</div>

<div class="mb-3">
    <label class="form-label">Zobrazení</label>

    <select name="layout" class="form-select">
        <option value="vertical" <?= ($layout ?? 'vertical') === 'vertical' ? 'selected' : '' ?>>
            Vertikální / karty
        </option>

        <option value="horizontal" <?= ($layout ?? '') === 'horizontal' ? 'selected' : '' ?>>
            Horizontální / seznam
        </option>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Zdroj článků</label>

    <select name="source_type"
            id="related_articles_source_type"
            class="form-select">
        <option value="manual" <?= $source_type === 'manual' ? 'selected' : '' ?>>
            Ruční výběr článků
        </option>

        <option value="category" <?= $source_type === 'category' ? 'selected' : '' ?>>
            Podle kategorie
        </option>
    </select>
</div>

<div id="related_articles_manual_box" class="mb-3">
    <label class="form-label">Vybrané články</label>

    <select name="related_articles[]"
            class="form-select related-articles-select"
            multiple>

        <?php foreach ($relatedArticles as $article): ?>
            <?php
            $articleId = (int)$article['id'];
            $articleTitle = $article['title'] ?? '';
            $date = !empty($article['publish_date'])
                ? ' — ' . date('j. n. Y', strtotime($article['publish_date']))
                : '';
            ?>

            <option value="<?= $articleId ?>"
                <?= in_array($articleId, $relatedSelected, true) ? 'selected' : '' ?>>
                <?= e($articleTitle . $date) ?>
            </option>
        <?php endforeach; ?>

    </select>

    <div class="form-text">
        Můžete vybrat více článků. Aktuální článek se na frontendu automaticky nezobrazí.
    </div>
</div>

<div id="related_articles_category_box">

    <div class="mb-3">
        <label class="form-label">Kategorie</label>

        <select name="category_ids[]"
                class="form-select related-categories-select"
                multiple>

            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>

                <option value="<?= $categoryId ?>"
                    <?= in_array($categoryId, $categorySelected, true) ? 'selected' : '' ?>>
                    <?= e($category['name'] ?? '') ?>
                </option>
            <?php endforeach; ?>

        </select>

        <div class="form-text">
            Můžete vybrat jednu nebo více kategorií. Články budou načteny automaticky podle nastaveného pořadí.
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Počet článků</label>

        <input type="number"
               name="article_limit"
               class="form-control"
               min="1"
               max="12"
               value="<?= (int)$article_limit ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Řazení článků</label>

        <select name="article_order" class="form-select">
            <option value="newest" <?= $article_order === 'newest' ? 'selected' : '' ?>>
                Nejnovější
            </option>

            <option value="oldest" <?= $article_order === 'oldest' ? 'selected' : '' ?>>
                Nejstarší
            </option>

            <option value="random" <?= $article_order === 'random' ? 'selected' : '' ?>>
                Náhodně
            </option>
        </select>
    </div>

</div>

<script>
window.addEventListener('load', function () {
    function toggleRelatedArticlesSource() {
        var source = document.getElementById('related_articles_source_type');
        var manualBox = document.getElementById('related_articles_manual_box');
        var categoryBox = document.getElementById('related_articles_category_box');

        if (!source || !manualBox || !categoryBox) {
            return;
        }

        if (source.value === 'category') {
            manualBox.style.display = 'none';
            categoryBox.style.display = '';
        } else {
            manualBox.style.display = '';
            categoryBox.style.display = 'none';
        }
    }

    var sourceSelect = document.getElementById('related_articles_source_type');

    if (sourceSelect) {
        sourceSelect.addEventListener('change', toggleRelatedArticlesSource);
        toggleRelatedArticlesSource();
    }

    setTimeout(function () {
        if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
            jQuery('.related-articles-select').select2({
                width: '100%',
                placeholder: 'Vyberte související články',
                allowClear: true
            });

            jQuery('.related-categories-select').select2({
                width: '100%',
                placeholder: 'Vyberte kategorie',
                allowClear: true
            });
        }
    }, 300);
});
</script>