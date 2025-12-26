<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'requests';
$userType = currentUserType();

$requests = [];
$requestsError = null;

try {
    $pdo = getPDO();
    $requests = listServiceRequestsByUser($pdo, (int) currentUserId());
} catch (Throwable $e) {
    $requestsError = 'Erro ao carregar suas solicitações: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meus protocolos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .badge-soft {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
            border: 1px solid rgba(125, 211, 252, 0.35);
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

        <div class="glass p-4 mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Meus protocolos</p>
                    <h2 class="fw-bold mb-2">Acompanhe todas as suas solicitações</h2>
                    <p class="mb-0 text-secondary">Veja o status, detalhes enviados e fotos de cada protocolo.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-brand" href="services.php#servicos">Abrir novo protocolo</a>
                    <a class="btn btn-outline-info text-white border-info" href="services.php">Voltar aos serviços</a>
                </div>
            </div>
        </div>

        <div class="glass p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Minhas solicitações</p>
                    <h4 class="mb-0">Protocolos enviados</h4>
                </div>
                <span class="badge bg-info text-dark"><?php echo count($requests); ?> itens</span>
            </div>
            <?php if ($requestsError): ?>
                <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($requestsError); ?></div>
            <?php elseif (!$requests): ?>
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
                    <p class="text-secondary mb-0">Você ainda não abriu protocolos. Clique no botão para iniciar seu primeiro.</p>
                    <a class="btn btn-brand" href="services.php#servicos">Abrir novo protocolo</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Subtipo</th>
                            <th>Evidências</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $statusOptions = listServiceStatusOptions(); ?>
                        <?php foreach ($requests as $req): ?>
                            <?php
                            $files = parseEvidenceFiles($req['evidence_files'] ?? null);
                            $firstImage = $files[0] ?? null;
                            $evidenceCount = count($files);
                            $tooltipHtml = $firstImage
                                ? '<img src="' . htmlspecialchars($firstImage, ENT_QUOTES) . '" style="max-width:220px; border-radius:8px;" />'
                                : 'Nenhuma imagem';
                            $tooltipAttr = htmlspecialchars($tooltipHtml, ENT_QUOTES);
                            $badgeClass = statusBadgeClass((string)$req['status']);
                            $statusKey = normalizeServiceStatus((string)$req['status']);
                            $statusLabel = $statusOptions[$statusKey] ?? (string)$req['status'];
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($req['id']); ?></td>
                                <td><?php echo htmlspecialchars($req['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['problem_type']); ?></td>
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
                                    <span class="badge <?php echo $badgeClass; ?> text-uppercase"><?php echo htmlspecialchars($statusLabel); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($req['created_at']))); ?></td>
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
    // Tooltips para evidências
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>
</body>
</html>
