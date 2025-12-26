<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Acesso restrito.';
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

$openStatuses = ['RECEBIDO', 'EM_ANALISE', 'ENCAMINHADO', 'EM_EXECUCAO'];

function logLine(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function listSlaCandidates(PDO $pdo, array $openStatuses, string $mode): array
{
    $statusList = '"' . implode('","', $openStatuses) . '"';
    $sql = '
        SELECT sr.id,
               sr.secretaria_id,
               sr.created_at,
               sr.sla_due_at,
               COALESCE(st.name, sr.service_name) AS service_name,
               COALESCE(ss.name, sr.problem_type) AS problem_type,
               sec.nome AS secretaria_nome
        FROM service_requests sr
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
        LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
        WHERE sr.status IN (' . $statusList . ')
          AND sr.sla_due_at IS NOT NULL
          AND sr.created_at IS NOT NULL
          AND sr.secretaria_id IS NOT NULL
          AND sr.sla_due_at > sr.created_at
    ';

    if ($mode === 'warning') {
        $sql .= '
          AND NOW() >= DATE_ADD(sr.created_at, INTERVAL ROUND(TIMESTAMPDIFF(SECOND, sr.created_at, sr.sla_due_at) * 0.8) SECOND)
          AND NOW() < sr.sla_due_at
        ';
    } elseif ($mode === 'overdue') {
        $sql .= ' AND NOW() >= sr.sla_due_at';
    } else {
        return [];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function queueSlaNotification(PDO $pdo, array $request, string $kind): int
{
    $requestId = (int)($request['id'] ?? 0);
    $secretariaId = (int)($request['secretaria_id'] ?? 0);
    if ($requestId <= 0 || $secretariaId <= 0) {
        return 0;
    }

    $titlePrefix = $kind === 'overdue' ? 'SLA vencido' : 'SLA 80%';
    $titulo = $titlePrefix . ' - Chamado #' . $requestId;
    $mensagem = sprintf(
        "Chamado #%d (%s - %s) %s.\nSecretaria: %s\nPrazo final: %s",
        $requestId,
        $request['service_name'] ?? 'Sem tipo',
        $request['problem_type'] ?? 'Sem subtipo',
        $kind === 'overdue' ? 'ultrapassou o SLA' : 'atingiu 80% do SLA',
        $request['secretaria_nome'] ?? 'Não definida',
        $request['sla_due_at'] ?? 'Não informado'
    );

    $recipients = listInternalUsersBySecretaria($pdo, $secretariaId);
    $queued = 0;
    foreach ($recipients as $recipient) {
        $userId = (int)($recipient['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        if (notificationExists($pdo, $userId, 'SLA_ALERTA', $titulo)) {
            continue;
        }
        try {
            createNotification($pdo, $userId, 'SLA_ALERTA', $titulo, $mensagem, 'email');
            $queued++;
        } catch (Throwable $e) {
            // Mantém o processo rodando mesmo com erro ao registrar.
        }
    }
    return $queued;
}

try {
    $pdo = getPDO();

    $warningRequests = listSlaCandidates($pdo, $openStatuses, 'warning');
    $warningQueued = 0;
    foreach ($warningRequests as $request) {
        $warningQueued += queueSlaNotification($pdo, $request, 'warning');
    }

    $overdueRequests = listSlaCandidates($pdo, $openStatuses, 'overdue');
    $overdueQueued = 0;
    foreach ($overdueRequests as $request) {
        $overdueQueued += queueSlaNotification($pdo, $request, 'overdue');
    }

    $pending = listPendingNotifications($pdo, 100);
    $sent = 0;
    $failed = 0;
    foreach ($pending as $notification) {
        $email = $notification['usuario_email'] ?? '';
        if (!filter_var((string)$email, FILTER_VALIDATE_EMAIL)) {
            markNotificationStatus($pdo, (int)$notification['id'], 'erro');
            $failed++;
            continue;
        }
        $ok = sendEmail($email, (string)$notification['titulo'], (string)$notification['mensagem']);
        markNotificationStatus($pdo, (int)$notification['id'], $ok ? 'enviado' : 'erro');
        $ok ? $sent++ : $failed++;
    }

    logLine('Alertas SLA 80% enfileirados: ' . $warningQueued);
    logLine('Alertas SLA vencido enfileirados: ' . $overdueQueued);
    logLine('Notificações enviadas: ' . $sent . ' | Erros: ' . $failed);
} catch (Throwable $e) {
    logLine('Erro ao processar notificações: ' . $e->getMessage());
    exit(1);
}
