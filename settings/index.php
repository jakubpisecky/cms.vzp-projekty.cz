<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";

requirePermission('settings');

$tabs = [
    'general'     => 'Obecné',
    'seo'         => 'SEO',
    'analytics'   => 'Analytika & Cookies',
    'social'      => 'Sociální sítě',
    'emails'      => 'E-maily',
    'forms'       => 'Formuláře',
    'invoices'    => 'Fakturace',
    'uploads'     => 'Soubory & Obrázky',
    'listing'     => 'Výpisy',
    'maintenance' => 'Údržba',
    'tools'       => 'Nástroje',
];

$active = $_GET['tab'] ?? 'general';
if (!array_key_exists($active, $tabs)) {
    $active = 'general';
}

$messages = [];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$allowedByTab = [
    'general' => [
        'site_title',
        'frontend_url',
        'site_tagline',
        'site_description',
        'site_logo_url',
        'site_favicon_url',
        'contact_email',
        'contact_phone',
        'contact_address',
    ],
    'seo' => [
        'seo_meta_title_pattern',
        'seo_meta_description_default',
    ],
    'analytics' => [
        'cookiebar_enabled',
        'cookiebar_text',
        'ga4_measurement_id',
        'meta_pixel_id',
    ],
    'social' => [
        'social_facebook_url',
        'social_instagram_url',
        'social_twitter_url',
        'social_linkedin_url',
        'social_youtube_url',
        'social_tiktok_url',
    ],
    'emails' => [
        'smtp_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_secure',
        'smtp_username',
        'smtp_password',
        'smtp_from_email',
        'smtp_from_name',

        'mailing_smtp_enabled',
        'mailing_smtp_host',
        'mailing_smtp_port',
        'mailing_smtp_secure',
        'mailing_smtp_username',
        'mailing_smtp_password',
        'mailing_from_email',
        'mailing_from_name',
        'mailing_reply_to',
        'mailing_batch_size',
        'mailing_max_attempts',
    ],
    'forms' => [
        'recaptcha_enabled',
        'recaptcha_site_key',
        'recaptcha_secret_key',
        'mailing_ai_api_key',
    ],
    'invoices' => [
        'invoice_supplier_name',
        'invoice_supplier_ico',
        'invoice_supplier_dic',
        'invoice_supplier_street',
        'invoice_supplier_city',
        'invoice_supplier_zip',
        'invoice_supplier_country',
        'invoice_supplier_email',
        'invoice_supplier_phone',
        'invoice_supplier_account_number',
        'invoice_supplier_bank_code',
        'invoice_supplier_iban',
        'invoice_supplier_swift',
        'invoice_is_vat_payer',
        'invoice_default_due_days',
        'invoice_default_currency',
        'invoice_number_prefix',
        'invoice_footer_note',
        'invoice_supplier_logo',
        'invoice_color_primary',
        'invoice_color_secondary',
        'invoice_color_primary_text',
        'invoice_color_secondary_text',
    ],
    'uploads' => [
        'uploads_max_mb',
        'uploads_allowed_ext',
        'image_max_width',
        'image_max_height',
    ],
    'listing' => [
        'pagination_per_page',
    ],
    'maintenance' => [
        'maintenance_enabled',
        'maintenance_message',
        'maintenance_ip_whitelist',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active !== 'tools') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $messages[] = [
            'type' => 'danger',
            'text' => 'Neplatný CSRF token.',
        ];
    } else {
        $allowed = $allowedByTab[$active] ?? [];
        $res = settings_bulk_save($_POST, $allowed);

        if (!empty($res['errors'])) {
            foreach ($res['errors'] as $key => $err) {
                $messages[] = [
                    'type' => 'danger',
                    'text' => e($key) . ': ' . e($err),
                ];
            }
        } else {
            $messages[] = [
                'type' => 'success',
                'text' => 'Nastavení bylo uloženo.',
            ];
        }
    }
}

function settingInput(string $name, string $label, string $type = 'text', string $placeholder = '', string $default = ''): void
{
    ?>
    <div class="mb-3">
        <label class="form-label"><?= e($label) ?></label>
        <input type="<?= e($type) ?>"
               name="<?= e($name) ?>"
               class="form-control"
               placeholder="<?= e($placeholder) ?>"
               value="<?= e(setting($name, $default)) ?>">
    </div>
    <?php
}

