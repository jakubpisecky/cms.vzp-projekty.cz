<?php

return [
    'title' => 'Obrázky',
    'description' => 'Knihovna obrázků',
    'icon' => 'bi bi-images',
    'url' => '/pictures/list.php',
    'permission' => 'pictures',
    'group' => 'Média',
    'order' => 60,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM pictures',
];