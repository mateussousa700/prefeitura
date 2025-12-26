<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'tickets';
$userType = currentUserType();
$canReassignSecretaria = canUserReassignSecretaria($userType);

requireRoles(['gestor', 'admin']);

$statusOptions = listServiceStatusOptions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken($_POST['csrf_token'] ?? null, 'tickets.php');

    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $advance = $action === 'advance';
    $apply = $action === 'apply';
    $selectedStatus = trim((string)($_POST['status'] ?? ''));
    $requestedStatus = $selectedStatus !== '' ? $selectedStatus : null;
    $userId = (int) currentUserId();

    if ($ticketId > 0 && ($advance || $apply)) {
        try {
            $pdo = getPDO();

            if ($apply && $requestedStatus === null) {
                throw new RuntimeException('Selecione o status desejado.');
            }

            $newStatus = updateServiceRequestStatus(
                $pdo,
                $ticketId,
                $advance ? null : $requestedStatus,
                $userId,
                null,
                $apply
            );
            flash('success', 'Status atualizado.');
        } catch (Throwable $e) {
            flash('danger', 'Erro ao atualizar status: ' . $e->getMessage());
        }
    } else {
        flash('danger', 'Dados inválidos para atualização.');
    }
    header('Location: tickets.php');
    exit;
}

$serviceTypes = [];
$filterTypeId = (int)($_GET['type_id'] ?? 0);

