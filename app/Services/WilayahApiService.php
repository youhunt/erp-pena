<?php

namespace App\Services;

use App\Models\CityModel;
use App\Models\CountryModel;
use App\Models\ProvinceModel;
use Config\WilayahApi;
use RuntimeException;
use Throwable;

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
        $rows = $this->requestProvinces();
        $count = 0;
        $model = new ProvinceModel();

        foreach ($rows as $row) {
            $code = $this->rowCode($row);
            $name = $this->rowName($row);

            if ($code === null || $name === null) {
                continue;
            }

            $this->upsert($model, ['code' => $code], [
                'code' => $code,
                'name' => $name,
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
            $rows = $this->requestCities((string) $province['code']);

            foreach ($rows as $row) {
                $code = $this->rowCode($row);
                $name = $this->rowName($row);

                if ($code === null || $name === null) {
                    continue;
                }

                $this->upsert($cityModel, ['code' => $code], [
                    'code' => $code,
                    'name' => $name,
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
    private function requestProvinces(): array
    {
        return $this->requestWithFallback([
            [$this->config->baseUrl . '/provinsi/', true],
            ['https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json', false],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestCities(string $provinceCode): array
    {
        return $this->requestWithFallback([
            [$this->config->baseUrl . '/kabupaten/getByProvinsi/' . $provinceCode, true],
            ['https://www.emsifa.com/api-wilayah-indonesia/api/regencies/' . $provinceCode . '.json', false],
        ]);
    }

    /**
     * @param list<array{0:string,1:bool}> $endpoints
     * @return list<array<string, mixed>>
     */
    private function requestWithFallback(array $endpoints): array
    {
        $errors = [];

        foreach ($endpoints as [$url, $requiresToken]) {
            if ($requiresToken && $this->config->token === '') {
                $errors[] = $url . ' skipped because wilayah.apiToken is empty.';
                continue;
            }

            try {
                return $this->request($url, $requiresToken);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        throw new RuntimeException('Wilayah API sync failed. ' . implode(' | ', $errors));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function request(string $url, bool $withToken): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($withToken && $this->config->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->config->token;
        }

        try {
            $response = service('curlrequest')->get($url, [
                'headers' => $headers,
                'timeout' => $this->config->timeout,
                'http_errors' => false,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Wilayah API request error for ' . $url . ': ' . $exception->getMessage());
        }

        $status = $response->getStatusCode();
        $body = trim($response->getBody());

        if ($status >= 400) {
            throw new RuntimeException('Wilayah API request failed for ' . $url . ' with status ' . $status . '. Body: ' . $this->snippet($body));
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Wilayah API returned invalid JSON for ' . $url . '. Body: ' . $this->snippet($body));
        }

        return array_is_list($payload) ? $payload : [$payload];
    }

    private function rowCode(array $row): ?string
    {
        $value = $row['id'] ?? $row['code'] ?? null;

        return $value === null ? null : (string) $value;
    }

    private function rowName(array $row): ?string
    {
        $value = $row['description'] ?? $row['name'] ?? null;

        return $value === null ? null : (string) $value;
    }

    private function snippet(string $body): string
    {
        $body = preg_replace('/\s+/', ' ', $body) ?? $body;

        return mb_substr($body, 0, 180);
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
