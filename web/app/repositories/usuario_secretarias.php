<?php
declare(strict_types=1);

function listUserSecretariaIds(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT secretaria_id FROM usuario_secretarias WHERE usuario_id = :id');
    $stmt->execute(['id' => $userId]);
    $rows = $stmt->fetchAll();
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['secretaria_id'];
    }
    return $ids;
}

function listUserSecretarias(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT s.id, s.nome, s.slug, s.ativa
        FROM usuario_secretarias us
        INNER JOIN secretarias s ON s.id = us.secretaria_id
        WHERE us.usuario_id = :id
        ORDER BY s.nome ASC
    ');
    $stmt->execute(['id' => $userId]);
    return $stmt->fetchAll();
}