$tickets = [];
$ticketsError = null;
try {
    $pdo = getPDO();
    $serviceTypes = listServiceTypes($pdo);
    $tickets = listTickets($pdo, $filterTypeId > 0 ? $filterTypeId : null);
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
            min-width: 190px;
            background-color: rgba(255,255,255,0.08);
            border: 1px solid rgba(14,165,233,0.4);
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 0.35rem 0.6rem;
        }
        .status-select:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.25);
            background-color: rgba(255,255,255,0.12);
            color: #fff;
        }
        .tickets-table th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #cbd5e1;
        }
        .tickets-table td {
            font-size: 0.92rem;
            padding: 0.55rem 0.65rem;
            vertical-align: middle;
        }
        .tickets-table .badge {
            font-size: 0.72rem;
            padding: 0.35rem 0.5rem;
        }
        .tickets-table .contact-cell {
            font-size: 0.85rem;
        }
        .tickets-table .contact-cell .small {
            font-size: 0.78rem;
        }
        .tickets-table .location-btn {
            font-size: 0.78rem;
            padding: 0.25rem 0.5rem;
        }
        .tickets-table img {
            height: 38px;
            width: 38px;
        }
        .action-form {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 220px;
        }
        .action-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }
        .action-controls {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            padding: 0.5rem;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.45);
            border: 1px solid rgba(148, 163, 184, 0.18);
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.35rem;
        }
        .action-apply {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.35);
            color: #e2e8f0;
            font-weight: 600;
        }
        .action-apply:hover {
            border-color: rgba(125, 211, 252, 0.6);
            color: #fff;
        }
        .filter-form .form-label {
            font-size: 0.78rem;
        }
        .filter-form .form-select,
        .filter-form .btn {
            font-size: 0.85rem;
            padding: 0.35rem 0.6rem;
        }
        .modal-content.glass {
            background: rgba(12, 18, 32, 0.93);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .location-label {
            color: #94a3b8;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .location-address {
            font-size: 1rem;
            color: #e2e8f0;
            white-space: pre-line;
        }
        .map-frame {
            width: 100%;
            height: 260px;
            border: 0;
            border-radius: 12px;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .history-item {
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .history-title {
            font-weight: 600;
            color: #e2e8f0;
        }
        .history-meta {
            font-size: 0.78rem;
            color: #94a3b8;
        }
        .history-empty {
            font-size: 0.85rem;
            color: #94a3b8;
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
                    <p class="text-uppercase small text-info mb-1">Administração</p>
                    <h2 class="panel-title">Chamados dos usuários</h2>
                    <p class="panel-subtitle">Visualize e altere o status das solicitações enviadas.</p>
                </div>
            </div>
        </div>

        <div class="glass p-4">
            <div class="panel-header flex-wrap">
                <div>
                    <p class="text-uppercase small text-info mb-1">Chamados</p>
                    <h4 class="panel-title">Solicitações recebidas</h4>
                    <p class="panel-subtitle">Use filtros rápidos e mantenha a fila atualizada.</p>
                </div>
                <span class="badge bg-info text-dark"><?php echo count($tickets); ?> itens</span>
            </div>
            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
                <form method="GET" class="d-flex align-items-center gap-2 filter-form">
                    <label class="form-label mb-0 text-secondary small" for="type_id">Filtrar por tipo</label>
                    <select name="type_id" id="type_id" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($serviceTypes as $type): ?>
                            <option value="<?php echo (int)$type['id']; ?>" <?php echo $filterTypeId === (int)$type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-brand btn-sm px-3" type="submit">Filtrar</button>
                </form>
            </div>
            <p class="form-hint mb-3">Dica: use “Aplicar status” para escolher qualquer etapa ou “Avançar” para seguir o fluxo padrão.</p>
            <?php if ($ticketsError): ?>
                <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($ticketsError); ?></div>
            <?php elseif (!$tickets): ?>
                <p class="text-secondary mb-0">Nenhuma solicitação enviada ainda.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 tickets-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Subtipo</th>
                            <th>Localização</th>
                            <th>Usuário</th>
                            <th>Contato</th>
                            <th>Evidência</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ação</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $statusTransitions = serviceStatusTransitions(); ?>
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
                            $currentStatus = normalizeServiceStatus((string)$t['status']);
                            $nextStatuses = $statusTransitions[$currentStatus] ?? [];
                            $nextStatus = $nextStatuses[0] ?? '';
                            $statusLabel = $statusOptions[$currentStatus] ?? $currentStatus;
                            $nextLabel = $nextStatus !== '' ? ($statusOptions[$nextStatus] ?? $nextStatus) : '';
                            $statusDisabled = !$nextStatuses;
                            $addressFull = formatServiceAddress($t['address'] ?? '', $t['neighborhood'] ?? '', $t['zip'] ?? '');
                            $secretariaName = $t['secretaria_nome'] ?? null;
                            $secretariaId = $t['secretaria_id'] ?? null;
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($t['id']); ?></td>
                                <td><?php echo htmlspecialchars($t['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($t['problem_type']); ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-outline-info btn-sm text-white border-info location-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#locationModal"
                                        data-address="<?php echo htmlspecialchars($addressFull, ENT_QUOTES); ?>"
                                        data-address-text="<?php echo htmlspecialchars((string)($t['address'] ?? ''), ENT_QUOTES); ?>"
                                        data-lat="<?php echo htmlspecialchars((string)($t['latitude'] ?? ''), ENT_QUOTES); ?>"
                                        data-lon="<?php echo htmlspecialchars((string)($t['longitude'] ?? ''), ENT_QUOTES); ?>"
                                        data-service="<?php echo htmlspecialchars((string)($t['service_name'] ?? ''), ENT_QUOTES); ?>"
                                        data-id="<?php echo htmlspecialchars((string)($t['id'] ?? ''), ENT_QUOTES); ?>"
                                        data-secretaria-id="<?php echo htmlspecialchars((string)($secretariaId ?? ''), ENT_QUOTES); ?>"
                                        data-secretaria-name="<?php echo htmlspecialchars((string)($secretariaName ?? ''), ENT_QUOTES); ?>"
                                    >
                                        Ver endereço
                                    </button>
                                </td>
                                <td><?php echo htmlspecialchars($t['user_name']); ?></td>
                                <td class="contact-cell">
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
                                                         alt="Evidência" style="height:38px;width:38px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.15);display:block;">
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
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t['created_at']))); ?></td>
                                <td>
                                    <form method="POST" class="action-form mb-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>">
                                        <div class="action-controls">
                                            <span class="action-label">Ação</span>
                                            <select class="form-select form-select-sm status-select" name="status" aria-label="Selecionar status">
                                                <?php foreach ($statusOptions as $statusCode => $statusText): ?>
                                                    <option value="<?php echo htmlspecialchars($statusCode); ?>" <?php echo $statusCode === $currentStatus ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($statusText); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="form-hint">Use a lista para saltos permitidos pelo gestor.</span>
                                            <div class="action-buttons">
                                                <button type="submit" class="btn btn-outline-info btn-sm action-apply" name="action" value="apply">Aplicar status</button>
                                                <button type="submit" class="btn btn-brand btn-sm action-advance" name="action" value="advance" <?php echo $statusDisabled ? 'disabled' : ''; ?>>
                                                    <?php echo $statusDisabled ? 'Finalizado' : 'Avançar para ' . htmlspecialchars($nextLabel); ?>
                                                </button>
                                            </div>
                                        </div>
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

<div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass">
            <div class="modal-header border-0">
                <div>
                    <p class="location-label mb-1">Localização da solicitação</p>
                    <h5 class="modal-title fw-bold" id="locationModalTitle">Endereço</h5>
                    <p class="panel-subtitle">Confira endereço, secretaria e histórico antes de ajustar.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p class="location-label mb-2">Endereço informado</p>
                    <div class="location-address" id="locationAddress">Endereço não disponível.</div>
                </div>
                <div class="mb-3">
                    <p class="location-label mb-2">Secretaria responsável</p>
                    <div class="location-address" id="locationSecretaria">Não definida.</div>
                </div>
                <?php if ($canReassignSecretaria): ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-info text-white border-info btn-sm" id="reassignSecretariaBtn">
                            Reatribuir secretaria
                        </button>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <p class="location-label mb-2">Histórico de reatribuições</p>
                    <div class="history-list" id="secretariaHistoryList">
                        <span class="history-empty">Nenhuma reatribuição registrada.</span>
                    </div>
                </div>
                <div class="mb-3">
                    <p class="location-label mb-2">Atualizar endereço textual</p>
                    <form method="POST" action="update_address.php" class="d-flex flex-column gap-2 form-clarity">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="ticket_id" id="locationTicketId" value="">
                        <textarea class="form-control" id="locationAddressInput" name="address" rows="2" required placeholder="Rua, número, ponto de referência"></textarea>
                        <span class="form-hint">Ajuste apenas o texto; coordenadas permanecem inalteradas.</span>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-info text-white border-info btn-sm">Salvar endereço</button>
                        </div>
                    </form>
                </div>
                <div class="mb-3">
                    <p class="location-label mb-2">Mapa</p>
                    <iframe class="map-frame" id="locationMap" title="Mapa do endereço"></iframe>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-secondary small" id="locationCoords"></span>
                    <a class="btn btn-outline-info text-white border-info btn-sm" id="locationLink" href="#" target="_blank" rel="noopener">
                        Abrir no mapa
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canReassignSecretaria): ?>
    <div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content glass">
            <div class="modal-header border-0">
                <div>
                    <p class="location-label mb-1">Gestão</p>
                    <h5 class="modal-title fw-bold">Reatribuir secretaria</h5>
                    <p class="panel-subtitle">Informe o motivo para manter a rastreabilidade.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="reassignForm" class="d-flex flex-column gap-3 form-clarity">
                    <div>
                        <label class="form-label" for="reassignSecretaria">Secretaria destino</label>
                        <select class="form-select" id="reassignSecretaria" required>
                            <option value="">Carregando secretarias...</option>
                        </select>
                        <span class="form-hint">Selecione uma secretaria ativa para receber o chamado.</span>
                    </div>
                    <div>
                        <label class="form-label" for="reassignMotivo">Motivo da reatribuição</label>
                        <textarea class="form-control" id="reassignMotivo" rows="3" required placeholder="Descreva o motivo da mudança"></textarea>
                        <span class="form-hint">Detalhe o contexto para auditoria.</span>
                    </div>
                        <div class="alert alert-danger d-none" id="reassignError"></div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-info text-white border-info" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand">Reatribuir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const locationModal = document.getElementById('locationModal');
    const locationTitle = document.getElementById('locationModalTitle');
    const locationAddress = document.getElementById('locationAddress');
    const locationSecretaria = document.getElementById('locationSecretaria');
    const locationCoords = document.getElementById('locationCoords');
    const locationLink = document.getElementById('locationLink');
    const locationMap = document.getElementById('locationMap');
    const locationAddressInput = document.getElementById('locationAddressInput');
    const locationTicketId = document.getElementById('locationTicketId');
    const secretariaHistoryList = document.getElementById('secretariaHistoryList');
    const reassignButton = document.getElementById('reassignSecretariaBtn');
    const reassignModalEl = document.getElementById('reassignModal');
    const reassignModal = reassignModalEl ? new bootstrap.Modal(reassignModalEl) : null;
    const reassignForm = document.getElementById('reassignForm');
    const reassignSelect = document.getElementById('reassignSecretaria');
    const reassignMotivo = document.getElementById('reassignMotivo');
    const reassignError = document.getElementById('reassignError');
    let currentTicketId = null;
    let currentSecretariaId = null;
    let currentLocationTrigger = null;
    let activeSecretarias = null;
    let reopenLocationOnClose = false;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function renderSecretariaHistory(items) {
        if (!secretariaHistoryList) return;
        if (!items || items.length === 0) {
            secretariaHistoryList.innerHTML = '<span class="history-empty">Nenhuma reatribuição registrada.</span>';
            return;
        }
        secretariaHistoryList.innerHTML = items.map((item) => {
            const anterior = item.secretaria_anterior?.nome || 'Não definida';
            const nova = item.secretaria_nova?.nome || 'Não definida';
            const motivo = item.motivo || '';
            const responsavel = item.usuario?.nome || 'Sistema';
            const data = item.created_at || '';
            return `
                <div class="history-item">
                    <div class="history-title">${escapeHtml(anterior)} → ${escapeHtml(nova)}</div>
                    <div class="history-meta">${escapeHtml(responsavel)} · ${escapeHtml(data)}</div>
                    <div class="text-secondary small">${escapeHtml(motivo)}</div>
                </div>
            `;
        }).join('');
    }

    async function loadSecretariaHistory(ticketId) {
        if (!secretariaHistoryList || !ticketId) {
            return;
        }
        secretariaHistoryList.innerHTML = '<span class="history-empty">Carregando histórico...</span>';
        try {
            const response = await fetch(`api/v1/chamados/index.php/${ticketId}/secretaria-historico`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.status !== 'ok') {
                secretariaHistoryList.innerHTML = '<span class="history-empty">Não foi possível carregar o histórico.</span>';
                return;
            }
            const secretariaAtual = data.secretaria_atual || null;
            if (locationSecretaria) {
                locationSecretaria.textContent = secretariaAtual?.nome || 'Não definida';
            }
            if (secretariaAtual?.id) {
                currentSecretariaId = secretariaAtual.id;
            }
            renderSecretariaHistory(data.items || []);
        } catch (e) {
            secretariaHistoryList.innerHTML = '<span class="history-empty">Erro ao carregar histórico.</span>';
        }
    }

    async function loadActiveSecretarias() {
        if (Array.isArray(activeSecretarias)) {
            return activeSecretarias;
        }
        const response = await fetch('api/v1/secretarias/index.php', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status !== 'ok') {
            throw new Error(data.error || 'Erro ao carregar secretarias.');
        }
        activeSecretarias = data.items || [];
        return activeSecretarias;
    }

    function fillSecretariaSelect(list, selectedId) {
        if (!reassignSelect) return;
        if (!list || list.length === 0) {
            reassignSelect.innerHTML = '<option value="">Nenhuma secretaria ativa disponível</option>';
            reassignSelect.disabled = true;
            return;
        }
        reassignSelect.disabled = false;
        reassignSelect.innerHTML = '<option value="">Selecione a secretaria</option>' +
            list.map((item) => {
                const selected = selectedId && String(item.id) === String(selectedId) ? 'selected' : '';
                return `<option value="${item.id}" ${selected}>${escapeHtml(item.nome)}</option>`;
            }).join('');
    }

    function buildMapsLink(address, lat, lon) {
        if (lat && lon) {
            return `https://www.google.com/maps?q=${encodeURIComponent(lat + ',' + lon)}`;
        }
        return `https://www.google.com/maps?q=${encodeURIComponent(address)}`;
    }

    function buildEmbedUrl(address, lat, lon) {
        if (lat && lon) {
            return `https://www.google.com/maps?q=${encodeURIComponent(lat + ',' + lon)}&output=embed`;
        }
        return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`;
    }

    locationModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        currentLocationTrigger = trigger;

        const address = trigger.getAttribute('data-address') || '';
        const addressText = trigger.getAttribute('data-address-text') || '';
        const lat = trigger.getAttribute('data-lat') || '';
        const lon = trigger.getAttribute('data-lon') || '';
        const service = trigger.getAttribute('data-service') || 'Solicitação';
        const id = trigger.getAttribute('data-id') || '';
        const secretariaName = trigger.getAttribute('data-secretaria-name') || '';
        const secretariaId = trigger.getAttribute('data-secretaria-id') || '';

        const formattedAddress = address ? address.replace(/\s*\|\s*/g, '\n') : 'Endereço não disponível.';
        locationTitle.textContent = id ? `${service} #${id}` : service;
        locationAddress.textContent = formattedAddress;
        if (locationSecretaria) {
            locationSecretaria.textContent = secretariaName || 'Não definida';
        }
        if (locationAddressInput) {
            locationAddressInput.value = addressText;
        }
        if (locationTicketId) {
            locationTicketId.value = id;
        }
        currentTicketId = id || null;
        currentSecretariaId = secretariaId || null;
        if (id) {
            loadSecretariaHistory(id);
        }

        if (lat && lon) {
            locationCoords.textContent = `Coordenadas: ${lat}, ${lon}`;
        } else {
            locationCoords.textContent = 'Coordenadas indisponíveis.';
        }

        const hasQuery = Boolean((lat && lon) || address);
        if (hasQuery) {
            const mapsLink = buildMapsLink(address, lat, lon);
            locationLink.setAttribute('href', mapsLink);
            locationLink.classList.remove('disabled');
            locationLink.removeAttribute('aria-disabled');

            const embedUrl = buildEmbedUrl(address, lat, lon);
            locationMap.classList.remove('d-none');
            locationMap.setAttribute('src', embedUrl);
        } else {
            locationLink.setAttribute('href', '#');
            locationLink.classList.add('disabled');
            locationLink.setAttribute('aria-disabled', 'true');
            locationMap.classList.add('d-none');
            locationMap.setAttribute('src', '');
        }
    });

    locationModal?.addEventListener('hidden.bs.modal', () => {
        if (locationMap) {
            locationMap.setAttribute('src', '');
        }
    });

    reassignButton?.addEventListener('click', async () => {
        if (!reassignModal) return;
        if (!currentTicketId) return;
        reassignError?.classList.add('d-none');
        reassignError.textContent = '';
        if (reassignMotivo) reassignMotivo.value = '';
        try {
            const list = await loadActiveSecretarias();
            fillSecretariaSelect(list, currentSecretariaId);
        } catch (e) {
            if (reassignError) {
                reassignError.textContent = e?.message || 'Erro ao carregar secretarias.';
                reassignError.classList.remove('d-none');
            }
        }
        if (locationModal) {
            reopenLocationOnClose = true;
            locationModal.hide();
        }
        reassignModal.show();
    });

    reassignModalEl?.addEventListener('hidden.bs.modal', () => {
        if (reopenLocationOnClose && locationModal && currentLocationTrigger) {
            locationModal.show();
        }
        reopenLocationOnClose = false;
    });

    reassignForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentTicketId) return;
        const secretariaId = reassignSelect ? parseInt(reassignSelect.value, 10) : 0;
        const motivo = reassignMotivo ? reassignMotivo.value.trim() : '';
        if (!secretariaId || !motivo) {
            if (reassignError) {
                reassignError.textContent = 'Selecione a secretaria e informe o motivo.';
                reassignError.classList.remove('d-none');
            }
            return;
        }
        if (reassignError) {
            reassignError.textContent = '';
            reassignError.classList.add('d-none');
        }

        try {
            const response = await fetch(`api/v1/chamados/index.php/${currentTicketId}/reatribuir-secretaria`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ secretaria_id: secretariaId, motivo })
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.error || 'Erro ao reatribuir chamado.');
            }
            currentSecretariaId = secretariaId;
            const selectedOption = reassignSelect?.selectedOptions?.[0];
            const newName = selectedOption ? selectedOption.textContent : '';
            if (currentLocationTrigger) {
                currentLocationTrigger.setAttribute('data-secretaria-id', String(secretariaId));
                currentLocationTrigger.setAttribute('data-secretaria-name', newName);
            }
            if (locationSecretaria) {
                locationSecretaria.textContent = newName || 'Não definida';
            }
            await loadSecretariaHistory(currentTicketId);
            reassignModal.hide();
        } catch (e) {
            if (reassignError) {
                reassignError.textContent = e?.message || 'Erro ao reatribuir chamado.';
                reassignError.classList.remove('d-none');
            }
        }
    });

</script>
</body>
</html>
