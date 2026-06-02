<?php

namespace App\Services\Ai\Ocr;

use Config\AiOcr;
use RuntimeException;

final class LocalCommandOcrEngine implements OcrEngineInterface
{
    public function __construct(private readonly AiOcr $config = new AiOcr())
    {
    }

    public function extractText(string $filePath, string $mimeType): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('OCR file was not found.');
        }

        if ($mimeType === 'application/pdf') {
            return $this->extractPdfText($filePath);
        }

        if (str_starts_with($mimeType, 'image/')) {
            return $this->extractImageText($filePath);
        }

        throw new RuntimeException('Unsupported OCR MIME type: ' . $mimeType);
    }

    private function extractImageText(string $filePath): array
    {
        $cmd = sprintf(
            '%s %s stdout -l %s 2>&1',
            escapeshellcmd($this->config->tesseractPath),
            escapeshellarg($filePath),
            escapeshellarg($this->config->tesseractLanguage),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Tesseract OCR failed. Make sure Tesseract is installed and available in PATH. Output: ' . implode("\n", $output));
        }

        $text = trim(implode("\n", $output));

        return [
            'provider' => 'local_tesseract',
            'text' => $text,
            'confidence' => $text === '' ? 0.0 : 70.0,
            'raw_response' => ['command' => 'tesseract', 'length' => strlen($text)],
        ];
    }

    private function extractPdfText(string $filePath): array
    {
        $cmd = sprintf(
            '%s -layout %s - 2>&1',
            escapeshellcmd($this->config->pdftotextPath),
            escapeshellarg($filePath),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('PDF text extraction failed. Install Poppler pdftotext or convert scanned PDF to image first. Output: ' . implode("\n", $output));
        }

        $text = trim(implode("\n", $output));

        return [
            'provider' => 'local_pdftotext',
            'text' => $text,
            'confidence' => $text === '' ? 0.0 : 80.0,
            'raw_response' => ['command' => 'pdftotext', 'length' => strlen($text)],
        ];
    }
}
