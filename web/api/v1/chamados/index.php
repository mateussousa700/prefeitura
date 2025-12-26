<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if (!in_array($method, ['GET', 'POST', 'PATCH'], true)) {
    jsonResponse(405, ['error' => 'Método não permitido.']);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(401, ['error' => 'Não autenticado.']);
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
    $id = isset($segments[0]) ? (int)$segments[0] : 0;
    $action = $segments[1] ?? '';

    if ($id <= 0 || $action !== 'secretaria-historico') {
        jsonResponse(404, ['error' => 'Rota inválida.']);
    }

    $userType = currentUserType();
    if (!canUserReassignSecretaria($userType)) {
        jsonResponse(403, ['error' => 'Acesso não permitido.']);
    }

    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('
            SELECT sr.secretaria_id,
                   sec.nome AS secretaria_nome,
                   sec.slug AS secretaria_slug
            FROM service_requests sr
            LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
            WHERE sr.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(404, ['error' => 'Chamado não encontrado.']);
        }

        $history = listServiceRequestSecretariaHistory($pdo, $id);
        $items = [];
        foreach ($history as $entry) {
            $items[] = [
                'id' => isset($entry['id']) ? (int)$entry['id'] : null,
                'secretaria_anterior' => [
                    'id' => $entry['secretaria_anterior_id'] !== null ? (int)$entry['secretaria_anterior_id'] : null,
                    'nome' => $entry['secretaria_anterior_nome'] ?? null,
                ],
                'secretaria_nova' => [
                    'id' => isset($entry['secretaria_nova_id']) ? (int)$entry['secretaria_nova_id'] : null,
                    'nome' => $entry['secretaria_nova_nome'] ?? null,
                ],
                'motivo' => $entry['motivo'] ?? null,
                'usuario' => [
                    'id' => isset($entry['usuario_id']) ? (int)$entry['usuario_id'] : null,
                    'nome' => $entry['usuario_nome'] ?? null,
                ],
                'created_at' => $entry['created_at'] ?? null,
            ];
        }

        jsonResponse(200, [
            'status' => 'ok',
            'chamado_id' => $id,
            'secretaria_atual' => [
                'id' => $row['secretaria_id'] !== null ? (int)$row['secretaria_id'] : null,
                'nome' => $row['secretaria_nome'] ?? null,
                'slug' => $row['secretaria_slug'] ?? null,
            ],
            'items' => $items,
        ]);
    } catch (Throwable $e) {
        jsonResponse(500, ['error' => 'Erro ao carregar histórico de secretaria.']);
    }
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    jsonResponse(422, ['error' => 'JSON inválido.']);
}

