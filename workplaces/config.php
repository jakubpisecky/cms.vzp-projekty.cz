<?php

return [
    'title' => 'Pracoviště',
    'description' => 'Správa klinik, oddělení, center a dalších pracovišť',
    'icon' => 'bi bi-hospital',
    'url' => '/workplaces/list.php',
    'permission' => 'workplaces',
    'group' => 'Obsah',
    'order' => 55,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM workplaces',
];