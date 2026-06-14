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
        if (class_exists(\ZipArchive::class)) {
            $this->extractWithZipArchive($zipPath, $destination);
            return;
        }

        $this->extractWithShellCommand($zipPath, $destination);
    }

    private function extractWithZipArchive(string $zipPath, string $destination): void
    {
        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);
        if ($result !== true) {
            throw new RuntimeException('Unable to open Excel file as ZIP archive. ZipArchive error code: ' . $result);
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                if ($entryName === false || $entryName === '') {
                    continue;
                }

                if ($this->isUnsafeZipEntry($entryName)) {
                    throw new RuntimeException('Unsafe Excel archive entry detected: ' . $entryName);
                }

                $targetPath = $destination . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $entryName);

                if (str_ends_with($entryName, '/')) {
                    if (! is_dir($targetPath) && ! mkdir($targetPath, 0777, true) && ! is_dir($targetPath)) {
                        throw new RuntimeException('Unable to create Excel extraction directory: ' . $entryName);
                    }
                    continue;
                }

                $targetDir = dirname($targetPath);
                if (! is_dir($targetDir) && ! mkdir($targetDir, 0777, true) && ! is_dir($targetDir)) {
                    throw new RuntimeException('Unable to create Excel extraction directory: ' . $entryName);
                }

                $source = $zip->getStream($entryName);
                if ($source === false) {
                    throw new RuntimeException('Unable to read Excel archive entry: ' . $entryName);
                }

                $target = fopen($targetPath, 'wb');
                if ($target === false) {
                    fclose($source);
                    throw new RuntimeException('Unable to write Excel archive entry: ' . $entryName);
                }

                stream_copy_to_stream($source, $target);
                fclose($source);
                fclose($target);
            }
        } finally {
            $zip->close();
        }
    }

    private function extractWithShellCommand(string $zipPath, string $destination): void
    {
        if (! function_exists('exec')) {
            throw new RuntimeException('PHP ZipArchive extension is not available and exec() is disabled. Enable PHP zip extension for Excel import.');
        }

        if (! function_exists('escapeshellarg')) {
            throw new RuntimeException('PHP ZipArchive extension is not available and escapeshellarg() is disabled. Enable PHP zip extension for Excel import.');
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -LiteralPath ' . $this->psQuote($zipPath) . ' -DestinationPath ' . $this->psQuote($destination) . ' -Force"';
            $this->runCommand($command, 'PowerShell Expand-Archive failed. Enable PHP ZipArchive extension instead.');
            return;
        }

        $command = 'unzip -qq -o ' . \escapeshellarg($zipPath) . ' -d ' . \escapeshellarg($destination);
        $this->runCommand($command, 'unzip command failed. Enable PHP ZipArchive extension instead.');
    }

    private function runCommand(string $command, string $errorMessage): void
    {
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

    private function isUnsafeZipEntry(string $entryName): bool
    {
        return str_starts_with($entryName, '/')
            || str_starts_with($entryName, '\\')
            || str_contains($entryName, '..')
            || preg_match('/^[A-Za-z]:/', $entryName) === 1;
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
