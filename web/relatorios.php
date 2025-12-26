<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'relatorios';
$userType = currentUserType();

requireRoles(['gestor', 'admin', 'gestor_global']);

$today = new DateTimeImmutable('now');
$defaultStart = $today->modify('first day of this month')->format('Y-m-d');
$defaultEnd = $today->format('Y-m-d');

$selectedType = $_GET['tipo'] ?? 'mensal-secretaria';
$startDate = $_GET['inicio'] ?? $defaultStart;
$endDate = $_GET['fim'] ?? $defaultEnd;

$reportTypes = [
    'mensal-secretaria' => 'Relatório mensal por secretaria',
    'sla' => 'Relatório de SLA',
    'bairro' => 'Relatório por bairro',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatórios oficiais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .report-card {
            background: linear-gradient(150deg, rgba(15, 23, 42, 0.75), rgba(30, 41, 59, 0.6));
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            padding: 20px;
        }
        .report-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

        <div class="glass p-4 mb-4">
            <div class="panel-header flex-wrap">
                <div>
                    <p class="text-uppercase small text-info mb-1">Relatórios</p>
                    <h2 class="panel-title">Relatórios oficiais em PDF</h2>
                    <p class="panel-subtitle">Selecione o período e exporte com QR Code de validação.</p>
                </div>
                <button type="submit" class="btn btn-brand" form="reportForm">Exportar PDF</button>
            </div>
        </div>

        <div class="report-card mb-4">
            <div class="panel-header flex-wrap">
                <div>
                    <h4 class="panel-title">Configurar relatório</h4>
                    <p class="panel-subtitle">Escolha o tipo e o intervalo de datas.</p>
                </div>
            </div>

            <form id="reportForm" class="form-clarity" novalidate>
                <div class="report-grid">
                    <div>
                        <label for="report_type" class="form-label">Tipo de relatório</label>
                        <select id="report_type" name="tipo" class="form-select" required>
                            <?php foreach ($reportTypes as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selectedType === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">Escolha o formato conforme a necessidade institucional.</span>
                    </div>
                    <div>
                        <label for="report_start" class="form-label">Início do período</label>
                        <input type="date" id="report_start" name="inicio" class="form-control" required value="<?php echo htmlspecialchars($startDate); ?>">
                        <span class="form-hint">Data inicial do relatório.</span>
                    </div>
                    <div>
                        <label for="report_end" class="form-label">Fim do período</label>
                        <input type="date" id="report_end" name="fim" class="form-control" required value="<?php echo htmlspecialchars($endDate); ?>">
                        <span class="form-hint">Data final do relatório.</span>
                    </div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="reportError"></div>

                <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                    <button type="submit" class="btn btn-brand">Exportar PDF</button>
                    <button type="button" class="btn btn-outline-info text-white border-info" id="previewLink">Copiar link</button>
                </div>
                <p class="form-hint mt-2 mb-0">O PDF abre em nova aba e pode ser compartilhado para validação.</p>
            </form>
        </div>
    </main>
</div>

<script>
    const form = document.getElementById('reportForm');
    const errorBox = document.getElementById('reportError');
    const copyButton = document.getElementById('previewLink');

    function buildReportUrl() {
        const type = document.getElementById('report_type').value;
        const start = document.getElementById('report_start').value;
        const end = document.getElementById('report_end').value;

        if (!type || !start || !end) {
            return null;
        }

        const params = new URLSearchParams({ inicio: start, fim: end });
        return `api/v1/relatorios/index.php/${type}?${params.toString()}`;
    }

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearError();
        const url = buildReportUrl();
        if (!url) {
            showError('Preencha o tipo e o período antes de exportar.');
            return;
        }
        const newTab = window.open(url, '_blank');
        if (!newTab) {
            window.location.href = url;
        }
    });

    copyButton?.addEventListener('click', async () => {
        clearError();
        const url = buildReportUrl();
        if (!url) {
            showError('Preencha o tipo e o período antes de copiar.');
            return;
        }
        const fullUrl = new URL(url, window.location.href).toString();
        try {
            await navigator.clipboard.writeText(fullUrl);
            copyButton.textContent = 'Link copiado';
            setTimeout(() => { copyButton.textContent = 'Copiar link'; }, 1800);
        } catch (e) {
            showError('Não foi possível copiar o link.');
        }
    });
</script>
</body>
</html>
