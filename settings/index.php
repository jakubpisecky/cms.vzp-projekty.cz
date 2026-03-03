<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/settings_helpers.php";
requirePermission('settings');

$tabs = ['general','seo','analytics','social','emails','forms','uploads','listing','maintenance','tools'];
$active = $_GET['tab'] ?? 'general';
if (!in_array($active, $tabs, true)) $active = 'general';

$messages = [];

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $messages[] = ['type'=>'danger','text'=>'Neplatný CSRF token.'];
    } else {
        switch ($active) {
            case 'general':
                $allowed = ['site_title','site_tagline','site_description','site_logo_url','site_favicon_url','contact_email','contact_phone','contact_address'];
                break;
            case 'seo':
                $allowed = ['seo_meta_title_pattern','seo_meta_description_default'];
                break;
            case 'analytics':
                $allowed = ['cookiebar_enabled','cookiebar_text','ga4_measurement_id','meta_pixel_id'];
                break;
            case 'emails':
                $allowed = ['smtp_enabled','smtp_host','smtp_port','smtp_secure','smtp_username','smtp_password','smtp_from_email','smtp_from_name'];
                break;
            case 'forms':
                $allowed = ['recaptcha_enabled','recaptcha_site_key','recaptcha_secret_key'];
                break;
            case 'uploads':
                $allowed = ['uploads_max_mb','uploads_allowed_ext','image_max_width','image_max_height'];
                break;
            case 'listing':
                $allowed = ['pagination_per_page'];
                break;
            case 'maintenance':
                $allowed = ['maintenance_enabled','maintenance_message','maintenance_ip_whitelist'];
                break;
            case 'social':
                $allowed = ['social_facebook_url','social_instagram_url','social_twitter_url','social_linkedin_url','social_youtube_url','social_tiktok_url'];
                break;
            default:
                $allowed = [];
        }
        $res = settings_bulk_save($_POST, $allowed);
        if ($res['errors']) {
            foreach ($res['errors'] as $k=>$err) {
                $messages[] = ['type'=>'danger','text'=> e($k).': '.e($err)];
            }
        } else {
            $messages[] = ['type'=>'success','text'=>'Nastavení bylo uloženo.'];
        }
    }
}

include "../includes/header.php";
?>
<div class="container py-4">
  <h2 class="mb-3">Nastavení webu (frontend)</h2>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $active==='general'?'active':'' ?>" href="?tab=general">Obecné</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='seo'?'active':'' ?>" href="?tab=seo">SEO</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='analytics'?'active':'' ?>" href="?tab=analytics">Analytika & Cookies</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='social'?'active':'' ?>" href="?tab=social">Sociální sítě</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='emails'?'active':'' ?>" href="?tab=emails">E-maily (SMTP)</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='forms'?'active':'' ?>" href="?tab=forms">Formuláře</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='uploads'?'active':'' ?>" href="?tab=uploads">Soubory & Obrázky</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='listing'?'active':'' ?>" href="?tab=listing">Výpisy</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='maintenance'?'active':'' ?>" href="?tab=maintenance">Údržba</a></li>
    <li class="nav-item"><a class="nav-link <?= $active==='tools'?'active':'' ?>" href="?tab=tools">Nástroje (Export/Import)</a></li>
  </ul>

  <?php foreach ($messages as $m): ?>
    <div class="alert alert-<?= e($m['type']) ?>"><?= e($m['text']) ?></div>
  <?php endforeach; ?>

  <?php if ($active!=='tools'): ?>

  <div class="row">
    <div class="col-12"><!-- full width -->

      <form method="post" class="g-3 bg-white p-4 border rounded shadow-sm">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <?php if ($active==='general'): ?>
          <div class="mb-3">
            <label class="form-label">Titulek webu</label>
            <input type="text" name="site_title" class="form-control" value="<?= e(setting('site_title')) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Slogan / tagline</label>
            <input type="text" name="site_tagline" class="form-control" value="<?= e(setting('site_tagline')) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Meta popis (krátký)</label>
            <textarea name="site_description" rows="3" class="form-control"><?= e(setting('site_description')) ?></textarea>
          </div>

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
           placeholder="URL favicon (.ico, .png nebo .svg)"
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
    Doporučeno: 32&times;32&nbsp;px (PNG) nebo .ico se 16/32/48&nbsp;px. SVG je možné pro moderní prohlížeče.
  </div>
  <div class="mt-2" id="favicon-preview" style="<?= setting('site_favicon_url') ? '' : 'display:none' ?>">
    <img src="<?= e(setting('site_favicon_url')) ?>"
         alt="Favicon náhled"
         class="rounded border"
         style="width:32px; height:32px; object-fit:contain;">
  </div>
