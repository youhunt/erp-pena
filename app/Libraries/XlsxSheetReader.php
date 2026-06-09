<?php

namespace App\Libraries;

use RuntimeException;
use SimpleXMLElement;

class XlsxSheetReader
{
    public function readFirstSheet(string $xlsxPath): array
    {
        if (! is_file($xlsxPath)) {
            throw new RuntimeException('Excel file was not found.');
        }

        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pena_xlsx_' . bin2hex(random_bytes(8));
        if (! mkdir($workDir, 0777, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create temporary Excel extraction directory.');
        }

        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'upload.zip';
        if (! copy($xlsxPath, $zipPath)) {
            $this->deleteDirectory($workDir);
            throw new RuntimeException('Unable to prepare uploaded Excel file.');
        }

        try {
            $this->extractZip($zipPath, $workDir);
            $sharedStrings = $this->readSharedStrings($workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml');
            $sheetPath = $workDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';
            if (! is_file($sheetPath)) {
                throw new RuntimeException('Excel sheet1.xml was not found after extraction.');
            }

            return $this->readSheetRows($sheetPath, $sharedStrings);
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function extractZip(string $zipPath, string $destination): void
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -LiteralPath ' . $this->psQuote($zipPath) . ' -DestinationPath ' . $this->psQuote($destination) . ' -Force"';
            $this->runCommand($command, 'PowerShell Expand-Archive failed. Make sure shell_exec is enabled and PowerShell is available.');
            return;
        }

        $command = 'unzip -qq -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($destination);
        $this->runCommand($command, 'unzip command failed. Install unzip or enable PHP ZipArchive.');
    }

    private function runCommand(string $command, string $errorMessage): void
    {
        if (! function_exists('shell_exec')) {
            throw new RuntimeException('shell_exec is disabled. Enable PHP ZipArchive, or allow shell_exec for Excel extraction.');
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

    private function readSharedStrings(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $xml = simplexml_load_file($path);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Unable to read Excel shared strings.');
        }

        $strings = [];
        foreach ($xml->si as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string) $si->t;
            }
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    $parts[] = (string) $run->t;
                }
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function readSheetRows(string $path, array $sharedStrings): array
    {
        $xml = simplexml_load_file($path);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Unable to read Excel worksheet.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $cells = [];
            foreach ($rowNode->c as $cellNode) {
                $ref = (string) ($cellNode['r'] ?? 'A1');
                $columnIndex = $this->columnIndex($ref);
                $type = (string) ($cellNode['t'] ?? '');
                $value = '';

                if ($type === 'inlineStr') {
                    $value = (string) ($cellNode->is->t ?? '');
                } else {
                    $raw = (string) ($cellNode->v ?? '');
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $raw] ?? '';
                    } else {
                        $value = $raw;
                    }
                }

                $cells[$columnIndex] = $value;
            }

            if ($cells !== []) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            }
        }

        return $rows;
    }

    private function columnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/i', $cellRef, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
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
