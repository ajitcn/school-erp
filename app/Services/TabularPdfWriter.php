<?php

namespace App\Services;

/** Creates readable landscape PDF tables without requiring an external PDF package. */
class TabularPdfWriter
{
    private const PAGE_WIDTH = 792;

    private const PAGE_HEIGHT = 612;

    private const MARGIN = 36;

    private const TABLE_WIDTH = self::PAGE_WIDTH - (self::MARGIN * 2);

    private const BOTTOM_LIMIT = 40;

    private const ROW_LINE_HEIGHT = 9;

    /**
     * Render tabular report data into a paginated landscape PDF.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function write(string $title, array $headers, iterable $rows): string
    {
        $columnWidths = $this->columnWidths($headers);
        $pages = [];
        $pageNumber = 1;
        $rowNumber = 0;
        [$stream, $cursorY] = $this->startPage($title, $headers, $columnWidths);

        foreach ($rows as $row) {
            $cells = [];
            $lineCount = 1;

            foreach ($headers as $index => $header) {
                $value = $this->text((string) ($row[$index] ?? ''));
                $maxCharacters = max(8, (int) floor(($columnWidths[$index] - 10) / 4.2));
                $cellLines = $this->wrap($value, $maxCharacters);
                $cells[] = $cellLines;
                $lineCount = max($lineCount, count($cellLines));
            }

            $lineCount = min($lineCount, 4);
            $rowHeight = max(22, ($lineCount * self::ROW_LINE_HEIGHT) + 8);

            if ($cursorY - $rowHeight < self::BOTTOM_LIMIT) {
                $pages[] = $stream;
                $pageNumber++;
                [$stream, $cursorY] = $this->startPage($title, $headers, $columnWidths);
            }

            if ($rowNumber % 2 === 0) {
                $stream .= $this->fillRectangle(self::MARGIN, $cursorY - $rowHeight, self::TABLE_WIDTH, $rowHeight, 0.965, 0.972, 0.982);
            }

            $columnX = self::MARGIN;
            foreach ($cells as $index => $cellLines) {
                $visibleLines = array_slice($cellLines, 0, $lineCount);
                if (count($cellLines) > $lineCount && $visibleLines !== []) {
                    $visibleLines[$lineCount - 1] = $this->truncate($visibleLines[$lineCount - 1], max(8, (int) floor(($columnWidths[$index] - 10) / 4.2) - 3)).'...';
                }

                foreach ($visibleLines as $lineIndex => $line) {
                    $stream .= $this->drawText(
                        $columnX + 5,
                        $cursorY - 13 - ($lineIndex * self::ROW_LINE_HEIGHT),
                        $line,
                        7,
                    );
                }
                $columnX += $columnWidths[$index];
            }

            $cursorY -= $rowHeight;
            $stream .= $this->drawRule(self::MARGIN, $cursorY, self::TABLE_WIDTH, 0.84, 0.87, 0.91);
            $rowNumber++;
        }

        $pages[] = $stream;
        $pageCount = count($pages);
        foreach ($pages as $index => $pageStream) {
            $pages[$index] = $pageStream.$this->drawText(
                self::PAGE_WIDTH - self::MARGIN - 74,
                18,
                'Page '.($index + 1).' of '.$pageCount,
                7,
            ).'ET';
        }

        return $this->writePdf($pages);
    }

    /** Set useful column proportions based on the report's column labels. */
    private function columnWidths(array $headers): array
    {
        $weights = [];
        foreach ($headers as $header) {
            $label = strtolower($header);
            $weights[] = match (true) {
                str_contains($label, 'description'), str_contains($label, 'remarks') => 2.6,
                str_contains($label, 'student'), str_contains($label, 'installment'), str_contains($label, 'vendor'),
                str_contains($label, 'category'), str_contains($label, 'branch'), str_contains($label, 'method') => 1.45,
                str_contains($label, 'date'), str_contains($label, 'amount'), str_contains($label, 'paid'),
                str_contains($label, 'balance'), str_contains($label, 'status'), str_contains($label, 'id') => 0.9,
                default => 1.1,
            };
        }

        $totalWeight = array_sum($weights) ?: 1;
        $remainingWidth = self::TABLE_WIDTH;
        $widths = [];
        foreach ($weights as $index => $weight) {
            $width = $index === array_key_last($weights)
                ? $remainingWidth
                : (int) floor(self::TABLE_WIDTH * $weight / $totalWeight);
            $widths[] = $width;
            $remainingWidth -= $width;
        }

        return $widths;
    }

