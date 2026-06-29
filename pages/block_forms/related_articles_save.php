<?php
// /pages/block_forms/related_articles_save.php

if (($block['type'] ?? '') !== 'related_articles') {
    return;
}

$relatedArticles = $_POST['related_articles'] ?? [];

if (!is_array($relatedArticles)) {
    $relatedArticles = [];
}

$relatedArticles = array_map('intval', $relatedArticles);
$relatedArticles = array_filter($relatedArticles);

$stmt = $conn->prepare("
    DELETE FROM article_block_related
    WHERE block_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

if (!empty($relatedArticles)) {
    $stmt = $conn->prepare("
        INSERT INTO article_block_related
            (block_id, article_id)
        VALUES
            (?, ?)
    ");

    foreach ($relatedArticles as $articleId) {
        $stmt->bind_param("ii", $id, $articleId);
        $stmt->execute();
    }

    $stmt->close();
}