<?php
declare(strict_types=1);

function listServiceTypes(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT id, name, active, created_at, updated_at FROM service_types';
    $params = [];
    if ($activeOnly) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function listServiceSubtypes(PDO $pdo, ?int $typeId = null, bool $activeOnly = false): array
{
    $sql = '
        SELECT ss.id, ss.service_type_id, ss.secretaria_id, ss.name, ss.sla_hours, ss.active, ss.created_at, ss.updated_at,
               sec.nome AS secretaria_nome, sec.slug AS secretaria_slug, sec.ativa AS secretaria_ativa
        FROM service_subtypes ss
        LEFT JOIN secretarias sec ON sec.id = ss.secretaria_id
    ';
    $params = [];
    $conditions = [];

    if ($typeId !== null) {
        $conditions[] = 'ss.service_type_id = :type_id';
        $params['type_id'] = $typeId;
    }
    if ($activeOnly) {
        $conditions[] = 'ss.active = 1';
        $conditions[] = 'ss.secretaria_id IS NOT NULL';
        $conditions[] = 'sec.ativa = 1';
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY ss.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createServiceType(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('INSERT INTO service_types (name, active, created_at, updated_at) VALUES (:name, 1, NOW(), NOW())');
    $stmt->execute(['name' => $name]);
    return (int)$pdo->lastInsertId();
}

function updateServiceType(PDO $pdo, int $id, string $name, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE service_types SET name = :name, active = :active, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['name' => $name, 'active' => $active ? 1 : 0, 'id' => $id]);
}

function setSubtypesActiveByType(PDO $pdo, int $typeId, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE service_subtypes SET active = :active, updated_at = NOW() WHERE service_type_id = :type_id');
    $stmt->execute(['active' => $active ? 1 : 0, 'type_id' => $typeId]);
}

function createServiceSubtype(PDO $pdo, int $typeId, string $name, int $slaHours, int $secretariaId): int
{
    $stmt = $pdo->prepare('
        INSERT INTO service_subtypes (service_type_id, secretaria_id, name, sla_hours, active, created_at, updated_at)
        VALUES (:type_id, :secretaria_id, :name, :sla_hours, 1, NOW(), NOW())
    ');
    $stmt->execute([
        'type_id' => $typeId,
        'secretaria_id' => $secretariaId,
        'name' => $name,
        'sla_hours' => $slaHours,
    ]);
    return (int)$pdo->lastInsertId();
}

function updateServiceSubtype(PDO $pdo, int $id, string $name, int $slaHours, int $secretariaId, bool $active): void
{
    $stmt = $pdo->prepare('
        UPDATE service_subtypes
        SET name = :name, sla_hours = :sla_hours, secretaria_id = :secretaria_id, active = :active, updated_at = NOW()
        WHERE id = :id
    ');
    $stmt->execute([
        'name' => $name,
        'sla_hours' => $slaHours,
        'secretaria_id' => $secretariaId,
        'active' => $active ? 1 : 0,
        'id' => $id,
    ]);
}

function findServiceSubtypeWithType(PDO $pdo, int $subtypeId): ?array
{
    $stmt = $pdo->prepare('
        SELECT st.id AS type_id, st.name AS type_name, st.active AS type_active,
               ss.id AS subtype_id, ss.name AS subtype_name, ss.sla_hours AS subtype_sla_hours, ss.active AS subtype_active,
               ss.secretaria_id AS secretaria_id, sec.nome AS secretaria_nome, sec.slug AS secretaria_slug, sec.ativa AS secretaria_ativa
        FROM service_subtypes ss
        INNER JOIN service_types st ON st.id = ss.service_type_id
        LEFT JOIN secretarias sec ON sec.id = ss.secretaria_id
        WHERE ss.id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $subtypeId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function listSubtypesByTypeWithSecretaria(PDO $pdo, int $typeId, bool $activeOnly = true): array
{
    $sql = '
        SELECT ss.id, ss.service_type_id, ss.name, ss.sla_hours, ss.active,
               sec.id AS secretaria_id, sec.nome AS secretaria_nome, sec.slug AS secretaria_slug, sec.ativa AS secretaria_ativa
        FROM service_subtypes ss
        LEFT JOIN secretarias sec ON sec.id = ss.secretaria_id
        WHERE ss.service_type_id = :type_id
    ';
    if ($activeOnly) {
        $sql .= ' AND ss.active = 1 AND ss.secretaria_id IS NOT NULL AND sec.ativa = 1';
    }
    $sql .= ' ORDER BY ss.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['type_id' => $typeId]);
    return $stmt->fetchAll();
}
