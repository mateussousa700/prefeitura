<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#login');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    flash('danger', 'Informe um e-mail e senha válidos.');
    header('Location: index.php#login');
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('danger', 'Credenciais inválidas.');
        header('Location: index.php#login');
        exit;
    }

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
