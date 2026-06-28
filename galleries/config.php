<?php

return [
    'title' => 'Fotogalerie',
    'description' => 'Správa fotogalerií',
    'icon' => 'bi bi-collection',
    'url' => '/galleries/list.php',
    'permission' => 'galleries',
    'group' => 'Média',
    'order' => 70,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM galleries',
];