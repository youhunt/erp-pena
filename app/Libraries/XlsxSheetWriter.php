<?php

namespace App\Libraries;

use RuntimeException;

class XlsxSheetWriter
{
    public function writeFirstSheet(array $rows, string $sheetName = 'Sheet1'): string
    {
        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pena_xlsx_write_' . bin2hex(random_bytes(8));
        if (! mkdir($workDir, 0777, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create temporary Excel write directory.');
        }

        try {
            $this->createWorkbookFiles($workDir, $rows, $sheetName);
            $xlsxPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pena_excel_' . bin2hex(random_bytes(8)) . '.xlsx';
            $this->compressWorkbook($workDir, $xlsxPath);

            if (! is_file($xlsxPath) || filesize($xlsxPath) < 1) {
                throw new RuntimeException('Unable to generate Excel .xlsx file.');
            }

            return $xlsxPath;
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function createWorkbookFiles(string $workDir, array $rows, string $sheetName): void
    {
        $this->mkdir($workDir . DIRECTORY_SEPARATOR . '_rels');
        $this->mkdir($workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . '_rels');
        $this->mkdir($workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets');

        file_put_contents($workDir . DIRECTORY_SEPARATOR . '[Content_Types].xml', $this->contentTypesXml());
        file_put_contents($workDir . DIRECTORY_SEPARATOR . '_rels' . DIRECTORY_SEPARATOR . '.rels', $this->rootRelsXml());
        file_put_contents($workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'workbook.xml', $this->workbookXml($sheetName));
        file_put_contents($workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . '_rels' . DIRECTORY_SEPARATOR . 'workbook.xml.rels', $this->workbookRelsXml());
        file_put_contents($workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml', $this->sheetXml($rows));
    }

    private function mkdir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create Excel directory: ' . $path);
        }
    }

    private function compressWorkbook(string $workDir, string $xlsxPath): void
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -LiteralPath ' . $this->psQuote($workDir . DIRECTORY_SEPARATOR . '*') . ' -DestinationPath ' . $this->psQuote($xlsxPath) . ' -Force"';
            $this->runCommand($command, 'PowerShell Compress-Archive failed. Make sure shell_exec/exec is enabled and PowerShell is available.');
            return;
        }

        $current = getcwd();
        chdir($workDir);
        try {
            $command = 'zip -qr ' . escapeshellarg($xlsxPath) . ' .';
            $this->runCommand($command, 'zip command failed. Install zip or enable PHP ZipArchive.');
        } finally {
            if ($current !== false) {
                chdir($current);
            }
        }
    }

    private function runCommand(string $command, string $errorMessage): void
    {
        if (! function_exists('exec')) {
            throw new RuntimeException('exec is disabled. Enable PHP ZipArchive, or allow exec for Excel generation.');
        }

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException($errorMessage . ' ' . trim(implode(' ', $output)));
        }
    }

    private function psQuote(string $path): string
    {
        return "'" . str_replace("'", "''", $path) . "'";
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="' . $this->xml($this->safeSheetName($sheetName)) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '</Relationships>';
    }

    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
            '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cellRef = $this->columnName($columnIndex) . $rowNumber;
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $this->xml((string) $value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $name;
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('~[\\/\?\*\[\]\:]~', ' ', $name) ?: 'Sheet1';
        $name = trim($name) ?: 'Sheet1';
        return substr($name, 0, 31);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
