<?php
declare(strict_types=1);

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function consumeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): void
{
    $flash = consumeFlash();
    if (!$flash) {
        return;
    }

    $type = htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES);
    $message = htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES);

    echo '<div class="alert alert-' . $type . ' mb-3">' . $message . '</div>';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function requireValidCsrfToken(?string $token, string $redirect, string $message = 'Sessão expirada. Tente novamente.'): void
{
    if (!verifyCsrfToken($token)) {
        flash('danger', $message);
        header('Location: ' . $redirect);
        exit;
    }
}

function buildVerificationLink(string $token): string
{
    return rtrim(BASE_URL, '/') . '/verify.php?token=' . urlencode($token);
}

function normalizeDigits(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value);
    return $digits ?? '';
}

function canUserReassignSecretaria(string $userType): bool
{
    return in_array($userType, ['gestor', 'admin'], true);
}

function secretariaLinkError(?int $secretariaId, bool $secretariaActive, string $missingMessage = 'Subtipo sem secretaria vinculada.', string $inactiveMessage = 'Secretaria indisponível para novos chamados.'): ?string
{
    if (!$secretariaId || $secretariaId <= 0) {
        return $missingMessage;
    }
    if (!$secretariaActive) {
        return $inactiveMessage;
    }
    return null;
}

function slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
    if ($ascii !== false) {
        $lower = $ascii;
    }
    $slug = preg_replace('/[^a-z0-9]+/', '-', $lower);
    $slug = trim((string)$slug, '-');
    return $slug;
}

function isValidCpf(string $cpf): bool
{
    $cpf = normalizeDigits($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digit = (10 * $sum) % 11;
        $digit = $digit === 10 ? 0 : $digit;
        if ((int)$cpf[$t] !== $digit) {
            return false;
        }
    }

    return true;
}

function isValidCnpj(string $cnpj): bool
{
    $cnpj = normalizeDigits($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$cnpj[$i] * $weights1[$i];
    }
    $rest = $sum % 11;
    $digit1 = $rest < 2 ? 0 : 11 - $rest;
    if ((int)$cnpj[12] !== $digit1) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 13; $i++) {
        $sum += (int)$cnpj[$i] * $weights2[$i];
    }
    $rest = $sum % 11;
    $digit2 = $rest < 2 ? 0 : 11 - $rest;

    return (int)$cnpj[13] === $digit2;
}

function parseEvidenceFiles(?string $payload): array
{
    if ($payload === null || $payload === '') {
        return [];
    }

    $decoded = json_decode($payload, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (is_string($payload)) {
        return [$payload];
    }

    return [];
}

function statusBadgeClass(string $status): string
{
    $key = strtolower(trim($status));
    $classes = [
        'aberta' => 'bg-info text-dark',
        'aberto' => 'bg-info text-dark',
        'novo' => 'bg-info text-dark',
        'em_andamento' => 'bg-warning text-dark',
        'em andamento' => 'bg-warning text-dark',
        'recebido' => 'bg-info text-dark',
        'em_analise' => 'bg-warning text-dark',
        'em análise' => 'bg-warning text-dark',
        'encaminhado' => 'bg-primary',
        'em_execucao' => 'bg-warning text-dark',
        'em execução' => 'bg-warning text-dark',
        'concluida' => 'bg-success',
        'concluido' => 'bg-success',
        'concluído' => 'bg-success',
        'resolvido' => 'bg-success',
        'resolvida' => 'bg-success',
        'encerrado' => 'bg-success',
        'encerrada' => 'bg-success',
        'cancelada' => 'bg-danger',
        'cancelado' => 'bg-danger',
    ];

    return $classes[$key] ?? 'bg-secondary';
}

function computeSlaStatus(?string $slaDueAt, ?string $createdAt = null): ?string
{
    if (!$slaDueAt) {
        return null;
    }

    try {
        $now = new DateTimeImmutable('now');
        $due = new DateTimeImmutable($slaDueAt);
    } catch (Throwable $e) {
        return null;
    }

    if ($now >= $due) {
        return 'VENCIDO';
    }

    $warningHours = defined('SLA_WARNING_HOURS') ? (int) SLA_WARNING_HOURS : 0;
    if ($warningHours <= 0) {
        $effectiveHours = null;
        if ($createdAt) {
            try {
                $created = new DateTimeImmutable($createdAt);
                $diffSeconds = $due->getTimestamp() - $created->getTimestamp();
                if ($diffSeconds > 0) {
                    $effectiveHours = (int) ceil($diffSeconds / 3600);
                }
            } catch (Throwable $e) {
                $effectiveHours = null;
            }
        }
        if ($effectiveHours !== null) {
            $warningHours = max(2, (int) round($effectiveHours * 0.2));
        } else {
            $warningHours = 6;
        }
    }

    $remainingHours = ($due->getTimestamp() - $now->getTimestamp()) / 3600;
    if ($remainingHours <= $warningHours) {
        return 'PROXIMO_DO_VENCIMENTO';
    }

    return 'DENTRO_DO_PRAZO';
}

function formatServiceAddress(?string $addressText, ?string $neighborhood, ?string $zip): string
{
    $parts = [];
    if ($addressText) {
        $parts[] = trim($addressText);
    }
    if ($neighborhood) {
        $parts[] = 'Bairro: ' . trim($neighborhood);
    }
    if ($zip) {
        $parts[] = 'CEP: ' . trim($zip);
    }

    return $parts ? implode(' | ', $parts) : 'Endereço não informado';
}
