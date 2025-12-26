<?php
declare(strict_types=1);

class SimpleQrCode
{
    private string $data;
    private int $version = 3;
    private int $size = 29;
    private int $dataCodewords = 55;
    private int $ecCodewords = 15;

    public function __construct(string $data)
    {
        $this->data = $this->normalizeData($data);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMatrix(): array
    {
        $dataCodewords = $this->buildDataCodewords();
        $ecc = $this->buildErrorCorrection($dataCodewords);
        $codewords = array_merge($dataCodewords, $ecc);
        $bits = $this->codewordsToBits($codewords);

        $matrix = array_fill(0, $this->size, array_fill(0, $this->size, null));
        $reserved = array_fill(0, $this->size, array_fill(0, $this->size, false));

        $this->placeFinder($matrix, $reserved, 0, 0);
        $this->placeFinder($matrix, $reserved, 0, $this->size - 7);
        $this->placeFinder($matrix, $reserved, $this->size - 7, 0);
        $this->placeTiming($matrix, $reserved);
        $this->placeAlignment($matrix, $reserved);
        $this->placeDarkModule($matrix, $reserved);
        $this->reserveFormatInfo($reserved);

        $this->placeData($matrix, $reserved, $bits);
        $this->placeFormatInfo($matrix);

        for ($r = 0; $r < $this->size; $r++) {
            for ($c = 0; $c < $this->size; $c++) {
                if ($matrix[$r][$c] === null) {
                    $matrix[$r][$c] = 0;
                }
            }
        }

        return $matrix;
    }

    private function normalizeData(string $data): string
    {
        $normalized = $data;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($converted !== false) {
                $normalized = $converted;
            }
        }
        $normalized = preg_replace('/[^\x20-\x7E]/', '', $normalized) ?? '';
        if (strlen($normalized) > $this->dataCodewords) {
            $normalized = substr($normalized, 0, $this->dataCodewords);
        }
        return $normalized;
    }

    private function buildDataCodewords(): array
    {
        $bytes = array_values(unpack('C*', $this->data));
        $length = count($bytes);

        $bits = [];
        $this->appendBits($bits, 0b0100, 4);
        $this->appendBits($bits, $length, 8);
        foreach ($bytes as $byte) {
            $this->appendBits($bits, $byte, 8);
        }
        $this->appendBits($bits, 0, 4);

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $value = 0;
            for ($j = 0; $j < 8; $j++) {
                $value = ($value << 1) | ($bits[$i + $j] ?? 0);
            }
            $codewords[] = $value;
        }

        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while (count($codewords) < $this->dataCodewords) {
            $codewords[] = $padBytes[$padIndex % 2];
            $padIndex++;
        }

