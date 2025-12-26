<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'users';
$userType = currentUserType();

requireRoles(['gestor', 'admin']);
$isManager = in_array($userType, ['gestor', 'admin'], true);

$types = [
    'populacao' => 'População',
    'gestor' => 'Gestor',
    'admin' => 'Admin',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isManager) {
    requireValidCsrfToken($_POST['csrf_token'] ?? null, 'users.php');

    $targetId = (int)($_POST['user_id'] ?? 0);
    $newType = $_POST['user_type'] ?? '';

    if ($targetId > 0 && array_key_exists($newType, $types)) {
        try {
            $pdo = getPDO();
            updateUserType($pdo, $targetId, $newType);

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
}

$users = [];
$listError = null;
try {
    $pdo = getPDO();
    $users = listUsers($pdo);
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
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
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
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <?php renderFlash(); ?>

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
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Criado em</th>
                            <?php if (in_array($userType, ['gestor','admin'], true)): ?>
                                <th>Ação</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $document = '';
                                if (($u['person_type'] ?? 'pf') === 'pj') {
                                    $document = $u['cnpj'] ?? '';
                                } else {
                                    $document = $u['cpf'] ?? '';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['id']); ?></td>
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['phone']); ?></td>
                                <td><?php echo htmlspecialchars($document); ?></td>
                                <td>
                                    <span class="badge badge-role text-uppercase"><?php echo htmlspecialchars($types[$u['user_type']] ?? $u['user_type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($u['created_at']))); ?></td>
                                <?php if (in_array($userType, ['gestor','admin'], true)): ?>
                                    <td>
                                        <form method="POST" class="d-flex gap-2 align-items-center mb-0">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
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