    /** Start a page with the report title and a repeated table heading. */
    private function startPage(string $title, array $headers, array $columnWidths): array
    {
        $stream = $this->drawText(self::MARGIN, 578, $this->text($title), 14, true)
            .$this->drawText(self::MARGIN, 562, 'Generated '.$this->text(now()->format('d M Y, h:i A')), 8)
            .$this->fillRectangle(self::MARGIN, 535, self::TABLE_WIDTH, 20, 0.16, 0.23, 0.33);

        $columnX = self::MARGIN;
        foreach ($headers as $index => $header) {
            $maxCharacters = max(8, (int) floor(($columnWidths[$index] - 10) / 4.2));
            $stream .= $this->drawText(
                $columnX + 5,
                542,
                $this->truncate($this->text($header), $maxCharacters),
                7,
                true,
                1,
                1,
                1,
            );
            $columnX += $columnWidths[$index];
        }

        return [$stream, 535];
    }

    /** Wrap long cell text to its column and cap exceptionally large descriptions. */
    private function wrap(string $value, int $width): array
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return [''];
        }

        $lines = explode("\n", wordwrap($value, $width, "\n", true));
        if (count($lines) > 4) {
            $lines = array_slice($lines, 0, 4);
            $lines[3] = $this->truncate($lines[3], max(5, $width - 3)).'...';
        }

        return $lines;
    }

    private function truncate(string $value, int $width): string
    {
        if (strlen($value) <= $width) {
            return $value;
        }

        return rtrim(substr($value, 0, max(1, $width - 3))).'...';
    }

    private function text(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: '';

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
    }

    private function drawText(
        int $x,
        int $y,
        string $value,
        int $fontSize,
        bool $bold = false,
        float $red = 0.16,
        float $green = 0.20,
        float $blue = 0.25,
    ): string {
        $font = $bold ? 'F2' : 'F1';
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->text($value));

        return sprintf("BT\n/{$font} %d Tf\n%.2f %.2f %.2f rg\n%d %d Td\n(%s) Tj\nET\n", $fontSize, $red, $green, $blue, $x, $y, $safe);
    }

    private function fillRectangle(int $x, int $y, int $width, int $height, float $red, float $green, float $blue): string
    {
        return sprintf("q\n%.3f %.3f %.3f rg\n%d %d %d %d re f\nQ\n", $red, $green, $blue, $x, $y, $width, $height);
    }

    private function drawRule(int $x, int $y, int $width, float $red, float $green, float $blue): string
    {
        return sprintf("q\n%.3f %.3f %.3f RG\n0.4 w\n%d %d m\n%d %d l S\nQ\n", $red, $green, $blue, $x, $y, $x + $width, $y);
    }

    /** Assemble PDF page, font, and cross-reference objects. */
    private function writePdf(array $pages): string
    {
        $fontRegularId = 3 + count($pages) * 2;
        $fontBoldId = $fontRegularId + 1;
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageIds = [];

        foreach ($pages as $index => $stream) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 '.$fontRegularId.' 0 R /F2 '.$fontBoldId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pages).' >>';
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }

        $xref = strlen($pdf);
        $objectCount = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 $objectCount\n0000000000 65535 f \n";
        for ($id = 1; $id < $objectCount; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0)."\n";
        }
        $pdf .= "trailer\n<< /Size $objectCount /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

        $path = tempnam(sys_get_temp_dir(), 'school-erp-pdf-');
        file_put_contents($path, $pdf);

        return $path;
    }
}
