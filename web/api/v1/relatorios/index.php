<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';
require __DIR__ . '/../../../app/lib/simple_pdf.php';
require __DIR__ . '/../../../app/lib/qr_code.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function jsonResponse(int $statusCode, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function parseReportDate(?string $value, bool $endOfDay): DateTimeImmutable
{
    $value = trim((string)$value);
    if ($value === '') {
        throw new RuntimeException('Periodo invalido.');
    }

    $hasTime = strlen($value) > 10;
    if (!$hasTime) {
        $value .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
    }

    return new DateTimeImmutable($value);
}

function resolveSecretariaScope(PDO $pdo, string $userType, int $userId): ?array
{
    if (in_array($userType, ['admin', 'gestor_global'], true)) {
        return null;
    }
    return listUserSecretariaIds($pdo, $userId);
}

function buildSecretariaFilter(?array $ids, array &$params, string $alias = 'sr'): string
{
    if ($ids === null) {
        return '';
    }
    if (!$ids) {
        return ' AND 1 = 0 ';
    }
    $placeholders = [];
    foreach ($ids as $idx => $id) {
        $key = 'sec_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = (int)$id;
    }
    return ' AND ' . $alias . '.secretaria_id IN (' . implode(',', $placeholders) . ') ';
}

function drawQrCode(SimplePdf $pdf, array $matrix, float $x, float $y, float $moduleSize = 3.0): float
{
    $size = count($matrix);
    $quiet = 4;
    $totalModules = $size + ($quiet * 2);
    $totalSize = $totalModules * $moduleSize;

    $pdf->rect($x, $y - $totalSize, $totalSize, $totalSize, [1, 1, 1]);

    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            if ($matrix[$row][$col] !== 1) {
                continue;
            }
            $px = $x + ($col + $quiet) * $moduleSize;
            $py = $y - ($row + $quiet + 1) * $moduleSize;
            $pdf->rect($px, $py, $moduleSize, $moduleSize, [0, 0, 0]);
        }
    }

    return $totalSize;
}

function pdfTextWidthApprox(string $text, float $size): float
{
    $text = pdfNormalizeText($text);
    return strlen($text) * $size * 0.48;
}

function pdfTruncate(string $text, int $maxChars): string
{
    $text = pdfNormalizeText($text);
    if ($maxChars <= 0 || strlen($text) <= $maxChars) {
        return $text;
    }
    if ($maxChars <= 3) {
        return substr($text, 0, $maxChars);
    }
    return substr($text, 0, $maxChars - 3) . '...';
}

function pdfDrawTextAligned(SimplePdf $pdf, float $x, float $y, float $width, float $size, string $text, string $align = 'left', string $font = 'F1'): void
{
    $textWidth = pdfTextWidthApprox($text, $size);
    $offset = 0.0;
    if ($align === 'right') {
        $offset = max(0.0, $width - $textWidth);
    } elseif ($align === 'center') {
        $offset = max(0.0, ($width - $textWidth) / 2);
    }
    $pdf->text($x + $offset, $y, $size, $text, $font);
}

function drawSummaryColumns(SimplePdf $pdf, float $x, float &$y, array $items, int $columns, float $colGap, float $colWidth): void
{
    if (!$items) {
        return;
    }
    $itemsPerCol = (int) ceil(count($items) / max(1, $columns));
    $rowHeight = 14;

    for ($col = 0; $col < $columns; $col++) {
        for ($row = 0; $row < $itemsPerCol; $row++) {
            $index = ($col * $itemsPerCol) + $row;
            if (!isset($items[$index])) {
                continue;
            }
            [$label, $value] = $items[$index];
            $baseY = $y - ($row * $rowHeight);
            $cellX = $x + ($col * ($colWidth + $colGap));
            $labelText = pdfTruncate((string)$label, 28);
            $valueText = pdfTruncate((string)$value, 16);
            $pdf->text($cellX, $baseY, 8.5, $labelText, 'F1', [0.25, 0.25, 0.25]);
            pdfDrawTextAligned($pdf, $cellX + ($colWidth * 0.45), $baseY, $colWidth * 0.55, 9.5, $valueText, 'right', 'F2');
        }
    }

    $y -= ($itemsPerCol * $rowHeight) + 8;
}

