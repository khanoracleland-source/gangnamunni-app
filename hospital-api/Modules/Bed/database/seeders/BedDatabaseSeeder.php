<?php

namespace Modules\Bed\Database\Seeders;

use Illuminate\Database\Seeder;

class BedDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            BedTypeTableSeeder::class,
               BedMasterTableSeeder::class,
        ]);
    }
}
