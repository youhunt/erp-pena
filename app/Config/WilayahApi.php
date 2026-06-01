<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class WilayahApi extends BaseConfig
{
    public string $baseUrl = 'https://api-wilayah.belajardisiniaja.com';
    public string $token = '';
    public int $timeout = 30;
}
