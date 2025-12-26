<?php
declare(strict_types=1);

function pdfNormalizeText(string $text): string
{
    $normalized = $text;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($converted !== false) {
            $normalized = $converted;
        }
    }
    $normalized = preg_replace('/[^\x20-\x7E]/', '', $normalized) ?? '';
    return $normalized;
}

function pdfEscapeText(string $text): string
{
    $text = pdfNormalizeText($text);
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return $text;
}

class SimplePdf
{
    private float $width;
    private float $height;
    private array $pages = [];
    private int $currentPage = -1;

    public function __construct(float $width = 595.28, float $height = 841.89)
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
    }

    public function text(float $x, float $y, float $size, string $text, string $font = 'F1', array $color = [0, 0, 0]): void
    {
        $this->ensurePage();
        $fill = $this->colorToFill($color);
        $content = sprintf(
            "%s\nBT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $fill,
            $font,
            $size,
            $x,
            $y,
            pdfEscapeText($text)
        );
        $this->append($content);
    }

    public function rect(float $x, float $y, float $w, float $h, array $color = [0, 0, 0]): void
    {
        $this->ensurePage();
        $fill = $this->colorToFill($color);
        $content = sprintf(
            "%s\n%.2f %.2f %.2f %.2f re f\n",
            $fill,
            $x,
            $y,
            $w,
            $h
        );
        $this->append($content);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [0, 0, 0], float $width = 1): void
    {
        $this->ensurePage();
        $stroke = $this->colorToStroke($color);
        $content = sprintf(
            "%.2f w\n%s\n%.2f %.2f m %.2f %.2f l S\n",
            $width,
            $stroke,
            $x1,
            $y1,
            $x2,
            $y2
        );
        $this->append($content);
    }

    public function output(): string
    {
        $pagesCount = count($this->pages);
        if ($pagesCount === 0) {
            $this->addPage();
        }

        $objectId = 1;
        $catalogId = $objectId++;
        $pagesId = $objectId++;
        $fontRegularId = $objectId++;
        $fontBoldId = $objectId++;

        $contentIds = [];
        $pageIds = [];
        foreach ($this->pages as $_) {
            $contentIds[] = $objectId++;
            $pageIds[] = $objectId++;
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        $this->addObject($pdf, $offsets, $catalogId, "<< /Type /Catalog /Pages {$pagesId} 0 R >>");

        $kids = [];
        foreach ($pageIds as $pageId) {
            $kids[] = $pageId . ' 0 R';
        }
        $kidsList = implode(' ', $kids);
        $this->addObject(
            $pdf,
            $offsets,
            $pagesId,
            "<< /Type /Pages /Kids [{$kidsList}] /Count {$pagesCount} >>"
        );

        $this->addObject(
            $pdf,
            $offsets,
            $fontRegularId,
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"
        );
        $this->addObject(
            $pdf,
            $offsets,
            $fontBoldId,
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>"
        );

        foreach ($this->pages as $index => $content) {
            $contentId = $contentIds[$index];
            $pageId = $pageIds[$index];
            $length = strlen($content);
            $stream = "<< /Length {$length} >>\nstream\n{$content}\nendstream";
            $this->addObject($pdf, $offsets, $contentId, $stream);

            $page = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>",
                $pagesId,
                $this->width,
                $this->height,
                $fontRegularId,
                $fontBoldId,
                $contentId
            );
            $this->addObject($pdf, $offsets, $pageId, $page);
        }

        $xrefPosition = strlen($pdf);
        $totalObjects = $objectId - 1;
        $pdf .= "xref\n0 " . ($totalObjects + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjects; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . ($totalObjects + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }

    private function addObject(string &$pdf, array &$offsets, int $id, string $content): void
    {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $content . "\nendobj\n";
    }

    private function ensurePage(): void
    {
        if ($this->currentPage === -1) {
            $this->addPage();
        }
    }

    private function append(string $content): void
    {
        $this->pages[$this->currentPage] .= $content;
    }

    private function colorToFill(array $color): string
    {
        [$r, $g, $b] = $this->normalizeColor($color);
        return sprintf("%.3f %.3f %.3f rg", $r, $g, $b);
    }

    private function colorToStroke(array $color): string
    {
        [$r, $g, $b] = $this->normalizeColor($color);
        return sprintf("%.3f %.3f %.3f RG", $r, $g, $b);
    }

    private function normalizeColor(array $color): array
    {
        $r = isset($color[0]) ? (float)$color[0] : 0.0;
        $g = isset($color[1]) ? (float)$color[1] : 0.0;
        $b = isset($color[2]) ? (float)$color[2] : 0.0;
        $r = min(1.0, max(0.0, $r));
        $g = min(1.0, max(0.0, $g));
        $b = min(1.0, max(0.0, $b));
        return [$r, $g, $b];
    }
}
