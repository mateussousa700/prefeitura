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

$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo === '') {
    $uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    if ($scriptDir !== '' && strpos($uriPath, $scriptDir) === 0) {
        $pathInfo = substr($uriPath, strlen($scriptDir));
    }
}

$typeId = 0;
if ($pathInfo !== '') {
    $typeId = (int)trim($pathInfo, '/');
}

if ($typeId <= 0) {
    jsonResponse(422, ['error' => 'Informe um tipo válido.']);
}

try {
    $pdo = getPDO();
    $itemsRaw = listSubtypesByTypeWithSecretaria($pdo, $typeId, true);

    $items = [];
    foreach ($itemsRaw as $row) {
        $items[] = [
            'id' => (int)$row['id'],
            'type_id' => (int)$row['service_type_id'],
            'nome' => $row['name'],
            'sla_hours' => isset($row['sla_hours']) ? (int)$row['sla_hours'] : null,
            'secretaria' => [
                'id' => isset($row['secretaria_id']) ? (int)$row['secretaria_id'] : null,
                'nome' => $row['secretaria_nome'] ?? null,
                'slug' => $row['secretaria_slug'] ?? null,
            ],
        ];
    }

    jsonResponse(200, [
        'status' => 'ok',
        'type_id' => $typeId,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao carregar subtipos.']);
}
