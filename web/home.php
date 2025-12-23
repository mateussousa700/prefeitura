<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'home';
$userName = currentUserName();
$userType = currentUserType();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefeitura Digital - Início</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
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
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>
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
