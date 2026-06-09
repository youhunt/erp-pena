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
            $this->writeZipFromDirectory($workDir, $xlsxPath);

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

    private function writeZipFromDirectory(string $sourceDir, string $zipPath): void
    {
        $files = $this->collectFiles($sourceDir);
        $handle = fopen($zipPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create Excel output file.');
        }

        $centralDirectory = '';
        $offset = 0;
        $dosTime = $this->dosTime();
        $dosDate = $this->dosDate();

        foreach ($files as $localName => $path) {
            $data = file_get_contents($path);
            if ($data === false) {
                fclose($handle);
                throw new RuntimeException('Unable to read Excel package part: ' . $localName);
            }

            $crc = hexdec(hash('crc32b', $data));
            $size = strlen($data);
            $nameLength = strlen($localName);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLength,
                0
            );

            fwrite($handle, $localHeader . $localName . $data);

            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $localName;

            $offset += strlen($localHeader) + $nameLength + $size;
        }

        $centralOffset = $offset;
        $centralSize = strlen($centralDirectory);
        fwrite($handle, $centralDirectory);
        fwrite($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), $centralSize, $centralOffset, 0));
        fclose($handle);
    }

    private function collectFiles(string $directory): array
    {
        $files = [];
        $baseLength = strlen(rtrim($directory, DIRECTORY_SEPARATOR)) + 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $localName = substr($path, $baseLength);
            $localName = str_replace(DIRECTORY_SEPARATOR, '/', $localName);
            $files[$localName] = $path;
        }

        ksort($files);
        return $files;
    }

    private function dosTime(): int
    {
        $time = getdate();
        return (($time['hours'] ?? 0) << 11) | (($time['minutes'] ?? 0) << 5) | (int) floor(($time['seconds'] ?? 0) / 2);
    }

    private function dosDate(): int
    {
        $time = getdate();
        $year = max(1980, (int) ($time['year'] ?? 1980));
        return (($year - 1980) << 9) | (($time['mon'] ?? 1) << 5) | ($time['mday'] ?? 1);
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
