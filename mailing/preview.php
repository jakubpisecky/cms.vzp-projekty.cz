<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/mailing.php";

requirePermission('mailing');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neplatné ID kampaně.");
}

$stmt = $conn->prepare("SELECT * FROM mailing_campaigns WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$campaign = $result->fetch_assoc();
$stmt->close();

if (!$campaign) {
    die("Kampaň nebyla nalezena.");
}

$previewRecipient = [
    'email' => 'jan.novak@example.cz',
    'first_name' => 'Jan',
    'last_name' => 'Novák',
    'company' => 'Ukázková firma s.r.o.',
    'unsubscribe_token' => 'preview-token-123'
];

$previewHtml = mailingReplaceVariables($campaign['content'], $previewRecipient);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Náhled kampaně – <?= htmlspecialchars($campaign['title']) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6f8;
        }

        .preview-page {
            width: min(100% - 2rem, 960px);
            margin: 24px auto 32px;
        }

        .preview-header {
            margin-bottom: 16px;
        }

        .preview-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .preview-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .preview-frame {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 .25rem 1rem rgba(0,0,0,.05);
        }

        .preview-scroll {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: #fff;
        }

        .preview-body {
            min-width: 320px;
        }

        .preview-body img {
            max-width: 100%;
            height: auto;
        }

        .preview-body table {
            max-width: 100%;
        }

        .preview-meta-row {
            display: grid;
            grid-template-columns: 120px minmax(0, 1fr);
            gap: .35rem .75rem;
            font-size: .95rem;
        }

        .preview-meta-row strong {
            color: #495057;
        }

        @media (max-width: 991.98px) {
            .preview-page {
                width: min(100% - 1rem, 960px);
                margin-top: 12px;
            }

            .preview-header-content {
                flex-direction: column;
                align-items: stretch !important;
            }

            .preview-actions {
                width: 100%;
            }

            .preview-actions .btn {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 575.98px) {
            .preview-page {
                width: 100%;
                margin: 0;
            }

            .preview-header {
                border-radius: 0;
            }

            .preview-frame {
                border-left: 0;
                border-right: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .preview-meta-row {
                grid-template-columns: 1fr;
                gap: .1rem;
            }

            .preview-meta-row + .preview-meta-row {
                margin-top: .6rem;
            }
        }
    </style>
</head>

<body>

<div class="preview-page">

    <div class="card shadow-sm border-0 preview-header">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 preview-header-content">
                <div>
                    <h1 class="h4 mb-3">Náhled e-mailu</h1>

                    <div class="preview-meta-row">
                        <strong>Kampaň:</strong>
                        <span><?= htmlspecialchars($campaign['title']) ?></span>
                    </div>

                    <div class="preview-meta-row">
                        <strong>Předmět:</strong>
                        <span><?= htmlspecialchars($campaign['subject']) ?></span>
                    </div>

                    <div class="preview-meta-row">
                        <strong>Příjemce:</strong>
                        <span>Jan Novák, jan.novak@example.cz</span>
                    </div>
                </div>

                <div class="preview-actions">
                    <a href="edit.php?id=<?= (int)$campaign['id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> Upravit kampaň
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="preview-frame">
        <div class="preview-scroll">
            <div class="preview-body">
                <?= $previewHtml ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>