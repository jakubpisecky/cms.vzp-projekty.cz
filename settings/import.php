<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";
requirePermission('settings');

session_start();
if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
    die('Neplatný CSRF token.');
}
if (empty($_FILES['file']['tmp_name'])) die('Soubor nebyl nahrán.');

$raw = file_get_contents($_FILES['file']['tmp_name']);
$payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

$allowedAll = [
  'site_title','site_tagline','site_description','site_logo_url',
  'contact_email','contact_phone','contact_address',
  'seo_meta_title_pattern','seo_meta_description_default',
  'ga4_measurement_id','meta_pixel_id','cookiebar_enabled','cookiebar_text',
  'smtp_enabled','smtp_host','smtp_port','smtp_secure','smtp_username','smtp_password','smtp_from_email','smtp_from_name',
  'recaptcha_enabled','recaptcha_site_key','recaptcha_secret_key',
  'uploads_max_mb','uploads_allowed_ext','image_max_width','image_max_height',
  'pagination_per_page','maintenance_enabled','maintenance_message','maintenance_ip_whitelist'
];

$toSave = array_intersect_key($payload, array_flip($allowedAll));
$errors = [];
foreach ($toSave as $k=>$v) {
    $v = (string)$v;
    if ($err = validate_setting($k, $v)) { $errors[$k] = $err; continue; }
    if (!setting_save($k, $v)) $errors[$k] = 'Chyba při ukládání';
}

if ($errors) {
    echo 'Některé položky se nepodařilo importovat: '.e(implode(', ', array_map(fn($k,$v)=>"$k ($v)", array_keys($errors), $errors)));
} else {
    echo 'Import hotov.';
}
