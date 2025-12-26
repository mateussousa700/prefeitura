<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$chamadoId = (int)($_GET['chamado_id'] ?? 0);
if ($chamadoId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Informe um chamado válido.']);
    exit;
}

try {
    $pdo = getPDO();
    $userType = currentUserType();
    $userId = (int) currentUserId();

    $sla = findServiceRequestSla($pdo, $chamadoId);
    if (!$sla) {
        http_response_code(404);
        echo json_encode(['error' => 'Chamado não encontrado.']);
        exit;
    }

    if (!in_array($userType, ['gestor', 'admin'], true)) {
        if ((int)$sla['user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso não permitido.']);
            exit;
        }
    }

    $slaDueAt = $sla['sla_due_at'] ?? null;
    $createdAt = $sla['created_at'] ?? null;
    $status = computeSlaStatus($slaDueAt, $createdAt);

    $remainingSeconds = null;
    $remainingHours = null;
    if ($slaDueAt) {
        try {
            $now = new DateTimeImmutable('now');
            $due = new DateTimeImmutable($slaDueAt);
            $remainingSeconds = $due->getTimestamp() - $now->getTimestamp();
            $remainingHours = $remainingSeconds / 3600;
        } catch (Throwable $e) {
            $remainingSeconds = null;
            $remainingHours = null;
        }
    }

    echo json_encode([
        'status' => 'ok',
        'chamado_id' => $chamadoId,
        'sla' => [
            'sla_hours' => isset($sla['sla_hours']) ? (int)$sla['sla_hours'] : null,
            'sla_due_at' => $slaDueAt,
            'sla_status' => $status,
            'remaining_seconds' => $remainingSeconds,
            'remaining_hours' => $remainingHours,
        ],
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar SLA.']);
    exit;
}