        return $codewords;
    }

    private function buildErrorCorrection(array $data): array
    {
        $generator = $this->rsGenerator($this->ecCodewords);
        $ecc = array_fill(0, $this->ecCodewords, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $ecc[0];
            array_shift($ecc);
            $ecc[] = 0;
            foreach ($generator as $i => $coef) {
                $ecc[$i] = $ecc[$i] ^ $this->gfMultiply($coef, $factor);
            }
        }

        return $ecc;
    }

    private function rsGenerator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $poly = $this->polyMultiply($poly, [1, $this->gfExp($i)]);
        }
        array_shift($poly);
        return $poly;
    }

    private function polyMultiply(array $a, array $b): array
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);
        foreach ($a as $i => $valA) {
            foreach ($b as $j => $valB) {
                $result[$i + $j] ^= $this->gfMultiply($valA, $valB);
            }
        }
        return $result;
    }

    private function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        $log = $this->gfLog();
        $exp = $this->gfExpTable();
        $idx = ($log[$a] + $log[$b]) % 255;
        return $exp[$idx];
    }

    private function gfLog(): array
    {
        static $log = null;
        if ($log !== null) {
            return $log;
        }
        $exp = $this->gfExpTable();
        $log = array_fill(0, 256, 0);
        for ($i = 0; $i < 255; $i++) {
            $log[$exp[$i]] = $i;
        }
        return $log;
    }

    private function gfExpTable(): array
    {
        static $exp = null;
        if ($exp !== null) {
            return $exp;
        }
        $exp = array_fill(0, 512, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        return $exp;
    }

    private function gfExp(int $power): int
    {
        $exp = $this->gfExpTable();
        return $exp[$power % 255];
    }

    private function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function codewordsToBits(array $codewords): array
    {
        $bits = [];
        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }
        return $bits;
    }

    private function placeFinder(array &$matrix, array &$reserved, int $row, int $col): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $cc < 0 || $rr >= $this->size || $cc >= $this->size) {
                    continue;
                }
                if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                    $val = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) ? 1 : 0;
                    $matrix[$rr][$cc] = $val;
                } else {
                    $matrix[$rr][$cc] = 0;
                }
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private function placeTiming(array &$matrix, array &$reserved): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $val = $i % 2 === 0 ? 1 : 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $val;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $val;
                $reserved[$i][6] = true;
            }
        }
    }

    private function placeAlignment(array &$matrix, array &$reserved): void
    {
        $positions = [6, 22];
        foreach ($positions as $row) {
            foreach ($positions as $col) {
                if ($this->isFinderArea($row, $col)) {
                    continue;
                }
                $this->placeAlignmentPattern($matrix, $reserved, $row - 2, $col - 2);
            }
        }
    }

    private function placeAlignmentPattern(array &$matrix, array &$reserved, int $row, int $col): void
    {
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $cc < 0 || $rr >= $this->size || $cc >= $this->size) {
                    continue;
                }
                $val = ($r === 0 || $r === 4 || $c === 0 || $c === 4 || ($r === 2 && $c === 2)) ? 1 : 0;
                $matrix[$rr][$cc] = $val;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private function placeDarkModule(array &$matrix, array &$reserved): void
    {
        $row = 4 * $this->version + 9;
        $col = 8;
        if ($row < $this->size) {
            $matrix[$row][$col] = 1;
            $reserved[$row][$col] = true;
        }
    }

    private function reserveFormatInfo(array &$reserved): void
    {
        [$primary, $secondary] = $this->formatInfoPositions();
        foreach ($primary as $pos) {
            $reserved[$pos[0]][$pos[1]] = true;
        }
        foreach ($secondary as $pos) {
            $reserved[$pos[0]][$pos[1]] = true;
        }
    }

    private function placeData(array &$matrix, array &$reserved, array $bits): void
    {
        $bitIndex = 0;
        $direction = -1;
        for ($col = $this->size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            $row = $direction === -1 ? $this->size - 1 : 0;
            while ($row >= 0 && $row < $this->size) {
                for ($i = 0; $i < 2; $i++) {
                    $cc = $col - $i;
                    if ($reserved[$row][$cc]) {
                        continue;
                    }
                    $bit = $bits[$bitIndex] ?? 0;
                    $bitIndex++;
                    $mask = (($row + $cc) % 2 === 0) ? 1 : 0;
                    $matrix[$row][$cc] = $bit ^ $mask;
                    $reserved[$row][$cc] = true;
                }
                $row += $direction;
            }
            $direction *= -1;
        }
    }

    private function placeFormatInfo(array &$matrix): void
    {
        $format = $this->formatInfoBits();
        [$primary, $secondary] = $this->formatInfoPositions();
        for ($i = 0; $i < 15; $i++) {
            $bit = ($format >> $i) & 1;
            $pos = $primary[$i];
            $matrix[$pos[0]][$pos[1]] = $bit;
            $pos = $secondary[$i];
            $matrix[$pos[0]][$pos[1]] = $bit;
        }
    }

    private function formatInfoBits(): int
    {
        $ecBits = 0b01;
        $mask = 0b000;
        $format = ($ecBits << 3) | $mask;
        $value = $format << 10;
        $generator = 0x537;
        while ($this->bitLength($value) - 1 >= 10) {
            $shift = $this->bitLength($value) - 11;
            $value ^= ($generator << $shift);
        }
        $formatBits = (($format << 10) | $value) ^ 0x5412;
        return $formatBits & 0x7FFF;
    }

    private function bitLength(int $value): int
    {
        $length = 0;
        while ($value > 0) {
            $value >>= 1;
            $length++;
        }
        return $length;
    }

    private function formatInfoPositions(): array
    {
        $primary = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5],
            [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];

        $secondary = [];
        for ($i = 0; $i < 8; $i++) {
            $secondary[] = [8, $this->size - 1 - $i];
        }
        for ($i = 0; $i < 7; $i++) {
            $secondary[] = [$this->size - 7 + $i, 8];
        }

        return [$primary, $secondary];
    }

    private function isFinderArea(int $row, int $col): bool
    {
        $max = $this->size - 7;
        if ($row <= 8 && $col <= 8) {
            return true;
        }
        if ($row <= 8 && $col >= $max - 1) {
            return true;
        }
        if ($row >= $max - 1 && $col <= 8) {
            return true;
        }
        return false;
    }
}
