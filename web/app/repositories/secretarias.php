<?php
declare(strict_types=1);

function listSecretarias(PDO $pdo, ?bool $activeOnly = null): array
{
    $sql = 'SELECT id, nome, slug, ativa, created_at FROM secretarias';
    $params = [];
    if ($activeOnly === true) {
        $sql .= ' WHERE ativa = 1';
    } elseif ($activeOnly === false) {
        $sql .= ' WHERE ativa = 0';
    }
    $sql .= ' ORDER BY nome ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function listActiveSecretarias(PDO $pdo): array
{
    return listSecretarias($pdo, true);
}

function findSecretariaById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, nome, slug, ativa, created_at FROM secretarias WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function secretariaSlugExists(PDO $pdo, string $slug, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM secretarias WHERE slug = :slug';
    $params = ['slug' => $slug];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

function createSecretaria(PDO $pdo, string $nome, string $slug, bool $ativa): int
{
    $stmt = $pdo->prepare('
        INSERT INTO secretarias (nome, slug, ativa, created_at)
        VALUES (:nome, :slug, :ativa, NOW())
    ');
    $stmt->execute([
        'nome' => $nome,
        'slug' => $slug,
        'ativa' => $ativa ? 1 : 0,
    ]);
    return (int)$pdo->lastInsertId();
}

function updateSecretaria(PDO $pdo, int $id, string $nome, string $slug, bool $ativa): void
{
    $stmt = $pdo->prepare('
        UPDATE secretarias
        SET nome = :nome, slug = :slug, ativa = :ativa
        WHERE id = :id
    ');
    $stmt->execute([
        'id' => $id,
        'nome' => $nome,
        'slug' => $slug,
        'ativa' => $ativa ? 1 : 0,
    ]);
}
