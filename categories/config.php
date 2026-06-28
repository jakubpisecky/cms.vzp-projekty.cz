<?php

return [
    'title' => 'Kategorie',
    'description' => 'Správa kategorií článků',
    'icon' => 'bi bi-tags',
    'url' => '/categories/list.php',
    'permission' => 'categories',
    'group' => 'Obsah',
    'order' => 30,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM categories',
];