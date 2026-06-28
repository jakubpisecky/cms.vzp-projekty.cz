<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$error = '';
$messages = [];

function normalizeCsvHeader(string $value): string
{
    $value = trim($value);
    $value = strtolower($value);
    $value = str_replace([' ', '-'], '_', $value);
    return $value;
}

function detectCsvDelimiter(string $line): string
{
    $delimiters = [';', ',', "\t"];
    $best = ';';
    $bestCount = -1;

    foreach ($delimiters as $delimiter) {
        $count = substr_count($line, $delimiter);
        if ($count > $bestCount) {
            $bestCount = $count;
            $best = $delimiter;
        }
    }

    return $best;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['csv_file']) || !isset($_FILES['csv_file']['tmp_name'])) {
        $error = "Nebyl vybrán žádný CSV soubor.";
    } elseif ((int)$_FILES['csv_file']['error'] !== 0) {
        $error = "Došlo k chybě při nahrávání souboru.";
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpPath, 'r');

        if (!$handle) {
            $error = "Soubor se nepodařilo otevřít.";
        } else {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                $error = "Soubor je prázdný.";
            } else {
                $delimiter = detectCsvDelimiter($firstLine);
                rewind($handle);

                $headers = fgetcsv($handle, 0, $delimiter);

                if (!$headers || count($headers) === 0) {
                    $error = "Nepodařilo se načíst hlavičku CSV.";
                } else {
                    $headers = array_map('normalizeCsvHeader', $headers);

                    if (!in_array('email', $headers, true)) {
                        $error = "CSV musí obsahovat sloupec 'email'.";
                    } else {
                        $inserted = 0;
                        $updated = 0;
                        $skipped = 0;
                        $lineNumber = 1;

                        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                            $lineNumber++;

                            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                                continue;
                            }

                            $data = [];
                            foreach ($headers as $index => $header) {
                                $data[$header] = trim((string)($row[$index] ?? ''));
                            }

                            $email = trim((string)($data['email'] ?? ''));
                            $firstName = trim((string)($data['first_name'] ?? ''));
                            $lastName = trim((string)($data['last_name'] ?? ''));
                            $company = trim((string)($data['company'] ?? ''));

                            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $skipped++;
                                continue;
                            }

                            $stmt = $conn->prepare("
                                SELECT id, unsubscribe_token
                                FROM mailing_recipients
                                WHERE email = ?
                                LIMIT 1
                            ");
                            if (!$stmt) {
                                $error = "Chyba SQL při ověřování e-mailu: " . $conn->error;
                                break;
                            }

                            $stmt->bind_param("s", $email);
                            $stmt->execute();
                            $existing = $stmt->get_result()->fetch_assoc();
                            $stmt->close();

                            if ($existing) {
                                $recipientId = (int)$existing['id'];
                                $token = trim((string)($existing['unsubscribe_token'] ?? ''));

                                if ($token === '') {
                                    $token = generateUnsubscribeToken();
                                    $stmt = $conn->prepare("
                                        UPDATE mailing_recipients
                                        SET first_name = ?, last_name = ?, company = ?, unsubscribe_token = ?
                                        WHERE id = ?
                                    ");
                                    if (!$stmt) {
                                        $error = "Chyba SQL při aktualizaci příjemce: " . $conn->error;
                                        break;
                                    }

                                    $stmt->bind_param("ssssi", $firstName, $lastName, $company, $token, $recipientId);
                                } else {
                                    $stmt = $conn->prepare("
                                        UPDATE mailing_recipients
                                        SET first_name = ?, last_name = ?, company = ?
                                        WHERE id = ?
                                    ");
                                    if (!$stmt) {
                                        $error = "Chyba SQL při aktualizaci příjemce: " . $conn->error;
                                        break;
                                    }

                                    $stmt->bind_param("sssi", $firstName, $lastName, $company, $recipientId);
                                }

                                if (!$stmt->execute()) {
                                    $error = "Nepodařilo se aktualizovat příjemce na řádku {$lineNumber}: " . $stmt->error;
                                    $stmt->close();
                                    break;
                                }

                                $stmt->close();
                                $updated++;
                            } else {
                                $token = generateUnsubscribeToken();

                                $stmt = $conn->prepare("
                                    INSERT INTO mailing_recipients
                                        (email, first_name, last_name, company, active, unsubscribed, unsubscribe_token)
                                    VALUES
                                        (?, ?, ?, ?, 1, 0, ?)
                                ");
                                if (!$stmt) {
                                    $error = "Chyba SQL při vložení příjemce: " . $conn->error;
                                    break;
                                }

                                $stmt->bind_param("sssss", $email, $firstName, $lastName, $company, $token);

                                if (!$stmt->execute()) {
                                    $error = "Nepodařilo se vložit příjemce na řádku {$lineNumber}: " . $stmt->error;
                                    $stmt->close();
                                    break;
                                }

                                $stmt->close();
                                $inserted++;
                            }
                        }

                        if ($error === '') {
                            if (function_exists('logAction')) {
                                logAction("Import mailing příjemců z CSV: vloženo {$inserted}, aktualizováno {$updated}, přeskočeno {$skipped}");
                            }

                            $messages[] = "Import dokončen.";
                            $messages[] = "Vloženo: {$inserted}";
                            $messages[] = "Aktualizováno: {$updated}";
                            $messages[] = "Přeskočeno: {$skipped}";
                        }
                    }
                }
            }

            fclose($handle);
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
                    <h2 class="mb-1">Import příjemců</h1>
                    <p class="text-muted mb-0">Nahrání kontaktů z CSV</p>
                </div>
                    <a href="recipients.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zpět na příjemce
                    </a>
            </div>

            <?php include "_menu.php"; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($messages)): ?>
                <div class="alert alert-success">
                    <?php foreach ($messages as $message): ?>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">CSV soubor</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                        </div>

                        <div class="form-text mb-3">
                            Očekávané sloupce: <code>email</code>, <code>first_name</code>, <code>last_name</code>, <code>company</code>.<br>
                            Povinný je pouze sloupec <code>email</code>. Oddělovač může být středník, čárka nebo tabulátor.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="recipients.php" class="btn btn-outline-secondary">Zrušit</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Importovat CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 mb-3">Ukázka CSV</h2>
                    <pre class="mb-0">email;first_name;last_name;company
                    jan.novak@example.cz;Jan;Novák;Firma s.r.o.
                    eva.svobodova@example.cz;Eva;Svobodová;Studio a.s.</pre>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>