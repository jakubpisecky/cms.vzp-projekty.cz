<?php

return [
    'title' => 'Bannery',
    'description' => 'Správa bannerů',
    'icon' => 'bi bi-image',
    'url' => '/banners/list.php',
    'permission' => 'banners',
    'group' => 'Obsah',
    'order' => 40,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM banners',
];