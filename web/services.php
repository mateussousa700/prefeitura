<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    flash('danger', 'Faça login para acessar.');
    header('Location: index.php#login');
    exit;
}

$currentPage = 'services';
$userName = $_SESSION['user_name'] ?? 'Cidadão';
$userType = $_SESSION['user_type'] ?? 'populacao';
$flash = consumeFlash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefeitura Digital - Serviços</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-bg: #0f172a;
            --brand-accent: #0ea5e9;
            --brand-soft: #1b2438;
        }
        * { font-family: 'Space Grotesk', 'Segoe UI', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0b1221 0%, #0f172a 60%, #0b1221 100%);
            color: #e2e8f0;
        }
        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            padding: 24px;
            box-shadow: 8px 0 30px rgba(0,0,0,0.2);
        }
        .brand {
            font-weight: 700;
            color: #7dd3fc;
            letter-spacing: 0.3px;
        }
        .nav-link {
            color: #cbd5e1;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }
        .nav-link.active, .nav-link:hover {
            background: rgba(14,165,233,0.12);
            color: #e2e8f0;
            border: 1px solid rgba(14,165,233,0.35);
        }
        .content {
            padding: 36px;
        }
        .glass {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        }
        .btn-brand {
            background: linear-gradient(135deg, #0ea5e9, #22d3ee);
            color: #0b1221;
            font-weight: 700;
            border: none;
        }
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
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
        .btn-brand:hover {
            background: linear-gradient(135deg, #0aa1e5, #1bcde7);
            color: #0b1221;
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
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #e2e8f0;
        }
        .modal .form-control:focus,
        .modal .form-select:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.25);
            background: rgba(255, 255, 255, 0.1);
        }
        .modal .form-control::placeholder {
            color: #cbd5e1;
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
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <span class="brand">Prefeitura Digital</span>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" href="home.php">Início</a>
            <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" aria-current="page" href="services.php">Serviços</a>
            <a class="nav-link <?php echo $currentPage === 'requests' ? 'active' : ''; ?>" href="requests.php">Meus protocolos</a>
            <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" href="profile.php">Meu perfil</a>
            <?php if (in_array($userType, ['gestor', 'admin'], true)): ?>
                <a class="nav-link <?php echo $currentPage === 'tickets' ? 'active' : ''; ?>" href="tickets.php">Chamados</a>
                <a class="nav-link <?php echo $currentPage === 'completed' ? 'active' : ''; ?>" href="completed.php">Concluídos</a>
            <?php endif; ?>
            <?php if (in_array($userType, ['gestor', 'admin'], true)): ?>
                <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">Gestão de usuários</a>
            <?php endif; ?>
            <a class="nav-link" href="logout.php">Sair</a>
        </nav>
    </aside>

    <main class="content">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> mb-3">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="glass p-4 mb-4 hero">
            <div>
                <p class="text-uppercase small text-info mb-1">Serviços</p>
                <h2 class="fw-bold mb-2">Abra uma solicitação em poucos cliques</h2>
                <p class="mb-3 text-secondary">Escolha um serviço abaixo, descreva o problema e envie fotos para agilizar o atendimento.</p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-brand" href="#servicos">Ver serviços</a>
                    <a class="btn btn-outline-info text-white border-info" href="requests.php">Ver meus protocolos</a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="step-chip"><span>1</span>Escolha o serviço</span>
                    <span class="step-chip"><span>2</span>Descreva e envie fotos</span>
                    <span class="step-chip"><span>3</span>Acompanhe o status</span>
                </div>
            </div>
            <div class="glass-sub p-3">
                <p class="fw-semibold mb-1">Dica rápida</p>
                <p class="text-secondary small mb-2">Fotos com localização preenchem latitude/longitude automaticamente e aceleram a resposta.</p>
                <ul class="text-secondary small ps-3 mb-0">
                    <li>Confirme endereço e um ponto de referência.</li>
                    <li>Se não tiver foto, descreva bem o local do problema.</li>
                </ul>
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
            <div class="col-md-4">
                <div class="glass service-card p-3 h-100" data-service="Iluminação pública" data-example="Poste apagado, oscilação, lâmpada queimada" role="button" tabindex="0" aria-label="Abrir solicitação para Iluminação pública">
                    <span class="card-cta">Abrir</span>
                    <div class="pill available mb-2">Pronto para solicitar</div>
                    <h5 class="mb-1">Iluminação pública</h5>
                    <p class="text-secondary mb-2">Abertura de chamados e acompanhamento de reparos.</p>
                    <p class="card-example mb-0">Exemplos: poste apagado, oscilação, lâmpada queimada.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass service-card p-3 h-100" data-service="Limpeza" data-example="Entulho, poda, varrição, lixo acumulado" role="button" tabindex="0" aria-label="Abrir solicitação para Limpeza">
                    <span class="card-cta">Abrir</span>
                    <div class="pill available mb-2">Pronto para solicitar</div>
                    <h5 class="mb-1">Limpeza</h5>
                    <p class="text-secondary mb-2">Solicitar coleta de entulho, poda e varrição.</p>
                    <p class="card-example mb-0">Exemplos: entulho, poda, varrição, lixo acumulado.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass service-card p-3 h-100" data-service="Tributos" data-example="2ª via de boleto, certidão, parcelamento" role="button" tabindex="0" aria-label="Abrir solicitação para Tributos">
                    <span class="card-cta">Abrir</span>
                    <div class="pill available mb-2">Pronto para solicitar</div>
                    <h5 class="mb-1">Tributos</h5>
                    <p class="text-secondary mb-2">2ª via de boletos, parcelamentos e certidões.</p>
                    <p class="card-example mb-0">Exemplos: 2ª via de boleto, certidão, parcelamento.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass service-card p-3 h-100" data-service="Pavimentação" data-example="Buraco, asfalto quebrado, falta de sinalização" role="button" tabindex="0" aria-label="Abrir solicitação para Pavimentação">
                    <span class="card-cta">Abrir</span>
                    <div class="pill available mb-2">Pronto para solicitar</div>
                    <h5 class="mb-1">Pavimentação</h5>
                    <p class="text-secondary mb-2">Conserto de buracos, sinalização, tapa-buraco e demais serviços viários.</p>
                    <p class="card-example mb-0">Exemplos: buraco, asfalto quebrado, falta de sinalização.</p>
                </div>
            </div>
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
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="serviceForm" action="service_submit.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="service_name" id="service_name" value="">
                    <div class="mb-3">
                        <label for="problem_type" class="form-label">Tipo de problema</label>
                        <input type="text" class="form-control" id="problem_type" name="problem_type" required placeholder="Ex.: Poste apagado, buraco, entulho">
                    </div>
                    <div class="mb-3">
                        <label for="evidence" class="form-label">Fotos (evidências)</label>
                        <input type="file" class="form-control" id="evidence" name="evidence[]" accept="image/*" multiple>
                        <div class="form-text">Detectamos latitude/longitude das fotos se disponível nos metadados.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="latitude">Latitude</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" placeholder="-3.12345" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="longitude">Longitude</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" placeholder="-38.12345" readonly>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="address" class="form-label">Endereço</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required placeholder="Rua, número, ponto de referência"></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="neighborhood" class="form-label">Bairro (Fortaleza)</label>
                            <select class="form-select" id="neighborhood" name="neighborhood" required>
                                <option value="">Carregando bairros...</option>
                            </select>
                            <div class="invalid-feedback">Selecione o bairro.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="zip" class="form-label">CEP</label>
                            <input type="text" class="form-control" id="zip" name="zip" required pattern="\d{5}-?\d{3}" inputmode="numeric" placeholder="00000-000">
                            <div class="invalid-feedback">Informe um CEP válido (8 dígitos).</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="duration" class="form-label">Há quanto tempo acontece?</label>
                        <select class="form-select" id="duration" name="duration" required>
                            <option value="" selected disabled>Selecione</option>
                            <option value="hoje">Hoje</option>
                            <option value="ultima_semana">Última semana</option>
                            <option value="ultimo_mes">Último mês</option>
                            <option value="mais_tempo">Mais tempo</option>
                        </select>
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
    const serviceNameInput = document.getElementById('service_name');
    const problemInput = document.getElementById('problem_type');
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

    // Tooltips para evidências
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    function openServiceCard(card) {
        const serviceName = card.dataset.service;
        const example = card.dataset.example || 'Descreva o problema';
        serviceLabel.textContent = serviceName;
        serviceNameInput.value = serviceName;
        if (problemInput) {
            problemInput.placeholder = `Ex.: ${example}`;
        }
        modal.show();
        setTimeout(() => {
            problemInput?.focus();
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
        latInput.value = '';
        lonInput.value = '';

        for (const file of files) {
            try {
                const buffer = await file.arrayBuffer();
                const tags = ExifReader.load(buffer);
                if (tags && tags.GPSLatitude && tags.GPSLongitude) {
                    latInput.value = tags.GPSLatitude.description || '';
                    lonInput.value = tags.GPSLongitude.description || '';
                    break; // usa a primeira foto com geolocalização
                }
            } catch (e) {
                console.warn('Sem EXIF ou erro ao ler EXIF', e);
            }
        }
    });
</script>
</body>
</html>
