<?php
require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#register');
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$address = trim($_POST['address'] ?? '');
$neighborhood = trim($_POST['neighborhood'] ?? '');
$zip = trim($_POST['zip'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['password_confirmation'] ?? '';

$errors = [];

if ($name === '') {
    $errors[] = 'Informe o nome.';
}

$phoneDigits = normalizeDigits($phone);
if ($phoneDigits === '' || strlen($phoneDigits) < 10) {
    $errors[] = 'Telefone inválido.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-mail inválido.';
}

$cpfDigits = normalizeDigits($cpf);
if ($cpfDigits === '' || strlen($cpfDigits) !== 11) {
    $errors[] = 'CPF deve conter 11 dígitos.';
}

if ($address === '') {
    $errors[] = 'Endereço (rua e número) é obrigatório.';
}

if ($neighborhood === '') {
    $errors[] = 'Selecione um bairro.';
}

$zipDigits = normalizeDigits($zip);
if ($zipDigits === '' || strlen($zipDigits) !== 8) {
    $errors[] = 'CEP deve conter 8 dígitos.';
}

if (strlen($password) < 8) {
    $errors[] = 'Senha deve ter pelo menos 8 caracteres.';
}

if ($password !== $passwordConfirmation) {
    $errors[] = 'As senhas não conferem.';
}

if ($errors) {
    flash('danger', implode(' ', $errors));
    header('Location: index.php#register');
    exit;
}

try {
    $pdo = getPDO();

    $existing = findUserByEmailOrCpf($pdo, $email, $cpfDigits);
    if ($existing) {
        flash('danger', 'E-mail ou CPF já cadastrado.');
        header('Location: index.php#register');
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $cepFormatted = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);

    createUser($pdo, [
        'name' => $name,
        'phone' => $phoneDigits,
        'email' => $email,
        'cpf' => $cpfDigits,
        'address' => $address,
        'neighborhood' => $neighborhood,
        'zip' => $cepFormatted,
        'user_type' => 'populacao',
        'password_hash' => $passwordHash,
        'token' => $token,
    ]);

    $verificationLink = buildVerificationLink($token);

    $emailSent = sendConfirmationEmail($email, $name, $verificationLink);
    $whatsSent = sendWhatsAppConfirmation($phoneDigits, $name, $verificationLink);

    $channels = [];
    if ($emailSent) {
        $channels[] = 'e-mail';
    }
    if ($whatsSent) {
        $channels[] = 'WhatsApp';
    }

    $channelText = $channels ? 'Confirme pelo ' . implode(' e ', $channels) . '.' : 'Configure os envios para confirmar sua conta.';

    flash('success', 'Cadastro criado com sucesso! ' . $channelText);
    header('Location: index.php#login');
    exit;
} catch (Throwable $e) {
    flash('danger', 'Erro ao salvar cadastro: ' . $e->getMessage());
    header('Location: index.php#register');
    exit;
}
