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

try {
    $pdo = getPDO();
    $secretariaIds = null;
    if ($userType !== 'admin') {
        $secretariaIds = listUserSecretariaIds($pdo, (int)currentUserId());
        if (!$secretariaIds) {
            jsonResponse(200, [
                'status' => 'ok',
                'totals' => ['open' => 0, 'total' => 0],
                'status_breakdown' => [],
                'sla' => ['dentro' => 0, 'vencido' => 0, 'total' => 0, 'dentro_percent' => 0, 'vencido_percent' => 0],
                'avg_resolution_hours' => null,
                'avg_resolution_label' => null,
                'top_subtypes' => [],
            ]);
        }
    }

    $params = [];
    $statusSql = '
        SELECT sr.status, COUNT(*) AS total
        FROM service_requests sr
        WHERE 1 = 1
    ';
    $statusSql .= buildSecretariaFilter($secretariaIds, $params);
    $statusSql .= ' GROUP BY sr.status';
    $stmt = $pdo->prepare($statusSql);
    $stmt->execute($params);
    $statusRows = $stmt->fetchAll();

    $statusCounts = [];
    foreach ($statusRows as $row) {
        $statusCounts[(string)$row['status']] = (int)$row['total'];
    }

    $totalRequests = array_sum($statusCounts);
    $totalOpen = 0;
    foreach ($openStatuses as $code) {
        $totalOpen += $statusCounts[$code] ?? 0;
    }

    $statusLabels = listServiceStatusOptions();
    $statusItems = [];
    foreach ($statusLabels as $code => $label) {
        $count = $statusCounts[$code] ?? 0;
        $percent = $totalRequests > 0 ? round(($count / $totalRequests) * 100, 1) : 0;
        $statusItems[] = [
            'code' => $code,
            'label' => $label,
            'total' => $count,
            'percent' => $percent,
        ];
    }

    $slaParams = [];
    $slaSql = '
        SELECT
            SUM(CASE WHEN sr.sla_due_at >= NOW() THEN 1 ELSE 0 END) AS dentro,
            SUM(CASE WHEN sr.sla_due_at < NOW() THEN 1 ELSE 0 END) AS vencido
        FROM service_requests sr
        WHERE sr.status IN ("' . implode('","', $openStatuses) . '")
          AND sr.sla_due_at IS NOT NULL
    ';
    $slaSql .= buildSecretariaFilter($secretariaIds, $slaParams);
    $stmt = $pdo->prepare($slaSql);
    $stmt->execute($slaParams);
    $slaRow = $stmt->fetch() ?: [];
    $slaDentro = isset($slaRow['dentro']) ? (int)$slaRow['dentro'] : 0;
    $slaVencido = isset($slaRow['vencido']) ? (int)$slaRow['vencido'] : 0;
    $slaTotal = $slaDentro + $slaVencido;
    $slaDentroPercent = $slaTotal > 0 ? round(($slaDentro / $slaTotal) * 100, 1) : 0;
    $slaVencidoPercent = $slaTotal > 0 ? round(($slaVencido / $slaTotal) * 100, 1) : 0;

    $avgParams = [];
    $avgSql = '
        SELECT AVG(TIMESTAMPDIFF(MINUTE, sr.created_at, sr.updated_at)) AS avg_minutes
        FROM service_requests sr
        WHERE sr.status IN ("' . implode('","', $closedStatuses) . '")
    ';
    $avgSql .= buildSecretariaFilter($secretariaIds, $avgParams);
    $stmt = $pdo->prepare($avgSql);
    $stmt->execute($avgParams);
    $avgRow = $stmt->fetch() ?: [];
    $avgMinutes = isset($avgRow['avg_minutes']) ? (float)$avgRow['avg_minutes'] : 0.0;
    $avgHours = $avgMinutes > 0 ? round($avgMinutes / 60, 1) : null;
    $avgLabel = null;
    if ($avgMinutes > 0) {
        $hours = (int)floor($avgMinutes / 60);
        $mins = (int)round($avgMinutes % 60);
        $avgLabel = sprintf('%dh %02dm', $hours, $mins);
    }

    $topParams = [];
    $topSql = '
        SELECT sr.service_subtype_id,
               COALESCE(ss.name, sr.problem_type) AS name,
               COUNT(*) AS total
        FROM service_requests sr
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        WHERE 1 = 1
    ';
    $topSql .= buildSecretariaFilter($secretariaIds, $topParams);
    $topSql .= ' GROUP BY sr.service_subtype_id, name ORDER BY total DESC LIMIT 5';
    $stmt = $pdo->prepare($topSql);
    $stmt->execute($topParams);
    $topRows = $stmt->fetchAll();
    $topItems = [];
    foreach ($topRows as $row) {
        $count = (int)$row['total'];
        $percent = $totalRequests > 0 ? round(($count / $totalRequests) * 100, 1) : 0;
        $topItems[] = [
            'id' => $row['service_subtype_id'] !== null ? (int)$row['service_subtype_id'] : null,
            'name' => $row['name'] ?? null,
            'total' => $count,
            'percent' => $percent,
        ];
    }

    jsonResponse(200, [
        'status' => 'ok',
        'totals' => [
            'open' => $totalOpen,
            'total' => $totalRequests,
        ],
        'status_breakdown' => $statusItems,
        'sla' => [
            'dentro' => $slaDentro,
            'vencido' => $slaVencido,
            'total' => $slaTotal,
            'dentro_percent' => $slaDentroPercent,
            'vencido_percent' => $slaVencidoPercent,
        ],
        'avg_resolution_hours' => $avgHours,
        'avg_resolution_label' => $avgLabel,
        'top_subtypes' => $topItems,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao carregar dashboard.']);
}
