<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repositories/users.php';
require_once __DIR__ . '/repositories/service_requests.php';
