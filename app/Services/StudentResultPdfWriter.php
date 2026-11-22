<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Render a branded, printable student result sheet as a self-contained PDF. */
class StudentResultPdfWriter
{
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const LEFT = 42;

    private const CONTENT_WIDTH = 511;

    private const NAVY = [0.11, 0.20, 0.34];

    private const BLUE = [0.16, 0.39, 0.72];

    private const TEXT = [0.16, 0.20, 0.27];

    private const MUTED = [0.40, 0.45, 0.53];

    /**
     * Build a branded result PDF and return its temporary file path for download.
     *
     * @param  array<string, mixed>  $document
     * @param  iterable<array<string, mixed>>  $subjects
     */
    public function write(array $document, iterable $subjects): string
    {
        $logo = $this->loadLogo($document['logo_path'] ?? null);
        $pages = [];
        $stream = $this->startPage($document, $logo, false);
        $cursorY = $this->drawStudentDetails($stream, $document);
        $stream .= $this->drawTableHeading($cursorY);
        $cursorY -= 28;
        $rowNumber = 0;
        $totalMarks = 0.0;
        $totalObtained = 0.0;

        foreach ($subjects as $subject) {
            $maximum = (float) ($subject['marks'] ?? 0);
            $obtained = (float) ($subject['marks_obtained'] ?? 0);
            $totalMarks += $maximum;
            $totalObtained += $obtained;

            if ($cursorY < 115) {
                $pages[] = $stream;
                $stream = $this->startPage($document, $logo, true);
                $stream .= $this->drawTableHeading(690);
                $cursorY = 662;
            }

            $stream .= $this->drawSubjectRow($subject, $cursorY, $rowNumber);
            $cursorY -= 27;
            $rowNumber++;
        }

        if ($cursorY < 165) {
            $pages[] = $stream;
            $stream = $this->startPage($document, $logo, true);
            $cursorY = 690;
        }

        $stream .= $this->drawTotals($cursorY, $totalMarks, $totalObtained, $document['status'] ?? 'draft');
        $stream .= $this->drawSignatureLines();
        $stream .= $this->drawText(self::LEFT, 38, 'Generated '.now()->format('d M Y, h:i A'), 8, false, self::MUTED);
        $pages[] = $stream;

        return $this->writePdf($pages, $logo['jpeg'] ?? null, $logo['width'] ?? null, $logo['height'] ?? null);
    }

    /** Draw the institution masthead on the first and continuation pages. */
    private function startPage(array $document, ?array $logo, bool $continued): string
    {
        $name = $this->text((string) ($document['institution_name'] ?? 'Institution'));
        $nameLines = array_slice(explode("\n", wordwrap($name, 50, "\n", true)), 0, 2);
        $contact = trim(implode('  |  ', array_filter([
            $document['branch_name'] ?? null,
            $document['address'] ?? null,
            $document['phone'] ?? null,
            $document['email'] ?? null,
        ])));
        $stream = $this->fillRectangle(self::LEFT, 810, self::CONTENT_WIDTH, 8, self::BLUE)
            .$this->drawText(self::LEFT, 786, $this->truncate($nameLines[0] ?? $name, 50), count($nameLines) > 1 ? 13 : 17, true, self::NAVY)
            .$this->drawText(self::LEFT, 769, $this->truncate($nameLines[1] ?? ucfirst((string) ($document['institution_type'] ?? 'school')), 50), 9, count($nameLines) > 1, self::MUTED)
            .$this->drawText(self::LEFT, 753, $this->truncate($this->text($contact), 90), 7, false, self::MUTED)
            .$this->drawText(self::LEFT, 724, $continued ? 'STUDENT ACADEMIC RESULT  ·  CONTINUED' : 'STUDENT ACADEMIC RESULT', 13, true, self::NAVY)
            .$this->drawRule(self::LEFT, 710, self::CONTENT_WIDTH, [0.84, 0.87, 0.91]);

        if ($logo && isset($logo['width'], $logo['height'])) {
            $maxWidth = 68;
            $maxHeight = 52;
            $scale = min($maxWidth / $logo['width'], $maxHeight / $logo['height']);
            $width = $logo['width'] * $scale;
            $height = $logo['height'] * $scale;
            $x = self::LEFT + self::CONTENT_WIDTH - $width;
            $y = 750 + (($maxHeight - $height) / 2);
            $stream .= sprintf("q\n%.2f 0 0 %.2f %.2f %.2f cm\n/Logo Do\nQ\n", $width, $height, $x, $y);
        }

        return $stream;
    }