</div>


          <div class="mb-3">
            <label class="form-label">Kontaktní e-mail</label>
            <input type="email" name="contact_email" class="form-control" value="<?= e(setting('contact_email')) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Telefon</label>
            <input type="text" name="contact_phone" class="form-control" value="<?= e(setting('contact_phone')) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Adresa</label>
            <textarea name="contact_address" rows="2" class="form-control"><?= e(setting('contact_address')) ?></textarea>
          </div>

        <?php elseif ($active==='seo'): ?>
          <div class="mb-3">
            <label class="form-label">Vzor titulku stránky</label>
            <input type="text" name="seo_meta_title_pattern" class="form-control" value="<?= e(setting('seo_meta_title_pattern')) ?>">
            <div class="form-text">Proměnné: {title}, {site}</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Výchozí meta description</label>
            <textarea name="seo_meta_description_default" rows="3" class="form-control"><?= e(setting('seo_meta_description_default')) ?></textarea>
          </div>

        <?php elseif ($active==='analytics'): ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="cookiebar_enabled" value="1" id="cookiebar_enabled" <?= setting('cookiebar_enabled')?'checked':'' ?>>
            <label class="form-check-label" for="cookiebar_enabled">Zobrazovat cookie lištu</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Text cookie lišty</label>
            <textarea name="cookiebar_text" rows="2" class="form-control"><?= e(setting('cookiebar_text')) ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Google Analytics 4 – Measurement ID</label>
            <input type="text" name="ga4_measurement_id" class="form-control" value="<?= e(setting('ga4_measurement_id')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Meta Pixel ID</label>
            <input type="text" name="meta_pixel_id" class="form-control" value="<?= e(setting('meta_pixel_id')) ?>">
          </div>

        <?php elseif ($active==='emails'): ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="smtp_enabled" value="1" id="smtp_enabled" <?= setting('smtp_enabled')?'checked':'' ?>>
            <label class="form-check-label" for="smtp_enabled">Posílat e-maily přes SMTP</label>
          </div>
          <div class="mb-3"><label class="form-label">SMTP server</label>
            <input type="text" name="smtp_host" class="form-control" value="<?= e(setting('smtp_host')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Port</label>
            <input type="number" name="smtp_port" class="form-control" value="<?= e(setting('smtp_port')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Zabezpečení</label>
            <select name="smtp_secure" class="form-select">
              <?php foreach (['none','ssl','tls'] as $opt): ?>
                <option value="<?= $opt ?>" <?= setting('smtp_secure')===$opt?'selected':'' ?>><?= strtoupper($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Uživatel</label>
            <input type="text" name="smtp_username" class="form-control" value="<?= e(setting('smtp_username')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Heslo</label>
            <div class="input-group">
              <input type="password" name="smtp_password" id="smtp_password" class="form-control" value="<?= e(setting('smtp_password')) ?>">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('smtp_password')">Zobrazit</button>
            </div>
          </div>
          <div class="mb-3"><label class="form-label">Odesílatel – e-mail</label>
            <input type="email" name="smtp_from_email" class="form-control" value="<?= e(setting('smtp_from_email')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Odesílatel – jméno</label>
            <input type="text" name="smtp_from_name" class="form-control" value="<?= e(setting('smtp_from_name')) ?>">
          </div>
          <div class="text-end">
            <a class="btn btn-outline-primary" href="test_email.php">Odeslat testovací e-mail</a>
          </div>

        <?php elseif ($active==='forms'): ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="recaptcha_enabled" value="1" id="recaptcha_enabled" <?= setting('recaptcha_enabled')?'checked':'' ?>>
            <label class="form-check-label" for="recaptcha_enabled">Používat Google reCAPTCHA</label>
          </div>
          <div class="mb-3"><label class="form-label">reCAPTCHA Site key</label>
            <input type="text" name="recaptcha_site_key" class="form-control" value="<?= e(setting('recaptcha_site_key')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">reCAPTCHA Secret key</label>
            <input type="text" name="recaptcha_secret_key" class="form-control" value="<?= e(setting('recaptcha_secret_key')) ?>">
          </div>

        <?php elseif ($active==='uploads'): ?>
          <div class="mb-3"><label class="form-label">Max. velikost uploadu (MB)</label>
            <input type="number" name="uploads_max_mb" class="form-control" value="<?= e(setting('uploads_max_mb')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Povolené přípony (čárkou)</label>
            <input type="text" name="uploads_allowed_ext" class="form-control" value="<?= e(setting('uploads_allowed_ext')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Max. šířka (px)</label>
            <input type="number" name="image_max_width" class="form-control" value="<?= e(setting('image_max_width')) ?>">
          </div>
          <div class="mb-3"><label class="form-label">Max. výška (px)</label>
            <input type="number" name="image_max_height" class="form-control" value="<?= e(setting('image_max_height')) ?>">
          </div>

        <?php elseif ($active==='listing'): ?>
          <div class="mb-3"><label class="form-label">Položek na stránku</label>
            <input type="number" name="pagination_per_page" class="form-control" value="<?= e(setting('pagination_per_page')) ?>">
          </div>

        <?php elseif ($active==='maintenance'): ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="maintenance_enabled" value="1" id="maintenance_enabled" <?= setting('maintenance_enabled')?'checked':'' ?>>
            <label class="form-check-label" for="maintenance_enabled">Zapnout režim údržby</label>
          </div>
          <div class="mb-3"><label class="form-label">Zpráva pro návštěvníky</label>
            <textarea name="maintenance_message" rows="2" class="form-control"><?= e(setting('maintenance_message')) ?></textarea>
          </div>
          <div class="mb-3"><label class="form-label">Whitelist IP (čárkou)</label>
            <input type="text" name="maintenance_ip_whitelist" class="form-control" value="<?= e(setting('maintenance_ip_whitelist')) ?>">
          </div>

        <?php elseif ($active==='social'): ?>
          <div class="mb-3">
            <label class="form-label">Facebook URL</label>
            <input type="url" name="social_facebook_url" class="form-control" placeholder="https://www.facebook.com/..." value="<?= e(setting('social_facebook_url')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Instagram URL</label>
            <input type="url" name="social_instagram_url" class="form-control" placeholder="https://www.instagram.com/..." value="<?= e(setting('social_instagram_url')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Twitter / X URL</label>
            <input type="url" name="social_twitter_url" class="form-control" placeholder="https://twitter.com/..." value="<?= e(setting('social_twitter_url')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">LinkedIn URL</label>
            <input type="url" name="social_linkedin_url" class="form-control" placeholder="https://www.linkedin.com/company/..." value="<?= e(setting('social_linkedin_url')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">YouTube URL</label>
            <input type="url" name="social_youtube_url" class="form-control" placeholder="https://www.youtube.com/..." value="<?= e(setting('social_youtube_url')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">TikTok URL</label>
            <input type="url" name="social_tiktok_url" class="form-control" placeholder="https://www.tiktok.com/@..." value="<?= e(setting('social_tiktok_url')) ?>">
          </div>

        <?php endif; ?>

        <div class="d-flex justify-content-between">
                <button class="btn btn-success">
                    <i class="bi bi-save me-1"></i> Uložit
                </button>
            </div>
      </form>

    </div>
  </div><!-- /row -->

  <?php else: ?>

  <div class="row">
    <div class="col-12"><!-- full width -->
      <div class="g-3 bg-white p-4 border rounded shadow-sm">
        <h5 class="mb-3">Nástroje</h5>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary" href="export.php">Exportovat JSON</a>
        </div>
        <hr>
        <form action="import.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <div class="mb-3">
            <label class="form-label">Import JSON</label>
            <input type="file" name="file" accept=".json,application/json" class="form-control" required>
          </div>
          <button class="btn btn-outline-primary" type="submit">Importovat</button>
        </form>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
