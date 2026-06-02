<?php

namespace App\Services\Ai\Ocr;

use Config\AiOcr;

final class OcrDiagnosticsService
{
    public function __construct(private readonly AiOcr $config = new AiOcr())
    {
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function check(): array
    {
        return [
            'tesseract' => $this->runVersionCommand($this->config->tesseractPath . ' --version', 'Tesseract OCR'),
            'pdftotext' => $this->runVersionCommand($this->config->pdftotextPath . ' -v', 'Poppler pdftotext'),
            'php_exec' => [
                'label' => 'PHP exec()',
                'ok' => function_exists('exec') && ! $this->isExecDisabled(),
                'message' => function_exists('exec') && ! $this->isExecDisabled()
                    ? 'PHP exec() is available.'
                    : 'PHP exec() is disabled. Enable exec() or use another OCR provider.',
                'output' => [],
            ],
        ];
    }

    private function runVersionCommand(string $command, string $label): array
    {
        if (! function_exists('exec') || $this->isExecDisabled()) {
            return [
                'label' => $label,
                'ok' => false,
                'message' => 'Cannot run command because PHP exec() is disabled.',
                'output' => [],
            ];
        }

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return [
            'label' => $label,
            'ok' => $exitCode === 0,
            'message' => $exitCode === 0 ? $label . ' is available.' : $label . ' is not available from PHP PATH.',
            'output' => array_slice($output, 0, 8),
            'command' => $command,
            'exit_code' => $exitCode,
        ];
    }

    private function isExecDisabled(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return in_array('exec', $disabled, true);
    }
}
