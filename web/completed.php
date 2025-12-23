<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    flash('danger', 'Faça login para acessar.');
    header('Location: index.php#login');
    exit;
}

$currentPage = 'completed';
$userName = $_SESSION['user_name'] ?? 'Cidadão';
$userType = $_SESSION['user_type'] ?? 'populacao';
$flash = consumeFlash();

if (!in_array($userType, ['gestor', 'admin'], true)) {
    flash('danger', 'Acesso restrito a gestores ou administradores.');
    header('Location: home.php');
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

$completed = [];
$listError = null;
try {
    $pdo = getPDO();
    $sql = '
        SELECT sr.id, sr.service_name, sr.problem_type, sr.status, sr.created_at, sr.evidence_files,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
        WHERE sr.status IN ("concluida","concluído","concluido","resolvido")
    ';
    $params = [];
    if ($filterService !== '' && array_key_exists($filterService, $serviceOptions)) {
        $sql .= ' AND sr.service_name = :service ';
        $params['service'] = $filterService;
    }
    $sql .= ' ORDER BY sr.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $completed = $stmt->fetchAll();
} catch (Throwable $e) {
    $listError = 'Erro ao carregar concluídos: ' . $e->getMessage();
}

$statusClasses = [
    'concluida' => 'bg-success',
    'concluído' => 'bg-success',
    'concluido' => 'bg-success',
    'resolvido' => 'bg-success',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Serviços concluídos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-bg: #0f172a;
            --brand-accent: #0ea5e9;
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
        .content { padding: 36px; }
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
        .btn-brand:hover {
            background: linear-gradient(135deg, #0aa1e5, #1bcde7);
            color: #0b1221;
        }
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
    <aside class="sidebar">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <span class="brand">Prefeitura Digital</span>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" href="home.php">Início</a>
            <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" href="services.php">Serviços</a>
            <a class="nav-link <?php echo $currentPage === 'requests' ? 'active' : ''; ?>" href="requests.php">Meus protocolos</a>
            <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" href="profile.php">Meu perfil</a>
            <?php if (in_array($userType, ['gestor', 'admin'], true)): ?>
                <a class="nav-link <?php echo $currentPage === 'tickets' ? 'active' : ''; ?>" href="tickets.php">Chamados</a>
                <a class="nav-link <?php echo $currentPage === 'completed' ? 'active' : ''; ?>" aria-current="page" href="completed.php">Concluídos</a>
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
                            $files = [];
                            if (!empty($t['evidence_files'])) {
                                $decoded = json_decode($t['evidence_files'], true);
                                if (is_array($decoded)) {
                                    $files = $decoded;
                                } elseif (is_string($t['evidence_files'])) {
                                    $files = [$t['evidence_files']];
                                }
                            }
                            $firstImage = $files[0] ?? null;
                            $evidenceCount = count($files);
                            $tooltipHtml = $firstImage
                                ? '<img src="' . htmlspecialchars($firstImage, ENT_QUOTES) . '" style="max-width:220px; border-radius:8px;" />'
                                : 'Nenhuma imagem';
                            $tooltipAttr = htmlspecialchars($tooltipHtml, ENT_QUOTES);
                            $statusKey = strtolower((string)$t['status']);
                            $badgeClass = $statusClasses[$statusKey] ?? 'bg-secondary';
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
