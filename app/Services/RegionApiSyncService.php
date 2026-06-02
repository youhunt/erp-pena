<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CityModel;
use App\Models\CountryModel;
use App\Models\ProvinceModel;
use RuntimeException;
use Throwable;

final class RegionApiSyncService
{
    /**
     * @return array{provinces:int,regencies:int,districts:int,villages:int}
     */
    public function sync(string $apiBaseUrl, string $apiToken, string $sourceVersion): array
    {
        $apiBaseUrl = rtrim($apiBaseUrl, '/');
        $countryId = $this->ensureIndonesia();
        $counts = ['provinces' => 0, 'regencies' => 0, 'districts' => 0, 'villages' => 0];

        $provinces = $this->request($apiBaseUrl . '/provinces.json', $apiToken);
        $provinceModel = new ProvinceModel();
        $cityModel = new CityModel();

        foreach ($provinces as $province) {
            $provinceCode = $this->code($province);
            $provinceName = $this->name($province);

            if ($provinceCode === null || $provinceName === null) {
                continue;
            }

            $provinceId = $this->upsert($provinceModel, ['code' => $provinceCode], [
                'code' => $provinceCode,
                'name' => $provinceName,
                'parent_id' => $countryId,
                'is_active' => 1,
                'updated_by' => null,
            ]);
            $counts['provinces']++;

            $regencies = $this->request($apiBaseUrl . '/regencies/' . $provinceCode . '.json', $apiToken);
            foreach ($regencies as $regency) {
                $regencyCode = $this->code($regency);
                $regencyName = $this->name($regency);

                if ($regencyCode === null || $regencyName === null) {
                    continue;
                }

                $this->upsert($cityModel, ['code' => $regencyCode], [
                    'code' => $regencyCode,
                    'name' => $regencyName,
                    'parent_id' => $provinceId,
                    'is_active' => 1,
                    'updated_by' => null,
                ]);
                $counts['regencies']++;
            }
        }

        (new AuditLogService())->log('setup.regions', 'regions.sync_api', [
            'description' => 'Region API sync completed from source version ' . $sourceVersion,
            'new_values' => $counts + ['source_version' => $sourceVersion, 'api_base_url' => $apiBaseUrl],
        ]);

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function request(string $url, string $apiToken): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($apiToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiToken;
        }

        try {
            $response = service('curlrequest')->get($url, [
                'headers' => $headers,
                'timeout' => 60,
                'http_errors' => false,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Region API request error for ' . $url . ': ' . $exception->getMessage());
        }

        $status = $response->getStatusCode();
        $body = trim($response->getBody());

        if ($status >= 400) {
            throw new RuntimeException('Region API failed for ' . $url . ' with status ' . $status . '. Body: ' . $this->snippet($body));
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Region API returned invalid JSON for ' . $url . '. Body: ' . $this->snippet($body));
        }

        return array_is_list($payload) ? $payload : [$payload];
    }

    private function code(array $row): ?string
    {
        $value = $row['id'] ?? $row['code'] ?? null;

        return $value === null ? null : (string) $value;
    }

    private function name(array $row): ?string
    {
        $value = $row['name'] ?? $row['description'] ?? null;

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

        $model->insert(['code' => 'IDN', 'name' => 'Indonesia', 'is_active' => 1]);

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
