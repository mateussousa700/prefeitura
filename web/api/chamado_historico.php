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

    if (!in_array($userType, ['gestor', 'admin'], true)) {
        $ownerId = findServiceRequestOwner($pdo, $chamadoId);
        if ($ownerId === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Chamado não encontrado.']);
            exit;
        }
        if ($ownerId !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso não permitido.']);
            exit;
        }
    }

    $history = listServiceRequestHistory($pdo, $chamadoId);

    echo json_encode([
        'status' => 'ok',
        'chamado_id' => $chamadoId,
        'items' => $history,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar histórico.']);
    exit;
}
