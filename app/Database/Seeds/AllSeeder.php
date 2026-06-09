<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AllSeeder extends Seeder
{
    /**
     * Run every application seeder found in app/Database/Seeds.
     *
     * This wrapper intentionally skips itself to avoid recursion. PenaErpSeeder is
     * always executed first because it prepares the ERP baseline company, site,
     * admin user, access, setup master data, and menu records.
     */
    public function run(): void
    {
        foreach ($this->seeders() as $seederClass) {
            $this->call($seederClass);
        }
    }

    /**
     * @return list<class-string<Seeder>>
     */
    private function seeders(): array
    {
        $namespace = __NAMESPACE__ . '\\';
        $seedPath = APPPATH . 'Database/Seeds';
        $seeders = [];

        foreach (glob($seedPath . '/*.php') ?: [] as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);

            if ($className === self::class || $className === 'AllSeeder') {
                continue;
            }

            $fqcn = $namespace . $className;

            if (! class_exists($fqcn) || ! is_subclass_of($fqcn, Seeder::class)) {
                continue;
            }

            $seeders[] = $fqcn;
        }

        usort($seeders, static function (string $left, string $right): int {
            if ($left === PenaErpSeeder::class) {
                return -1;
            }

            if ($right === PenaErpSeeder::class) {
                return 1;
            }

            return $left <=> $right;
        });

        return $seeders;
    }
}
