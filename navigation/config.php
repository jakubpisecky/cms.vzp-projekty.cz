<?php

return [
    'title' => 'Navigace',
    'description' => 'Správa vlastních navigací a levých menu',
    'icon' => 'bi bi-list-nested',
    'url' => '/navigation/list.php',
    'permission' => 'pages',
    'group' => 'Obsah',
    'order' => 45,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM navigation',
];