    /** Add the student identity and academic grouping block to the first page. */
    private function drawStudentDetails(string &$stream, array $document): int
    {
        $stream .= $this->fillRectangle(self::LEFT, 586, self::CONTENT_WIDTH, 104, [0.95, 0.97, 0.99]);
        $this->detail($stream, 55, 671, 'STUDENT', $document['student_name'] ?? '—');
        $this->detail($stream, 55, 644, 'STUDENT ID', $document['student_number'] ?? '—');
        $this->detail($stream, 55, 617, 'EXAMINATION', $document['examination_name'] ?? 'Not linked');
        $this->detail($stream, 310, 671, 'ACADEMIC YEAR', $document['academic_year'] ?? '—');
        $this->detail($stream, 310, 644, 'CLASS / SECTION', $document['class_section'] ?? '—');
        $this->detail($stream, 310, 617, 'COURSE / SEMESTER', $document['course_semester'] ?? '—');

        return 574;
    }

    /** Draw the result table header. */
    private function drawTableHeading(int $top): string
    {
        $y = $top - 25;

        return $this->fillRectangle(self::LEFT, $y, self::CONTENT_WIDTH, 25, self::NAVY)
            .$this->drawText(52, $y + 8, 'SUBJECT', 8, true, [1, 1, 1])
            .$this->drawText(350, $y + 8, 'MAX MARKS', 8, true, [1, 1, 1])
            .$this->drawText(427, $y + 8, 'OBTAINED', 8, true, [1, 1, 1])
            .$this->drawText(510, $y + 8, 'GRADE', 8, true, [1, 1, 1]);
    }

    /** Draw one subject row with alternate row shading. */
    private function drawSubjectRow(array $subject, int $top, int $rowNumber): string
    {
        $bottom = $top - 27;
        $stream = $rowNumber % 2 === 0
            ? $this->fillRectangle(self::LEFT, $bottom, self::CONTENT_WIDTH, 27, [0.97, 0.98, 0.99])
            : '';
        $stream .= $this->drawText(52, $bottom + 10, $this->truncate((string) ($subject['subject'] ?? '—'), 46), 9, true, self::TEXT)
            .$this->drawText(350, $bottom + 10, $this->text((string) ($subject['marks'] ?? '—')), 9, false, self::TEXT)
            .$this->drawText(427, $bottom + 10, $this->text((string) ($subject['marks_obtained'] ?? '—')), 9, false, self::TEXT)
            .$this->drawText(510, $bottom + 10, $this->text((string) ($subject['grade'] ?: '—')), 9, false, self::TEXT)
            .$this->drawRule(self::LEFT, $bottom, self::CONTENT_WIDTH, [0.88, 0.90, 0.93]);

        return $stream;
    }

    /** Print combined marks and the result's current publication state. */
    private function drawTotals(int $top, float $maximum, float $obtained, string $status): string
    {
        $bottom = $top - 54;
        $percentage = $maximum > 0 ? number_format(($obtained / $maximum) * 100, 2).'%' : '—';

        return $this->fillRectangle(self::LEFT, $bottom, self::CONTENT_WIDTH, 48, [0.93, 0.96, 0.99])
            .$this->drawText(55, $bottom + 29, 'TOTAL MARKS', 8, true, self::MUTED)
            .$this->drawText(55, $bottom + 12, number_format($maximum, 2), 12, true, self::NAVY)
            .$this->drawText(220, $bottom + 29, 'TOTAL OBTAINED', 8, true, self::MUTED)
            .$this->drawText(220, $bottom + 12, number_format($obtained, 2), 12, true, self::NAVY)
            .$this->drawText(390, $bottom + 29, 'PERCENTAGE', 8, true, self::MUTED)
            .$this->drawText(390, $bottom + 12, $percentage, 12, true, self::NAVY)
            .$this->drawText(487, $bottom + 20, Str::headline($status), 8, true, self::BLUE);
    }

