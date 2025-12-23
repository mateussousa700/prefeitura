<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    flash('danger', 'Faça login para acessar.');
    header('Location: index.php#login');
    exit;
}

$currentPage = 'users';
$userName = $_SESSION['user_name'] ?? 'Cidadão';
$userType = $_SESSION['user_type'] ?? 'populacao';
$flash = consumeFlash();

$isManager = in_array($userType, ['gestor', 'admin'], true);
if (!$isManager) {
    flash('danger', 'Acesso restrito a gestores ou administradores.');
    header('Location: home.php');
    exit;
}

$types = [
    'populacao' => 'População',
    'gestor' => 'Gestor',
    'admin' => 'Admin',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isManager) {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $newType = $_POST['user_type'] ?? '';

    if ($targetId > 0 && array_key_exists($newType, $types)) {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('UPDATE users SET user_type = :type, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['type' => $newType, 'id' => $targetId]);

            // Se o usuário alterou o próprio tipo, atualiza a sessão para refletir imediatamente.
            if ($targetId === (int)$_SESSION['user_id']) {
                $_SESSION['user_type'] = $newType;
                $userType = $newType;
            }

            flash('success', 'Tipo de usuário atualizado.');
            header('Location: users.php');
            exit;
        } catch (Throwable $e) {
            flash('danger', 'Erro ao atualizar usuário: ' . $e->getMessage());
            header('Location: users.php');
            exit;
        }
    } else {
        flash('danger', 'Dados inválidos para atualização.');
        header('Location: users.php');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('danger', 'Você não tem permissão para alterar usuários.');
    header('Location: users.php');
    exit;
}

$users = [];
$listError = null;
try {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT id, name, email, phone, cpf, user_type, created_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    $listError = 'Erro ao carregar usuários: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestão de usuários</title>
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
        .badge-role {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
            border: 1px solid rgba(14,165,233,0.3);
        }
        .form-select {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            color: #e2e8f0;
        }
        .form-select:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.25);
        }
        .btn-brand {
            background: linear-gradient(135deg, #0ea5e9, #22d3ee);
            color: #0b1221;
            font-weight: 700;
            border: none;
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
                <a class="nav-link <?php echo $currentPage === 'completed' ? 'active' : ''; ?>" href="completed.php">Concluídos</a>
            <?php endif; ?>
            <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" aria-current="page" href="users.php">Gestão de usuários</a>
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
            <p class="text-uppercase small text-info mb-1">Gestão de usuários</p>
            <h2 class="fw-bold mb-3">Controle de perfis de acesso</h2>
            <p class="mb-0">Tipos disponíveis: População, Gestor e Admin. Admin/Gestor podem ajustar o tipo dos demais usuários.</p>
        </div>

        <div class="glass p-4">
            <?php if ($listError): ?>
                <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($listError); ?></div>
            <?php elseif (!$users): ?>
                <p class="text-secondary mb-0">Nenhum usuário encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>CPF</th>
                            <th>Tipo</th>
                            <th>Criado em</th>
                            <?php if (in_array($userType, ['gestor','admin'], true)): ?>
                                <th>Ação</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['id']); ?></td>
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['phone']); ?></td>
                                <td><?php echo htmlspecialchars($u['cpf']); ?></td>
                                <td>
                                    <span class="badge badge-role text-uppercase"><?php echo htmlspecialchars($types[$u['user_type']] ?? $u['user_type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($u['created_at']))); ?></td>
                                <?php if (in_array($userType, ['gestor','admin'], true)): ?>
                                    <td>
                                        <form method="POST" class="d-flex gap-2 align-items-center mb-0">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <select name="user_type" class="form-select form-select-sm">
                                                <?php foreach ($types as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $u['user_type'] === $key ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-brand btn-sm">Salvar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
