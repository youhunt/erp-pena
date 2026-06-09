<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AllSeeder extends Seeder
{
    /**
     * Run all application seeders in a controlled order.
     *
     * Add future seeders to this list instead of running many db:seed commands manually.
     */
    public function run(): void
    {
        $this->call(PenaErpSeeder::class);
    }
}
