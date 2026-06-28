<?php

return [
    'title' => 'Jídelníčky',
    'description' => 'Správa jídelníčků',
    'icon' => 'bi bi-list',
    'url' => '/menus/list.php',
    'permission' => 'menus',
    'group' => 'Obsah',
    'order' => 35,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM menu_days',
];