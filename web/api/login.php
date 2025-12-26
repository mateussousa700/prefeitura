<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$email = trim($payload['email'] ?? '');
$password = $payload['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Informe e-mail e senha válidos.']);
    exit;
}

try {
    $pdo = getPDO();
    $user = findUserByEmail($pdo, $email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciais inválidas.']);
        exit;
    }

    $verified = ($user['email_verified_at'] !== null) || ($user['whatsapp_verified_at'] !== null);
    $pendingVerification = isset($user['verification_token']) && $user['verification_token'] !== null && $user['verification_token'] !== '';
    if (!$verified && $pendingVerification) {
        $userType = $user['user_type'] ?? 'populacao';
        $isInternal = in_array($userType, ['admin', 'gestor', 'gestor_global'], true);
        if ($isInternal || userHasServiceRequests($pdo, (int)$user['id'])) {
            verifyUserById($pdo, (int)$user['id']);
            $verified = true;
        }
    }
    if (!$verified && $pendingVerification) {
        http_response_code(403);
        echo json_encode(['error' => 'Conta não verificada. Confirme e-mail ou WhatsApp.']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'verified' => $verified,
        ],
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao validar login.']);
    exit;
}
