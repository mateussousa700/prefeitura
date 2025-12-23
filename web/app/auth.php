<?php
declare(strict_types=1);

function requireLogin(string $message = 'Faça login para acessar.'): void
{
    if (!isset($_SESSION['user_id'])) {
        flash('danger', $message);
        header('Location: index.php#login');
        exit;
    }
}

function requireRoles(array $roles, string $message = 'Acesso restrito a gestores ou administradores.', string $redirect = 'home.php'): void
{
    $userType = currentUserType();
    if (!in_array($userType, $roles, true)) {
        flash('danger', $message);
        header('Location: ' . $redirect);
        exit;
    }
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentUserName(): string
{
    return $_SESSION['user_name'] ?? 'Cidadão';
}

function currentUserType(): string
{
    return $_SESSION['user_type'] ?? 'populacao';
}
