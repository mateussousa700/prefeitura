<?php
require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('danger', 'Informe um e-mail válido.');
    header('Location: forgot_password.php');
    exit;
}

function generateTemporaryPassword(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxIndex = strlen($chars) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $maxIndex)];
    }

    return $password;
}

try {
    $pdo = getPDO();
    $user = findUserByEmail($pdo, $email);

    if ($user) {
        $temporaryPassword = generateTemporaryPassword();
        $temporaryHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        $userId = (int)$user['id'];
        $previousHash = $user['password_hash'] ?? null;

        updateUserPassword($pdo, $userId, $temporaryHash);

        $name = $user['name'] ?? 'Cidadão';
        $emailSent = false;
        $whatsSent = false;

        if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $subject = 'Sua senha temporária - Prefeitura Digital';
            $body = <<<TXT
Olá, {$name}!

Sua senha temporária é: {$temporaryPassword}

Use-a para acessar o sistema e, em seguida, atualize sua senha em "Meu perfil".
Se você não solicitou, ignore esta mensagem.
TXT;
            $emailSent = sendEmail($user['email'], $subject, $body);
        }

        if (!empty($user['phone'])) {
            $phoneDigits = normalizeDigits($user['phone']);
            if ($phoneDigits !== '') {
                $whatsBody = "Olá, {$name}! Sua senha temporária é {$temporaryPassword}. Após entrar, atualize em Meu perfil.";
                $whatsSent = sendWhatsAppMessage($phoneDigits, $whatsBody);
            }
        }

        if (!$emailSent && !$whatsSent && $previousHash) {
            updateUserPassword($pdo, $userId, $previousHash);
        }
    }

    flash('success', 'Se o e-mail existir, enviamos uma senha temporária por e-mail e WhatsApp.');
    header('Location: index.php#login');
    exit;
} catch (Throwable $e) {
    flash('danger', 'Erro ao solicitar recuperação: ' . $e->getMessage());
    header('Location: forgot_password.php');
    exit;
}
