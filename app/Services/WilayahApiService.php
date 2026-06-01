<?php

namespace App\Services;

use App\Models\CityModel;
use App\Models\CountryModel;
use App\Models\ProvinceModel;
use Config\WilayahApi;
use RuntimeException;

class WilayahApiService
{
    private WilayahApi $config;

    public function __construct()
    {
        $this->config = config(WilayahApi::class);
        $this->config->baseUrl = rtrim((string) env('wilayah.baseUrl', $this->config->baseUrl), '/');
        $this->config->token = (string) env('wilayah.apiToken', $this->config->token);
    }

    public function syncProvinces(): int
    {
        $countryId = $this->ensureIndonesia();
        $rows = $this->request('/provinsi/');
        $count = 0;
        $model = new ProvinceModel();

        foreach ($rows as $row) {
            if (! isset($row['id'], $row['description'])) {
                continue;
            }

            $this->upsert($model, ['code' => (string) $row['id']], [
                'code' => (string) $row['id'],
                'name' => (string) $row['description'],
                'parent_id' => $countryId,
                'is_active' => 1,
            ]);
            $count++;
        }

        return $count;
    }

    public function syncCities(): int
    {
        $provinceModel = new ProvinceModel();
        $cityModel = new CityModel();
        $count = 0;

        foreach ($provinceModel->where('is_active', 1)->findAll() as $province) {
            $rows = $this->request('/kabupaten/getByProvinsi/' . $province['code']);

            foreach ($rows as $row) {
                if (! isset($row['id'], $row['description'])) {
                    continue;
                }

                $this->upsert($cityModel, ['code' => (string) $row['id']], [
                    'code' => (string) $row['id'],
                    'name' => (string) $row['description'],
                    'parent_id' => (int) $province['id'],
                    'is_active' => 1,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function request(string $path): array
    {
        if ($this->config->token === '') {
            throw new RuntimeException('Wilayah API token is not configured. Set wilayah.apiToken in .env.');
        }

        $response = service('curlrequest')->get($this->config->baseUrl . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->token,
                'Accept' => 'application/json',
            ],
            'timeout' => $this->config->timeout,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Wilayah API request failed with status ' . $response->getStatusCode());
        }

        $payload = json_decode($response->getBody(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Wilayah API returned invalid JSON.');
        }

        return array_is_list($payload) ? $payload : [$payload];
    }

    private function ensureIndonesia(): int
    {
        $model = new CountryModel();
        $row = $model->where('code', 'IDN')->first();

        if ($row !== null) {
            return (int) $row['id'];
        }

        $model->insert([
            'code' => 'IDN',
            'name' => 'Indonesia',
            'is_active' => 1,
        ]);

        return (int) $model->getInsertID();
    }

    private function upsert($model, array $where, array $data): int
    {
        $row = $model->where($where)->first();

        if ($row !== null) {
            $model->update($row['id'], $data);

            return (int) $row['id'];
        }

        $model->insert($data);

        return (int) $model->getInsertID();
    }
}