function drawTable(SimplePdf $pdf, float $x, float &$y, float $pageTopY, float $bottomMargin, array $headers, array $rows, array $widths, array $alignments = []): void
{
    $rowHeight = 16;
    $headerHeight = 18;
    $totalWidth = array_sum($widths);

    $drawHeader = function () use ($pdf, $x, &$y, $headers, $widths, $headerHeight, $totalWidth, $alignments): void {
        $pdf->rect($x, $y - $headerHeight + 4, $totalWidth, $headerHeight, [0.9, 0.9, 0.9]);
        $cursor = $x + 6;
        foreach ($headers as $idx => $label) {
            $cellWidth = $widths[$idx];
            $text = pdfTruncate((string)$label, 20);
            $align = $alignments[$idx] ?? 'left';
            pdfDrawTextAligned($pdf, $cursor, $y - 10, $cellWidth - 6, 9, $text, $align, 'F2');
            $cursor += $cellWidth;
        }
        $y -= $headerHeight + 2;
    };

    $drawHeader();

    $rowIndex = 0;
    foreach ($rows as $row) {
        if ($y < $bottomMargin + $rowHeight) {
            $pdf->addPage();
            $y = $pageTopY;
            $drawHeader();
        }
        if ($rowIndex % 2 === 1) {
            $pdf->rect($x, $y - $rowHeight + 4, $totalWidth, $rowHeight, [0.97, 0.97, 0.97]);
        }
        $cursor = $x + 6;
        foreach ($row as $idx => $value) {
            $cellWidth = $widths[$idx];
            $align = $alignments[$idx] ?? 'left';
            $text = $value === null ? '-' : (string)$value;
            $maxChars = (int) floor(($cellWidth - 8) / 4.8);
            $text = pdfTruncate($text, max(4, $maxChars));
            pdfDrawTextAligned($pdf, $cursor, $y - 10, $cellWidth - 6, 9, $text, $align, 'F1');
            $cursor += $cellWidth;
        }
        $y -= $rowHeight;
        $rowIndex++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(405, ['error' => 'Metodo nao permitido.']);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(401, ['error' => 'Nao autenticado.']);
}

$userType = currentUserType();
if (!in_array($userType, ['gestor', 'admin', 'gestor_global'], true)) {
    jsonResponse(403, ['error' => 'Acesso nao permitido.']);
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
$tipo = $segments[0] ?? '';
$aliases = [
    'mensal' => 'mensal-secretaria',
    'secretaria' => 'mensal-secretaria',
];
if (isset($aliases[$tipo])) {
    $tipo = $aliases[$tipo];
}

$tipoLabel = [
    'mensal-secretaria' => 'Relatorio mensal por secretaria',
    'sla' => 'Relatorio de SLA',
    'bairro' => 'Relatorio por bairro',
][$tipo] ?? null;

if ($tipoLabel === null) {
    jsonResponse(404, ['error' => 'Tipo de relatorio invalido.']);
}

$inicio = $_GET['inicio'] ?? '';
$fim = $_GET['fim'] ?? '';

try {
    $inicioAt = parseReportDate($inicio, false);
    $fimAt = parseReportDate($fim, true);
    if ($fimAt < $inicioAt) {
        jsonResponse(422, ['error' => 'Periodo invalido.']);
    }
} catch (Throwable $e) {
    jsonResponse(422, ['error' => 'Periodo invalido.']);
}

$inicioStr = $inicioAt->format('Y-m-d H:i:s');
$fimStr = $fimAt->format('Y-m-d H:i:s');

try {
    $pdo = getPDO();
    $secretariaIds = resolveSecretariaScope($pdo, $userType, (int)currentUserId());
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao preparar relatorio.']);
}

$openStatuses = ['RECEBIDO', 'EM_ANALISE', 'ENCAMINHADO', 'EM_EXECUCAO'];
$closedStatuses = ['RESOLVIDO', 'ENCERRADO'];

$reportRows = [];
$summaryMetrics = [];

try {
    if ($tipo === 'mensal-secretaria') {
        $params = [
            'inicio' => $inicioStr,
            'fim' => $fimStr,
        ];

        $sql = '
            SELECT sec.id,
                   sec.nome,
                   COUNT(sr.id) AS total,
                   SUM(sr.status IN ("' . implode('","', $openStatuses) . '")) AS abertos,
                   SUM(sr.status IN ("' . implode('","', $closedStatuses) . '")) AS encerrados,
                   SUM(CASE WHEN sr.sla_due_at >= NOW() THEN 1 ELSE 0 END) AS sla_dentro,
                   SUM(CASE WHEN sr.sla_due_at < NOW() THEN 1 ELSE 0 END) AS sla_vencido,
                   AVG(CASE WHEN sr.status IN ("' . implode('","', $closedStatuses) . '")
                            THEN TIMESTAMPDIFF(MINUTE, sr.created_at, sr.updated_at)
                            ELSE NULL END) AS avg_minutes
            FROM secretarias sec
            LEFT JOIN service_requests sr
                ON sr.secretaria_id = sec.id
               AND sr.created_at BETWEEN :inicio AND :fim
            WHERE 1 = 1
        ';
        if ($secretariaIds !== null) {
            if (!$secretariaIds) {
                $sql .= ' AND 1 = 0 ';
            } else {
                $placeholders = [];
                foreach ($secretariaIds as $idx => $id) {
                    $key = 'sec_' . $idx;
                    $placeholders[] = ':' . $key;
                    $params[$key] = (int)$id;
                }
                $sql .= ' AND sec.id IN (' . implode(',', $placeholders) . ')';
            }
        }
        $sql .= ' GROUP BY sec.id ORDER BY total DESC, sec.nome ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalChamados = 0;
        $totalAbertos = 0;
        $totalEncerrados = 0;
        $totalSlaDentro = 0;
        $totalSlaVencido = 0;

        foreach ($rows as $row) {
            $avgMinutes = isset($row['avg_minutes']) ? (float)$row['avg_minutes'] : 0.0;
            $avgHours = $avgMinutes > 0 ? round($avgMinutes / 60, 1) : null;

            $reportRows[] = [
                $row['nome'] ?? 'Sem secretaria',
                (string)(int)$row['total'],
                (string)(int)$row['abertos'],
                (string)(int)$row['encerrados'],
                (string)(int)$row['sla_dentro'],
                (string)(int)$row['sla_vencido'],
                $avgHours !== null ? (string)$avgHours : '-',
            ];

            $totalChamados += (int)$row['total'];
            $totalAbertos += (int)$row['abertos'];
            $totalEncerrados += (int)$row['encerrados'];
            $totalSlaDentro += (int)$row['sla_dentro'];
            $totalSlaVencido += (int)$row['sla_vencido'];
        }

        $summaryMetrics = [
            ['Total de chamados', $totalChamados],
            ['Abertos', $totalAbertos],
            ['Encerrados', $totalEncerrados],
            ['SLA dentro', $totalSlaDentro],
            ['SLA vencido', $totalSlaVencido],
        ];
    }

    if ($tipo === 'sla') {
        $params = [
            'inicio' => $inicioStr,
            'fim' => $fimStr,
        ];
        $sql = '
            SELECT sr.id,
                   sr.secretaria_id,
                   sec.nome AS secretaria_nome,
                   sr.sla_due_at,
                   sr.created_at
            FROM service_requests sr
            LEFT JOIN secretarias sec ON sec.id = sr.secretaria_id
            WHERE sr.created_at BETWEEN :inicio AND :fim
              AND sr.sla_due_at IS NOT NULL
        ';
        $sql .= buildSecretariaFilter($secretariaIds, $params, 'sr');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $secId = $row['secretaria_id'] !== null ? (int)$row['secretaria_id'] : 0;
            if (!isset($grouped[$secId])) {
                $grouped[$secId] = [
                    'nome' => $row['secretaria_nome'] ?? 'Sem secretaria',
                    'DENTRO_DO_PRAZO' => 0,
                    'PROXIMO_DO_VENCIMENTO' => 0,
                    'VENCIDO' => 0,
                    'total' => 0,
                ];
            }
            $status = computeSlaStatus($row['sla_due_at'] ?? null, $row['created_at'] ?? null);
            if ($status !== null) {
                $grouped[$secId][$status] = ($grouped[$secId][$status] ?? 0) + 1;
                $grouped[$secId]['total']++;
            }
        }

        $total = 0;
        $totalDentro = 0;
        $totalProximo = 0;
        $totalVencido = 0;

        foreach ($grouped as $row) {
            $reportRows[] = [
                $row['nome'],
                (string)($row['DENTRO_DO_PRAZO'] ?? 0),
                (string)($row['PROXIMO_DO_VENCIMENTO'] ?? 0),
                (string)($row['VENCIDO'] ?? 0),
                (string)($row['total'] ?? 0),
            ];
            $total += (int)$row['total'];
            $totalDentro += (int)$row['DENTRO_DO_PRAZO'];
            $totalProximo += (int)$row['PROXIMO_DO_VENCIMENTO'];
            $totalVencido += (int)$row['VENCIDO'];
        }

        $summaryMetrics = [
            ['Total com SLA', $total],
            ['Dentro do prazo', $totalDentro],
            ['Proximo do vencimento', $totalProximo],
            ['Vencido', $totalVencido],
        ];
    }

    if ($tipo === 'bairro') {
        $params = [
            'inicio' => $inicioStr,
            'fim' => $fimStr,
        ];
        $sql = '
            SELECT COALESCE(NULLIF(TRIM(sr.neighborhood), \'\'), \'Nao informado\') AS bairro,
                   COUNT(*) AS total,
                   SUM(sr.status IN ("' . implode('","', $openStatuses) . '")) AS abertos,
                   SUM(sr.status IN ("' . implode('","', $closedStatuses) . '")) AS encerrados
            FROM service_requests sr
            WHERE sr.created_at BETWEEN :inicio AND :fim
        ';
        $sql .= buildSecretariaFilter($secretariaIds, $params, 'sr');
        $sql .= ' GROUP BY bairro ORDER BY total DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $total = 0;
        foreach ($rows as $row) {
            $reportRows[] = [
                $row['bairro'],
                (string)(int)$row['total'],
                (string)(int)$row['abertos'],
                (string)(int)$row['encerrados'],
            ];
            $total += (int)$row['total'];
        }
        $summaryMetrics = [
            ['Total de chamados', $total],
        ];
    }
} catch (Throwable $e) {
    jsonResponse(500, ['error' => 'Erro ao gerar dados do relatorio.']);
}

$generatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$secret = defined('REPORTS_SECRET') ? REPORTS_SECRET : (defined('DB_PASS') ? DB_PASS : '');
$tokenPayload = $tipo . '|' . $inicioStr . '|' . $fimStr;
$token = strtoupper(substr(hash_hmac('sha256', $tokenPayload, $secret), 0, 32));

$qr = new SimpleQrCode('RPT:' . $token);
$qrMatrix = $qr->getMatrix();

$pdf = new SimplePdf();
$pdf->addPage();
$pageWidth = $pdf->getWidth();
$pageHeight = $pdf->getHeight();
$margin = 40;

$headerY = $pageHeight - 46;
$pdf->text($margin, $headerY, 18, 'Prefeitura Digital', 'F2');
$pdf->text($margin, $headerY - 20, 12, $tipoLabel, 'F2');
$pdf->text($margin, $headerY - 36, 9, 'Periodo: ' . $inicioAt->format('d/m/Y') . ' a ' . $fimAt->format('d/m/Y'), 'F1');
$pdf->text($margin, $headerY - 50, 9, 'Gerado em: ' . $generatedAt, 'F1');

$qrModule = 2.6;
$qrTotal = (count($qrMatrix) + 8) * $qrModule;
$qrX = $pageWidth - $margin - $qrTotal;
$qrTop = $pageHeight - 34;
$pdf->text($qrX, $qrTop + 8, 8.5, 'Validacao', 'F2');
$qrSize = drawQrCode($pdf, $qrMatrix, $qrX, $qrTop, $qrModule);
$pdf->text($qrX, $qrTop - $qrSize - 10, 7.5, 'Codigo: ' . $token, 'F1');

$lineY = min($headerY - 62, $qrTop - $qrSize - 6);
$pdf->line($margin, $lineY, $pageWidth - $margin, $lineY, [0.75, 0.75, 0.75], 0.6);

$summaryY = $lineY - 16;
$pdf->text($margin, $summaryY, 10.5, 'Resumo do periodo', 'F2');
$summaryY -= 12;

$summaryCols = $tipo === 'mensal-secretaria' ? 3 : 2;
$summaryColGap = 14;
$summaryColWidth = ($pageWidth - ($margin * 2) - ($summaryColGap * ($summaryCols - 1))) / $summaryCols;
drawSummaryColumns($pdf, $margin, $summaryY, $summaryMetrics, $summaryCols, $summaryColGap, $summaryColWidth);

$tableY = $summaryY - 6;
$pageTopY = $pageHeight - 60;
$bottomMargin = 60;

if (!$reportRows) {
    $pdf->text($margin, $tableY, 10, 'Nenhum dado encontrado para o periodo informado.', 'F1');
} else {
    $pdf->text($margin, $tableY, 10.5, 'Detalhamento', 'F2');
    $tableY -= 12;
    if ($tipo === 'mensal-secretaria') {
        drawTable(
            $pdf,
            $margin,
            $tableY,
            $pageTopY,
            $bottomMargin,
            ['Secretaria', 'Total', 'Abertos', 'Encerrados', 'SLA Dentro', 'SLA Vencido', 'Media (h)'],
            $reportRows,
            [190, 55, 55, 65, 65, 65, 55],
            ['left', 'right', 'right', 'right', 'right', 'right', 'right']
        );
    } elseif ($tipo === 'sla') {
        drawTable(
            $pdf,
            $margin,
            $tableY,
            $pageTopY,
            $bottomMargin,
            ['Secretaria', 'Dentro', 'Proximo', 'Vencido', 'Total'],
            $reportRows,
            [210, 70, 70, 70, 60],
            ['left', 'right', 'right', 'right', 'right']
        );
    } else {
        drawTable(
            $pdf,
            $margin,
            $tableY,
            $pageTopY,
            $bottomMargin,
            ['Bairro', 'Total', 'Abertos', 'Encerrados'],
            $reportRows,
            [220, 70, 70, 80],
            ['left', 'right', 'right', 'right']
        );
    }
}

$filename = 'relatorio-' . $tipo . '-' . $inicioAt->format('Ymd') . '-' . $fimAt->format('Ymd') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $pdf->output();
