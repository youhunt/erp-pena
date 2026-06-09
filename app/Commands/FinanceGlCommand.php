<?php

namespace App\Commands;

use App\Database\Seeds\FinanceGlSeeder;
use App\Services\Finance\LegacyGlBridgeService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;

class FinanceGlCommand extends BaseCommand
{
    protected $group = 'PENA ERP';
    protected $name = 'finance:gl';
    protected $description = 'Initialize and sync Finance General Ledger data.';
    protected $usage = 'finance:gl [init|sync-legacy|sync-coa|sync-books] [--company-id=1]';

    public function run(array $params): void
    {
        $action = $params[0] ?? 'init';
        $companyId = $this->companyId();

        try {
            match ($action) {
                'init' => $this->init(),
                'sync-legacy' => $this->syncLegacy($companyId),
                'sync-coa' => $this->syncCoa($companyId),
                'sync-books' => $this->syncBooks($companyId),
                default => throw new RuntimeException('Unknown action: ' . $action),
            };
        } catch (RuntimeException $exception) {
            CLI::error($exception->getMessage());
        }
    }

    private function init(): void
    {
        CLI::write('Seeding Finance GL defaults...', 'yellow');
        (new FinanceGlSeeder(Database::connect()))->run();
        CLI::write('Finance GL defaults completed.', 'green');
    }

    private function syncLegacy(?int $companyId): void
    {
        $this->syncBooks($companyId);
        $this->syncCoa($companyId);
    }

    private function syncCoa(?int $companyId): void
    {
        CLI::write('Syncing legacy coa to chart_accounts...', 'yellow');
        $result = (new LegacyGlBridgeService())->syncCoaToChartAccounts($companyId);
        CLI::write("COA sync completed. Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}", 'green');
    }

    private function syncBooks(?int $companyId): void
    {
        CLI::write('Syncing legacy glbook to gl_books...', 'yellow');
        $result = (new LegacyGlBridgeService())->syncGlBookToModern($companyId);
        CLI::write("GL Book sync completed. Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}", 'green');
    }

    private function companyId(): ?int
    {
        $value = CLI::getOption('company-id');
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;
        return $id > 0 ? $id : null;
    }
}
