<?php

return [
    'title' => 'Kategorie článků',
    'description' => 'Správa kategorií článků',
    'icon' => 'bi bi-tags',
    'url' => '/categories/list.php',
    'permission' => 'articles',
    'group' => 'Taxonomie',
    'order' => 41,
    'show_in_menu' => true,
    'show_on_dashboard' => false,
    'count_query' => 'SELECT COUNT(*) FROM categories',
];