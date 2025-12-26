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
$closedStatuses = ['RESOLVIDO', 'ENCERRADO'];
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
        SELECT sr.secretaria_id,
               sec.nome,
               sec.slug,
               sec.ativa,
               sr.status,
               COUNT(*) AS total
        FROM service_requests sr
        LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
        WHERE 1 = 1
    ';
    $sql .= buildSecretariaFilter($secretariaIds, $params);
    $sql .= ' GROUP BY sr.secretaria_id, sec.nome, sec.slug, sec.ativa, sr.status';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $itemsBySecretaria = [];
    foreach ($rows as $row) {
        $secId = $row['secretaria_id'] !== null ? (int)$row['secretaria_id'] : 0;
        if (!isset($itemsBySecretaria[$secId])) {
            $itemsBySecretaria[$secId] = [
                'secretaria' => [
                    'id' => $secId ?: null,
                    'nome' => $row['nome'] ?? null,
                    'slug' => $row['slug'] ?? null,
                    'ativa' => isset($row['ativa']) ? (int)$row['ativa'] : null,
                ],
                'total' => 0,
                'abertos' => 0,
                'status' => array_fill_keys(array_keys($statusLabels), 0),
                'sla' => [
                    'dentro' => 0,
                    'vencido' => 0,
                    'total' => 0,
                    'dentro_percent' => 0,
                    'vencido_percent' => 0,
                ],
                'avg_resolution_hours' => null,
                'avg_resolution_label' => null,
            ];
        }
        $status = (string)$row['status'];
        $count = (int)$row['total'];
        if (array_key_exists($status, $statusLabels)) {
            $itemsBySecretaria[$secId]['status'][$status] += $count;
        }
        $itemsBySecretaria[$secId]['total'] += $count;
        if (in_array($status, $openStatuses, true)) {
            $itemsBySecretaria[$secId]['abertos'] += $count;
        }
    }

    if (!$itemsBySecretaria) {
        jsonResponse(200, ['status' => 'ok', 'items' => []]);
    }

    $slaParams = [];
    $slaSql = '
        SELECT sr.secretaria_id,
               SUM(CASE WHEN sr.sla_due_at >= NOW() THEN 1 ELSE 0 END) AS dentro,
               SUM(CASE WHEN sr.sla_due_at < NOW() THEN 1 ELSE 0 END) AS vencido
        FROM service_requests sr
        WHERE sr.status IN ("' . implode('","', $openStatuses) . '")
          AND sr.sla_due_at IS NOT NULL
    ';
    $slaSql .= buildSecretariaFilter($secretariaIds, $slaParams);
    $slaSql .= ' GROUP BY sr.secretaria_id';
    $stmt = $pdo->prepare($slaSql);
    $stmt->execute($slaParams);
    $slaRows = $stmt->fetchAll();
    foreach ($slaRows as $row) {
        $secId = $row['secretaria_id'] !== null ? (int)$row['secretaria_id'] : 0;
        if (!isset($itemsBySecretaria[$secId])) {
            continue;
        }
        $dentro = isset($row['dentro']) ? (int)$row['dentro'] : 0;
        $vencido = isset($row['vencido']) ? (int)$row['vencido'] : 0;
        $total = $dentro + $vencido;
        $itemsBySecretaria[$secId]['sla'] = [
            'dentro' => $dentro,
            'vencido' => $vencido,
            'total' => $total,
            'dentro_percent' => $total > 0 ? round(($dentro / $total) * 100, 1) : 0,
            'vencido_percent' => $total > 0 ? round(($vencido / $total) * 100, 1) : 0,
        ];
    }

    $avgParams = [];
    $avgSql = '
        SELECT sr.secretaria_id,
               AVG(TIMESTAMPDIFF(MINUTE, sr.created_at, sr.updated_at)) AS avg_minutes
        FROM service_requests sr
        WHERE sr.status IN ("' . implode('","', $closedStatuses) . '")
    ';
    $avgSql .= buildSecretariaFilter($secretariaIds, $avgParams);
    $avgSql .= ' GROUP BY sr.secretaria_id';
    $stmt = $pdo->prepare($avgSql);
    $stmt->execute($avgParams);
    $avgRows = $stmt->fetchAll();
    foreach ($avgRows as $row) {
        $secId = $row['secretaria_id'] !== null ? (int)$row['secretaria_id'] : 0;
        if (!isset($itemsBySecretaria[$secId])) {
            continue;
        }
        $avgMinutes = isset($row['avg_minutes']) ? (float)$row['avg_minutes'] : 0.0;
        if ($avgMinutes > 0) {
            $hours = (int)floor($avgMinutes / 60);
            $mins = (int)round($avgMinutes % 60);
            $itemsBySecretaria[$secId]['avg_resolution_hours'] = round($avgMinutes / 60, 1);
            $itemsBySecretaria[$secId]['avg_resolution_label'] = sprintf('%dh %02dm', $hours, $mins);
        }
    }

    $items = array_values($itemsBySecretaria);
    usort($items, static function (array $a, array $b): int {
        return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
    });

    jsonResponse(200, ['status' => 'ok', 'items' => $items]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao carregar dashboard por secretaria.']);
}
