<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/** Builds a minimal dependency-free XLSX workbook for report exports. */
class SpreadsheetWriter
{
    /**
     * Write the provided tabular data to a temporary XLSX file.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function write(array $headers, iterable $rows): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for XLSX export.');
        }

        $directory = storage_path('app/report-exports');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the export directory.');
        }

        $path = $directory.DIRECTORY_SEPARATOR.'school-erp-xlsx-'.bin2hex(random_bytes(8)).'.xlsx';
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not create the spreadsheet archive.');
        }

        $this->addWorkbookMetadata($archive);
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
        $archive->close();

        return $path;
    }

    /** Add the manifest and relationship files required by the XLSX format. */
    private function addWorkbookMetadata(ZipArchive $archive): void
    {
        $archive->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
        );
        $archive->addFromString(
            '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
        );
        $archive->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
        );
        $archive->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
        );
    }

    /** Serialize headers and records as inline-string/numeric worksheet cells. */
    private function worksheetXml(array $headers, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $rowNumber = 1;

        foreach ($this->allRows($headers, $rows) as $row) {
            $xml .= '<row r="'.$rowNumber.'">';
            foreach (array_values($row) as $column => $value) {
                $reference = $this->column($column + 1).$rowNumber;
                $xml .= $this->cellXml($reference, $value);
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    /** Yield headers first without materializing the input iterator in memory. */
    private function allRows(array $headers, iterable $rows): iterable
    {
        yield $headers;

        foreach ($rows as $row) {
            yield $row;
        }
    }

    private function cellXml(string $reference, mixed $value): string
    {
        if (is_numeric($value) && $value !== null) {
            return '<c r="'.$reference.'"><v>'.(float) $value.'</v></c>';
        }

        $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'
            .$escaped.'</t></is></c>';
    }

    /** Convert a one-based column index into its Excel A1 column label. */
    private function column(int $number): string
    {
        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(65 + ($number % 26)).$label;
            $number = intdiv($number, 26);
        }

        return $label;
    }
}
