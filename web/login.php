<?php
require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#login');
    exit;
}

requireValidCsrfToken($_POST['csrf_token'] ?? null, 'index.php#login');

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    flash('danger', 'Informe um e-mail e senha válidos.');
    header('Location: index.php#login');
    exit;
}

try {
    $pdo = getPDO();
    $user = findUserByEmail($pdo, $email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('danger', 'Credenciais inválidas.');
        header('Location: index.php#login');
        exit;
    }

    $verified = ($user['email_verified_at'] !== null) || ($user['whatsapp_verified_at'] !== null);
    $pendingVerification = isset($user['verification_token']) && $user['verification_token'] !== null && $user['verification_token'] !== '';
    if (!$verified && $pendingVerification) {
        $userType = $user['user_type'] ?? 'populacao';
        $isInternal = in_array($userType, ['admin', 'gestor', 'gestor_global'], true);
        if ($isInternal || userHasServiceRequests($pdo, (int)$user['id'])) {
            verifyUserById($pdo, (int)$user['id']);
            $verified = true;
        }
    }
    if (!$verified && $pendingVerification) {
        flash('danger', 'Confirme seu cadastro por e-mail ou WhatsApp para continuar.');
        header('Location: index.php#login');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_type'] = $user['user_type'] ?? 'populacao';

    flash('success', 'Login realizado com sucesso!');

    header('Location: home.php');
    exit;
} catch (Throwable $e) {
    flash('danger', 'Erro ao validar login: ' . $e->getMessage());
    header('Location: index.php#login');
    exit;
}
