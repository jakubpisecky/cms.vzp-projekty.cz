<?php

return [
    'title' => 'Odbornosti pracovišť',
    'description' => 'Správa odborností pracovišť',
    'icon' => 'bi bi-list-check',
    'url' => '/workplace_specializations/list.php',
    'permission' => 'workplaces',
    'group' => 'Taxonomie',
    'order' => 42,
    'show_in_menu' => true,
    'show_on_dashboard' => false,
    'count_query' => 'SELECT COUNT(*) FROM workplace_specializations',
];