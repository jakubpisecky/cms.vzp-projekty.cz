<?php

return [
    'title' => 'Uživatelé',
    'description' => 'Správa uživatelů',
    'icon' => 'bi bi-people',
    'url' => '/users/list.php',
    'permission' => 'users',
    'group' => 'Uživatelé',
    'order' => 800,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM users',
];