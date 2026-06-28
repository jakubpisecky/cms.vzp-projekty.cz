<?php

return [
    'title' => 'Kontaktní zprávy',
    'description' => 'Přijaté zprávy z formuláře',
    'icon' => 'bi bi-envelope',
    'url' => '/contact_messages/index.php',
    'permission' => 'contact_messages',
    'group' => 'Komunikace',
    'order' => 120,
    'show_in_menu' => true,
    'show_on_dashboard' => true,
    'count_query' => 'SELECT COUNT(*) FROM contact_messages',
];