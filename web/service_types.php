<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'service_types';
$userType = currentUserType();
$defaultSlaHours = defined('SLA_DEFAULT_HOURS') ? (int) SLA_DEFAULT_HOURS : 72;

requireRoles(['gestor', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken($_POST['csrf_token'] ?? null, 'service_types.php');

    $action = $_POST['action'] ?? '';

    try {
        $pdo = getPDO();

        $typesById = [];
        foreach (listServiceTypes($pdo) as $typeRow) {
            $typesById[(int)$typeRow['id']] = $typeRow;
        }
        $secretarias = listSecretarias($pdo);
        $secretariasById = [];
        foreach ($secretarias as $secretaria) {
            $secretariasById[(int)$secretaria['id']] = $secretaria;
        }

        if ($action === 'create_type') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || strlen($name) < 3) {
                throw new RuntimeException('Informe um tipo com pelo menos 3 caracteres.');
            }
            createServiceType($pdo, $name);
            flash('success', 'Tipo de chamado criado.');
        } elseif ($action === 'update_type') {
            $typeId = (int)($_POST['type_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $active = ($_POST['active'] ?? '1') === '1';
            if ($typeId <= 0 || $name === '' || strlen($name) < 3) {
                throw new RuntimeException('Dados inválidos para atualizar tipo.');
            }
            updateServiceType($pdo, $typeId, $name, $active);
            if (!$active) {
                setSubtypesActiveByType($pdo, $typeId, false);
            }
            flash('success', 'Tipo atualizado.');
        } elseif ($action === 'create_subtype') {
            $typeId = (int)($_POST['type_id'] ?? 0);
            $secretariaId = (int)($_POST['secretaria_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $slaHours = (int)($_POST['sla_hours'] ?? $defaultSlaHours);
            if ($typeId <= 0 || $name === '' || strlen($name) < 3) {
                throw new RuntimeException('Informe o tipo e um subtipo válido.');
            }
            if ($secretariaId <= 0) {
                throw new RuntimeException('Selecione a secretaria responsável.');
            }
            if ($slaHours <= 0) {
                throw new RuntimeException('Informe um SLA válido em horas.');
            }
            if (!isset($typesById[$typeId])) {
                throw new RuntimeException('Tipo de chamado inexistente.');
            }
            if ((int)$typesById[$typeId]['active'] !== 1) {
                throw new RuntimeException('Ative o tipo antes de incluir subtipos.');
            }
            $secretaria = $secretariasById[$secretariaId] ?? null;
            if (!$secretaria) {
                throw new RuntimeException('Secretaria inválida.');
            }
            if ((int)$secretaria['ativa'] !== 1) {
                throw new RuntimeException('Secretaria inativa não pode receber subtipos.');
            }
            createServiceSubtype($pdo, $typeId, $name, $slaHours, $secretariaId);
            flash('success', 'Subtipo criado.');
        } elseif ($action === 'update_subtype') {
            $subtypeId = (int)($_POST['subtype_id'] ?? 0);
            $secretariaId = (int)($_POST['secretaria_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $active = ($_POST['active'] ?? '1') === '1';
            $slaHours = (int)($_POST['sla_hours'] ?? $defaultSlaHours);
            if ($subtypeId <= 0 || $name === '' || strlen($name) < 3) {
                throw new RuntimeException('Dados inválidos para atualizar subtipo.');
            }
            if ($secretariaId <= 0) {
                throw new RuntimeException('Selecione a secretaria responsável.');
            }
            if ($slaHours <= 0) {
                throw new RuntimeException('Informe um SLA válido em horas.');
            }
            $secretaria = $secretariasById[$secretariaId] ?? null;
            if (!$secretaria) {
                throw new RuntimeException('Secretaria inválida.');
            }
            if ((int)$secretaria['ativa'] !== 1) {
                throw new RuntimeException('Secretaria inativa não pode receber subtipos.');
            }
            updateServiceSubtype($pdo, $subtypeId, $name, $slaHours, $secretariaId, $active);
            flash('success', 'Subtipo atualizado.');
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        flash('danger', 'Erro ao atualizar catálogo: ' . $e->getMessage());
    }

    header('Location: service_types.php');
    exit;
}

$types = [];
$subtypesByType = [];
$secretarias = [];
$secretariasById = [];
$listError = null;

try {
    $pdo = getPDO();
    $types = listServiceTypes($pdo);
    $subtypes = listServiceSubtypes($pdo);
    $secretarias = listSecretarias($pdo);
    foreach ($secretarias as $secretaria) {
        $secretariasById[(int)$secretaria['id']] = $secretaria;
    }
    foreach ($subtypes as $subtype) {
        $typeId = (int)$subtype['service_type_id'];
        $subtypesByType[$typeId][] = $subtype;
    }
} catch (Throwable $e) {
    $listError = 'Erro ao carregar catálogo: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tipos de chamado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        .tag {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            border: 1px solid rgba(255,255,255,0.2);
            color: #e2e8f0;
        }
        .tag.active { background: rgba(16,185,129,0.15); }
        .tag.inactive { background: rgba(248,113,113,0.15); }
        .form-panel {
            background: linear-gradient(160deg, rgba(20, 30, 50, 0.65), rgba(10, 15, 28, 0.85));
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 14px;
            padding: 16px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        }
        .panel-title {
            margin: 0;
            font-weight: 600;
        }
        .panel-subtitle {
            margin: 2px 0 0;
            font-size: 0.84rem;
            color: #94a3b8;
        }
        .form-grid {
            display: grid;
            gap: 14px;
            align-items: end;
        }
        .form-grid.type {
            grid-template-columns: minmax(0, 1fr) auto;
            column-gap: 16px;
        }
        .form-grid.subtype {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 16px;
            row-gap: 14px;
        }
        .field-group {
            display: grid;
            gap: 6px;
        }
        .field-group.full {
            grid-column: 1 / -1;
        }
        .field-group.sla input {
            max-width: 180px;
        }
        .field-group.actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .field-group.actions .btn {
            min-width: 160px;
        }
        .field-label {
            font-size: 0.82rem;
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            font-weight: 600;
            line-height: 1.2;
        }
        .form-select, .form-control {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(148, 163, 184, 0.65);
            color: #f8fafc !important;
            min-height: 44px;
            font-weight: 500;
            caret-color: #38bdf8;
            box-shadow: inset 0 1px 2px rgba(2, 6, 23, 0.6);
        }
        .form-control::placeholder {
            color: #94a3b8;
            opacity: 1;
        }
        .form-select {
            color-scheme: dark;
        }
        .form-control:focus, .form-select:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 0.22rem rgba(56, 189, 248, 0.25);
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.98), rgba(15, 23, 42, 0.98));
        }
        .form-control:disabled, .form-select:disabled {
            color: rgba(226, 232, 240, 0.6) !important;
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(148, 163, 184, 0.25);
        }
        .btn-brand {
            min-height: 44px;
        }
        .table .form-control, .table .form-select {
            min-height: 38px;
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(2, 6, 23, 0.7);
            z-index: 1050;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-card {
            width: 100%;
            max-width: 520px;
            background: linear-gradient(160deg, rgba(20, 30, 50, 0.9), rgba(10, 15, 28, 0.98));
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 16px;
            padding: 20px;
            color: #e2e8f0;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.5);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .modal-title {
            margin: 0;
            font-weight: 600;
        }
        .modal-close {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: transparent;
            color: #e2e8f0;
            border-radius: 999px;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 16px;
        }
        .helper-text {
            font-size: 0.78rem;
            color: #94a3b8;
        }
        .form-hint {
            font-size: 0.78rem;
            color: #94a3b8;
        }
        @media (max-width: 992px) {
            .form-grid.type,
            .form-grid.subtype {
                grid-template-columns: 1fr;
            }
            .form-grid .btn {
                width: 100%;
            }
            .field-group.actions {
                justify-content: stretch;
            }
            .field-group.actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

        <div class="glass p-4 mb-4">
            <p class="text-uppercase small text-info mb-1">Catálogo</p>
            <h2 class="fw-bold mb-2">Tipos e subtipos de chamado</h2>
            <p class="mb-0 text-secondary">Gerencie o catálogo usado na abertura de protocolos.</p>
        </div>

        <div class="glass p-4 mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-panel h-100">
                        <div class="panel-header">
                            <div>
                                <h5 class="panel-title">Criar tipo</h5>
                                <p class="panel-subtitle">Grupo principal exibido para o cidadão.</p>
                            </div>
                        </div>
                        <form method="POST" class="form-grid type">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="action" value="create_type">
                            <div class="field-group">
                                <label class="field-label" for="type_name">Nome do tipo</label>
                                <input type="text" class="form-control" id="type_name" name="name" placeholder="Ex.: Iluminação pública" required>
                                <span class="form-hint">Use um nome curto e fácil de reconhecer.</span>
                            </div>
                            <button type="submit" class="btn btn-brand">Adicionar tipo</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-panel h-100">
                        <div class="panel-header flex-wrap">
                            <div>
                                <h5 class="panel-title">Criar subtipo</h5>
                                <p class="panel-subtitle">Selecione o tipo e a secretaria responsável.</p>
                            </div>
                            <?php if ($userType === 'admin'): ?>
                                <button type="button" class="btn btn-outline-info btn-sm" id="openSecretariaModal">Nova secretaria</button>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="form-grid subtype">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="action" value="create_subtype">
                            <div class="field-group">
                                <label class="field-label" for="subtype_type">Tipo de chamado</label>
                                <select name="type_id" id="subtype_type" class="form-select" required>
                                    <option value="" selected disabled>Selecione o tipo</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo (int)$type['id']; ?>">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label class="field-label" for="subtype_secretaria">Secretaria</label>
                                <select name="secretaria_id" id="subtype_secretaria" class="form-select" required>
                                    <option value="" selected disabled>Selecione a secretaria</option>
                                    <?php foreach ($secretarias as $secretaria): ?>
                                        <?php if ((int)$secretaria['ativa'] !== 1) continue; ?>
                                        <option value="<?php echo (int)$secretaria['id']; ?>">
                                            <?php echo htmlspecialchars($secretaria['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group full">
                                <label class="field-label" for="subtype_name">Nome do subtipo</label>
                                <input type="text" class="form-control" id="subtype_name" name="name" placeholder="Ex.: Poste apagado" required>
                            </div>
                            <div class="field-group sla">
                                <label class="field-label" for="subtype_sla">SLA (horas)</label>
                                <input type="number" class="form-control" id="subtype_sla" name="sla_hours" min="1" step="1"
                                       value="<?php echo $defaultSlaHours; ?>" required>
                                <span class="form-hint">Prazo base para atendimento.</span>
                            </div>
                            <div class="field-group actions">
                                <button type="submit" class="btn btn-brand">Adicionar subtipo</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Catálogo</p>
                    <h4 class="mb-0">Tipos cadastrados</h4>
                </div>
                <span class="badge bg-info text-dark"><?php echo count($types); ?> tipos</span>
            </div>

            <?php if ($listError): ?>
                <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($listError); ?></div>
            <?php elseif (!$types): ?>
                <p class="text-secondary mb-0">Nenhum tipo cadastrado ainda.</p>
            <?php else: ?>
                <?php foreach ($types as $type): ?>
                    <?php
                        $typeId = (int)$type['id'];
                        $subtypes = $subtypesByType[$typeId] ?? [];
                    ?>
                    <div class="border border-white border-opacity-10 rounded-3 p-3 mb-3">
                        <form method="POST" class="row g-2 align-items-center mb-2">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="action" value="update_type">
                            <input type="hidden" name="type_id" value="<?php echo $typeId; ?>">
                            <div class="col-md-6">
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($type['name']); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <select name="active" class="form-select">
                                    <option value="1" <?php echo (int)$type['active'] === 1 ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="0" <?php echo (int)$type['active'] === 0 ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-info text-white border-info">Salvar tipo</button>
                            </div>
                        </form>

                        <?php if (!$subtypes): ?>
                            <p class="text-secondary mb-0">Nenhum subtipo cadastrado.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Subtipo</th>
                                        <th>Secretaria</th>
                                        <th>SLA (h)</th>
                                        <th>Status</th>
                                        <th>Ação</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($subtypes as $subtype): ?>
                                        <tr>
                                            <td>
                                                <input type="text" form="subtype-<?php echo (int)$subtype['id']; ?>" name="name" class="form-control"
                                                       value="<?php echo htmlspecialchars($subtype['name']); ?>" required>
                                            </td>
                                            <td>
                                                <?php
                                                    $currentSecretariaId = (int)($subtype['secretaria_id'] ?? 0);
                                                    $currentSecretaria = $secretariasById[$currentSecretariaId] ?? null;
                                                ?>
                                                <select form="subtype-<?php echo (int)$subtype['id']; ?>" name="secretaria_id" class="form-select" required>
                                                    <?php if ($currentSecretaria && (int)$currentSecretaria['ativa'] !== 1): ?>
                                                        <option value="<?php echo (int)$currentSecretaria['id']; ?>" selected disabled>
                                                            Inativa: <?php echo htmlspecialchars($currentSecretaria['nome']); ?>
                                                        </option>
                                                    <?php elseif ($currentSecretariaId <= 0): ?>
                                                        <option value="" selected disabled>Selecione a secretaria</option>
                                                    <?php endif; ?>
                                                    <?php foreach ($secretarias as $secretaria): ?>
                                                        <?php if ((int)$secretaria['ativa'] !== 1) continue; ?>
                                                        <option value="<?php echo (int)$secretaria['id']; ?>" <?php echo (int)$secretaria['id'] === $currentSecretariaId ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($secretaria['nome']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" form="subtype-<?php echo (int)$subtype['id']; ?>" name="sla_hours" class="form-control"
                                                       value="<?php echo (int)($subtype['sla_hours'] ?? $defaultSlaHours); ?>" min="1" step="1" required>
                                            </td>
                                            <td>
                                                <select form="subtype-<?php echo (int)$subtype['id']; ?>" name="active" class="form-select">
                                                    <option value="1" <?php echo (int)$subtype['active'] === 1 ? 'selected' : ''; ?>>Ativo</option>
                                                    <option value="0" <?php echo (int)$subtype['active'] === 0 ? 'selected' : ''; ?>>Inativo</option>
                                                </select>
                                            </td>
                                            <td class="text-end">
                                                <form id="subtype-<?php echo (int)$subtype['id']; ?>" method="POST" class="d-inline-flex gap-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                                    <input type="hidden" name="action" value="update_subtype">
                                                    <input type="hidden" name="subtype_id" value="<?php echo (int)$subtype['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-info text-white border-info btn-sm">Salvar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php if ($userType === 'admin'): ?>
<div class="modal-overlay" id="secretariaModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="secretariaTitle">
        <div class="modal-header">
            <h5 class="modal-title" id="secretariaTitle">Nova secretaria</h5>
            <button type="button" class="modal-close" id="closeSecretariaModal" aria-label="Fechar">X</button>
        </div>
        <div class="field-group mb-2">
            <label class="field-label" for="secretaria_nome">Nome da secretaria</label>
            <input type="text" class="form-control" id="secretaria_nome" placeholder="Ex.: Secretaria de Zeladoria" required>
        </div>
        <div class="field-group mb-2">
            <label class="field-label" for="secretaria_slug">Slug</label>
            <input type="text" class="form-control" id="secretaria_slug" placeholder="secretaria-de-zeladoria">
            <span class="helper-text">Gerado automaticamente se você não preencher.</span>
        </div>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="secretaria_ativa" checked>
            <label class="form-check-label" for="secretaria_ativa">Secretaria ativa</label>
        </div>
        <div class="alert alert-danger py-2 d-none" id="secretaria_error"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-light" id="cancelSecretariaBtn">Cancelar</button>
            <button type="button" class="btn btn-brand" id="saveSecretariaBtn">Criar secretaria</button>
        </div>
    </div>
</div>
<script>
(() => {
    const openBtn = document.getElementById('openSecretariaModal');
    const modal = document.getElementById('secretariaModal');
    const closeBtn = document.getElementById('closeSecretariaModal');
    const cancelBtn = document.getElementById('cancelSecretariaBtn');
    const saveBtn = document.getElementById('saveSecretariaBtn');
    const nameInput = document.getElementById('secretaria_nome');
    const slugInput = document.getElementById('secretaria_slug');
    const activeInput = document.getElementById('secretaria_ativa');
    const errorBox = document.getElementById('secretaria_error');

    if (!openBtn || !modal || !nameInput || !slugInput || !saveBtn) return;

    let slugTouched = false;

    const slugify = (value) => {
        const normalized = value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
        return normalized
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-');
    };

    const openModal = () => {
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        nameInput.focus();
    };

    const closeModal = () => {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    };

    const resetForm = () => {
        nameInput.value = '';
        slugInput.value = '';
        activeInput.checked = true;
        slugTouched = false;
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    };

    openBtn.addEventListener('click', () => {
        resetForm();
        openModal();
    });
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    nameInput.addEventListener('input', () => {
        if (!slugTouched) {
            slugInput.value = slugify(nameInput.value);
        }
    });
    slugInput.addEventListener('input', () => {
        slugTouched = slugInput.value.trim() !== '';
    });

    const appendSecretariaOption = (secretaria) => {
        const selects = document.querySelectorAll('select[name="secretaria_id"]');
        selects.forEach((select) => {
            const exists = Array.from(select.options).some((opt) => String(opt.value) === String(secretaria.id));
            if (!exists) {
                const option = new Option(secretaria.nome, secretaria.id, false, false);
                select.add(option);
            }
        });
        const createSelect = document.getElementById('subtype_secretaria');
        if (createSelect) {
            createSelect.value = String(secretaria.id);
        }
    };

    saveBtn.addEventListener('click', async () => {
        const nome = nameInput.value.trim();
        const slug = slugInput.value.trim();
        const ativa = activeInput.checked;

        if (!nome || nome.length < 3) {
            errorBox.textContent = 'Informe um nome válido.';
            errorBox.classList.remove('d-none');
            return;
        }

        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        saveBtn.disabled = true;

        try {
            const response = await fetch('api/v1/secretarias/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nome, slug, ativa })
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Erro ao criar secretaria.');
            }

            if (data.secretaria) {
                appendSecretariaOption(data.secretaria);
            }
            closeModal();
        } catch (error) {
            errorBox.textContent = error?.message || 'Erro ao criar secretaria.';
            errorBox.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
