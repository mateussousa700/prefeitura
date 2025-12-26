<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

requireRoles(['gestor', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tickets.php');
    exit;
}

requireValidCsrfToken($_POST['csrf_token'] ?? null, 'tickets.php');

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$addressText = trim($_POST['address'] ?? '');

if ($ticketId <= 0 || $addressText === '') {
    flash('danger', 'Dados inválidos para atualizar endereço.');
    header('Location: tickets.php');
    exit;
}

try {
    $pdo = getPDO();
    $userId = (int) currentUserId();
    updateServiceRequestAddress($pdo, $ticketId, $addressText, $userId);
    flash('success', 'Endereço atualizado e registrado no histórico.');
} catch (Throwable $e) {
    flash('danger', 'Erro ao atualizar endereço: ' . $e->getMessage());
}

header('Location: tickets.php');
exit;
