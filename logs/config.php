<?php

return [
    'title' => 'Logy',
    'description' => 'Systémové logy',
    'icon' => 'bi bi-clock-history',
    'url' => '/logs/list.php',
    'permission' => 'logs',
    'group' => 'Systém',
    'order' => 900,
    'show_in_menu' => true,
    'show_on_dashboard' => false,
    'count_query' => 'SELECT COUNT(*) FROM logs',
];