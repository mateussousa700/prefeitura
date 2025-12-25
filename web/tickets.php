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
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Chamados</p>
                    <h4 class="mb-0">Solicitações recebidas</h4>
                </div>
                <span class="badge bg-info text-dark"><?php echo count($tickets); ?> itens</span>
            </div>
            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
                <form method="GET" class="d-flex align-items-center gap-2 filter-form">
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
            </div>
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
                            <th>Serviço</th>
                            <th>Problema</th>
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
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-outline-info btn-sm text-white border-info location-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#locationModal"
                                        data-address="<?php echo htmlspecialchars((string)($t['address'] ?? ''), ENT_QUOTES); ?>"
                                        data-lat="<?php echo htmlspecialchars((string)($t['latitude'] ?? ''), ENT_QUOTES); ?>"
                                        data-lon="<?php echo htmlspecialchars((string)($t['longitude'] ?? ''), ENT_QUOTES); ?>"
                                        data-service="<?php echo htmlspecialchars((string)($t['service_name'] ?? ''), ENT_QUOTES); ?>"
                                        data-id="<?php echo htmlspecialchars((string)($t['id'] ?? ''), ENT_QUOTES); ?>"
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

<div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass">
            <div class="modal-header border-0">
                <div>
                    <p class="location-label mb-1">Localização da solicitação</p>
                    <h5 class="modal-title fw-bold" id="locationModalTitle">Endereço</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p class="location-label mb-2">Endereço informado</p>
                    <div class="location-address" id="locationAddress">Endereço não disponível.</div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const locationModal = document.getElementById('locationModal');
    const locationTitle = document.getElementById('locationModalTitle');
    const locationAddress = document.getElementById('locationAddress');
    const locationCoords = document.getElementById('locationCoords');
    const locationLink = document.getElementById('locationLink');
    const locationMap = document.getElementById('locationMap');

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

        const address = trigger.getAttribute('data-address') || '';
        const lat = trigger.getAttribute('data-lat') || '';
        const lon = trigger.getAttribute('data-lon') || '';
        const service = trigger.getAttribute('data-service') || 'Solicitação';
        const id = trigger.getAttribute('data-id') || '';

        const formattedAddress = address ? address.replace(/\s*\|\s*/g, '\n') : 'Endereço não disponível.';
        locationTitle.textContent = id ? `${service} #${id}` : service;
        locationAddress.textContent = formattedAddress;

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

</script>
</body>
</html>
