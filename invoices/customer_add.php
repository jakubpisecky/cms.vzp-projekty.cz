<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";

requirePermission('invoices');

$name = '';
$ico = '';
$dic = '';
$street = '';
$city = '';
$zip = '';
$country = 'Česká republika';
$email = '';
$phone = '';
$note = '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $ico     = trim($_POST['ico'] ?? '');
    $dic     = trim($_POST['dic'] ?? '');
    $street  = trim($_POST['street'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $zip     = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $note    = trim($_POST['note'] ?? '');

    if ($name === '') {
        $error = "Název odběratele je povinný.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "E-mail není ve správném formátu.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO invoice_customers
            (name, ico, dic, street, city, zip, country, email, phone, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "Nepodařilo se připravit SQL dotaz: " . $conn->error;
        } else {
            $stmt->bind_param(
                "ssssssssss",
                $name,
                $ico,
                $dic,
                $street,
                $city,
                $zip,
                $country,
                $email,
                $phone,
                $note
            );

            if ($stmt->execute()) {
                $newId = (int)$stmt->insert_id;
                $stmt->close();

                if (function_exists('logAction')) {
                    logAction("Vytvořen odběratel ID {$newId}: {$name}");
                }

                header("Location: customers.php?created=1");
                exit;
            } else {
                $error = "Nepodařilo se uložit odběratele: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">

            <div class="admin-page-header-content mb-4">
                <div>
                    <h2 class="mb-1">Nový odběratel</h2>
                    <p class="text-muted mb-0">Vytvoření nového odběratele</p>
                </div>
                    <a href="customers.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět
                    </a>
                
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <label for="ares-search" class="form-label">Vyhledat firmu v ARES</label>
                    <input type="text"
                           id="ares-search"
                           class="form-control"
                           placeholder="Začněte psát název firmy">

                    <div class="form-text">
                        Po kliknutí na nalezenou firmu se údaje automaticky doplní do formuláře níže.
                    </div>

                    <div id="ares-suggestions" class="list-group mt-2"></div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="post">

                        <div class="mb-3">
                            <label for="name" class="form-label">Název / jméno <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ico" class="form-label">IČO</label>
                                <input type="text" name="ico" id="ico" class="form-control" value="<?= htmlspecialchars($ico) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dic" class="form-label">DIČ</label>
                                <input type="text" name="dic" id="dic" class="form-control" value="<?= htmlspecialchars($dic) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="street" class="form-label">Ulice a číslo</label>
                            <input type="text" name="street" id="street" class="form-control" value="<?= htmlspecialchars($street) ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">Město</label>
                                <input type="text" name="city" id="city" class="form-control" value="<?= htmlspecialchars($city) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="zip" class="form-label">PSČ</label>
                                <input type="text" name="zip" id="zip" class="form-control" value="<?= htmlspecialchars($zip) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="country" class="form-label">Země</label>
                                <input type="text" name="country" id="country" class="form-control" value="<?= htmlspecialchars($country) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">Poznámka</label>
                            <textarea name="note" id="note" rows="4" class="form-control"><?= htmlspecialchars($note) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="customers.php" class="btn btn-outline-secondary">Zrušit</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Uložit odběratele
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('ares-search');
    const suggestions = document.getElementById('ares-suggestions');

    const nameInput = document.getElementById('name');
    const icoInput = document.getElementById('ico');
    const dicInput = document.getElementById('dic');
    const streetInput = document.getElementById('street');
    const cityInput = document.getElementById('city');
    const zipInput = document.getElementById('zip');
    const countryInput = document.getElementById('country');

    if (!input || !suggestions) return;

    let timer = null;
    let controller = null;

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[ch];
        });
    }

    function clearSuggestions() {
        suggestions.innerHTML = '';
    }

    input.addEventListener('input', function () {
        const q = input.value.trim();

        clearTimeout(timer);

        if (q.length < 3) {
            clearSuggestions();
            return;
        }

        timer = setTimeout(function () {
            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            suggestions.innerHTML = '<div class="list-group-item text-muted">Vyhledávám...</div>';

            fetch('ajax_ares_search.php?q=' + encodeURIComponent(q), {
                signal: controller.signal
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('ARES nedostupný');
                    }
                    return response.json();
                })
                .then(function (items) {
                    clearSuggestions();

                    if (!Array.isArray(items) || items.length === 0) {
                        suggestions.innerHTML = '<div class="list-group-item text-muted">Nic nebylo nalezeno.</div>';
                        return;
                    }

                    items.slice(0, 8).forEach(function (item) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';

                        const address = [
                            item.street || '',
                            item.zip || '',
                            item.city || ''
                        ].filter(Boolean).join(', ');

                        btn.innerHTML =
                            '<div><strong>' + escapeHtml(item.name) + '</strong></div>' +
                            '<small class="text-muted">IČO: ' + escapeHtml(item.ico) +
                            (address ? ' | ' + escapeHtml(address) : '') +
                            '</small>';

                        btn.addEventListener('click', function () {
                            if (nameInput) nameInput.value = item.name || '';
                            if (icoInput) icoInput.value = item.ico || '';
                            if (dicInput && item.ico) dicInput.value = 'CZ' + item.ico;
                            if (streetInput) streetInput.value = item.street || '';
                            if (cityInput) cityInput.value = item.city || '';
                            if (zipInput) zipInput.value = item.zip || '';
                            if (countryInput) countryInput.value = 'Česká republika';

                            input.value = item.name || '';
                            clearSuggestions();
                        });

                        suggestions.appendChild(btn);
                    });
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') return;
                    suggestions.innerHTML = '<div class="list-group-item text-danger">Nepodařilo se načíst data z ARES.</div>';
                });

        }, 350);
    });
})();
</script>

<?php include "../includes/footer.php"; ?>