<?php
// Definice oprávnění podle rolí
$rolePermissions = [
    'admin' => [
        'users'      => true,
        'articles'   => true,
        'categories' => true,
        'documents'  => true,
        'pictures'   => true,
        'visits'     => true,
        'logs'       => true,
        'galleries'  => true,
        'pages'  => true,
        'settings'  => true,
    ],
    'user' => [
        'users'      => false,
        'articles'   => true,
        'categories' => false,
        'documents'  => false,
        'pictures'   => true,
        'visits'     => false,
        'logs'       => false,
        'galleries'  => false,
        'pages'  => false,
        'settings'  => false,
    ],
];

/** Vrátí aktuální roli uživatele */
function getUserRole(): string {
    return $_SESSION['role'] ?? 'user';
}

/** Vrátí, zda má aktuální uživatel oprávnění na danou sekci */
function hasPermission(string $section): bool {
    global $rolePermissions;
    $role = getUserRole();
    return $rolePermissions[$role][$section] ?? false;
}
