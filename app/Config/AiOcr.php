<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class AiOcr extends BaseConfig
{
    /**
     * OCR engine: null, local_command.
     */
    public string $ocrEngine = 'local_command';

    /**
     * AI extraction engine: null, rule_based.
     */
    public string $extractionEngine = 'rule_based';

    public string $tesseractPath = 'tesseract';

    public string $tesseractLanguage = 'eng+ind';

    public string $pdftotextPath = 'pdftotext';
}
