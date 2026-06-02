<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\RegionApiSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Regions;
use RuntimeException;
use Throwable;

final class SyncRegionsApi extends BaseCommand
{
    protected $group = 'Pena ERP';
    protected $name = 'regions:sync-api';
    protected $description = 'Sinkronkan master wilayah Indonesia dari API yang dikonfigurasi.';
    protected $usage = 'regions:sync-api <source_version>';
    protected $arguments = [
        'source_version' => 'Identitas versi sumber API untuk audit master wilayah.',
    ];

    public function run(array $params): int
    {
        $version = trim((string) ($params[0] ?? ''));

        if ($version === '') {
            CLI::error('Gunakan: php spark regions:sync-api <source_version>');

            return EXIT_ERROR;
        }

        /** @var Regions $config */
        $config = config(Regions::class);
        $config->apiBaseUrl = rtrim((string) env('regions.apiBaseUrl', $config->apiBaseUrl), '/');
        $config->apiToken = (string) env('regions.apiToken', $config->apiToken);

        try {
            $counts = (new RegionApiSyncService())->sync($config->apiBaseUrl, $config->apiToken, $version);
        } catch (Throwable $e) {
            CLI::error($e instanceof RuntimeException ? $e->getMessage() : 'Sinkronisasi wilayah gagal.');

            return EXIT_ERROR;
        }

        CLI::write(sprintf(
            'Sinkronisasi selesai: %d provinsi, %d kabupaten/kota, %d kecamatan, %d desa/kelurahan.',
            $counts['provinces'],
            $counts['regencies'],
            $counts['districts'],
            $counts['villages'],
        ), 'green');

        return EXIT_SUCCESS;
    }
}
