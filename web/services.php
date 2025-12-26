<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'services';
$userType = currentUserType();

$serviceTypes = [];
$subtypesByType = [];
$serviceError = null;

try {
    $pdo = getPDO();
    $serviceTypes = listServiceTypes($pdo, true);
    $serviceSubtypes = listServiceSubtypes($pdo, null, true);
    foreach ($serviceSubtypes as $subtype) {
        $typeId = (int)$subtype['service_type_id'];
        $subtypesByType[$typeId][] = [
            'id' => (int)$subtype['id'],
            'name' => $subtype['name'],
        ];
    }
} catch (Throwable $e) {
    $serviceError = 'Erro ao carregar tipos de chamado: ' . $e->getMessage();
}

$duplicateContext = $_SESSION['duplicate_context'] ?? null;
unset($_SESSION['duplicate_context']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefeitura Digital - Serviços</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            align-items: stretch;
        }
        @media (max-width: 992px) {
            .hero { grid-template-columns: 1fr; }
        }
        .glass-sub {
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed rgba(125, 211, 252, 0.35);
            border-radius: 14px;
        }
        .step-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(14,165,233,0.12);
            color: #e2e8f0;
            font-size: 0.9rem;
        }
        .step-chip span {
            background: rgba(14,165,233,0.35);
            color: #0b1221;
            border-radius: 10px;
            padding: 2px 8px;
            font-weight: 700;
        }
        .modal-content.glass {
            background: rgba(12, 18, 32, 0.93);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .modal-backdrop.show {
            opacity: 0.7;
        }
        .service-card {
            transition: transform 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
            position: relative;
        }
        .service-card:hover {
            transform: translateY(-2px);
            border-color: rgba(14,165,233,0.35);
        }
        .service-card:focus-visible {
            outline: 2px solid var(--brand-accent);
            outline-offset: 3px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
            font-size: 0.85rem;
            margin-right: 6px;
            font-weight: 600;
            border: 1px solid rgba(125, 211, 252, 0.35);
        }
        .pill.available {
            background: rgba(16, 185, 129, 0.18);
            color: #bbf7d0;
            border-color: rgba(16, 185, 129, 0.35);
        }
        .pill.soon {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
        }
        .card-example {
            font-size: 0.9rem;
            color: #94a3b8;
        }
        .card-cta {
            position: absolute;
            right: 14px;
            top: 14px;
            font-size: 0.85rem;
            color: #7dd3fc;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .card-cta::after {
            content: '→';
            font-weight: 700;
        }
        .empty-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(14,165,233,0.12);
            color: #e2e8f0;
            text-decoration: none;
        }
        .empty-cta:hover {
            background: rgba(14,165,233,0.2);
        }
        .modal .form-control,
        .modal .form-select {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.45);
            color: #f8fafc;
            min-height: 46px;
        }
        .modal .form-control:focus,
        .modal .form-select:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.25);
            background: rgba(15, 23, 42, 0.88);
            color: #ffffff;
        }
        .modal .form-label {
            color: #e2e8f0;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
        }
        .modal .form-text {
            color: #94a3b8;
            font-size: 0.82rem;
        }
        .modal .form-control::placeholder {
            color: #94a3b8;
            opacity: 0.9;
        }
        .tooltip-inner {
            max-width: 260px;
            padding: 0;
            background: transparent;
        }
        .tooltip.show {
            opacity: 1;
        }
        .modal-content.glass { position: relative; }
        .loading-cover {
            position: absolute;
            inset: 0;
            background: rgba(12, 18, 32, 0.65);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            z-index: 10;
            border-radius: 12px;
        }
        .loading-cover .spinner-border {
            width: 2.4rem;
            height: 2.4rem;
        }
        .duplicate-block {
            background: rgba(234, 179, 8, 0.08);
            border: 1px solid rgba(234, 179, 8, 0.35);
            border-radius: 12px;
            padding: 12px;
        }
        .duplicate-list {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }
        .duplicate-item {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .duplicate-meta {
            font-size: 0.82rem;
            color: #94a3b8;
        }
        .duplicate-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

        <div class="glass p-4 mb-4 hero">
            <div class="panel-header flex-wrap">
                <div>
                    <p class="text-uppercase small text-info mb-1">Serviços</p>
                    <h2 class="panel-title">Abra uma solicitação em poucos cliques</h2>
                    <p class="panel-subtitle">Escolha o serviço, descreva o problema e envie evidências.</p>
                </div>
                <a class="btn btn-brand" href="#servicos">Iniciar solicitação</a>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2" id="servicos">
            <div>
                <p class="text-uppercase small text-info mb-1">Escolha um serviço</p>
                <h4 class="mb-0">Clique em um card para abrir o protocolo</h4>
            </div>
            <div class="d-none d-md-flex align-items-center gap-2 text-secondary small">
                <span class="pill available mb-0">Pronto para solicitar</span>
            </div>
        </div>

        <div class="row g-3">
            <?php if ($serviceError): ?>
                <div class="col-12">
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($serviceError); ?></div>
                </div>
            <?php elseif (!$serviceTypes): ?>
                <div class="col-12">
                    <div class="glass-sub p-4">
                        <h5 class="mb-2">Nenhum tipo de chamado disponível</h5>
                        <p class="text-secondary mb-0">Aguarde a liberação do catálogo de chamados pela equipe gestora.</p>
                        <?php if (in_array($userType, ['gestor', 'admin'], true)): ?>
                            <a class="empty-cta mt-3" href="service_types.php">Cadastrar tipos agora</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($serviceTypes as $type): ?>
                    <?php
                        $typeId = (int)$type['id'];
                        $subtypes = $subtypesByType[$typeId] ?? [];
                        $subtypeNames = array_map(static function (array $item): string {
                            return (string)$item['name'];
                        }, $subtypes);
                        $example = $subtypeNames ? implode(', ', array_slice($subtypeNames, 0, 3)) : 'Nenhum subtipo cadastrado';
                    ?>
                    <div class="col-md-4">
                        <div class="glass service-card p-3 h-100"
                             data-type-id="<?php echo $typeId; ?>"
                             data-type-name="<?php echo htmlspecialchars($type['name'], ENT_QUOTES); ?>"
                             role="button"
                             tabindex="0"
                             aria-label="Abrir solicitação para <?php echo htmlspecialchars($type['name'], ENT_QUOTES); ?>">
                            <span class="card-cta">Abrir</span>
                            <div class="pill available mb-2">Pronto para solicitar</div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($type['name']); ?></h5>
                            <p class="text-secondary mb-2">Selecione um subtipo para continuar.</p>
                            <p class="card-example mb-0">Subtipos: <?php echo htmlspecialchars($example); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal de solicitação -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass">
            <div class="modal-header border-0">
                <div>
                    <p class="text-uppercase small text-info mb-1">Solicitação</p>
                    <h5 class="modal-title fw-bold" id="serviceModalLabel">Serviço</h5>
                    <p class="panel-subtitle">Confirme os dados antes de enviar.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="serviceForm" action="service_submit.php" method="POST" enctype="multipart/form-data" class="form-clarity">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                    <input type="hidden" name="service_type_id" id="service_type_id" value="">
                    <div class="mb-3">
                        <label for="service_subtype_id" class="form-label">Subtipo do chamado</label>
                        <select class="form-select" id="service_subtype_id" name="service_subtype_id" required>
                            <option value="" selected disabled>Selecione um tipo de chamado</option>
                        </select>
                        <span class="form-hint">Escolha o subtipo que melhor descreve o problema.</span>
                    </div>
                    <input type="hidden" name="duplicate_action" id="duplicate_action" value="">
                    <input type="hidden" name="duplicate_parent_id" id="duplicate_parent_id" value="">
                    <div class="duplicate-block d-none" id="duplicateBlock">
                        <strong>Encontramos chamados parecidos próximos.</strong>
                        <div class="duplicate-meta">Você pode apoiar um existente ou justificar um novo chamado.</div>
                        <div class="duplicate-list" id="duplicateList"></div>
                        <div class="duplicate-actions">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="duplicate_choice" id="duplicateSupport" value="support" checked>
                                <label class="form-check-label" for="duplicateSupport">Apoiar chamado existente</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="duplicate_choice" id="duplicateCreate" value="create_new">
                                <label class="form-check-label" for="duplicateCreate">Criar novo com justificativa</label>
                            </div>
                        </div>
                        <div class="mt-2 d-none" id="duplicateReasonGroup">
                            <label for="duplicate_reason" class="form-label">Justificativa</label>
                            <textarea class="form-control" id="duplicate_reason" name="duplicate_reason" rows="2" placeholder="Explique por que este chamado deve ser aberto novamente."></textarea>
                        </div>
                        <div class="duplicate-meta mt-2">Anexe as fotos novamente, se necessário.</div>
                    </div>
                    <div class="mb-3">
                        <label for="evidence" class="form-label">Fotos (evidências)</label>
                        <input type="file" class="form-control" id="evidence" name="evidence[]" accept="image/*" multiple>
                        <div class="form-text">Detectamos latitude/longitude das fotos se disponível nos metadados.</div>
                        <span class="form-hint">Inclua fotos claras para agilizar o atendimento.</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                        <label class="form-label" for="latitude">Latitude</label>
                        <input type="text" class="form-control" id="latitude" name="latitude" placeholder="-3.12345" required inputmode="decimal">
                        <span class="form-hint">Preenchido automaticamente quando possível.</span>
                        </div>
                        <div class="col-md-6">
                        <label class="form-label" for="longitude">Longitude</label>
                        <input type="text" class="form-control" id="longitude" name="longitude" placeholder="-38.12345" required inputmode="decimal">
                        <span class="form-hint">Confirme antes de enviar.</span>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="address" class="form-label">Endereço (informativo)</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required placeholder="Rua, número, ponto de referência"></textarea>
                        <span class="form-hint">Use referências visuais para facilitar a localização.</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="neighborhood" class="form-label">Bairro (Fortaleza)</label>
                            <select class="form-select" id="neighborhood" name="neighborhood" required>
                                <option value="">Carregando bairros...</option>
                            </select>
                            <div class="invalid-feedback">Selecione o bairro.</div>
                            <span class="form-hint">Informe o bairro onde o problema acontece.</span>
                        </div>
                        <div class="col-md-5">
                            <label for="zip" class="form-label">CEP</label>
                            <input type="text" class="form-control" id="zip" name="zip" required pattern="\d{5}-?\d{3}" inputmode="numeric" placeholder="00000-000">
                            <div class="invalid-feedback">Informe um CEP válido (8 dígitos).</div>
                            <span class="form-hint">Use apenas números para preencher mais rápido.</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="tempo_ocorrencia" class="form-label">Há quanto tempo acontece?</label>
                        <select class="form-select" id="tempo_ocorrencia" name="tempo_ocorrencia" required>
                            <option value="" selected disabled>Selecione</option>
                            <option value="MENOS_24H">Menos de 24h</option>
                            <option value="ENTRE_1_E_3_DIAS">Entre 1 e 3 dias</option>
                            <option value="MAIS_3_DIAS">Mais de 3 dias</option>
                            <option value="RECORRENTE">Recorrente</option>
                        </select>
                        <span class="form-hint">Esse dado ajuda a definir a prioridade inicial.</span>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-brand">Enviar solicitação</button>
                    </div>
                </form>
                <div class="loading-cover d-none" id="serviceLoading">
                    <div class="spinner-border text-info" role="status" aria-hidden="true"></div>
                    <p class="mb-0 text-white">Enviando solicitação...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/exifreader@4.12.0/dist/exif-reader.min.js"></script>
<script>
    const modalEl = document.getElementById('serviceModal');
    const modal = new bootstrap.Modal(modalEl);
    const serviceLabel = document.getElementById('serviceModalLabel');
    const serviceTypeInput = document.getElementById('service_type_id');
    const serviceSubtypeSelect = document.getElementById('service_subtype_id');
    const evidenceInput = document.getElementById('evidence');
    const latInput = document.getElementById('latitude');
    const lonInput = document.getElementById('longitude');
    const addressInput = document.getElementById('address');
    const neighborhoodSelect = document.getElementById('neighborhood');
    const zipInput = document.getElementById('zip');
    const serviceForm = document.getElementById('serviceForm');
    const submitBtn = serviceForm?.querySelector('button[type="submit"]');
    const loadingCover = document.getElementById('serviceLoading');
    const serviceCards = document.querySelectorAll('.service-card');
    const subtypesByType = <?php echo json_encode($subtypesByType, JSON_UNESCAPED_UNICODE); ?>;
    const duplicateContext = <?php echo json_encode($duplicateContext, JSON_UNESCAPED_UNICODE); ?>;
    const duplicateBlock = document.getElementById('duplicateBlock');
    const duplicateList = document.getElementById('duplicateList');
    const duplicateActionInput = document.getElementById('duplicate_action');
    const duplicateParentInput = document.getElementById('duplicate_parent_id');
    const duplicateReasonGroup = document.getElementById('duplicateReasonGroup');
    const duplicateReasonInput = document.getElementById('duplicate_reason');
    const duplicateChoiceInputs = document.querySelectorAll('input[name="duplicate_choice"]');
    const statusLabels = {
        RECEBIDO: 'Recebido',
        EM_ANALISE: 'Em análise',
        ENCAMINHADO: 'Encaminhado',
        EM_EXECUCAO: 'Em execução',
        RESOLVIDO: 'Resolvido',
        ENCERRADO: 'Encerrado'
    };
    let geolocationAttempted = false;

    function formatCoordinate(value) {
        if (!Number.isFinite(value)) {
            return '';
        }
        return value.toFixed(7);
    }

    function setAutoCoordinate(input, value, origin) {
        if (!input || !Number.isFinite(value)) {
            return;
        }
        const current = input.value.trim();
        const currentOrigin = input.dataset.origin || '';
        if (current !== '' && currentOrigin === 'manual') {
            return;
        }
        input.value = formatCoordinate(value);
        input.dataset.origin = origin;
    }

    function setCoordinates(lat, lon, origin) {
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
            return;
        }
        setAutoCoordinate(latInput, lat, origin);
        setAutoCoordinate(lonInput, lon, origin);
    }

    function toNumber(value) {
        if (Array.isArray(value) && value.length === 2 && typeof value[0] === 'number' && typeof value[1] === 'number') {
            return value[1] ? value[0] / value[1] : value[0];
        }
        if (typeof value === 'number') {
            return value;
        }
        if (typeof value === 'string') {
            const numeric = Number(value.replace(',', '.'));
            return Number.isFinite(numeric) ? numeric : null;
        }
        return null;
    }

    function parseGpsCoordinate(tag, refTag) {
        if (!tag) {
            return null;
        }
        let decimal = null;
        const raw = tag.value;
        if (Array.isArray(raw) && raw.length >= 3) {
            const deg = toNumber(raw[0]);
            const min = toNumber(raw[1]);
            const sec = toNumber(raw[2]);
            if (deg !== null && min !== null && sec !== null) {
                decimal = deg + (min / 60) + (sec / 3600);
            }
        } else if (typeof raw === 'number') {
            decimal = raw;
        } else if (typeof tag.description === 'string') {
            const parts = tag.description.match(/-?\d+(?:\.\d+)?/g) || [];
            if (parts.length >= 3) {
                const deg = Number(parts[0]);
                const min = Number(parts[1]);
                const sec = Number(parts[2]);
                if (Number.isFinite(deg) && Number.isFinite(min) && Number.isFinite(sec)) {
                    decimal = Math.abs(deg) + (min / 60) + (sec / 3600);
                    if (deg < 0) {
                        decimal = -decimal;
                    }
                }
            } else {
                const numeric = Number(tag.description.replace(',', '.'));
                if (Number.isFinite(numeric)) {
                    decimal = numeric;
                }
            }
        }

        if (decimal === null || !Number.isFinite(decimal)) {
            return null;
        }

        const ref = refTag?.description || refTag?.value;
        if (typeof ref === 'string') {
            const refNorm = ref.trim().toUpperCase();
            if (refNorm === 'S' || refNorm === 'W') {
                decimal = -Math.abs(decimal);
            }
            if (refNorm === 'N' || refNorm === 'E') {
                decimal = Math.abs(decimal);
            }
        }
        return decimal;
    }

    function prefillFromGeolocation() {
        if (!navigator.geolocation) {
            return;
        }
        if (geolocationAttempted) {
            return;
        }
        if (latInput?.value.trim() && lonInput?.value.trim()) {
            return;
        }
        geolocationAttempted = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                setCoordinates(lat, lon, 'geo');
            },
            (err) => {
                if (err?.code !== 1) {
                    geolocationAttempted = false;
                }
                console.warn('Nao foi possivel obter geolocalizacao', err);
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
    }

    function resetDuplicateState() {
        if (!duplicateBlock) return;
        duplicateBlock.classList.add('d-none');
        if (duplicateList) {
            duplicateList.innerHTML = '';
        }
        if (duplicateActionInput) {
            duplicateActionInput.value = '';
        }
        if (duplicateParentInput) {
            duplicateParentInput.value = '';
        }
        if (duplicateReasonGroup) {
            duplicateReasonGroup.classList.add('d-none');
        }
        if (duplicateReasonInput) {
            duplicateReasonInput.required = false;
            duplicateReasonInput.value = '';
        }
        if (duplicateChoiceInputs?.length) {
            duplicateChoiceInputs.forEach(input => {
                if (input.value === 'support') {
                    input.checked = true;
                }
            });
        }
    }

    function bindDuplicateChoice() {
        if (!duplicateChoiceInputs?.length) return;
        duplicateChoiceInputs.forEach(input => {
            input.addEventListener('change', () => {
                const action = input.value;
                if (duplicateActionInput) {
                    duplicateActionInput.value = action;
                }
                if (duplicateReasonGroup) {
                    duplicateReasonGroup.classList.toggle('d-none', action !== 'create_new');
                }
                if (duplicateReasonInput) {
                    duplicateReasonInput.required = action === 'create_new';
                }
            });
        });
    }

    function renderDuplicateBlock(context) {
        if (!duplicateBlock || !duplicateList) return;
        const duplicates = context?.duplicates || [];
        if (!duplicates.length) return;

        duplicateBlock.classList.remove('d-none');
        duplicateList.innerHTML = duplicates.map((item, index) => {
            const distance = item.distance_m ? `${Math.round(item.distance_m)}m` : '';
            const dateLabel = item.created_at || '';
            const statusLabel = statusLabels[item.status] || item.status || 'Status não informado';
            return `
                <label class="duplicate-item">
                    <input type="radio" class="form-check-input" name="duplicate_parent" value="${item.id}" ${index === 0 ? 'checked' : ''}>
                    <div>
                        <div><strong>#${item.id}</strong> ${item.problem_type || ''}</div>
                        <div class="duplicate-meta">${item.service_name || ''} • ${item.secretaria_nome || 'Secretaria não definida'}</div>
                        <div class="duplicate-meta">${statusLabel} ${distance ? '• ' + distance : ''} ${dateLabel ? '• ' + dateLabel : ''}</div>
                    </div>
                </label>
            `;
        }).join('');

        const selected = duplicates[0]?.id;
        if (duplicateParentInput && selected) {
            duplicateParentInput.value = selected;
        }
        if (duplicateActionInput) {
            duplicateActionInput.value = 'support';
        }

        duplicateList.querySelectorAll('input[name="duplicate_parent"]').forEach((input) => {
            input.addEventListener('change', () => {
                if (duplicateParentInput) {
                    duplicateParentInput.value = input.value;
                }
            });
        });
    }

    // Tooltips para evidências
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    function openServiceCard(card) {
        const typeId = card.dataset.typeId;
        const typeName = card.dataset.typeName || 'Chamado';
        serviceLabel.textContent = typeName;
        if (serviceTypeInput) {
            serviceTypeInput.value = typeId || '';
        }
        resetDuplicateState();
        fillSubtypes(typeId);
        modal.show();
        prefillFromGeolocation();
        setTimeout(() => {
            serviceSubtypeSelect?.focus();
        }, 150);
    }

    serviceCards.forEach(card => {
        card.addEventListener('click', () => openServiceCard(card));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openServiceCard(card);
            }
        });
    });

    modalEl?.addEventListener('hidden.bs.modal', () => {
        resetDuplicateState();
    });

    function fillSubtypes(typeId) {
        if (!serviceSubtypeSelect) return;
        const list = subtypesByType?.[typeId] || [];
        if (!typeId) {
            serviceSubtypeSelect.innerHTML = '<option value="" selected disabled>Selecione um tipo de chamado</option>';
            serviceSubtypeSelect.disabled = true;
            return;
        }
        if (list.length === 0) {
            serviceSubtypeSelect.innerHTML = '<option value="" selected disabled>Sem subtipos disponíveis</option>';
            serviceSubtypeSelect.disabled = false;
            return;
        }
        serviceSubtypeSelect.disabled = false;
        serviceSubtypeSelect.innerHTML = '<option value="" selected disabled>Selecione o subtipo</option>' +
            list.map((item) => `<option value="${item.id}">${item.name}</option>`).join('');
    }

    bindDuplicateChoice();

    if (duplicateContext && duplicateContext.payload) {
        const payload = duplicateContext.payload;
        const card = document.querySelector(`.service-card[data-type-id="${payload.service_type_id}"]`);
        if (card && serviceLabel) {
            serviceLabel.textContent = card.dataset.typeName || 'Chamado';
        }
        if (serviceTypeInput) {
            serviceTypeInput.value = payload.service_type_id || '';
        }
        fillSubtypes(payload.service_type_id);
        if (serviceSubtypeSelect && payload.service_subtype_id) {
            serviceSubtypeSelect.value = String(payload.service_subtype_id);
        }
        if (addressInput && payload.address) {
            addressInput.value = payload.address;
        }
        if (neighborhoodSelect && payload.neighborhood) {
            neighborhoodSelect.dataset.prefill = payload.neighborhood;
            setNeighborhoodValue(payload.neighborhood);
        }
        if (zipInput && payload.zip) {
            zipInput.value = payload.zip;
        }
        if (payload.latitude && payload.longitude) {
            setCoordinates(Number(payload.latitude), Number(payload.longitude), 'auto');
        }
        if (payload.tempo_ocorrencia) {
            const tempoSelect = document.getElementById('tempo_ocorrencia');
            if (tempoSelect) {
                tempoSelect.value = payload.tempo_ocorrencia;
            }
        }
        renderDuplicateBlock(duplicateContext);
        modal.show();
        prefillFromGeolocation();
        setTimeout(() => {
            serviceSubtypeSelect?.focus();
        }, 150);
    }

    serviceForm?.addEventListener('submit', (event) => {
        if (!serviceForm.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            serviceForm.classList.add('was-validated');
            return;
        }
        if (submitBtn?.disabled) {
            event.preventDefault();
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerText = 'Enviando...';
        loadingCover?.classList.remove('d-none');
    });

    // Bairro via Cep.Ia e preenchimento por CEP
    const fallbackNeighborhoods = [
        'Aldeota', 'Barra do Ceará', 'Benfica', 'Cidade dos Funcionários', 'Cocó',
        'Fátima', 'Jacarecanga', 'Jangurussu', 'Mondubim', 'Meireles', 'Messejana',
        'Parangaba', 'Parquelândia', 'Passaré', 'Praia de Iracema', 'Papicu',
        'Serrinha', 'Varjota', 'Vicente Pinzon', 'Centro'
    ];

    const sortList = (list) => [...list]
        .filter(Boolean)
        .map(item => item.trim())
        .filter(item => item.length > 0)
        .sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));

    function setNeighborhoodValue(name) {
        if (!neighborhoodSelect || !name) return;
        const target = name.trim().toLowerCase();
        const options = Array.from(neighborhoodSelect.options || []);
        const match = options.find(opt => opt.value.toLowerCase() === target);
        if (match) {
            neighborhoodSelect.value = match.value;
        }
    }

    async function loadNeighborhoods() {
        if (!neighborhoodSelect) return;
        neighborhoodSelect.disabled = true;
        neighborhoodSelect.innerHTML = '<option value="">Carregando bairros...</option>';
        const apiUrl = 'https://cep.ia/api/v1/neighborhoods?city=Fortaleza&state=CE';

        try {
            const response = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('Erro ao consultar Cep.Ia');
            const data = await response.json();
            const neighborhoods = Array.isArray(data?.neighborhoods) ? data.neighborhoods : [];
            const list = neighborhoods.length ? neighborhoods : fallbackNeighborhoods;
            const ordered = sortList(list);
            neighborhoodSelect.innerHTML = '<option value="">Selecione</option>' +
                ordered.map(n => `<option value="${n}">${n}</option>`).join('');
        } catch (e) {
            console.warn('Falha na API Cep.Ia, usando lista padrão.', e);
            const ordered = sortList(fallbackNeighborhoods);
            neighborhoodSelect.innerHTML = '<option value="">Selecione</option>' +
                ordered.map(n => `<option value="${n}">${n}</option>`).join('');
        } finally {
            neighborhoodSelect.disabled = false;
            if (neighborhoodSelect.dataset.prefill) {
                setNeighborhoodValue(neighborhoodSelect.dataset.prefill);
            }
        }
    }
    loadNeighborhoods();

    function formatCep(value) {
        const digits = value.replace(/\D/g, '').slice(0, 8);
        if (digits.length >= 6) {
            return digits.slice(0, 5) + '-' + digits.slice(5);
        }
        return digits;
    }

    async function lookupCep(cepDigits) {
        const url = `https://cep.ia/api/v1/cep/${cepDigits}`;
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) throw new Error('CEP não encontrado');
        return response.json();
    }

    function applyCepData(data) {
        if (!data) return;
        const street = data.street || data.logradouro || data.address || '';
        const neigh = data.neighborhood || data.district || data.bairro || '';
        if (street && addressInput) {
            addressInput.value = street;
        }
        if (neigh) {
            setNeighborhoodValue(neigh);
        }
    }

    async function handleCepFill() {
        if (!zipInput) return;
        const digits = zipInput.value.replace(/\D/g, '').slice(0, 8);
        if (digits.length !== 8) return;
        try {
            const data = await lookupCep(digits);
            applyCepData(data);
        } catch (e) {
            console.warn('Não foi possível preencher pelo CEP.', e);
        }
    }

    zipInput?.addEventListener('input', (event) => {
        event.target.value = formatCep(event.target.value);
    });
    zipInput?.addEventListener('blur', handleCepFill);
    zipInput?.addEventListener('change', handleCepFill);

    evidenceInput?.addEventListener('change', async (event) => {
        const files = Array.from(event.target.files || []);
        const previousLat = latInput?.value || '';
        const previousLon = lonInput?.value || '';
        let updated = false;

        for (const file of files) {
            try {
                const buffer = await file.arrayBuffer();
                const tags = ExifReader.load(buffer);
                if (tags && tags.GPSLatitude && tags.GPSLongitude) {
                    const lat = parseGpsCoordinate(tags.GPSLatitude, tags.GPSLatitudeRef);
                    const lon = parseGpsCoordinate(tags.GPSLongitude, tags.GPSLongitudeRef);
                    if (Number.isFinite(lat) && Number.isFinite(lon)) {
                        setCoordinates(lat, lon, 'exif');
                        updated = true;
                        break; // usa a primeira foto com geolocalizacao
                    }
                }
            } catch (e) {
                console.warn('Sem EXIF ou erro ao ler EXIF', e);
            }
        }

        if (!updated) {
            if (latInput) latInput.value = previousLat;
            if (lonInput) lonInput.value = previousLon;
        }
    });

    latInput?.addEventListener('input', () => {
        latInput.dataset.origin = 'manual';
    });
    lonInput?.addEventListener('input', () => {
        lonInput.dataset.origin = 'manual';
    });
</script>
</body>
</html>
