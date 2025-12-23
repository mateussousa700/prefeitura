<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    flash('danger', 'Faça login para acessar.');
    header('Location: index.php#login');
    exit;
}

$currentPage = 'requests';
$userName = $_SESSION['user_name'] ?? 'Cidadão';
$userType = $_SESSION['user_type'] ?? 'populacao';
$flash = consumeFlash();

$requests = [];
$requestsError = null;

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('
        SELECT id, service_name, problem_type, status, created_at, evidence_files
        FROM service_requests
        WHERE user_id = :uid
        ORDER BY created_at DESC
    ');
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $requests = $stmt->fetchAll();
} catch (Throwable $e) {
    $requestsError = 'Erro ao carregar suas solicitações: ' . $e->getMessage();
}

$statusClasses = [
    'aberta' => 'bg-info text-dark',
    'aberto' => 'bg-info text-dark', // fallback
    'novo' => 'bg-info text-dark',
    'em_andamento' => 'bg-warning text-dark',
    'em andamento' => 'bg-warning text-dark',
    'concluida' => 'bg-success',
    'concluido' => 'bg-success',
    'concluído' => 'bg-success',
    'resolvido' => 'bg-success',
    'cancelada' => 'bg-danger',
    'cancelado' => 'bg-danger',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meus protocolos</title>
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
        .badge-soft {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
            border: 1px solid rgba(125, 211, 252, 0.35);
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
            <a class="nav-link <?php echo $currentPage === 'requests' ? 'active' : ''; ?>" aria-current="page" href="requests.php">Meus protocolos</a>
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
                            <th>Serviço</th>
                            <th>Problema</th>
                            <th>Evidências</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($requests as $req): ?>
                            <?php
                            $files = [];
                            if (!empty($req['evidence_files'])) {
                                $decoded = json_decode($req['evidence_files'], true);
                                if (is_array($decoded)) {
                                    $files = $decoded;
                                } elseif (is_string($req['evidence_files'])) {
                                    $files = [$req['evidence_files']];
                                }
                            }
                            $firstImage = $files[0] ?? null;
                            $evidenceCount = count($files);
                            $tooltipHtml = $firstImage
                                ? '<img src="' . htmlspecialchars($firstImage, ENT_QUOTES) . '" style="max-width:220px; border-radius:8px;" />'
                                : 'Nenhuma imagem';
                            $tooltipAttr = htmlspecialchars($tooltipHtml, ENT_QUOTES);
                            $statusKey = strtolower((string)$req['status']);
                            $badgeClass = $statusClasses[$statusKey] ?? 'bg-secondary';
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
                                    <span class="badge <?php echo $badgeClass; ?> text-uppercase"><?php echo htmlspecialchars($req['status']); ?></span>
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
