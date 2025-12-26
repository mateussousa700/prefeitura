<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    jsonResponse(405, ['error' => 'Metodo nao permitido.']);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(401, ['error' => 'Nao autenticado.']);
}

$userType = currentUserType();
$allowedRoles = ['gestor', 'admin', 'gestor_global'];
if (!in_array($userType, $allowedRoles, true)) {
    jsonResponse(403, ['error' => 'Acesso nao permitido.']);
}

$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo === '') {
    $uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    if ($scriptDir !== '' && strpos($uriPath, $scriptDir) === 0) {
        $pathInfo = substr($uriPath, strlen($scriptDir));
    }
}
$segments = array_values(array_filter(explode('/', trim($pathInfo, '/'))));

if ($method === 'GET') {
    $firstSegment = $segments[0] ?? '';
    if ($firstSegment === '' || $firstSegment === 'proximos') {
        $latRaw = trim((string)($_GET['lat'] ?? $_GET['latitude'] ?? ''));
        $lonRaw = trim((string)($_GET['lon'] ?? $_GET['lng'] ?? $_GET['longitude'] ?? ''));
        $radius = (int)($_GET['raio'] ?? $_GET['radius'] ?? (defined('ATIVOS_RADIUS_METERS') ? ATIVOS_RADIUS_METERS : 50));
        $limit = (int)($_GET['limit'] ?? (defined('ATIVOS_MAX_RESULTS') ? ATIVOS_MAX_RESULTS : 10));
        $tipo = trim((string)($_GET['tipo'] ?? ''));

        $latRaw = str_replace(',', '.', $latRaw);
        $lonRaw = str_replace(',', '.', $lonRaw);
        $latitude = ($latRaw !== '' && is_numeric($latRaw)) ? (float)$latRaw : null;
        $longitude = ($lonRaw !== '' && is_numeric($lonRaw)) ? (float)$lonRaw : null;

        if ($latitude === null || $longitude === null) {
            jsonResponse(422, ['error' => 'Informe latitude e longitude validas.']);
        }
        if ($latitude < -90 || $latitude > 90) {
            jsonResponse(422, ['error' => 'Latitude invalida.']);
        }
        if ($longitude < -180 || $longitude > 180) {
            jsonResponse(422, ['error' => 'Longitude invalida.']);
        }

        if ($tipo !== '') {
            if (!isValidAtivoTipo($tipo)) {
                jsonResponse(422, ['error' => 'Tipo de ativo invalido.']);
            }
            $tipo = normalizeAtivoTipo($tipo);
        } else {
            $tipo = null;
        }

        try {
            $pdo = getPDO();
            $rows = listAtivosNearby($pdo, $latitude, $longitude, $radius, $limit, $tipo);
            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int)$row['id'],
                    'tipo' => $row['tipo'],
                    'identificador_publico' => $row['identificador_publico'],
                    'latitude' => isset($row['latitude']) ? (float)$row['latitude'] : null,
                    'longitude' => isset($row['longitude']) ? (float)$row['longitude'] : null,
                    'status' => $row['status'] ?? null,
                    'distance_m' => isset($row['distance_m']) ? (float)$row['distance_m'] : null,
                    'created_at' => $row['created_at'] ?? null,
                ];
            }

            jsonResponse(200, [
                'status' => 'ok',
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Erro ao buscar ativos proximos.']);
        }
    }

    $ativoId = (int)$firstSegment;
    if ($ativoId <= 0) {
        jsonResponse(422, ['error' => 'Ativo invalido.']);
    }

    if (isset($segments[1]) && $segments[1] === 'chamados') {
        try {
            $pdo = getPDO();
            $ativo = findAtivoById($pdo, $ativoId);
            if (!$ativo) {
                jsonResponse(404, ['error' => 'Ativo nao encontrado.']);
            }
            $history = listAtivoChamadosHistory($pdo, $ativoId);
            $items = [];
            foreach ($history as $entry) {
                $items[] = [
                    'id' => isset($entry['id']) ? (int)$entry['id'] : null,
                    'chamado_id' => isset($entry['chamado_id']) ? (int)$entry['chamado_id'] : null,
                    'status' => $entry['status'] ?? null,
                    'service_name' => $entry['service_name'] ?? null,
                    'problem_type' => $entry['problem_type'] ?? null,
                    'chamado_criado_em' => $entry['chamado_criado_em'] ?? null,
                    'usuario' => [
                        'id' => isset($entry['usuario_id']) ? (int)$entry['usuario_id'] : null,
                        'nome' => $entry['usuario_nome'] ?? null,
                    ],
                    'created_at' => $entry['created_at'] ?? null,
                ];
            }

            jsonResponse(200, [
                'status' => 'ok',
                'ativo' => [
                    'id' => (int)$ativo['id'],
                    'tipo' => $ativo['tipo'],
                    'identificador_publico' => $ativo['identificador_publico'],
                    'latitude' => isset($ativo['latitude']) ? (float)$ativo['latitude'] : null,
                    'longitude' => isset($ativo['longitude']) ? (float)$ativo['longitude'] : null,
                    'status' => $ativo['status'] ?? null,
                    'created_at' => $ativo['created_at'] ?? null,
                ],
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Erro ao carregar historico do ativo.']);
        }
    }

    try {
        $pdo = getPDO();
        $ativo = findAtivoById($pdo, $ativoId);
        if (!$ativo) {
            jsonResponse(404, ['error' => 'Ativo nao encontrado.']);
        }

        jsonResponse(200, [
            'status' => 'ok',
            'ativo' => [
                'id' => (int)$ativo['id'],
                'tipo' => $ativo['tipo'],
                'identificador_publico' => $ativo['identificador_publico'],
                'latitude' => isset($ativo['latitude']) ? (float)$ativo['latitude'] : null,
                'longitude' => isset($ativo['longitude']) ? (float)$ativo['longitude'] : null,
                'status' => $ativo['status'] ?? null,
                'created_at' => $ativo['created_at'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        jsonResponse(500, ['error' => 'Erro ao carregar ativo.']);
    }
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    jsonResponse(422, ['error' => 'JSON invalido.']);
}

$tipo = normalizeAtivoTipo((string)($payload['tipo'] ?? ''));
$identificador = trim((string)($payload['identificador_publico'] ?? ''));
$status = trim((string)($payload['status'] ?? 'ATIVO'));
$latRaw = trim((string)($payload['latitude'] ?? ''));
$lonRaw = trim((string)($payload['longitude'] ?? ''));
$latRaw = str_replace(',', '.', $latRaw);
$lonRaw = str_replace(',', '.', $lonRaw);
$latitude = ($latRaw !== '' && is_numeric($latRaw)) ? (float)$latRaw : null;
$longitude = ($lonRaw !== '' && is_numeric($lonRaw)) ? (float)$lonRaw : null;
$chamadoId = (int)($payload['chamado_id'] ?? 0);

$errors = [];
if ($tipo === '' || !isValidAtivoTipo($tipo)) $errors[] = 'Tipo de ativo invalido.';
if ($identificador === '') $errors[] = 'Informe o identificador publico.';
if ($latitude === null || $longitude === null) $errors[] = 'Informe latitude e longitude validas.';
if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $errors[] = 'Latitude invalida.';
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $errors[] = 'Longitude invalida.';

if ($errors) {
    jsonResponse(422, ['error' => implode(' ', $errors)]);
}

try {
    $pdo = getPDO();
    $existing = findAtivoByIdentificador($pdo, $identificador);
    if ($existing) {
        jsonResponse(422, ['error' => 'Identificador publico ja cadastrado.']);
    }

    $pdo->beginTransaction();
    try {
        $ativoId = createAtivo($pdo, [
            'tipo' => $tipo,
            'identificador_publico' => $identificador,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => $status !== '' ? $status : 'ATIVO',
        ]);

        if ($chamadoId > 0) {
            linkChamadoToAtivo($pdo, $chamadoId, $ativoId, (int)currentUserId(), false);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    jsonResponse(201, [
        'status' => 'ok',
        'ativo_id' => $ativoId,
    ]);
} catch (RuntimeException $e) {
    jsonResponse(422, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao criar ativo.']);
}
