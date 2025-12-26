<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repositories/users.php';
require_once __DIR__ . '/repositories/service_catalog.php';
require_once __DIR__ . '/repositories/service_requests.php';
require_once __DIR__ . '/repositories/secretarias.php';
require_once __DIR__ . '/repositories/usuario_secretarias.php';
require_once __DIR__ . '/repositories/notificacoes.php';
require_once __DIR__ . '/repositories/ativos.php';
