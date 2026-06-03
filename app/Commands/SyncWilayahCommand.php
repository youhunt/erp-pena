<?php

namespace App\Commands;

use App\Services\WilayahApiService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class SyncWilayahCommand extends BaseCommand
{
    protected $group = 'PENA ERP';
    protected $name = 'wilayah:sync';
    protected $description = 'Sync Indonesian provinces and cities from the configured Wilayah API.';

    public function run(array $params): void
    {
        $service = new WilayahApiService();

        try {
            $provinceCount = $service->syncProvinces();
            CLI::write("Synced {$provinceCount} provinces.", 'green');

            $cityCount = $service->syncCities();
            CLI::write("Synced {$cityCount} cities.", 'green');
        } catch (RuntimeException $exception) {
            CLI::error($exception->getMessage());
        }
    }
}
