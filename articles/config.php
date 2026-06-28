<?php

return [
    'title' => 'Články',
    'description' => 'Správa článků a aktualit',
    'icon' => 'bi bi-newspaper',
    'url' => '/articles/list.php',
    'permission' => 'articles',
    'group' => 'Obsah',
    'order' => 10,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM articles',
];