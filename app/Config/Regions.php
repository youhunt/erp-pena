<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Regions extends BaseConfig
{
    public string $apiBaseUrl = 'https://www.emsifa.com/api-wilayah-indonesia/api';
    public string $apiToken = '';
    public int $timeout = 60;
}
