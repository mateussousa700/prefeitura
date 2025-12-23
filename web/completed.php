<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'completed';
$userType = currentUserType();

requireRoles(['gestor', 'admin']);

$serviceOptions = [
    '' => 'Todos os serviços',
    'Iluminação pública' => 'Iluminação pública',
    'Limpeza' => 'Limpeza',
    'Tributos' => 'Tributos',
    'Pavimentação' => 'Pavimentação',
];
$filterService = trim($_GET['service'] ?? '');

$completed = [];
$listError = null;
try {
    $pdo = getPDO();
    $completed = listCompletedRequests($pdo, $filterService !== '' && array_key_exists($filterService, $serviceOptions) ? $filterService : null);
} catch (Throwable $e) {
    $listError = 'Erro ao carregar concluídos: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Serviços concluídos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .status-pill {
            border-radius: 12px;
            padding: 6px 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

        <div class="glass p-4 mb-4">
            <p class="text-uppercase small text-info mb-1">Concluídos</p>
            <h2 class="fw-bold mb-2">Serviços finalizados</h2>
            <p class="mb-0 text-secondary">Visualize os protocolos já resolvidos e filtre por tipo de serviço.</p>
        </div>

        <div class="glass p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Lista</p>
                    <h4 class="mb-0">Protocolos concluídos</h4>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 text-secondary small" for="service">Filtrar por serviço</label>
                        <select name="service" id="service" class="form-select form-select-sm">
                            <?php foreach ($serviceOptions as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filterService === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-brand btn-sm px-3" type="submit">Filtrar</button>
                    </form>
                    <span class="badge bg-info text-dark"><?php echo count($completed); ?> itens</span>
                </div>
            </div>
            <?php if ($listError): ?>
                <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($listError); ?></div>
            <?php elseif (!$completed): ?>
                <p class="text-secondary mb-0">Nenhum protocolo concluído.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Serviço</th>
                            <th>Problema</th>
                            <th>Usuário</th>
                            <th>Contato</th>
                            <th>Evidência</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($completed as $t): ?>
                            <?php
                            $files = parseEvidenceFiles($t['evidence_files'] ?? null);
                            $firstImage = $files[0] ?? null;
                            $evidenceCount = count($files);
                            $tooltipHtml = $firstImage
                                ? '<img src="' . htmlspecialchars($firstImage, ENT_QUOTES) . '" style="max-width:220px; border-radius:8px;" />'
                                : 'Nenhuma imagem';
                            $tooltipAttr = htmlspecialchars($tooltipHtml, ENT_QUOTES);
                            $badgeClass = statusBadgeClass((string)$t['status']);
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($t['id']); ?></td>
                                <td><?php echo htmlspecialchars($t['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($t['problem_type']); ?></td>
                                <td><?php echo htmlspecialchars($t['user_name']); ?></td>
                                <td>
                                    <div class="small">
                                        <div><?php echo htmlspecialchars($t['user_email']); ?></div>
                                        <div><?php echo htmlspecialchars($t['user_phone']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($evidenceCount > 0): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($firstImage): ?>
                                                <a class="d-inline-flex" href="<?php echo htmlspecialchars($firstImage); ?>" target="_blank" rel="noopener"
                                                   data-bs-toggle="tooltip" data-bs-html="true" title="<?php echo $tooltipAttr; ?>">
                                                    <img src="<?php echo htmlspecialchars($firstImage); ?>"
                                                         alt="Evidência" style="height:42px;width:42px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.15);display:block;">
                                                </a>
                                            <?php endif; ?>
                                            <span class="badge bg-primary"><?php echo $evidenceCount; ?> foto(s)</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">Sem evidência</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?> text-uppercase"><?php echo htmlspecialchars($t['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>
</body>
</html>
