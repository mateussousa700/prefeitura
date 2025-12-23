<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    flash('danger', 'Faça login para acessar.');
    header('Location: index.php#login');
    exit;
}

$currentPage = 'home';
$userName = $_SESSION['user_name'] ?? 'Cidadão';
$userType = $_SESSION['user_type'] ?? 'populacao';
$flash = consumeFlash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefeitura Digital - Início</title>
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
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
            border: 1px solid rgba(14,165,233,0.3);
            font-weight: 600;
        }
        .btn-brand {
            background: linear-gradient(135deg, #0ea5e9, #22d3ee);
            color: #0b1221;
            font-weight: 700;
            border: none;
        }
        .btn-ghost {
            border: 1px solid rgba(255,255,255,0.2);
            color: #e2e8f0;
        }
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .step-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 16px 18px;
            height: 100%;
        }
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(14,165,233,0.2);
            color: #7dd3fc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 8px;
        }
        .helper-text { color: #cbd5e1; }
        .panel {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 18px;
            height: 100%;
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
            <a class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" aria-current="page" href="home.php">Início</a>
            <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" href="services.php">Serviços</a>
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
        <div class="glass p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="text-uppercase small text-info mb-1">Início</p>
                    <h2 class="fw-bold mb-2">Bem-vindo, <?php echo htmlspecialchars($userName); ?>!</h2>
                    <p class="mb-3 helper-text">Abra solicitações, acompanhe protocolos e mantenha seus dados sempre atualizados em poucos cliques.</p>
                    <div class="quick-actions">
                        <a class="btn btn-brand" href="services.php">Abrir solicitação</a>
                    </div>
                </div>
                <span class="pill">Portal da Prefeitura Digital</span>
            </div>
        </div>

        <div class="glass p-4">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="panel h-100">
                        <h5 class="fw-semibold mb-2">O que você pode fazer aqui</h5>
                        <ul class="helper-text mb-0">
                            <li>Solicitar serviços municipais (iluminação, limpeza, pavimentação, tributos).</li>
                            <li>Acompanhar status e evidências dos protocolos enviados.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel h-100">
                        <h5 class="fw-semibold mb-3">Como abrir uma solicitação</h5>
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="step-card">
                                    <span class="step-number">1</span>
                                    <span class="helper-text">Entre em “Serviços” e clique no serviço desejado.</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="step-card">
                                    <span class="step-number">2</span>
                                    <span class="helper-text">Descreva o problema, envie fotos, endereço e CEP.</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="step-card">
                                    <span class="step-number">3</span>
                                    <span class="helper-text">Acompanhe em “Meus protocolos” o andamento e respostas.</span>
                                </div>
                            </div>
                        </div>
                        <p class="helper-text mt-2 mb-0">Dica: dados de contato atualizados agilizam retornos da prefeitura.</p>
                    </div>
                </div>
            </div>
            <div class="panel">
                <h5 class="fw-semibold mb-3">Como ver suas solicitações</h5>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="step-card">
                            <span class="step-number">1</span>
                            <span class="helper-text">Acesse o menu “Meus protocolos” no lado esquerdo.</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step-card">
                            <span class="step-number">2</span>
                            <span class="helper-text">Veja a lista com status e quantidade de evidências enviadas.</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step-card">
                            <span class="step-number">3</span>
                            <span class="helper-text">Clique no protocolo para revisar dados e acompanhar atualizações.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
