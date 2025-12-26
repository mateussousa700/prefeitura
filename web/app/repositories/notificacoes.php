<?php
declare(strict_types=1);

function createNotification(PDO $pdo, int $userId, string $tipo, string $titulo, string $mensagem, string $canal = 'email'): int
{
    $stmt = $pdo->prepare('
        INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, canal, status_envio, created_at)
        VALUES (:usuario_id, :tipo, :titulo, :mensagem, :canal, "pendente", NOW())
    ');
    $stmt->execute([
        'usuario_id' => $userId,
        'tipo' => $tipo,
        'titulo' => $titulo,
        'mensagem' => $mensagem,
        'canal' => $canal,
    ]);
    return (int)$pdo->lastInsertId();
}

function notificationExists(PDO $pdo, int $userId, string $tipo, string $titulo): bool
{
    $stmt = $pdo->prepare('
        SELECT id
        FROM notificacoes
        WHERE usuario_id = :usuario_id AND tipo = :tipo AND titulo = :titulo
        LIMIT 1
    ');
    $stmt->execute([
        'usuario_id' => $userId,
        'tipo' => $tipo,
        'titulo' => $titulo,
    ]);
    return (bool)$stmt->fetch();
}

function listPendingNotifications(PDO $pdo, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $sql = '
        SELECT n.id,
               n.usuario_id,
               n.tipo,
               n.titulo,
               n.mensagem,
               n.canal,
               n.status_envio,
               n.created_at,
               u.name AS usuario_nome,
               u.email AS usuario_email
        FROM notificacoes n
        INNER JOIN users u ON u.id = n.usuario_id
        WHERE n.status_envio = "pendente"
        ORDER BY n.created_at ASC
        LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function markNotificationStatus(PDO $pdo, int $id, string $status): void
{
    if (!in_array($status, ['pendente', 'enviado', 'erro'], true)) {
        throw new RuntimeException('Status de envio inválido.');
    }
    $stmt = $pdo->prepare('UPDATE notificacoes SET status_envio = :status WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'id' => $id,
    ]);
}

function queueStatusNotification(PDO $pdo, int $userId, int $requestId, string $newStatus): void
{
    $labels = listServiceStatusOptions();
    $label = $labels[$newStatus] ?? $newStatus;
    $tipo = $newStatus === 'ENCERRADO' ? 'ENCERRAMENTO' : 'STATUS';
    $titulo = $tipo === 'ENCERRAMENTO'
        ? 'Chamado #' . $requestId . ' encerrado'
        : 'Status atualizado - Chamado #' . $requestId;
    $mensagem = $tipo === 'ENCERRAMENTO'
        ? "Seu chamado #{$requestId} foi encerrado. Obrigado por utilizar a Prefeitura Digital."
        : "Seu chamado #{$requestId} foi atualizado para o status: {$label}.";

    createNotification($pdo, $userId, $tipo, $titulo, $mensagem, 'email');
}
