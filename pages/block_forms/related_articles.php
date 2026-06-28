<?php
// admin/pages/block_forms/related_articles.php

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
        Můžete vybrat více článků. Pořadí se zatím ukládá podle pořadí výběru.
    </div>
</div>

<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
            jQuery('.related-articles-select').select2({
                width: '100%',
                placeholder: 'Vyberte související články',
                allowClear: true
            });
        }
    }, 300);
});
</script>