<?php
require __DIR__ . '/app/bootstrap.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    flash('danger', 'Token de verificação inválido.');
    header('Location: index.php#login');
    exit;
}

try {
    $pdo = getPDO();
    $user = findUserByVerificationToken($pdo, $token);

    if (!$user) {
        flash('danger', 'Token não encontrado ou já utilizado.');
        header('Location: index.php#login');
        exit;
    }

    verifyUserById($pdo, (int)$user['id']);

    flash('success', 'Conta confirmada com sucesso! Agora você já pode acessar o sistema.');
    header('Location: index.php#login');
    exit;
} catch (Throwable $e) {
    flash('danger', 'Erro ao verificar token: ' . $e->getMessage());
    header('Location: index.php#login');
    exit;
}