if ($method === 'PATCH') {
    $id = isset($segments[0]) ? (int)$segments[0] : 0;
    $action = $segments[1] ?? '';

    if ($id <= 0) {
        jsonResponse(404, ['error' => 'Rota inválida.']);
    }

    if ($action === 'reatribuir-secretaria') {
        $userType = currentUserType();
        if (!canUserReassignSecretaria($userType)) {
            jsonResponse(403, ['error' => 'Acesso não permitido.']);
        }

        $newSecretariaId = (int)($payload['secretaria_id'] ?? 0);
        $motivo = trim((string)($payload['motivo'] ?? ''));
        if ($newSecretariaId <= 0) {
            jsonResponse(422, ['error' => 'Informe a secretaria responsável.']);
        }
        if ($motivo === '') {
            jsonResponse(422, ['error' => 'Informe o motivo da reatribuição.']);
        }

        try {
            $pdo = getPDO();
            $secretaria = findSecretariaById($pdo, $newSecretariaId);
            if (!$secretaria) {
                jsonResponse(404, ['error' => 'Secretaria não encontrada.']);
            }
            if ((int)$secretaria['ativa'] !== 1) {
                jsonResponse(422, ['error' => 'Secretaria inativa não pode receber chamados.']);
            }

            reassignServiceRequestSecretaria($pdo, $id, $newSecretariaId, (int)currentUserId(), $motivo);

            jsonResponse(200, [
                'status' => 'ok',
                'chamado_id' => $id,
                'secretaria_id' => $newSecretariaId,
            ]);
        } catch (RuntimeException $e) {
            jsonResponse(422, ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Erro ao reatribuir chamado.']);
        }
    }

    if ($action === 'vincular-ativo') {
        $userType = currentUserType();
        $allowedRoles = ['gestor', 'admin', 'gestor_global'];
        if (!in_array($userType, $allowedRoles, true)) {
            jsonResponse(403, ['error' => 'Acesso não permitido.']);
        }

        $ativoId = (int)($payload['ativo_id'] ?? 0);
        $novoAtivo = $payload['ativo'] ?? $payload['novo_ativo'] ?? null;

        if ($ativoId <= 0 && !is_array($novoAtivo)) {
            jsonResponse(422, ['error' => 'Informe o ativo para vincular.']);
        }

        try {
            $pdo = getPDO();
            $userId = (int) currentUserId();

            if ($ativoId > 0) {
                $ativo = findAtivoById($pdo, $ativoId);
                if (!$ativo) {
                    jsonResponse(404, ['error' => 'Ativo não encontrado.']);
                }

                linkChamadoToAtivo($pdo, $id, $ativoId, $userId, true);

                jsonResponse(200, [
                    'status' => 'ok',
                    'chamado_id' => $id,
                    'ativo_id' => $ativoId,
                ]);
            }

            $tipo = normalizeAtivoTipo((string)($novoAtivo['tipo'] ?? ''));
            $identificador = trim((string)($novoAtivo['identificador_publico'] ?? ''));
            $status = trim((string)($novoAtivo['status'] ?? 'ATIVO'));
            $latRaw = trim((string)($novoAtivo['latitude'] ?? ''));
            $lonRaw = trim((string)($novoAtivo['longitude'] ?? ''));
            $latRaw = str_replace(',', '.', $latRaw);
            $lonRaw = str_replace(',', '.', $lonRaw);
            $latitude = ($latRaw !== '' && is_numeric($latRaw)) ? (float)$latRaw : null;
            $longitude = ($lonRaw !== '' && is_numeric($lonRaw)) ? (float)$lonRaw : null;

            $errors = [];
            if ($tipo === '' || !isValidAtivoTipo($tipo)) $errors[] = 'Tipo de ativo inválido.';
            if ($identificador === '') $errors[] = 'Informe o identificador público.';
            if ($latitude === null || $longitude === null) $errors[] = 'Informe latitude e longitude válidas.';
            if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $errors[] = 'Latitude inválida.';
            if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $errors[] = 'Longitude inválida.';

            if ($errors) {
                jsonResponse(422, ['error' => implode(' ', $errors)]);
            }

            if (findAtivoByIdentificador($pdo, $identificador)) {
                jsonResponse(422, ['error' => 'Identificador público já cadastrado.']);
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
                linkChamadoToAtivo($pdo, $id, $ativoId, $userId, false);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            jsonResponse(201, [
                'status' => 'ok',
                'chamado_id' => $id,
                'ativo_id' => $ativoId,
            ]);
        } catch (RuntimeException $e) {
            jsonResponse(422, ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            jsonResponse(500, ['error' => 'Erro ao vincular ativo.']);
        }
    }

    jsonResponse(404, ['error' => 'Rota inválida.']);
}

$userId = (int) currentUserId();
$serviceTypeId = (int)($payload['service_type_id'] ?? 0);
$serviceSubtypeId = (int)($payload['subtipo_id'] ?? $payload['service_subtype_id'] ?? 0);
$addressText = trim((string)($payload['address'] ?? ''));
$neighborhood = trim((string)($payload['neighborhood'] ?? ''));
$zip = trim((string)($payload['zip'] ?? ''));
$tempoOcorrencia = (string)($payload['tempo_ocorrencia'] ?? '');
$duplicateAction = trim((string)($payload['duplicate_action'] ?? ''));
$duplicateParentId = (int)($payload['duplicate_parent_id'] ?? $payload['chamado_pai_id'] ?? 0);
$duplicateReason = trim((string)($payload['duplicate_reason'] ?? ''));
$latitudeRaw = trim((string)($payload['latitude'] ?? ''));
$longitudeRaw = trim((string)($payload['longitude'] ?? ''));
$latitudeValue = $latitudeRaw !== '' ? str_replace(',', '.', $latitudeRaw) : '';
$longitudeValue = $longitudeRaw !== '' ? str_replace(',', '.', $longitudeRaw) : '';
$latitude = ($latitudeValue !== '' && is_numeric($latitudeValue)) ? (float) $latitudeValue : null;
$longitude = ($longitudeValue !== '' && is_numeric($longitudeValue)) ? (float) $longitudeValue : null;

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
if ($serviceSubtypeId <= 0) $errors[] = 'Informe o subtipo do chamado.';
if ($addressText === '') $errors[] = 'Informe o endereço.';
if ($neighborhood === '') $errors[] = 'Selecione o bairro.';
$zipDigits = normalizeDigits($zip);
if ($zipDigits === '' || strlen($zipDigits) !== 8) $errors[] = 'CEP inválido.';
if (!in_array($tempoOcorrencia, $allowedTempos, true)) $errors[] = 'Selecione o tempo de ocorrência.';
if ($latitude === null || $longitude === null) $errors[] = 'Informe latitude e longitude válidas.';
if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $errors[] = 'Latitude inválida.';
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $errors[] = 'Longitude inválida.';

if ($errors) {
    jsonResponse(422, ['error' => implode(' ', $errors)]);
}

try {
    $pdo = getPDO();
    $subtype = findServiceSubtypeWithType($pdo, $serviceSubtypeId);
    if (!$subtype) {
        jsonResponse(404, ['error' => 'Subtipo não encontrado.']);
    }
    if ($serviceTypeId > 0 && (int)$subtype['type_id'] !== $serviceTypeId) {
        jsonResponse(422, ['error' => 'Subtipo inválido para o tipo informado.']);
    }

    $typeActive = (int)$subtype['type_active'] === 1;
    $subtypeActive = (int)$subtype['subtype_active'] === 1;
    $secretariaId = isset($subtype['secretaria_id']) ? (int)$subtype['secretaria_id'] : 0;
    $secretariaActive = (int)($subtype['secretaria_ativa'] ?? 0) === 1;
    if (!$typeActive || !$subtypeActive) {
        jsonResponse(422, ['error' => 'Tipo ou subtipo indisponível.']);
    }
    $secretariaError = secretariaLinkError($secretariaId > 0 ? $secretariaId : null, $secretariaActive);
    if ($secretariaError !== null) {
        jsonResponse(422, ['error' => $secretariaError]);
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
                jsonResponse(422, ['error' => 'Selecione um chamado existente para apoiar.']);
            }
            registerServiceRequestSupport($pdo, $selectedId, $userId);
            jsonResponse(200, [
                'status' => 'ok',
                'action' => 'support',
                'chamado_id' => $selectedId,
            ]);
        }

        if ($duplicateAction === 'create_new') {
            if (!$selectedId || !in_array($selectedId, $duplicateIds, true)) {
                jsonResponse(422, ['error' => 'Selecione um chamado existente para justificar a duplicidade.']);
            }
            if (strlen($duplicateReason) < 10) {
                jsonResponse(422, ['error' => 'Informe uma justificativa com pelo menos 10 caracteres.']);
            }
            $duplicateParentId = $selectedId;
        } else {
            $duplicateItems = array_map(static function (array $row): array {
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
            }, $duplicates);

            jsonResponse(409, [
                'status' => 'duplicate_found',
                'message' => 'Encontramos chamados similares próximos.',
                'items' => $duplicateItems,
            ]);
        }
    }
    if (!$duplicates) {
        $duplicateParentId = 0;
        $duplicateAction = '';
    }

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
    $priority = $priorityByTempo[$tempoOcorrencia] ?? 'MEDIA';

    $evidencePayload = $payload['evidence_files'] ?? null;
    $evidenceFiles = [];
    if (is_array($evidencePayload)) {
        foreach ($evidencePayload as $item) {
            if (is_string($item) && trim($item) !== '') {
                $evidenceFiles[] = trim($item);
            }
        }
    } elseif (is_string($evidencePayload) && trim($evidencePayload) !== '') {
        $evidenceFiles[] = trim($evidencePayload);
    }

    $cepFormatted = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);

    $newId = createServiceRequest($pdo, [
        'user_id' => $userId,
        'service_type_id' => (int)$subtype['type_id'],
        'service_subtype_id' => $serviceSubtypeId,
        'secretaria_id' => $secretariaId,
        'chamado_pai_id' => $duplicateParentId > 0 ? $duplicateParentId : null,
        'contador_apoios' => 0,
        'service_name' => (string)$subtype['type_name'],
        'problem_type' => (string)$subtype['subtype_name'],
        'address' => $addressText,
        'neighborhood' => $neighborhood,
        'zip' => $cepFormatted,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'tempo_ocorrencia' => $tempoOcorrencia,
        'priority' => $priority,
        'sla_due_at' => $slaDueAt,
        'evidence_files' => $evidenceFiles ? json_encode($evidenceFiles, JSON_UNESCAPED_SLASHES) : null,
    ]);

    if ($duplicateAction === 'create_new' && $duplicateParentId > 0 && $duplicateReason !== '') {
        registerDuplicateJustification($pdo, $newId, $userId, $duplicateParentId, $duplicateReason);
    }

    jsonResponse(201, [
        'status' => 'ok',
        'chamado_id' => $newId,
        'secretaria_id' => $secretariaId,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao criar chamado.']);
}
