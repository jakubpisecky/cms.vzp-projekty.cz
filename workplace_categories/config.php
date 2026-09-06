<?php

return [
    'title' => 'Kategorie pracovišť',
    'description' => 'Správa kategorií pracovišť',
    'icon' => 'bi bi-tags',
    'url' => '/workplace_categories/list.php',
    'permission' => 'pages',
    'group' => 'Taxonomie',
    'order' => 42,
    'show_in_menu' => true,
    'show_on_dashboard' => false,
    'count_query' => 'SELECT COUNT(*) FROM workplace_categories',
];