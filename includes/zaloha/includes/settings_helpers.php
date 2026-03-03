<?php
// e() – pokud ji už máš globálně, tuhle vynech
if (!function_exists('e')) {
    function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
}

/** Jednoduchá paměťová cache v rámci requestu */
$GLOBALS['_settings_cache'] = [];

function setting_raw(string $key, $default='') {
    global $conn;
    if (array_key_exists($key, $GLOBALS['_settings_cache'])) {
        return $GLOBALS['_settings_cache'][$key];
    }
    $stmt = $conn->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($val);
    if ($stmt->fetch()) {
        $GLOBALS['_settings_cache'][$key] = $val;
        return $val;
    }
    return $default;
}

/** Typované čtení – bool/int jinak string */
function setting(string $key, $default='') {
    $val = setting_raw($key, $default);
    $bool = ['cookiebar_enabled','smtp_enabled','recaptcha_enabled','maintenance_enabled'];
    $ints = ['smtp_port','uploads_max_mb','image_max_width','image_max_height','pagination_per_page'];
    if (in_array($key,$bool,true)) return ($val==='1' || $val===1 || $val===true);
    if (in_array($key,$ints,true)) return (int)$val;
    return $val;
}

function setting_save(string $key, $value): bool {
    global $conn;
    $old = setting_raw($key, null);
    $value = (string)$value;
    $stmt = $conn->prepare("
        INSERT INTO settings(`key`,`value`) VALUES(?,?)
        ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)
    ");
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    if ($ok) {
        $GLOBALS['_settings_cache'][$key] = $value;
        // Audit
        $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
        $hst = $conn->prepare("INSERT INTO settings_history(`key`,`old_value`,`new_value`,`changed_by`) VALUES(?,?,?,?)");
        $hst->bind_param('sssi', $key, $old, $value, $adminId);
        $hst->execute();
    }
    return $ok;
}

/** Validace dle klíčů – jednoduché guardy */
function validate_setting(string $key, string $value): ?string {
    switch ($key) {
        case 'contact_email':
        case 'smtp_from_email':
            if ($value!=='' && !filter_var($value, FILTER_VALIDATE_EMAIL)) return 'Neplatný e‑mail';
            break;
        case 'smtp_port':
        case 'uploads_max_mb':
        case 'image_max_width':
        case 'image_max_height':
        case 'pagination_per_page':
            if ($value!=='' && (!ctype_digit($value) || (int)$value<0)) return 'Musí být nezáporné číslo';
            break;
        case 'uploads_allowed_ext':
            if ($value!=='' && !preg_match('~^[a-z0-9]+(?:,[a-z0-9]+)*$~i', $value)) return 'Povolené přípony odděl čárkami (bez teček)';
            break;
        case 'smtp_secure':
            if (!in_array($value, ['none','ssl','tls'], true)) return 'Neplatná hodnota zabezpečení';
            break;
    }
    return null; // OK
}

/** Hromadný zápis s validací a checkbox defaulty */
function settings_bulk_save(array $data, array $allowedKeys): array {
    $saved=0; $errors=[];
    // Checkboxy, které se při POST nepošlou → 0
    foreach (['cookiebar_enabled','smtp_enabled','recaptcha_enabled','maintenance_enabled'] as $cb) {
        if (in_array($cb, $allowedKeys,true) && !array_key_exists($cb, $data)) {
            $data[$cb] = '0';
        }
    }
    foreach ($allowedKeys as $k) {
        if (array_key_exists($k, $data)) {
            $val = trim((string)$data[$k]);
            if ($err = validate_setting($k, $val)) {
                $errors[$k] = $err;
                continue;
            }
            if (!setting_save($k, $val)) {
                $errors[$k] = 'Chyba při ukládání';
            } else {
                $saved++;
            }
        }
    }
    return ['saved'=>$saved,'errors'=>$errors];
}