function settingTextarea(string $name, string $label, int $rows = 3, string $placeholder = '', string $default = ''): void
{
    ?>
    <div class="mb-3">
        <label class="form-label"><?= e($label) ?></label>
        <textarea name="<?= e($name) ?>"
                  rows="<?= (int)$rows ?>"
                  class="form-control"
                  placeholder="<?= e($placeholder) ?>"><?= e(setting($name, $default)) ?></textarea>
    </div>
    <?php
}

function settingSwitch(string $name, string $label): void
{
    ?>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input"
               type="checkbox"
               name="<?= e($name) ?>"
               value="1"
               id="<?= e($name) ?>"
               <?= setting($name) ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= e($name) ?>">
            <?= e($label) ?>
        </label>
    </div>
    <?php
}

function settingColor(string $name, string $label, string $default): void
{
    ?>
    <div class="mb-3">
        <label class="form-label"><?= e($label) ?></label>
        <input type="color"
               name="<?= e($name) ?>"
               class="form-control form-control-color"
               value="<?= e(setting($name, $default)) ?>">
    </div>
    <?php
}

function sectionTitle(string $title, string $text = ''): void
{
    ?>
    <div class="mb-4">
        <h5 class="mb-1"><?= e($title) ?></h5>
        <?php if ($text !== ''): ?>
            <p class="text-muted mb-0"><?= e($text) ?></p>
        <?php endif; ?>
    </div>
    <?php
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Nastavení webu</h2>
            <p class="text-muted mb-0">Globální nastavení administrace a frontendu</p>
        </div>
    </div>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-<?= e($m['type']) ?>">
            <?= $m['text'] ?>
        </div>
    <?php endforeach; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <ul class="nav nav-pills flex-wrap gap-2">
                <?php foreach ($tabs as $key => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === $key ? 'active' : '' ?>"
                           href="?tab=<?= e($key) ?>">
                            <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if ($active !== 'tools'): ?>

        <form method="post" class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong><?= e($tabs[$active]) ?></strong>
            </div>

            <div class="card-body">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <?php if ($active === 'general'): ?>

                    <?php sectionTitle('Základní informace'); ?>

                    <?php settingInput('site_title', 'Titulek webu'); ?>
                    <?php settingInput('frontend_url', 'Adresa webu'); ?>
                    <?php settingInput('site_tagline', 'Slogan / tagline'); ?>
                    <?php settingTextarea('site_description', 'Meta popis webu', 3); ?>

                    <hr class="my-4">

                    <?php sectionTitle('Logo a favicon'); ?>

                    <div class="mb-3">
                        <label class="form-label">Logo webu</label>
                        <div class="input-group">
                            <input type="text"
                                   name="site_logo_url"
                                   id="site_logo_url"
                                   class="form-control"
                                   placeholder="URL loga"
                                   value="<?= e(setting('site_logo_url')) ?>"
                                   readonly>
                            <button type="button"
                                    class="btn btn-outline-secondary open-picker"
                                    data-bs-toggle="modal"
                                    data-bs-target="#imagePickerModal"
                                    data-target-input="#site_logo_url"
                                    data-target-preview="#logo-preview">
                                Vybrat
                            </button>
                        </div>

                        <div class="mt-2" id="logo-preview" style="<?= setting('site_logo_url') ? '' : 'display:none' ?>">
                            <img src="<?= e(setting('site_logo_url')) ?>"
                                 alt="Logo"
                                 class="img-fluid mt-2 rounded border"
                                 style="max-height:150px;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Favicon</label>
                        <div class="input-group">
                            <input type="text"
                                   name="site_favicon_url"
                                   id="site_favicon_url"
                                   class="form-control"
                                   placeholder="URL favicon"
                                   value="<?= e(setting('site_favicon_url')) ?>"
                                   readonly>
                            <button type="button"
                                    class="btn btn-outline-secondary open-picker"
                                    data-bs-toggle="modal"
                                    data-bs-target="#imagePickerModal"
                                    data-target-input="#site_favicon_url"
                                    data-target-preview="#favicon-preview">
                                Vybrat
                            </button>
                        </div>
                        <div class="form-text">
                            Doporučeno: 32×32 px, PNG nebo ICO.
                        </div>

                        <div class="mt-2" id="favicon-preview" style="<?= setting('site_favicon_url') ? '' : 'display:none' ?>">
                            <img src="<?= e(setting('site_favicon_url')) ?>"
                                 alt="Favicon"
                                 class="rounded border"
                                 style="width:32px; height:32px; object-fit:contain;">
                        </div>
                    </div>

                    <hr class="my-4">

                    <?php sectionTitle('Kontakt'); ?>

                    <?php settingInput('contact_email', 'Kontaktní e-mail', 'email'); ?>
                    <?php settingInput('contact_phone', 'Telefon'); ?>
                    <?php settingTextarea('contact_address', 'Adresa', 2); ?>

                <?php elseif ($active === 'seo'): ?>

                    <?php sectionTitle('SEO nastavení'); ?>

                    <?php settingInput('seo_meta_title_pattern', 'Vzor titulku stránky', 'text', 'např. {title} | {site}'); ?>

                    <div class="form-text mb-3">
                        Dostupné proměnné: <code>{title}</code>, <code>{site}</code>
                    </div>

                    <?php settingTextarea('seo_meta_description_default', 'Výchozí meta description', 3); ?>

                <?php elseif ($active === 'analytics'): ?>

                    <?php sectionTitle('Cookies'); ?>

                    <?php settingSwitch('cookiebar_enabled', 'Zobrazovat cookie lištu'); ?>
                    <?php settingTextarea('cookiebar_text', 'Text cookie lišty', 2); ?>

                    <hr class="my-4">

                    <?php sectionTitle('Měřicí kódy'); ?>

                    <?php settingInput('ga4_measurement_id', 'Google Analytics 4 – Measurement ID'); ?>
                    <?php settingInput('meta_pixel_id', 'Meta Pixel ID'); ?>

                <?php elseif ($active === 'social'): ?>

                    <?php sectionTitle('Sociální sítě'); ?>

                    <?php settingInput('social_facebook_url', 'Facebook URL', 'url', 'https://www.facebook.com/...'); ?>
                    <?php settingInput('social_instagram_url', 'Instagram URL', 'url', 'https://www.instagram.com/...'); ?>
                    <?php settingInput('social_twitter_url', 'Twitter / X URL', 'url', 'https://x.com/...'); ?>
                    <?php settingInput('social_linkedin_url', 'LinkedIn URL', 'url', 'https://www.linkedin.com/company/...'); ?>
                    <?php settingInput('social_youtube_url', 'YouTube URL', 'url', 'https://www.youtube.com/...'); ?>
                    <?php settingInput('social_tiktok_url', 'TikTok URL', 'url', 'https://www.tiktok.com/@...'); ?>

                <?php elseif ($active === 'emails'): ?>

                    <?php sectionTitle('SMTP pro běžné e-maily'); ?>

                    <?php settingSwitch('smtp_enabled', 'Posílat e-maily přes SMTP'); ?>
                    <?php settingInput('smtp_host', 'SMTP server'); ?>
                    <?php settingInput('smtp_port', 'Port', 'number'); ?>

                    <div class="mb-3">
                        <label class="form-label">Zabezpečení</label>
                        <select name="smtp_secure" class="form-select">
                            <?php foreach (['none', 'ssl', 'tls'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= setting('smtp_secure') === $opt ? 'selected' : '' ?>>
                                    <?= strtoupper(e($opt)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php settingInput('smtp_username', 'Uživatel'); ?>

                    <div class="mb-3">
                        <label class="form-label">Heslo</label>
                        <div class="input-group">
                            <input type="password"
                                   name="smtp_password"
                                   id="smtp_password"
                                   class="form-control"
                                   value="<?= e(setting('smtp_password')) ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('smtp_password')">
                                Zobrazit
                            </button>
                        </div>
                    </div>

                    <?php settingInput('smtp_from_email', 'Odesílatel – e-mail', 'email'); ?>
                    <?php settingInput('smtp_from_name', 'Odesílatel – jméno'); ?>

                    <div class="mb-3">
                        <a class="btn btn-outline-primary" href="test_email.php">
                            Odeslat testovací e-mail
                        </a>
                    </div>

                    <hr class="my-4">

                    <?php sectionTitle('Mailing / hromadná rozesílka'); ?>

                    <?php settingSwitch('mailing_smtp_enabled', 'Používat samostatný SMTP pro mailing'); ?>
                    <?php settingInput('mailing_smtp_host', 'Mailing SMTP server'); ?>
                    <?php settingInput('mailing_smtp_port', 'Port', 'number'); ?>

                    <div class="mb-3">
                        <label class="form-label">Zabezpečení</label>
                        <select name="mailing_smtp_secure" class="form-select">
                            <?php foreach (['none', 'ssl', 'tls'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= setting('mailing_smtp_secure') === $opt ? 'selected' : '' ?>>
                                    <?= strtoupper(e($opt)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php settingInput('mailing_smtp_username', 'Uživatel'); ?>

                    <div class="mb-3">
                        <label class="form-label">Heslo</label>
                        <div class="input-group">
                            <input type="password"
                                   name="mailing_smtp_password"
                                   id="mailing_smtp_password"
                                   class="form-control"
                                   value="<?= e(setting('mailing_smtp_password')) ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('mailing_smtp_password')">
                                Zobrazit
                            </button>
                        </div>
                    </div>

                    <?php settingInput('mailing_from_email', 'Odesílatel – e-mail', 'email'); ?>
                    <?php settingInput('mailing_from_name', 'Odesílatel – jméno'); ?>
                    <?php settingInput('mailing_reply_to', 'Reply-To', 'email'); ?>

                    <hr class="my-4">

                    <?php sectionTitle('Dávkování'); ?>

                    <?php settingInput('mailing_batch_size', 'Počet e-mailů na běh cronu', 'number'); ?>
                    <?php settingInput('mailing_max_attempts', 'Maximální počet pokusů', 'number'); ?>

                <?php elseif ($active === 'forms'): ?>

                    <?php sectionTitle('Ochrana formulářů'); ?>

                    <?php settingSwitch('recaptcha_enabled', 'Používat Google reCAPTCHA'); ?>
                    <?php settingInput('recaptcha_site_key', 'reCAPTCHA Site key'); ?>
                    <?php settingInput('recaptcha_secret_key', 'reCAPTCHA Secret key'); ?>

                    <hr class="my-4">

                    <?php sectionTitle('AI nastavení'); ?>

                    <?php settingInput('mailing_ai_api_key', 'OpenAI API klíč', 'text', 'sk-...'); ?>

                <?php elseif ($active === 'invoices'): ?>

                    <?php sectionTitle('Dodavatel'); ?>

                    <?php settingInput('invoice_supplier_name', 'Název firmy / jméno'); ?>

                    <div class="row">
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_ico', 'IČO'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_dic', 'DIČ'); ?>
                        </div>
                    </div>

                    <?php settingInput('invoice_supplier_street', 'Ulice a číslo'); ?>

                    <div class="row">
                        <div class="col-md-4">
                            <?php settingInput('invoice_supplier_city', 'Město'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php settingInput('invoice_supplier_zip', 'PSČ'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php settingInput('invoice_supplier_country', 'Země'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_email', 'E-mail', 'email'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_phone', 'Telefon'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Logo dodavatele</label>
                        <div class="input-group">
                            <input type="text"
                                   name="invoice_supplier_logo"
                                   id="invoice_supplier_logo"
                                   class="form-control"
                                   placeholder="URL loga"
                                   value="<?= e(setting('invoice_supplier_logo')) ?>"
                                   readonly>
                            <button type="button"
                                    class="btn btn-outline-secondary open-picker"
                                    data-bs-toggle="modal"
                                    data-bs-target="#imagePickerModal"
                                    data-target-input="#invoice_supplier_logo"
                                    data-target-preview="#invoice-logo-preview">
                                Vybrat
                            </button>
                        </div>

                        <div class="mt-2" id="invoice-logo-preview" style="<?= setting('invoice_supplier_logo') ? '' : 'display:none' ?>">
                            <img src="<?= e(setting('invoice_supplier_logo')) ?>"
                                 alt="Logo dodavatele"
                                 class="img-fluid mt-2 rounded border"
                                 style="max-height:120px;">
                        </div>
                    </div>

                    <hr class="my-4">

                    <?php sectionTitle('Bankovní údaje'); ?>

                    <div class="row">
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_account_number', 'Číslo účtu'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_bank_code', 'Kód banky'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_iban', 'IBAN'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php settingInput('invoice_supplier_swift', 'SWIFT'); ?>
                        </div>
                    </div>

                    <hr class="my-4">

                    <?php sectionTitle('Nastavení fakturace'); ?>

                    <?php settingSwitch('invoice_is_vat_payer', 'Dodavatel je plátce DPH'); ?>

                    <div class="row">
                        <div class="col-md-4">
                            <?php settingInput('invoice_default_due_days', 'Výchozí splatnost (dny)', 'number', '', '14'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php settingInput('invoice_default_currency', 'Výchozí měna', 'text', '', 'CZK'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php settingInput('invoice_number_prefix', 'Prefix čísla faktury', 'text', 'např. 2026-'); ?>
                        </div>
                    </div>

                    <hr class="my-4">

                    <?php sectionTitle('Vzhled faktury'); ?>

                    <div class="row">
                        <div class="col-md-6">
                            <?php settingColor('invoice_color_primary', 'Barva hlavního pruhu faktury', '#eebc00'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php settingColor('invoice_color_primary_text', 'Barva textu – hlavní pruh', '#000000'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <?php settingColor('invoice_color_secondary', 'Barva datumového pruhu faktury', '#eeeeee'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php settingColor('invoice_color_secondary_text', 'Barva textu – datumový pruh', '#000000'); ?>
                        </div>
                    </div>

                    <?php settingTextarea('invoice_footer_note', 'Poznámka / patička faktury', 4); ?>

                <?php elseif ($active === 'uploads'): ?>

                    <?php sectionTitle('Uploady'); ?>

                    <?php settingInput('uploads_max_mb', 'Max. velikost uploadu (MB)', 'number'); ?>
                    <?php settingInput('uploads_allowed_ext', 'Povolené přípony', 'text', 'jpg,png,pdf,docx'); ?>
                    <?php settingInput('image_max_width', 'Max. šířka obrázku (px)', 'number'); ?>
                    <?php settingInput('image_max_height', 'Max. výška obrázku (px)', 'number'); ?>

                <?php elseif ($active === 'listing'): ?>

                    <?php sectionTitle('Výpisy v administraci'); ?>

                    <?php settingInput('pagination_per_page', 'Položek na stránku', 'number'); ?>

                <?php elseif ($active === 'maintenance'): ?>

                    <?php sectionTitle('Režim údržby'); ?>

                    <?php settingSwitch('maintenance_enabled', 'Zapnout režim údržby'); ?>
                    <?php settingTextarea('maintenance_message', 'Zpráva pro návštěvníky', 2); ?>
                    <?php settingInput('maintenance_ip_whitelist', 'Whitelist IP', 'text', 'IP adresy oddělené čárkou'); ?>

                <?php endif; ?>
            </div>

            <div class="card-footer bg-white d-flex justify-content-between">
                <button class="btn btn-success">
                    <i class="bi bi-save me-1"></i> Uložit nastavení
                </button>
            </div>
        </form>

    <?php else: ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Nástroje</strong>
            </div>

            <div class="card-body">
                <h5 class="mb-3">Export nastavení</h5>

                <p class="text-muted">
                    Export stáhne aktuální nastavení do JSON souboru.
                </p>

                <a class="btn btn-outline-secondary" href="export.php">
                    <i class="bi bi-download me-1"></i> Exportovat JSON
                </a>

                <hr class="my-4">

                <h5 class="mb-3">Import nastavení</h5>

                <form action="import.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                    <div class="mb-3">
                        <label class="form-label">Import JSON</label>
                        <input type="file"
                               name="file"
                               accept=".json,application/json"
                               class="form-control"
                               required>
                    </div>

                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-upload me-1"></i> Importovat
                    </button>
                </form>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>