    /** Add signature spaces for school and parent acknowledgement. */
    private function drawSignatureLines(): string
    {
        return $this->drawRule(55, 93, 185, [0.60, 0.64, 0.70])
            .$this->drawRule(355, 93, 185, [0.60, 0.64, 0.70])
            .$this->drawText(55, 79, 'Class teacher / authorized signature', 8, false, self::MUTED)
            .$this->drawText(355, 79, 'Parent / guardian signature', 8, false, self::MUTED);
    }

    /** Draw a small uppercase label and its value in the student summary. */
    private function detail(string &$stream, int $x, int $y, string $label, string $value): void
    {
        $stream .= $this->drawText($x, $y, $label, 7, true, self::MUTED)
            .$this->drawText($x, $y - 13, $this->truncate($value, 42), 9, true, self::TEXT);
    }

    /** Convert the tenant logo to JPEG bytes supported by the PDF image object. */
    private function loadLogo(?string $path): ?array
    {
        if (! $path || ! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        return $jpeg ? ['jpeg' => $jpeg, 'width' => $width, 'height' => $height] : null;
    }

    /** Assemble the page streams, fonts, and optional logo into a valid PDF file. */
    private function writePdf(array $pages, ?string $logo, ?int $logoWidth, ?int $logoHeight): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($index) => (3 + ($index * 2)).' 0 R', array_keys($pages))).'] /Count '.count($pages).' >>',
        ];
        $fontRegularId = 3 + (count($pages) * 2);
        $fontBoldId = $fontRegularId + 1;
        $imageId = $fontBoldId + 1;

        foreach ($pages as $index => $stream) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $resources = ' /Font << /F1 '.$fontRegularId.' 0 R /F2 '.$fontBoldId.' 0 R >>';
            if ($logo) {
                $resources .= ' /XObject << /Logo '.$imageId.' 0 R >>';
            }
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources <<'.$resources.' >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }

        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        if ($logo) {
            $objects[$imageId] = '<< /Type /XObject /Subtype /Image /Width '.$logoWidth.' /Height '.$logoHeight.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logo).">>\nstream\n".$logo."\nendstream";
        }
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

        $file = tempnam(sys_get_temp_dir(), 'student-result-');
        file_put_contents($file, $pdf);

        return $file;
    }

    /** Create a PDF text drawing command with escaped, printable content. */
    private function drawText(int $x, int $y, string $value, int $size, bool $bold = false, array $color = self::TEXT): string
    {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->text($value));
        $font = $bold ? 'F2' : 'F1';

        return sprintf("BT\n/%s %d Tf\n%.3f %.3f %.3f rg\n%d %d Td\n(%s) Tj\nET\n", ...[$font, $size, ...$color, $x, $y, $safe]);
    }

    private function fillRectangle(int $x, int $y, int $width, int $height, array $color): string
    {
        return sprintf("q\n%.3f %.3f %.3f rg\n%d %d %d %d re f\nQ\n", ...[...$color, $x, $y, $width, $height]);
    }

    private function drawRule(int $x, int $y, int $width, array $color): string
    {
        return sprintf("q\n%.3f %.3f %.3f RG\n0.5 w\n%d %d m\n%d %d l S\nQ\n", ...[...$color, $x, $y, $x + $width, $y]);
    }

    private function text(string $value): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: '';
    }

    private function truncate(string $value, int $length): string
    {
        $value = $this->text($value);

        return strlen($value) > $length ? substr($value, 0, $length - 3).'...' : $value;
    }
}
