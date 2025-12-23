<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'tickets';
$userType = currentUserType();

requireRoles(['gestor', 'admin']);

$statusOptions = [
    'aberta' => 'Aberta',
    'em_andamento' => 'Em andamento',
    'concluida' => 'Concluída',
    'cancelada' => 'Cancelada',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if ($ticketId > 0 && array_key_exists($newStatus, $statusOptions)) {
        try {
            $pdo = getPDO();
            // Carrega dados do protocolo e do usuário para notificar
            $info = findServiceRequestInfo($pdo, $ticketId);

            updateServiceRequestStatus($pdo, $ticketId, $newStatus);
            flash('success', 'Status atualizado.');

            // Notifica usuário sobre o novo status
            if ($info) {
                $statusLabel = $statusOptions[$newStatus] ?? $newStatus;
                $emailBody = <<<TXT
Olá, {$info['name']}!

Sua solicitação de "{$info['service_name']}" teve o status atualizado.
Status: {$statusLabel}
Problema: {$info['problem_type']}
Endereço: {$info['address']}

Responderemos com novidades assim que possível.
TXT;
                if (!empty($info['email']) && filter_var($info['email'], FILTER_VALIDATE_EMAIL)) {
                    sendEmail($info['email'], 'Atualização de status da sua solicitação', $emailBody);
                }
                if (!empty($info['phone'])) {
                    $whatsMsg = "Status atualizado da sua solicitação de {$info['service_name']}: {$statusLabel}. Problema: {$info['problem_type']}.";
                    sendWhatsAppMessage(normalizeDigits($info['phone']), $whatsMsg);
                }
            }
        } catch (Throwable $e) {
            flash('danger', 'Erro ao atualizar status: ' . $e->getMessage());
        }
    } else {
        flash('danger', 'Dados inválidos para atualização.');
    }
    header('Location: tickets.php');
    exit;
}

$serviceOptions = [
    '' => 'Todos os serviços',
    'Iluminação pública' => 'Iluminação pública',
    'Limpeza' => 'Limpeza',
    'Tributos' => 'Tributos',
    'Pavimentação' => 'Pavimentação',
];

$filterService = trim($_GET['service'] ?? '');

$tickets = [];
$ticketsError = null;
try {
    $pdo = getPDO();
    $tickets = listTickets($pdo, $filterService !== '' && array_key_exists($filterService, $serviceOptions) ? $filterService : null);
} catch (Throwable $e) {
    $ticketsError = 'Erro ao carregar chamados: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chamados - Administração</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .badge-soft {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
            border: 1px solid rgba(125, 211, 252, 0.35);
        }
        .btn-ghost {
            border: 1px solid rgba(255,255,255,0.2);
            color: #e2e8f0;
        }
        .status-select {
            min-width: 170px;
            background-color: rgba(255,255,255,0.08);
            border: 1px solid rgba(14,165,233,0.4);
            color: #e2e8f0;
        }
        .status-select:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.25);
            background-color: rgba(255,255,255,0.12);
            color: #fff;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

        <div class="glass p-4 mb-4">
            <p class="text-uppercase small text-info mb-1">Administração</p>
            <h2 class="fw-bold mb-2">Chamados dos usuários</h2>
            <p class="mb-0 text-secondary">Visualize e altere o status das solicitações enviadas.</p>
        </div>

        <div class="glass p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Chamados</p>
                    <h4 class="mb-0">Solicitações recebidas</h4>
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
                    <span class="badge bg-info text-dark"><?php echo count($tickets); ?> itens</span>
                </div>
            </div>
            <?php if ($ticketsError): ?>
                <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($ticketsError); ?></div>
            <?php elseif (!$tickets): ?>
                <p class="text-secondary mb-0">Nenhuma solicitação enviada ainda.</p>
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
                            <th>Ação</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $t): ?>
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
                                <td>
                                    <form method="POST" class="d-flex gap-2 align-items-center mb-0">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>">
                                        <select name="status" class="form-select form-select-sm status-select" aria-label="Alterar status">
                                            <option value="" disabled <?php echo !array_key_exists($t['status'], $statusOptions) ? 'selected' : ''; ?>>Selecione</option>
                                            <?php foreach ($statusOptions as $key => $label): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $t['status'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-brand btn-sm">Salvar</button>
                                    </form>
                                </td>
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
