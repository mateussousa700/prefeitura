<?php
declare(strict_types=1);

function listServiceRequestsByUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT sr.id,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sr.address, sr.neighborhood, sr.zip,
               sr.status, sr.created_at, sr.evidence_files
        FROM service_requests sr
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        WHERE sr.user_id = :uid
        ORDER BY sr.created_at DESC
    ');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function userHasServiceRequests(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM service_requests WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    return (bool)$stmt->fetchColumn();
}

function createServiceRequest(PDO $pdo, array $data): int
{
    $secretariaId = isset($data['secretaria_id']) ? (int)$data['secretaria_id'] : 0;
    if ($secretariaId <= 0) {
        throw new RuntimeException('Secretaria obrigatória para o chamado.');
    }
    $data['secretaria_id'] = $secretariaId;
    $data['chamado_pai_id'] = $data['chamado_pai_id'] ?? null;
    $data['contador_apoios'] = isset($data['contador_apoios']) ? (int)$data['contador_apoios'] : 0;

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO service_requests
            (user_id, service_type_id, service_subtype_id, secretaria_id, chamado_pai_id, contador_apoios, service_name, problem_type, address, neighborhood, zip, latitude, longitude, tempo_ocorrencia, priority, sla_due_at, evidence_files, created_at, updated_at)
            VALUES (:user_id, :service_type_id, :service_subtype_id, :secretaria_id, :chamado_pai_id, :contador_apoios, :service_name, :problem_type, :address, :neighborhood, :zip, :latitude, :longitude, :tempo_ocorrencia, :priority, :sla_due_at, :evidence_files, NOW(), NOW())
        ');
        $stmt->execute($data);

        $requestId = (int)$pdo->lastInsertId();

        $statusStmt = $pdo->prepare('SELECT status FROM service_requests WHERE id = :id');
        $statusStmt->execute(['id' => $requestId]);
        $statusRow = $statusStmt->fetch();
        $status = $statusRow ? normalizeServiceStatus((string)$statusRow['status']) : 'RECEBIDO';

        $stmt = $pdo->prepare('
            INSERT INTO chamado_historico (chamado_id, status_anterior, status_novo, usuario_id, observacao, created_at)
            VALUES (:chamado_id, :status_anterior, :status_novo, :usuario_id, :observacao, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $requestId,
            'status_anterior' => $status,
            'status_novo' => $status,
            'usuario_id' => (int)($data['user_id'] ?? 0),
            'observacao' => 'Chamado criado.',
        ]);

        $evidenceFiles = $data['evidence_files'] ?? null;
        if (is_string($evidenceFiles) && $evidenceFiles !== '') {
            $decoded = json_decode($evidenceFiles, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $evidenceFiles = $decoded;
            }
        }

        logServiceRequestAudit($pdo, $requestId, (int)($data['user_id'] ?? 0), 'CHAMADO_CRIADO', [
            'status' => $status,
            'service_type_id' => $data['service_type_id'] ?? null,
            'service_subtype_id' => $data['service_subtype_id'] ?? null,
            'secretaria_id' => $data['secretaria_id'] ?? null,
            'chamado_pai_id' => $data['chamado_pai_id'] ?? null,
            'contador_apoios' => $data['contador_apoios'] ?? 0,
            'service_name' => $data['service_name'] ?? null,
            'problem_type' => $data['problem_type'] ?? null,
            'address' => $data['address'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'zip' => $data['zip'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'tempo_ocorrencia' => $data['tempo_ocorrencia'] ?? null,
            'priority' => $data['priority'] ?? null,
            'sla_due_at' => $data['sla_due_at'] ?? null,
            'evidence_files' => $evidenceFiles,
        ]);

        if ($ownTransaction) {
            $pdo->commit();
        }

        return $requestId;
    } catch (Throwable $e) {
        if ($ownTransaction) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function listOpenServiceStatusCodes(): array
{
    return ['RECEBIDO', 'EM_ANALISE', 'ENCAMINHADO', 'EM_EXECUCAO'];
}

function findDuplicateServiceRequests(PDO $pdo, int $subtypeId, float $latitude, float $longitude, int $daysWindow, int $radiusMeters, int $limit = 5): array
{
    $daysWindow = max(1, $daysWindow);
    $radiusMeters = max(1, $radiusMeters);
    $limit = max(1, min(20, $limit));

    $latDelta = $radiusMeters / 111320;
    $cosLat = cos(deg2rad($latitude));
    $lonDelta = $cosLat > 0 ? $radiusMeters / (111320 * $cosLat) : $latDelta;

    $minLat = $latitude - $latDelta;
    $maxLat = $latitude + $latDelta;
    $minLon = $longitude - $lonDelta;
    $maxLon = $longitude + $lonDelta;

    $since = (new DateTimeImmutable('now'))->modify('-' . $daysWindow . ' days')->format('Y-m-d H:i:s');
    $openStatuses = listOpenServiceStatusCodes();

    $sql = '
        SELECT sr.id,
               sr.status,
               sr.created_at,
               sr.address,
               sr.neighborhood,
               sr.zip,
               sr.latitude,
               sr.longitude,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sec.nome AS secretaria_nome,
               (6371000 * 2 * ASIN(SQRT(
                   POWER(SIN(RADIANS(:lat - sr.latitude) / 2), 2) +
                   COS(RADIANS(:lat)) * COS(RADIANS(sr.latitude)) *
                   POWER(SIN(RADIANS(:lon - sr.longitude) / 2), 2)
               ))) AS distance_m
        FROM service_requests sr
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
        WHERE sr.service_subtype_id = :subtype_id
          AND sr.chamado_pai_id IS NULL
          AND sr.status IN ("' . implode('","', $openStatuses) . '")
          AND sr.created_at >= :since
          AND sr.latitude BETWEEN :min_lat AND :max_lat
          AND sr.longitude BETWEEN :min_lon AND :max_lon
          AND sr.latitude <> 0
          AND sr.longitude <> 0
        HAVING distance_m <= :radius
        ORDER BY distance_m ASC
        LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'lat' => $latitude,
        'lon' => $longitude,
        'subtype_id' => $subtypeId,
        'since' => $since,
        'min_lat' => $minLat,
        'max_lat' => $maxLat,
        'min_lon' => $minLon,
        'max_lon' => $maxLon,
        'radius' => $radiusMeters,
    ]);
    return $stmt->fetchAll();
}

function registerServiceRequestSupport(PDO $pdo, int $requestId, int $userId): void
{
    if ($requestId <= 0 || $userId <= 0) {
        throw new RuntimeException('Dados inválidos para apoio.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, contador_apoios FROM service_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $requestId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Chamado não encontrado.');
        }

        $currentStatus = normalizeServiceStatus((string)$row['status']);
        $previousSupports = isset($row['contador_apoios']) ? (int)$row['contador_apoios'] : 0;
        if (!in_array($currentStatus, listOpenServiceStatusCodes(), true)) {
            throw new RuntimeException('Chamado não está aberto para apoio.');
        }

        $stmt = $pdo->prepare('
            UPDATE service_requests
            SET contador_apoios = contador_apoios + 1, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $requestId]);

        $stmt = $pdo->prepare('
            INSERT INTO chamado_historico (chamado_id, status_anterior, status_novo, usuario_id, observacao, created_at)
            VALUES (:chamado_id, :status_anterior, :status_novo, :usuario_id, :observacao, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $requestId,
            'status_anterior' => $currentStatus,
            'status_novo' => $currentStatus,
            'usuario_id' => $userId,
            'observacao' => 'Apoio registrado pelo usuário.',
        ]);

        logServiceRequestAudit($pdo, $requestId, $userId, 'CHAMADO_APOIO_REGISTRADO', [
            'status' => $currentStatus,
            'contador_apoios_anterior' => $previousSupports,
            'contador_apoios_novo' => $previousSupports + 1,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function registerDuplicateJustification(PDO $pdo, int $requestId, int $userId, int $parentId, string $reason): void
{
    if ($requestId <= 0 || $userId <= 0 || $parentId <= 0) {
        return;
    }

    $stmt = $pdo->prepare('SELECT status FROM service_requests WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $requestId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $status = normalizeServiceStatus((string)$row['status']);

    $observation = sprintf(
        'Criado como duplicado do chamado #%d. Justificativa: %s',
        $parentId,
        $reason
    );

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO chamado_historico (chamado_id, status_anterior, status_novo, usuario_id, observacao, created_at)
            VALUES (:chamado_id, :status_anterior, :status_novo, :usuario_id, :observacao, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $requestId,
            'status_anterior' => $status,
            'status_novo' => $status,
            'usuario_id' => $userId,
            'observacao' => $observation,
        ]);

        logServiceRequestAudit($pdo, $requestId, $userId, 'CHAMADO_DUPLICIDADE_REGISTRADA', [
            'status' => $status,
            'chamado_pai_id' => $parentId,
            'motivo' => $reason,
        ]);

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function findServiceRequestInfo(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sr.address, sr.neighborhood, sr.zip,
               sr.status, u.name, u.email, u.phone
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        WHERE sr.id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);
    $info = $stmt->fetch();

    return $info ?: null;
}

function listServiceStatusOptions(): array
{
    return [
        'RECEBIDO' => 'Recebido',
        'EM_ANALISE' => 'Em análise',
        'ENCAMINHADO' => 'Encaminhado',
        'EM_EXECUCAO' => 'Em execução',
        'RESOLVIDO' => 'Resolvido',
        'ENCERRADO' => 'Encerrado',
    ];
}

function serviceStatusTransitions(): array
{
    return [
        'RECEBIDO' => ['EM_ANALISE'],
        'EM_ANALISE' => ['ENCAMINHADO'],
        'ENCAMINHADO' => ['EM_EXECUCAO'],
        'EM_EXECUCAO' => ['RESOLVIDO'],
        'RESOLVIDO' => ['ENCERRADO'],
        'ENCERRADO' => [],
    ];
}

function normalizeServiceStatus(string $status): string
{
    return strtoupper(trim($status));
}

function logServiceRequestAudit(PDO $pdo, int $chamadoId, int $userId, string $acao, ?array $detalhes = null): void
{
    $acao = trim($acao);
    if ($chamadoId <= 0 || $userId <= 0 || $acao === '') {
        throw new RuntimeException('Dados invalidos para auditoria do chamado.');
    }

    $detalhesJson = null;
    if ($detalhes !== null) {
        $detalhesJson = json_encode($detalhes, JSON_UNESCAPED_SLASHES);
        if ($detalhesJson === false) {
            throw new RuntimeException('Falha ao serializar dados de auditoria.');
        }
    }

    $stmt = $pdo->prepare('
        INSERT INTO chamado_auditoria (chamado_id, usuario_id, acao, detalhes, created_at)
        VALUES (:chamado_id, :usuario_id, :acao, :detalhes, NOW())
    ');
    $stmt->execute([
        'chamado_id' => $chamadoId,
        'usuario_id' => $userId,
        'acao' => $acao,
        'detalhes' => $detalhesJson,
    ]);
}

function canTransitionServiceStatus(string $from, string $to): bool
{
    $from = normalizeServiceStatus($from);
    $to = normalizeServiceStatus($to);
    $transitions = serviceStatusTransitions();

    if (!array_key_exists($from, $transitions) || !array_key_exists($to, $transitions)) {
        return false;
    }

    if ($from === $to) {
        return true;
    }

    return in_array($to, $transitions[$from], true);
}

function updateServiceRequestStatus(PDO $pdo, int $id, ?string $status, int $userId, ?string $observation = null, bool $allowAnyTransition = false): string
{
    if ($userId <= 0) {
        throw new RuntimeException('Usuário inválido para atualização.');
    }

    $newStatus = $status !== null ? normalizeServiceStatus($status) : '';
    if ($observation !== null) {
        $observation = trim($observation);
        if ($observation === '') {
            $observation = null;
        }
    }
    $validStatuses = listServiceStatusOptions();

    if ($newStatus !== '' && !array_key_exists($newStatus, $validStatuses)) {
        throw new RuntimeException('Status inválido.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, user_id FROM service_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Chamado não encontrado.');
        }

        $currentStatus = normalizeServiceStatus((string)$row['status']);
        $ownerId = isset($row['user_id']) ? (int)$row['user_id'] : 0;
        if ($newStatus === '') {
            $nextStatuses = serviceStatusTransitions()[$currentStatus] ?? [];
            $newStatus = $nextStatuses[0] ?? '';
            if ($newStatus === '') {
                throw new RuntimeException('Nenhuma transição disponível para este chamado.');
            }
        }

        if (!array_key_exists($newStatus, $validStatuses)) {
            throw new RuntimeException('Status inválido.');
        }

        $transitionAllowed = canTransitionServiceStatus($currentStatus, $newStatus);
        if (!$transitionAllowed && !$allowAnyTransition) {
            throw new RuntimeException('Transição de status não permitida.');
        }
        if ($allowAnyTransition && $observation === null) {
            $observation = $transitionAllowed
                ? 'Atualização manual via painel.'
                : 'Atualização manual fora do fluxo.';
        }

        if ($currentStatus === $newStatus) {
            $pdo->commit();
            return $currentStatus;
        }

        $stmt = $pdo->prepare('UPDATE service_requests SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $newStatus, 'id' => $id]);

        $stmt = $pdo->prepare('
            INSERT INTO chamado_historico (chamado_id, status_anterior, status_novo, usuario_id, observacao, created_at)
            VALUES (:chamado_id, :status_anterior, :status_novo, :usuario_id, :observacao, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $id,
            'status_anterior' => $currentStatus,
            'status_novo' => $newStatus,
            'usuario_id' => $userId,
            'observacao' => $observation,
        ]);

        logServiceRequestAudit($pdo, $id, $userId, 'CHAMADO_STATUS_ATUALIZADO', [
            'status_anterior' => $currentStatus,
            'status_novo' => $newStatus,
            'observacao' => $observation,
            'transicao_forcada' => $allowAnyTransition && !$transitionAllowed,
        ]);

        $pdo->commit();

        if ($ownerId > 0) {
            try {
                queueStatusNotification($pdo, $ownerId, $id, $newStatus);
            } catch (Throwable $e) {
                // Não bloqueia o fluxo se a fila de notificações falhar.
            }
        }
        return $newStatus;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function listTickets(PDO $pdo, ?int $serviceTypeId): array
{
    $sql = '
        SELECT sr.id,
               sr.secretaria_id,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sr.address, sr.neighborhood, sr.zip,
               sr.status, sr.created_at, sr.evidence_files,
               sr.latitude, sr.longitude,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               sec.nome AS secretaria_nome, sec.slug AS secretaria_slug
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
    ';
    $params = [];
    if ($serviceTypeId !== null) {
        $sql .= ' WHERE sr.service_type_id = :service_type_id ';
        $params['service_type_id'] = $serviceTypeId;
    }
    $sql .= ' ORDER BY sr.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function listCompletedRequests(PDO $pdo, ?int $serviceTypeId): array
{
    $sql = '
        SELECT sr.id,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sr.address, sr.neighborhood, sr.zip,
               sr.status, sr.created_at, sr.evidence_files,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM service_requests sr
        INNER JOIN users u ON u.id = sr.user_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        WHERE sr.status IN ("RESOLVIDO","ENCERRADO","concluida","concluido","concluído","resolvido")
    ';
    $params = [];
    if ($serviceTypeId !== null) {
        $sql .= ' AND sr.service_type_id = :service_type_id ';
        $params['service_type_id'] = $serviceTypeId;
    }
    $sql .= ' ORDER BY sr.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function updateServiceRequestAddress(PDO $pdo, int $id, string $addressText, int $userId): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Usuário inválido.');
    }

    $addressText = trim($addressText);
    if ($addressText === '' || strlen($addressText) < 5) {
        throw new RuntimeException('Informe um endereço válido.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT address FROM service_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Chamado não encontrado.');
        }

        $current = (string)($row['address'] ?? '');
        if ($current === $addressText) {
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare('UPDATE service_requests SET address = :address, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['address' => $addressText, 'id' => $id]);

        $stmt = $pdo->prepare('
            INSERT INTO chamado_localizacao_historico (chamado_id, endereco_anterior, endereco_novo, usuario_id, created_at)
            VALUES (:chamado_id, :endereco_anterior, :endereco_novo, :usuario_id, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $id,
            'endereco_anterior' => $current,
            'endereco_novo' => $addressText,
            'usuario_id' => $userId,
        ]);

        logServiceRequestAudit($pdo, $id, $userId, 'CHAMADO_ENDERECO_ATUALIZADO', [
            'endereco_anterior' => $current,
            'endereco_novo' => $addressText,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function listServiceRequestLocationHistory(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('
        SELECT h.id,
               h.chamado_id,
               h.endereco_anterior,
               h.endereco_novo,
               h.usuario_id,
               u.name AS usuario_nome,
               h.created_at
        FROM chamado_localizacao_historico h
        LEFT JOIN users u ON u.id = h.usuario_id
        WHERE h.chamado_id = :id
        ORDER BY h.created_at ASC, h.id ASC
    ');
    $stmt->execute(['id' => $id]);

    return $stmt->fetchAll();
}

function findServiceRequestOwner(PDO $pdo, int $id): ?int
{
    $stmt = $pdo->prepare('SELECT user_id FROM service_requests WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ? (int)$row['user_id'] : null;
}

function listServiceRequestHistory(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('
        SELECT h.id,
               h.chamado_id,
               h.status_anterior,
               h.status_novo,
               h.usuario_id,
               u.name AS usuario_nome,
               h.observacao,
               h.created_at
        FROM chamado_historico h
        LEFT JOIN users u ON u.id = h.usuario_id
        WHERE h.chamado_id = :id
        ORDER BY h.created_at ASC, h.id ASC
    ');
    $stmt->execute(['id' => $id]);

    return $stmt->fetchAll();
}

function findServiceRequestSla(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT sr.id,
               sr.user_id,
               sr.created_at,
               sr.sla_due_at,
               ss.sla_hours
        FROM service_requests sr
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        WHERE sr.id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function listServiceRequestsQueue(PDO $pdo, array $filters, ?array $secretariaIds = null): array
{
    $sql = '
        SELECT sr.id,
               sr.service_type_id,
               sr.service_subtype_id,
               sr.secretaria_id,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sr.status,
               sr.priority,
               sr.tempo_ocorrencia,
               sr.sla_due_at,
               sr.created_at,
               u.name AS user_name,
               u.email AS user_email,
               u.phone AS user_phone,
               sec.nome AS secretaria_nome,
               sec.slug AS secretaria_slug
        FROM service_requests sr
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
        INNER JOIN users u ON u.id = sr.user_id
    ';
    $params = [];
    $conditions = [];

    if (is_array($secretariaIds)) {
        if (!$secretariaIds) {
            return [];
        }
        $placeholders = [];
        foreach ($secretariaIds as $idx => $secId) {
            $key = 'sec_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$secId;
        }
        $conditions[] = 'sr.secretaria_id IN (' . implode(',', $placeholders) . ')';
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'sr.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['tipo_id'])) {
        $conditions[] = 'sr.service_type_id = :tipo_id';
        $params['tipo_id'] = (int)$filters['tipo_id'];
    }
    if (!empty($filters['subtipo_id'])) {
        $conditions[] = 'sr.service_subtype_id = :subtipo_id';
        $params['subtipo_id'] = (int)$filters['subtipo_id'];
    }
    if (!empty($filters['data_inicio'])) {
        $conditions[] = 'sr.created_at >= :data_inicio';
        $params['data_inicio'] = $filters['data_inicio'];
    }
    if (!empty($filters['data_fim'])) {
        $conditions[] = 'sr.created_at <= :data_fim';
        $params['data_fim'] = $filters['data_fim'];
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY sr.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function reassignServiceRequestSecretaria(PDO $pdo, int $id, int $secretariaId, int $userId, string $motivo): void
{
    if ($id <= 0 || $secretariaId <= 0) {
        throw new RuntimeException('Dados inválidos para reatribuição.');
    }
    if ($userId <= 0) {
        throw new RuntimeException('Usuário inválido para reatribuição.');
    }
    $motivo = trim($motivo);
    if ($motivo === '') {
        throw new RuntimeException('Motivo obrigatório.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT secretaria_id, status FROM service_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Chamado não encontrado.');
        }

        $currentSecretariaId = $row['secretaria_id'] !== null ? (int)$row['secretaria_id'] : null;
        $currentStatus = normalizeServiceStatus((string)($row['status'] ?? ''));
        if ($currentStatus === '') {
            throw new RuntimeException('Status inválido para histórico.');
        }
        if ($currentSecretariaId === $secretariaId) {
            throw new RuntimeException('Chamado já está vinculado a essa secretaria.');
        }

        $stmt = $pdo->prepare('
            UPDATE service_requests
            SET secretaria_id = :secretaria_id, atribuido_em = NOW(), updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['secretaria_id' => $secretariaId, 'id' => $id]);

        $stmt = $pdo->prepare('
            INSERT INTO chamado_secretaria_historico
                (chamado_id, secretaria_anterior_id, secretaria_nova_id, motivo, usuario_id, created_at)
            VALUES
                (:chamado_id, :secretaria_anterior_id, :secretaria_nova_id, :motivo, :usuario_id, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $id,
            'secretaria_anterior_id' => $currentSecretariaId,
            'secretaria_nova_id' => $secretariaId,
            'motivo' => $motivo,
            'usuario_id' => $userId,
        ]);

        $observation = sprintf(
            'Reatribuição de secretaria: %s -> %s. Motivo: %s',
            $currentSecretariaId !== null ? (string)$currentSecretariaId : 'nenhuma',
            (string)$secretariaId,
            $motivo
        );
        $stmt = $pdo->prepare('
            INSERT INTO chamado_historico (chamado_id, status_anterior, status_novo, usuario_id, observacao, created_at)
            VALUES (:chamado_id, :status_anterior, :status_novo, :usuario_id, :observacao, NOW())
        ');
        $stmt->execute([
            'chamado_id' => $id,
            'status_anterior' => $currentStatus,
            'status_novo' => $currentStatus,
            'usuario_id' => $userId,
            'observacao' => $observation,
        ]);

        logServiceRequestAudit($pdo, $id, $userId, 'CHAMADO_SECRETARIA_REATRIBUIDA', [
            'status' => $currentStatus,
            'secretaria_anterior_id' => $currentSecretariaId,
            'secretaria_nova_id' => $secretariaId,
            'motivo' => $motivo,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function listServiceRequestSecretariaHistory(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('
        SELECT h.id,
               h.chamado_id,
               h.secretaria_anterior_id,
               h.secretaria_nova_id,
               h.motivo,
               h.usuario_id,
               u.name AS usuario_nome,
               sa.nome AS secretaria_anterior_nome,
               sn.nome AS secretaria_nova_nome,
               h.created_at
        FROM chamado_secretaria_historico h
        LEFT JOIN users u ON u.id = h.usuario_id
        LEFT JOIN secretarias sa ON sa.id = h.secretaria_anterior_id
        LEFT JOIN secretarias sn ON sn.id = h.secretaria_nova_id
        WHERE h.chamado_id = :id
        ORDER BY h.created_at ASC, h.id ASC
    ');
    $stmt->execute(['id' => $id]);
    return $stmt->fetchAll();
}
