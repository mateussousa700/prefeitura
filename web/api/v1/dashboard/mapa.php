<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function buildSecretariaFilter(?array $ids, array &$params, string $alias = 'sr'): string
{
    if (!$ids) {
        return '';
    }
    $placeholders = [];
    foreach ($ids as $idx => $id) {
        $key = 'sec_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = (int)$id;
    }
    return ' AND ' . $alias . '.secretaria_id IN (' . implode(',', $placeholders) . ')';
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['error' => 'Método não permitido.']);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(401, ['error' => 'Não autenticado.']);
}

$userType = currentUserType();
if (!in_array($userType, ['gestor', 'admin'], true)) {
    jsonResponse(403, ['error' => 'Acesso não permitido.']);
}

$openStatuses = ['RECEBIDO', 'EM_ANALISE', 'ENCAMINHADO', 'EM_EXECUCAO'];
$statusLabels = listServiceStatusOptions();

try {
    $pdo = getPDO();
    $secretariaIds = null;
    if ($userType !== 'admin') {
        $secretariaIds = listUserSecretariaIds($pdo, (int)currentUserId());
        if (!$secretariaIds) {
            jsonResponse(200, ['status' => 'ok', 'items' => []]);
        }
    }

    $params = [];
    $sql = '
        SELECT sr.id,
               sr.latitude,
               sr.longitude,
               sr.status,
               sr.created_at,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sr.secretaria_id,
               sec.nome AS secretaria_nome,
               sec.slug AS secretaria_slug
        FROM service_requests sr
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
        WHERE sr.status IN ("' . implode('","', $openStatuses) . '")
          AND sr.latitude IS NOT NULL
          AND sr.longitude IS NOT NULL
          AND sr.latitude <> 0
          AND sr.longitude <> 0
    ';
    $sql .= buildSecretariaFilter($secretariaIds, $params);
    $sql .= ' ORDER BY sr.created_at DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $status = (string)$row['status'];
        $items[] = [
            'id' => (int)$row['id'],
            'latitude' => isset($row['latitude']) ? (float)$row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float)$row['longitude'] : null,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? $status,
            'service_name' => $row['service_name'] ?? null,
            'problem_type' => $row['problem_type'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'secretaria' => [
                'id' => isset($row['secretaria_id']) ? (int)$row['secretaria_id'] : null,
                'nome' => $row['secretaria_nome'] ?? null,
                'slug' => $row['secretaria_slug'] ?? null,
            ],
        ];
    }

    jsonResponse(200, ['status' => 'ok', 'items' => $items]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao carregar mapa de chamados.']);
}
