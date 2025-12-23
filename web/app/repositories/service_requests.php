<?php
declare(strict_types=1);

function listServiceRequestsByUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT id, service_name, problem_type, status, created_at, evidence_files
        FROM service_requests
        WHERE user_id = :uid
        ORDER BY created_at DESC
    ');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function createServiceRequest(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO service_requests
        (user_id, service_name, problem_type, address, latitude, longitude, duration, evidence_files, created_at, updated_at)
        VALUES (:user_id, :service_name, :problem_type, :address, :latitude, :longitude, :duration, :evidence_files, NOW(), NOW())
    ');
    $stmt->execute($data);

    return (int)$pdo->lastInsertId();
}

function findServiceRequestInfo(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT sr.service_name, sr.problem_type, sr.address, sr.status, u.name, u.email, u.phone
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
        WHERE sr.id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);
    $info = $stmt->fetch();

    return $info ?: null;
}

function updateServiceRequestStatus(PDO $pdo, int $id, string $status): void
{
    $stmt = $pdo->prepare('UPDATE service_requests SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);
}

function listTickets(PDO $pdo, ?string $serviceFilter): array
{
    $sql = '
        SELECT sr.id, sr.service_name, sr.problem_type, sr.status, sr.created_at, sr.evidence_files,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
    ';
    $params = [];
    if ($serviceFilter !== null && $serviceFilter !== '') {
        $sql .= ' WHERE sr.service_name = :service ';
        $params['service'] = $serviceFilter;
    }
    $sql .= ' ORDER BY sr.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function listCompletedRequests(PDO $pdo, ?string $serviceFilter): array
{
    $sql = '
        SELECT sr.id, sr.service_name, sr.problem_type, sr.status, sr.created_at, sr.evidence_files,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
        WHERE sr.status IN ("concluida","concluido","concluído","resolvido")
    ';
    $params = [];
    if ($serviceFilter !== null && $serviceFilter !== '') {
        $sql .= ' AND sr.service_name = :service ';
        $params['service'] = $serviceFilter;
    }
    $sql .= ' ORDER BY sr.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
