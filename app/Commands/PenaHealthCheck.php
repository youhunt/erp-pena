<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Quick local readiness check for the PENA ERP starter implementation.
 */
final class PenaHealthCheck extends BaseCommand
{
    protected $group       = 'PENA ERP';
    protected $name        = 'pena:health';
    protected $description = 'Checks required folders, environment, database connectivity, and important core tables.';

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        CLI::write('PENA ERP local health check', 'green');
        CLI::newLine();

        $checks = [
            'Writable path exists' => is_dir(WRITEPATH),
            'Cache path exists'    => is_dir(WRITEPATH . 'cache'),
            'Logs path exists'     => is_dir(WRITEPATH . 'logs'),
            'Uploads path exists'  => is_dir(WRITEPATH . 'uploads'),
            'Public index exists'  => is_file(FCPATH . 'index.php'),
            'App env configured'   => env('CI_ENVIRONMENT') !== null,
            'Base URL configured'  => (string) config('App')->baseURL !== '',
        ];

        foreach ($checks as $label => $passed) {
            $this->printCheck($label, $passed);
        }

        $this->checkDatabase();
    }

    private function checkDatabase(): void
    {
        try {
            $db = Database::connect();
            $db->initialize();

            $this->printCheck('Database connection', true);

            $tables = [
                'users',
                'auth_identities',
                'auth_groups_users',
                'companies',
                'sites',
                'user_companies',
                'user_sites',
                'menu_items',
                'audit_logs',
                'system_settings',
            ];

            foreach ($tables as $table) {
                $this->printCheck('Table: ' . $table, $db->tableExists($table));
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
