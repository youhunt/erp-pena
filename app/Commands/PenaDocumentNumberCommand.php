<?php

namespace App\Commands;

use App\Services\Support\DocumentNumberService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;

/**
 * CLI helper for manually testing ERP document numbering.
 */
final class PenaDocumentNumberCommand extends BaseCommand
{
    protected $group       = 'PENA ERP';
    protected $name        = 'pena:docno';
    protected $description = 'Preview or generate an ERP document number for a transaction code.';

    protected $usage = 'pena:docno <transaction_code> [options]';

    protected $arguments = [
        'transaction_code' => 'Transaction code, for example SO, PO, DO, SI, PI, JV.',
    ];

    protected $options = [
        '--preview'      => 'Preview next number without incrementing the sequence.',
        '--company'      => 'Company ID. If omitted, active tenant company is used.',
        '--site'         => 'Site ID. If omitted, active tenant site is used. Use 0 for company-level sequence.',
        '--prefix'       => 'Prefix value. Example: SO, PO, CPI/SO.',
        '--format'       => 'Format template. Example: {PREFIX}/{YYYY}{MM}/{SEQ}.',
        '--reset-period' => 'daily, monthly, yearly, or never.',
        '--padding'      => 'Sequence padding length. Example: 5 => 00001.',
        '--date'         => 'Document date in YYYY-MM-DD format. Defaults to today.',
    ];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $transactionCode = $params[0] ?? null;

        if ($transactionCode === null || trim($transactionCode) === '') {
            CLI::error('Transaction code is required. Example: php spark pena:docno SO --preview --company=1 --site=1');
            return;
        }

        $dateOption = CLI::getOption('date');
        $date = $dateOption !== null && trim((string) $dateOption) !== ''
            ? new DateTimeImmutable((string) $dateOption)
            : new DateTimeImmutable();

        $options = [];

        if (($company = CLI::getOption('company')) !== null) {
            $options['company_id'] = (int) $company;
        }

        if (($site = CLI::getOption('site')) !== null) {
            $options['site_id'] = (int) $site;
        }

        if (($prefix = CLI::getOption('prefix')) !== null) {
            $options['prefix'] = (string) $prefix;
        }

        if (($format = CLI::getOption('format')) !== null) {
            $options['format'] = (string) $format;
        }

        if (($resetPeriod = CLI::getOption('reset-period')) !== null) {
            $options['reset_period'] = (string) $resetPeriod;
        }

        if (($padding = CLI::getOption('padding')) !== null) {
            $options['padding'] = (int) $padding;
        }

        try {
            $service = new DocumentNumberService();
            $number = CLI::getOption('preview') !== null
                ? $service->preview((string) $transactionCode, $date, $options)
                : $service->next((string) $transactionCode, $date, $options);

            CLI::write($number, 'green');
        } catch (\Throwable $exception) {
            CLI::error($exception->getMessage());
        }
    }
}
