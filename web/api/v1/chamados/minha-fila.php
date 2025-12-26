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
$allowedRoles = ['gestor', 'admin', 'gestor_global'];
if (!in_array($userType, $allowedRoles, true)) {
    jsonResponse(403, ['error' => 'Acesso não permitido.']);
}

$statusRaw = trim((string)($_GET['status'] ?? ''));
$tipoId = (int)($_GET['tipo'] ?? $_GET['tipo_id'] ?? 0);
$subtipoId = (int)($_GET['subtipo'] ?? $_GET['subtipo_id'] ?? 0);
$dataParam = trim((string)($_GET['data'] ?? ''));
$dataInicioParam = trim((string)($_GET['data_inicio'] ?? ''));
$dataFimParam = trim((string)($_GET['data_fim'] ?? ''));
$slaStatus = strtoupper(trim((string)($_GET['sla_status'] ?? '')));

$filters = [];
if ($statusRaw !== '') {
    $status = normalizeServiceStatus($statusRaw);
    $validStatuses = listServiceStatusOptions();
    if (!array_key_exists($status, $validStatuses)) {
        jsonResponse(422, ['error' => 'Status inválido.']);
    }
    $filters['status'] = $status;
}
if ($tipoId > 0) {
    $filters['tipo_id'] = $tipoId;
}
if ($subtipoId > 0) {
    $filters['subtipo_id'] = $subtipoId;
}

try {
    if ($dataParam !== '') {
        $date = new DateTimeImmutable($dataParam . ' 00:00:00');
        $filters['data_inicio'] = $date->format('Y-m-d H:i:s');
        $filters['data_fim'] = $date->modify('+1 day -1 second')->format('Y-m-d H:i:s');
    } else {
        if ($dataInicioParam !== '') {
            $start = new DateTimeImmutable($dataInicioParam . ' 00:00:00');
            $filters['data_inicio'] = $start->format('Y-m-d H:i:s');
        }
        if ($dataFimParam !== '') {
            $end = new DateTimeImmutable($dataFimParam . ' 23:59:59');
            $filters['data_fim'] = $end->format('Y-m-d H:i:s');
        }
    }
} catch (Throwable $e) {
    jsonResponse(422, ['error' => 'Data inválida.']);
}

$slaFilter = null;
if ($slaStatus !== '') {
    $allowedSla = ['DENTRO_DO_PRAZO', 'PROXIMO_DO_VENCIMENTO', 'VENCIDO'];
    if (!in_array($slaStatus, $allowedSla, true)) {
        jsonResponse(422, ['error' => 'SLA status inválido.']);
    }
    $slaFilter = $slaStatus;
}

try {
    $pdo = getPDO();
    $userId = (int) currentUserId();
    $isGlobal = in_array($userType, ['admin', 'gestor_global'], true);
    $secretariaIds = null;

    if (!$isGlobal) {
        $secretariaIds = listUserSecretariaIds($pdo, $userId);
        if (!$secretariaIds) {
            jsonResponse(200, ['status' => 'ok', 'items' => []]);
        }
    }

    $rows = listServiceRequestsQueue($pdo, $filters, $secretariaIds);
    $items = [];

    foreach ($rows as $row) {
        $slaComputed = computeSlaStatus($row['sla_due_at'] ?? null, $row['created_at'] ?? null);
        if ($slaFilter && $slaComputed !== $slaFilter) {
            continue;
        }
        $items[] = [
            'id' => (int)$row['id'],
            'status' => $row['status'],
            'service_type_id' => isset($row['service_type_id']) ? (int)$row['service_type_id'] : null,
            'service_subtype_id' => isset($row['service_subtype_id']) ? (int)$row['service_subtype_id'] : null,
            'service_name' => $row['service_name'] ?? null,
            'problem_type' => $row['problem_type'] ?? null,
            'priority' => $row['priority'] ?? null,
            'tempo_ocorrencia' => $row['tempo_ocorrencia'] ?? null,
            'sla_due_at' => $row['sla_due_at'] ?? null,
            'sla_status' => $slaComputed,
            'created_at' => $row['created_at'] ?? null,
            'secretaria' => [
                'id' => isset($row['secretaria_id']) ? (int)$row['secretaria_id'] : null,
                'nome' => $row['secretaria_nome'] ?? null,
                'slug' => $row['secretaria_slug'] ?? null,
            ],
            'usuario' => [
                'nome' => $row['user_name'] ?? null,
                'email' => $row['user_email'] ?? null,
                'telefone' => $row['user_phone'] ?? null,
            ],
        ];
    }

    jsonResponse(200, ['status' => 'ok', 'items' => $items]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao carregar fila de chamados.']);
}
