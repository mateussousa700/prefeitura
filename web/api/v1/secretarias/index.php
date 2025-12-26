<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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

if (!isset($_SESSION['user_id'])) {
    jsonResponse(401, ['error' => 'Não autenticado.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo === '') {
    $uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    if ($scriptDir !== '' && strpos($uriPath, $scriptDir) === 0) {
        $pathInfo = substr($uriPath, strlen($scriptDir));
    }
}
$id = 0;
if ($pathInfo !== '') {
    $id = (int)trim($pathInfo, '/');
}

try {
    $pdo = getPDO();

    if ($method === 'GET') {
        if ($id > 0) {
            jsonResponse(404, ['error' => 'Rota inválida.']);
        }
        $items = listActiveSecretarias($pdo);
        jsonResponse(200, ['status' => 'ok', 'items' => $items]);
    }

    if (!in_array(currentUserType(), ['admin'], true)) {
        jsonResponse(403, ['error' => 'Apenas admin pode executar esta ação.']);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        jsonResponse(422, ['error' => 'JSON inválido.']);
    }

    $nome = trim((string)($payload['nome'] ?? ''));
    $slug = trim((string)($payload['slug'] ?? ''));
    $ativa = isset($payload['ativa']) ? (bool)$payload['ativa'] : true;

    if ($slug === '' && $nome !== '') {
        $slug = slugify($nome);
    } else {
        $slug = slugify($slug);
    }

    if ($nome === '' || strlen($nome) < 3) {
        jsonResponse(422, ['error' => 'Informe um nome válido.']);
    }
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        jsonResponse(422, ['error' => 'Informe um slug válido.']);
    }

    if ($method === 'POST') {
        if (secretariaSlugExists($pdo, $slug)) {
            jsonResponse(409, ['error' => 'Slug já utilizado.']);
        }
        $newId = createSecretaria($pdo, $nome, $slug, $ativa);
        $secretaria = findSecretariaById($pdo, $newId);
        jsonResponse(201, ['status' => 'ok', 'secretaria' => $secretaria]);
    }

    if ($method === 'PUT') {
        if ($id <= 0) {
            jsonResponse(422, ['error' => 'Informe um ID válido.']);
        }
        if (secretariaSlugExists($pdo, $slug, $id)) {
            jsonResponse(409, ['error' => 'Slug já utilizado.']);
        }
        updateSecretaria($pdo, $id, $nome, $slug, $ativa);
        $secretaria = findSecretariaById($pdo, $id);
        if (!$secretaria) {
            jsonResponse(404, ['error' => 'Secretaria não encontrada.']);
        }
        jsonResponse(200, ['status' => 'ok', 'secretaria' => $secretaria]);
    }

    jsonResponse(405, ['error' => 'Método não permitido.']);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao processar solicitação.']);
}
