<?php

return [
    'title' => 'Stránky',
    'description' => 'Správa stránek webu',
    'icon' => 'bi bi-file-earmark-text',
    'url' => '/pages/list.php',
    'permission' => 'pages',
    'group' => 'Obsah',
    'order' => 20,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM pages',
];