<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Quick local readiness check for the PENA ERP development environment.
 */
final class PenaHealthCheck extends BaseCommand
{
    protected $group       = 'PENA ERP';
    protected $name        = 'pena:health';
    protected $description = 'Checks writable paths, database connectivity, and important ERP foundation tables.';

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        CLI::write('PENA ERP local health check', 'green');
        CLI::newLine();

        $this->checkPaths();
        $this->checkDatabase();
    }

    private function checkPaths(): void
    {
        $checks = [
            'Writable path exists'  => is_dir(WRITEPATH),
            'Cache path exists'     => is_dir(WRITEPATH . 'cache'),
            'Logs path exists'      => is_dir(WRITEPATH . 'logs'),
            'Uploads path exists'   => is_dir(WRITEPATH . 'uploads'),
            'Public index exists'   => is_file(FCPATH . 'index.php'),
            'Base URL configured'   => (string) config('App')->baseURL !== '',
        ];

        foreach ($checks as $label => $passed) {
            $this->printCheck($label, $passed);
        }
    }

    private function checkDatabase(): void
    {
        try {
            $db = Database::connect();
            $db->initialize();

            $this->printCheck('Database connection', true);

            $requiredTables = [
                'users',
                'auth_identities',
                'auth_groups_users',
                'companies',
                'sites',
                'user_company_access',
                'user_site_access',
                'menu_items',
                'audit_logs',
                'document_number_sequences',
            ];

            foreach ($requiredTables as $table) {
                $this->printCheck('Required table: ' . $table, $db->tableExists($table));
            }

            $optionalTables = [
                'document_uploads',
                'document_extractions',
                'document_processing_logs',
                'sales_orders',
                'purchase_orders',
                'inventory_stock_balances',
                'gl_entries',
            ];

            foreach ($optionalTables as $table) {
                $this->printCheck('Optional module table: ' . $table, $db->tableExists($table));
            }
        } catch (\Throwable $exception) {
            $this->printCheck('Database connection', false);
            CLI::write('  ' . $exception->getMessage(), 'red');
        }
    }

    private function printCheck(string $label, bool $passed): void
    {
        $status = $passed ? '[OK]' : '[FAIL]';
        $color  = $passed ? 'green' : 'red';

        CLI::write(str_pad($status, 8) . $label, $color);
    }
}
