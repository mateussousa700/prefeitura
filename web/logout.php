<?php
require __DIR__ . '/config.php';

// Finaliza sessão e redireciona para o login.
session_unset();
session_destroy();
session_start();
flash('success', 'Sessão encerrada com sucesso.');
header('Location: index.php#login');
exit;
