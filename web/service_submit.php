<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin('Faça login para enviar solicitações.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services.php');
    exit;
}

requireValidCsrfToken($_POST['csrf_token'] ?? null, 'services.php');

$userId = (int) currentUserId();
$serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
$serviceSubtypeId = (int)($_POST['service_subtype_id'] ?? 0);
$serviceName = '';
$problemType = '';
$addressText = trim($_POST['address'] ?? '');
$neighborhood = trim($_POST['neighborhood'] ?? '');
$zip = trim($_POST['zip'] ?? '');
$tempoOcorrencia = $_POST['tempo_ocorrencia'] ?? '';
$legacyDuration = $_POST['duration'] ?? '';
$legacyDurationMap = [
    'hoje' => 'MENOS_24H',
    'ultima_semana' => 'MAIS_3_DIAS',
    'ultimo_mes' => 'MAIS_3_DIAS',
    'mais_tempo' => 'MAIS_3_DIAS',
];
if ($tempoOcorrencia === '' && $legacyDuration !== '') {
    $tempoOcorrencia = $legacyDurationMap[$legacyDuration] ?? '';
}
$latitudeRaw = trim((string)($_POST['latitude'] ?? ''));
$longitudeRaw = trim((string)($_POST['longitude'] ?? ''));
$latitudeValue = $latitudeRaw !== '' ? str_replace(',', '.', $latitudeRaw) : '';
$longitudeValue = $longitudeRaw !== '' ? str_replace(',', '.', $longitudeRaw) : '';
$latitude = ($latitudeValue !== '' && is_numeric($latitudeValue)) ? (float) $latitudeValue : null;
$longitude = ($longitudeValue !== '' && is_numeric($longitudeValue)) ? (float) $longitudeValue : null;
$duplicateAction = trim((string)($_POST['duplicate_action'] ?? ''));
$duplicateParentId = (int)($_POST['duplicate_parent_id'] ?? 0);
$duplicateReason = trim((string)($_POST['duplicate_reason'] ?? ''));

$tempoLabels = [
    'MENOS_24H' => 'Menos de 24h',
    'ENTRE_1_E_3_DIAS' => 'Entre 1 e 3 dias',
    'MAIS_3_DIAS' => 'Mais de 3 dias',
    'RECORRENTE' => 'Recorrente',
];
$allowedTempos = array_keys($tempoLabels);
$priorityByTempo = [
    'MENOS_24H' => 'BAIXA',
    'ENTRE_1_E_3_DIAS' => 'MEDIA',
    'MAIS_3_DIAS' => 'ALTA',
    'RECORRENTE' => 'CRITICA',
];
$slaMultipliers = [
    'MENOS_24H' => 1.1,
    'ENTRE_1_E_3_DIAS' => 1.0,
    'MAIS_3_DIAS' => 0.8,
    'RECORRENTE' => 0.6,
];

$errors = [];
$secretariaId = null;
if ($serviceTypeId <= 0) $errors[] = 'Selecione o tipo de chamado.';
if ($serviceSubtypeId <= 0) $errors[] = 'Selecione o subtipo do chamado.';
if ($addressText === '') $errors[] = 'Informe o endereço.';
if ($neighborhood === '') $errors[] = 'Selecione o bairro.';
$zipDigits = normalizeDigits($zip);
if ($zipDigits === '' || strlen($zipDigits) !== 8) $errors[] = 'CEP inválido.';
if (!in_array($tempoOcorrencia, $allowedTempos, true)) $errors[] = 'Selecione o tempo de ocorrência.';
if ($latitude === null || $longitude === null) $errors[] = 'Informe latitude e longitude válidas.';
if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $errors[] = 'Latitude inválida.';
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $errors[] = 'Longitude inválida.';

$evidencePaths = [];
$evidenceLinks = [];

