<?php

return [
    'title' => 'Návštěvy',
    'description' => 'Správa návštěv',
    'icon' => 'bi bi-person-badge',
    'url' => '/visits/list.php',
    'permission' => 'visits',
    'group' => 'Evidence',
    'order' => 700,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM visits',
];