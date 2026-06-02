<?php

namespace App\Controllers\Setup;

use App\Controllers\BaseController;
use App\Services\WilayahApiService;
use RuntimeException;

class WilayahSyncController extends BaseController
{
    public function provinces()
    {
        return $this->sync('provinces', static fn (WilayahApiService $service): int => $service->syncProvinces());
    }

    public function cities()
    {
        return $this->sync('cities', static fn (WilayahApiService $service): int => $service->syncCities());
    }

    private function sync(string $resource, callable $callback)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('setup.master.manage') && ! $user->inGroup('superadmin'))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        try {
            $count = $callback(new WilayahApiService());
        } catch (RuntimeException $exception) {
            return redirect()->to("setup/{$resource}")->with('error', $exception->getMessage());
        }

        return redirect()->to("setup/{$resource}")->with('message', "{$count} records synced from Wilayah API.");
    }
}
