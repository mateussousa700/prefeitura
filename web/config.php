<?php
declare(strict_types=1);

// Ajuste conforme seu ambiente local.
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'prefeitura_app';
const DB_USER = 'root';
const DB_PASS = '';

// URL base usada nos links de confirmação enviados por e-mail e WhatsApp.
const BASE_URL = 'http://localhost/prefeitura/web';

// Configurações da API Ultramsg (WhatsApp). Preencha antes de enviar mensagens.
const ULTRAMSG_INSTANCE_ID = 'instance124122';
const ULTRAMSG_TOKEN = 'vtts75qh13n0jdc7';
// Número remetente no formato internacional, ex: 55DDDNUMERO
const ULTRAMSG_SENDER = '5585987319678';

// Configuração de e-mail (SMTP). Evite deixar credenciais em repositório; use variáveis de ambiente.
const MAIL_TRANSPORT = 'smtp';
const MAIL_HOST = 'smtp.hostinger.com';
const MAIL_PORT = 587;
const MAIL_ENCRYPTION = 'tls';
const MAIL_USER = 'contato@apexobras.com.br';
const MAIL_PASS = 'Caninde.123';
const MAIL_FROM = 'contato@apexobras.com.br';
const MAIL_FROM_NAME = 'Prefeitura Digital';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getPDO(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

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

function buildVerificationLink(string $token): string
{
    return rtrim(BASE_URL, '/') . '/verify.php?token=' . urlencode($token);
}

function normalizeDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function sendConfirmationEmail(string $to, string $name, string $link): bool
{
    $subject = 'Confirme seu cadastro na Prefeitura Digital';
    $body = <<<TXT
Olá, {$name}!

Recebemos seu cadastro no sistema da Prefeitura.
Confirme sua conta acessando o link abaixo:
{$link}

Se você não solicitou, ignore esta mensagem.
TXT;

    return sendEmail($to, $subject, $body);
}

function sendWhatsAppConfirmation(string $phone, string $name, string $link): bool
{
    $body = "Olá, {$name}! Confirme seu cadastro: {$link}";
    return sendWhatsAppMessage($phone, $body);
}

function sendWhatsAppMessage(string $phone, string $body): bool
{
    if (ULTRAMSG_INSTANCE_ID === '' || ULTRAMSG_TOKEN === '' || ULTRAMSG_SENDER === '') {
        return false;
    }

    $url = sprintf(
        'https://api.ultramsg.com/%s/messages/chat',
        ULTRAMSG_INSTANCE_ID
    );

    $payload = [
        'token' => ULTRAMSG_TOKEN,
        'to' => $phone,
        'from' => ULTRAMSG_SENDER,
        'body' => $body,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response !== false && $status >= 200 && $status < 400;
}

function sendWhatsAppImage(string $phone, string $imageUrl, string $caption = ''): bool
{
    if (ULTRAMSG_INSTANCE_ID === '' || ULTRAMSG_TOKEN === '' || ULTRAMSG_SENDER === '') {
        return false;
    }
    $url = sprintf(
        'https://api.ultramsg.com/%s/messages/image',
        ULTRAMSG_INSTANCE_ID
    );

    $payload = [
        'token' => ULTRAMSG_TOKEN,
        'to' => $phone,
        'from' => ULTRAMSG_SENDER,
        'image' => $imageUrl,
        'caption' => $caption,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response !== false && $status >= 200 && $status < 400;
}

function sendEmail(string $to, string $subject, string $body, bool $isHtml = false): bool
{
    if (MAIL_TRANSPORT === 'smtp') {
        return sendEmailSmtp($to, $subject, $body, $isHtml);
    }

    // Fallback para mail() se configurado no servidor.
    $contentType = $isHtml ? 'text/html' : 'text/plain';
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: ' . $contentType . '; charset=utf-8',
        'From: ' . MAIL_FROM,
    ];
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function sendEmailSmtp(string $to, string $subject, string $body, bool $isHtml = false): bool
{
    $host = MAIL_HOST;
    $port = MAIL_PORT;
    $encryption = strtolower(MAIL_ENCRYPTION);
    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

    $conn = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$conn) {
        return false;
    }
    stream_set_timeout($conn, 10);

    $readResponse = function ($expectedCode) use ($conn): bool {
        $resp = '';
        while (($line = fgets($conn, 512)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return strncmp($resp, (string)$expectedCode, 3) === 0;
    };

    $readAny = function (array $expectedCodes) use ($conn): bool {
        $resp = '';
        while (($line = fgets($conn, 512)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        foreach ($expectedCodes as $code) {
            if (strncmp($resp, (string)$code, 3) === 0) {
                return true;
            }
        }
        return false;
    };

    $send = function (string $cmd) use ($conn): bool {
        return fwrite($conn, $cmd . "\r\n") !== false;
    };

    if (!$readResponse(220)) {
        fclose($conn);
        return false;
    }

    $hostname = 'localhost';
    $send("EHLO {$hostname}");
    if (!$readResponse(250)) {
        fclose($conn);
        return false;
    }

    if ($encryption === 'tls') {
        $send("STARTTLS");
        if (!$readResponse(220)) {
            fclose($conn);
            return false;
        }
        if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($conn);
            return false;
        }
        $send("EHLO {$hostname}");
        if (!$readResponse(250)) {
            fclose($conn);
            return false;
        }
    }

    if (MAIL_USER !== '' && MAIL_PASS !== '') {
        $send("AUTH LOGIN");
        if (!$readResponse(334)) {
            fclose($conn);
            return false;
        }
        $send(base64_encode(MAIL_USER));
        if (!$readResponse(334)) {
            fclose($conn);
            return false;
        }
        $send(base64_encode(MAIL_PASS));
        if (!$readResponse(235)) {
            fclose($conn);
            return false;
        }
    }

    $send('MAIL FROM: <' . MAIL_FROM . '>');
    if (!$readResponse(250)) {
        fclose($conn);
        return false;
    }

    $send('RCPT TO: <' . $to . '>');
    if (!$readAny([250, 251])) {
        fclose($conn);
        return false;
    }

    $send('DATA');
    if (!$readResponse(354)) {
        fclose($conn);
        return false;
    }

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
    $fromHeader = MAIL_FROM_NAME !== '' ? sprintf('"%s" <%s>', MAIL_FROM_NAME, MAIL_FROM) : MAIL_FROM;
    $contentType = $isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
    $headers = [
        'From: ' . $fromHeader,
        'To: ' . $to,
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType,
        'Content-Transfer-Encoding: 8bit',
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
    $send($message);
    if (!$readResponse(250)) {
        fclose($conn);
        return false;
    }

    $send('QUIT');
    fclose($conn);
    return true;
}
