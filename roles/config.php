<?php

return [
    'title' => 'Role',
    'description' => 'Správa rolí a oprávnění',
    'icon' => 'bi bi-shield-lock',
    'url' => '/roles/list.php',
    'permission' => 'users',
    'group' => 'Uživatelé',
    'order' => 800,
    'show_in_menu' => true,
    'show_on_dashboard' => false,
    'count_query' => 'SELECT COUNT(*) FROM roles',
];