<?php
require __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    flash('danger', 'Token de verificação inválido.');
    header('Location: index.php#login');
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, email_verified_at, whatsapp_verified_at FROM users WHERE verification_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        flash('danger', 'Token não encontrado ou já utilizado.');
        header('Location: index.php#login');
        exit;
    }

    $update = $pdo->prepare('
        UPDATE users
        SET email_verified_at = COALESCE(email_verified_at, NOW()),
            whatsapp_verified_at = COALESCE(whatsapp_verified_at, NOW()),
            verification_token = NULL,
            updated_at = NOW()
        WHERE id = :id
    ');
    $update->execute(['id' => $user['id']]);

    flash('success', 'Conta confirmada com sucesso! Agora você já pode acessar o sistema.');
    header('Location: index.php#login');
    exit;
} catch (Throwable $e) {
    flash('danger', 'Erro ao verificar token: ' . $e->getMessage());
    header('Location: index.php#login');
    exit;
}
