<?php
require_once "../includes/auth.php";

requirePermission('invoices');

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));

if (mb_strlen($q, 'UTF-8') < 3) {
    echo json_encode([]);
    exit;
}

$url = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/vyhledat';

$payload = [
    'obchodniJmeno' => $q,
    'pocet' => 10,
    'start' => 0
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode < 200 || $httpCode >= 300) {
    echo json_encode([
        'error' => true,
        'message' => 'ARES neodpověděl.',
        'http_code' => $httpCode,
        'curl_error' => $error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data)) {
    echo json_encode([]);
    exit;
}

$out = [];

foreach (($data['ekonomickeSubjekty'] ?? []) as $item) {
    $sidlo = $item['sidlo'] ?? [];

    $streetParts = [];

    if (!empty($sidlo['nazevUlice'])) {
        $streetParts[] = $sidlo['nazevUlice'];
    }

    $house = '';

    if (!empty($sidlo['cisloDomovni'])) {
        $house .= $sidlo['cisloDomovni'];
    }

    if (!empty($sidlo['cisloOrientacni'])) {
        $house .= '/' . $sidlo['cisloOrientacni'];
    }

    if ($house !== '') {
        $streetParts[] = $house;
    }

    $street = trim(implode(' ', $streetParts));

    // když není ulice, použij obec + číslo
    if ($street === '' && !empty($sidlo['nazevObce']) && !empty($sidlo['cisloDomovni'])) {
        $street = trim($sidlo['nazevObce'] . ' ' . $sidlo['cisloDomovni']);
    }

    $out[] = [
        'name' => $item['obchodniJmeno'] ?? '',
        'ico' => $item['ico'] ?? '',
        'dic' => !empty($item['dic']) ? $item['dic'] : (!empty($item['ico']) ? 'CZ' . $item['ico'] : ''),
        'street' => $street,
        'city' => $sidlo['nazevObce'] ?? '',
        'zip' => isset($sidlo['psc']) ? (string)$sidlo['psc'] : '',
        'country' => 'Česká republika',
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);