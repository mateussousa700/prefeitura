<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin('Faça login para enviar solicitações.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services.php');
    exit;
}

$userId = (int) currentUserId();
$serviceName = trim($_POST['service_name'] ?? '');
$problemType = trim($_POST['problem_type'] ?? '');
$address = trim($_POST['address'] ?? '');
$neighborhood = trim($_POST['neighborhood'] ?? '');
$zip = trim($_POST['zip'] ?? '');
$duration = $_POST['duration'] ?? '';
$latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float) $_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null;

$allowedDurations = ['hoje', 'ultima_semana', 'ultimo_mes', 'mais_tempo'];

$errors = [];
if ($serviceName === '') $errors[] = 'Serviço é obrigatório.';
if ($problemType === '') $errors[] = 'Informe o tipo de problema.';
if ($address === '') $errors[] = 'Informe o endereço.';
if ($neighborhood === '') $errors[] = 'Selecione o bairro.';
$zipDigits = normalizeDigits($zip);
if ($zipDigits === '' || strlen($zipDigits) !== 8) $errors[] = 'CEP inválido.';
if (!in_array($duration, $allowedDurations, true)) $errors[] = 'Selecione o tempo do problema.';

$evidencePaths = [];
$evidenceLinks = [];

try {
    // Upload de fotos
    if (!empty($_FILES['evidence']['name'][0])) {
        $uploadDir = __DIR__ . '/storage/evidence';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $fileCount = count($_FILES['evidence']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $tmpName = $_FILES['evidence']['tmp_name'][$i] ?? null;
            $error = $_FILES['evidence']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            $name = $_FILES['evidence']['name'][$i] ?? 'evidence';

            if ($error !== UPLOAD_ERR_OK || !$tmpName) {
                continue;
            }

            $mime = mime_content_type($tmpName);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'], true)) {
                continue;
            }

            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $safeName = uniqid('evidence_', true) . ($ext ? '.' . $ext : '');
            $dest = $uploadDir . '/' . $safeName;
            if (move_uploaded_file($tmpName, $dest)) {
                $relativePath = 'storage/evidence/' . $safeName;
                $evidencePaths[] = $relativePath;
                $evidenceLinks[] = rtrim(BASE_URL, '/') . '/' . $relativePath;
            }
        }
    }

    if ($errors) {
        flash('danger', implode(' ', $errors));
        header('Location: services.php');
        exit;
    }

    $pdo = getPDO();

    // Dados do usuário para notificação
    $user = findUserContactById($pdo, $userId);

    $cepFormatted = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);
    $addressFull = $address;
    if ($neighborhood !== '') {
        $addressFull .= ' | Bairro: ' . $neighborhood;
    }
    if ($zipDigits !== '') {
        $addressFull .= ' | CEP: ' . $cepFormatted;
    }

    createServiceRequest($pdo, [
        'user_id' => $userId,
        'service_name' => $serviceName,
        'problem_type' => $problemType,
        'address' => $addressFull,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'duration' => $duration,
        'evidence_files' => $evidencePaths ? json_encode($evidencePaths, JSON_UNESCAPED_SLASHES) : null,
    ]);

    // Notificações
    $firstEvidence = $evidenceLinks[0] ?? null;

    if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $imageHtml = $firstEvidence
            ? '<p><strong>Evidência:</strong><br><img src="' . htmlspecialchars($firstEvidence, ENT_QUOTES) . '" alt="Evidência" style="max-width:480px;border-radius:8px;" /></p>'
            : '';

        $emailBody = <<<HTML
<p>Olá, {$user['name']}!</p>
<p>Recebemos sua solicitação de "<strong>{$serviceName}</strong>".</p>
<ul>
  <li><strong>Tipo de problema:</strong> {$problemType}</li>
  <li><strong>Endereço:</strong> {$addressFull}</li>
  <li><strong>Quando começou:</strong> {$duration}</li>
</ul>
{$imageHtml}
<p>Em breve entraremos em contato com atualizações.</p>
HTML;
        sendEmail($user['email'], 'Recebemos sua solicitação', $emailBody, true);
    }

    if ($user && !empty($user['phone'])) {
        $caption = "Recebemos sua solicitação de {$serviceName}. Problema: {$problemType}. Endereço: {$addressFull}.";
        $phoneDigits = normalizeDigits($user['phone']);
        if ($firstEvidence) {
            sendWhatsAppImage($phoneDigits, $firstEvidence, $caption);
        } else {
            sendWhatsAppMessage($phoneDigits, $caption);
        }
    }

    flash('success', 'Solicitação enviada com sucesso!');
    header('Location: services.php');
    exit;
} catch (Throwable $e) {
    flash('danger', 'Erro ao salvar solicitação: ' . $e->getMessage());
    header('Location: services.php');
    exit;
}
