<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

requirePermission('mailing');

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$mode = trim((string)($input['mode'] ?? 'generate'));
$prompt = trim((string)($input['prompt'] ?? ''));
$content = (string)($input['content'] ?? '');

if ($prompt === '') {
    echo json_encode([
        'success' => false,
        'error' => 'Chybí zadání pro AI.'
    ]);
    exit;
}

if (!in_array($mode, ['generate', 'edit'], true)) {
    echo json_encode([
        'success' => false,
        'error' => 'Neplatný režim.'
    ]);
    exit;
}

$apiKey = trim((string)setting('mailing_ai_api_key'));
if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'error' => 'Chybí OPENAI_API_KEY v prostředí serveru.'
    ]);
    exit;
}

$systemPrompt = <<<PROMPT
Jsi generátor HTML e-mailových šablon.

Pravidla:
- vrať pouze čisté HTML
- nepřidávej markdown
- nepřidávej vysvětlení
- žádný JavaScript
- používej e-mailově kompatibilní HTML
- preferuj tabulkové rozložení
- max šířka hlavního wrapperu 600px
- používej inline styly
- zachovej placeholdery jako {first_name}, {last_name}, {name}, {email}, {company}, {unsubscribe_link}
- nevkládej externí fonty ani skripty
- výstup musí být přímo použitelný jako HTML obsah e-mailu
PROMPT;

$userPrompt = $prompt;

if ($mode === 'edit') {
    $userPrompt .= "\n\nUprav následující existující HTML podle zadání:\n\n" . $content;
}

$payload = [
    'model' => 'gpt-5.4',
    'input' => [
        [
            'role' => 'system',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => $systemPrompt
                ]
            ]
        ],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => $userPrompt
                ]
            ]
        ]
    ]
];

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 90,
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Chyba cURL: ' . $curlError
    ]);
    exit;
}

$data = json_decode($responseBody, true);

if ($httpCode < 200 || $httpCode >= 300) {
    $apiError = $data['error']['message'] ?? ('HTTP ' . $httpCode);
    echo json_encode([
        'success' => false,
        'error' => 'OpenAI API chyba: ' . $apiError
    ]);
    exit;
}

// Responses API běžně vrací text i v output_text;
// necháme fallback i na parsování output pole.
$html = trim((string)($data['output_text'] ?? ''));

if ($html === '' && !empty($data['output']) && is_array($data['output'])) {
    $parts = [];

    foreach ($data['output'] as $item) {
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($item['content'] as $contentItem) {
                if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                    $parts[] = $contentItem['text'];
                }
            }
        }
    }

    $html = trim(implode("\n", $parts));
}

if ($html === '') {
    echo json_encode([
        'success' => false,
        'error' => 'AI nevrátila žádný HTML výstup.'
    ]);
    exit;
}

if (stripos($html, '<script') !== false) {
    echo json_encode([
        'success' => false,
        'error' => 'AI vrátila nepovolený obsah.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'html' => $html
]);