try {
    if ($errors) {
        flash('danger', implode(' ', $errors));
        header('Location: services.php');
        exit;
    }

    $pdo = getPDO();

    $subtype = findServiceSubtypeWithType($pdo, $serviceSubtypeId);
    if (!$subtype || (int)$subtype['type_id'] !== $serviceTypeId) {
        $errors[] = 'Subtipo inválido para o tipo selecionado.';
    } else {
        $typeActive = (int)$subtype['type_active'] === 1;
        $subtypeActive = (int)$subtype['subtype_active'] === 1;
        $secretariaId = isset($subtype['secretaria_id']) ? (int)$subtype['secretaria_id'] : null;
        $secretariaActive = (int)($subtype['secretaria_ativa'] ?? 0) === 1;
        if (!$typeActive || !$subtypeActive) {
            $errors[] = 'Tipo ou subtipo indisponível no momento.';
        }
        $secretariaError = secretariaLinkError($secretariaId, $secretariaActive);
        if ($secretariaError !== null) {
            $errors[] = $secretariaError;
        }
        $serviceName = (string)$subtype['type_name'];
        $problemType = (string)$subtype['subtype_name'];
    }

    if ($errors) {
        flash('danger', implode(' ', $errors));
        header('Location: services.php');
        exit;
    }

    $duplicateRadius = defined('DUPLICATE_RADIUS_METERS') ? (int) DUPLICATE_RADIUS_METERS : 50;
    $duplicateDays = defined('DUPLICATE_DAYS_WINDOW') ? (int) DUPLICATE_DAYS_WINDOW : 3;
    $duplicateLimit = defined('DUPLICATE_MAX_RESULTS') ? (int) DUPLICATE_MAX_RESULTS : 5;

    $duplicates = findDuplicateServiceRequests(
        $pdo,
        $serviceSubtypeId,
        (float)$latitude,
        (float)$longitude,
        $duplicateDays,
        $duplicateRadius,
        $duplicateLimit
    );

    if ($duplicates) {
        $duplicateIds = array_map(static function (array $row): int {
            return (int)$row['id'];
        }, $duplicates);
        $selectedId = $duplicateParentId > 0 ? $duplicateParentId : ($duplicateIds[0] ?? 0);

        if ($duplicateAction === 'support') {
            if (!$selectedId || !in_array($selectedId, $duplicateIds, true)) {
                flash('danger', 'Selecione um chamado existente para apoiar.');
                header('Location: services.php');
                exit;
            }
            registerServiceRequestSupport($pdo, $selectedId, $userId);
            flash('success', 'Apoio registrado. Obrigado por fortalecer este chamado.');
            header('Location: requests.php');
            exit;
        }

        if ($duplicateAction === 'create_new') {
            if (!$selectedId || !in_array($selectedId, $duplicateIds, true)) {
                flash('danger', 'Selecione um chamado existente para justificar a duplicidade.');
                header('Location: services.php');
                exit;
            }
            if (strlen($duplicateReason) < 10) {
                flash('danger', 'Informe uma justificativa com pelo menos 10 caracteres.');
                header('Location: services.php');
                exit;
            }
            $duplicateParentId = $selectedId;
        } else {
            $_SESSION['duplicate_context'] = [
                'duplicates' => array_map(static function (array $row): array {
                    return [
                        'id' => (int)$row['id'],
                        'status' => $row['status'] ?? null,
                        'created_at' => $row['created_at'] ?? null,
                        'service_name' => $row['service_name'] ?? null,
                        'problem_type' => $row['problem_type'] ?? null,
                        'address' => $row['address'] ?? null,
                        'neighborhood' => $row['neighborhood'] ?? null,
                        'zip' => $row['zip'] ?? null,
                        'secretaria_nome' => $row['secretaria_nome'] ?? null,
                        'distance_m' => isset($row['distance_m']) ? (float)$row['distance_m'] : null,
                    ];
                }, $duplicates),
                'payload' => [
                    'service_type_id' => $serviceTypeId,
                    'service_subtype_id' => $serviceSubtypeId,
                    'address' => $addressText,
                    'neighborhood' => $neighborhood,
                    'zip' => $zip,
                    'tempo_ocorrencia' => $tempoOcorrencia,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ];
            flash('warning', 'Encontramos chamados próximos. Você pode apoiar um existente ou justificar um novo.');
            header('Location: services.php');
            exit;
        }
    }
    if (!$duplicates) {
        $duplicateParentId = 0;
        $duplicateAction = '';
    }

    $priority = $priorityByTempo[$tempoOcorrencia] ?? 'MEDIA';
    $baseHours = (int)($subtype['subtype_sla_hours'] ?? 0);
    if ($baseHours <= 0) {
        if (defined('SLA_DEFAULT_HOURS') && (int)SLA_DEFAULT_HOURS > 0) {
            $baseHours = (int) SLA_DEFAULT_HOURS;
        } elseif (defined('SLA_BASE_HOURS') && (int)SLA_BASE_HOURS > 0) {
            $baseHours = (int) SLA_BASE_HOURS;
        } else {
            $baseHours = 72;
        }
    }
    $multiplier = $slaMultipliers[$tempoOcorrencia] ?? 1.0;
    $hours = max(1, (int) round($baseHours * $multiplier));
    $slaDueAt = (new DateTimeImmutable('now'))->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
    $tempoLabel = $tempoLabels[$tempoOcorrencia] ?? $tempoOcorrencia;

    // Upload de fotos (apenas se o chamado for criado)
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

    // Dados do usuário para notificação
    $user = findUserContactById($pdo, $userId);

    $cepFormatted = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);
    $addressFull = formatServiceAddress($addressText, $neighborhood, $cepFormatted);

    $newRequestId = createServiceRequest($pdo, [
        'user_id' => $userId,
        'service_type_id' => $serviceTypeId,
        'service_subtype_id' => $serviceSubtypeId,
        'secretaria_id' => $secretariaId,
        'chamado_pai_id' => $duplicateParentId > 0 ? $duplicateParentId : null,
        'contador_apoios' => 0,
        'service_name' => $serviceName,
        'problem_type' => $problemType,
        'address' => $addressText,
        'neighborhood' => $neighborhood,
        'zip' => $cepFormatted,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'tempo_ocorrencia' => $tempoOcorrencia,
        'priority' => $priority,
        'sla_due_at' => $slaDueAt,
        'evidence_files' => $evidencePaths ? json_encode($evidencePaths, JSON_UNESCAPED_SLASHES) : null,
    ]);

    if ($duplicateAction === 'create_new' && $duplicateParentId > 0 && $duplicateReason !== '') {
        registerDuplicateJustification($pdo, $newRequestId, $userId, $duplicateParentId, $duplicateReason);
    }

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
  <li><strong>Subtipo do chamado:</strong> {$problemType}</li>
  <li><strong>Endereço:</strong> {$addressFull}</li>
  <li><strong>Quando começou:</strong> {$tempoLabel}</li>
</ul>
{$imageHtml}
<p>Em breve entraremos em contato com atualizações.</p>
HTML;
        sendEmail($user['email'], 'Recebemos sua solicitação', $emailBody, true);
    }

    if ($user && !empty($user['phone'])) {
        $caption = "Recebemos sua solicitação de {$serviceName}. Subtipo: {$problemType}. Endereço: {$addressFull}.";
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
