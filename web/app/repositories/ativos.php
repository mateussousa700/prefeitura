<?php
declare(strict_types=1);

function listAtivoTipos(): array
{
    return ['poste', 'via', 'arvore', 'lixeira'];
}

function normalizeAtivoTipo(string $tipo): string
{
    return strtolower(trim($tipo));
}

function isValidAtivoTipo(string $tipo): bool
{
    return in_array(normalizeAtivoTipo($tipo), listAtivoTipos(), true);
}

function findAtivoById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, tipo, identificador_publico, latitude, longitude, status, created_at
        FROM ativos
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function findAtivoByIdentificador(PDO $pdo, string $identificador): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, tipo, identificador_publico, latitude, longitude, status, created_at
        FROM ativos
        WHERE identificador_publico = :identificador
        LIMIT 1
    ');
    $stmt->execute(['identificador' => $identificador]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function createAtivo(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO ativos (tipo, identificador_publico, latitude, longitude, status, created_at)
        VALUES (:tipo, :identificador_publico, :latitude, :longitude, :status, NOW())
    ');
    $stmt->execute([
        'tipo' => $data['tipo'],
        'identificador_publico' => $data['identificador_publico'],
        'latitude' => $data['latitude'],
        'longitude' => $data['longitude'],
        'status' => $data['status'],
    ]);

    return (int)$pdo->lastInsertId();
}

function listAtivosNearby(PDO $pdo, float $latitude, float $longitude, int $radiusMeters, int $limit = 10, ?string $tipo = null): array
{
    $radiusMeters = max(1, $radiusMeters);
    $limit = max(1, min(50, $limit));

    $latDelta = $radiusMeters / 111320;
    $cosLat = cos(deg2rad($latitude));
    $lonDelta = $cosLat > 0 ? $radiusMeters / (111320 * $cosLat) : $latDelta;

    $minLat = $latitude - $latDelta;
    $maxLat = $latitude + $latDelta;
    $minLon = $longitude - $lonDelta;
    $maxLon = $longitude + $lonDelta;

    $sql = '
        SELECT a.id,
               a.tipo,
               a.identificador_publico,
               a.latitude,
               a.longitude,
               a.status,
               a.created_at,
               (6371000 * 2 * ASIN(SQRT(
                   POWER(SIN(RADIANS(:lat - a.latitude) / 2), 2) +
                   COS(RADIANS(:lat)) * COS(RADIANS(a.latitude)) *
                   POWER(SIN(RADIANS(:lon - a.longitude) / 2), 2)
               ))) AS distance_m
        FROM ativos a
        WHERE a.latitude BETWEEN :min_lat AND :max_lat
          AND a.longitude BETWEEN :min_lon AND :max_lon
    ';
    $params = [
        'lat' => $latitude,
        'lon' => $longitude,
        'min_lat' => $minLat,
        'max_lat' => $maxLat,
        'min_lon' => $minLon,
        'max_lon' => $maxLon,
        'radius' => $radiusMeters,
    ];

    if ($tipo !== null && $tipo !== '') {
        $sql .= ' AND a.tipo = :tipo';
        $params['tipo'] = $tipo;
    }

    $sql .= ' HAVING distance_m <= :radius ORDER BY distance_m ASC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function linkChamadoToAtivo(PDO $pdo, int $chamadoId, int $ativoId, int $usuarioId, bool $useTransaction = true): void
{
    if ($chamadoId <= 0 || $ativoId <= 0 || $usuarioId <= 0) {
        throw new RuntimeException('Dados inválidos para vincular ativo.');
    }

    if ($useTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare('SELECT ativo_id FROM service_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $chamadoId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Chamado não encontrado.');
        }

        $currentAtivo = $row['ativo_id'] !== null ? (int)$row['ativo_id'] : null;
        if ($currentAtivo === $ativoId) {
            throw new RuntimeException('Chamado já está vinculado a este ativo.');
        }

        $stmt = $pdo->prepare('
            UPDATE service_requests
            SET ativo_id = :ativo_id, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['ativo_id' => $ativoId, 'id' => $chamadoId]);

        $stmt = $pdo->prepare('
            INSERT INTO ativo_chamado_historico (ativo_id, chamado_id, usuario_id, created_at)
            VALUES (:ativo_id, :chamado_id, :usuario_id, NOW())
        ');
        $stmt->execute([
            'ativo_id' => $ativoId,
            'chamado_id' => $chamadoId,
            'usuario_id' => $usuarioId,
        ]);

        if ($useTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($useTransaction) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function listAtivoChamadosHistory(PDO $pdo, int $ativoId): array
{
    $stmt = $pdo->prepare('
        SELECT h.id,
               h.ativo_id,
               h.chamado_id,
               h.usuario_id,
               u.name AS usuario_nome,
               sr.status,
               sr.created_at AS chamado_criado_em,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               h.created_at
        FROM ativo_chamado_historico h
        LEFT JOIN users u ON u.id = h.usuario_id
        LEFT JOIN service_requests sr ON sr.id = h.chamado_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        WHERE h.ativo_id = :id
        ORDER BY h.created_at DESC, h.id DESC
    ');
    $stmt->execute(['id' => $ativoId]);
    return $stmt->fetchAll();
}
