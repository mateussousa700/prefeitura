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

function buildVerificationLink(string $token): string
{
    return rtrim(BASE_URL, '/') . '/verify.php?token=' . urlencode($token);
}

function normalizeDigits(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value);
    return $digits ?? '';
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
        'concluida' => 'bg-success',
        'concluido' => 'bg-success',
        'concluído' => 'bg-success',
        'resolvido' => 'bg-success',
        'cancelada' => 'bg-danger',
        'cancelado' => 'bg-danger',
    ];

    return $classes[$key] ?? 'bg-secondary';
}
